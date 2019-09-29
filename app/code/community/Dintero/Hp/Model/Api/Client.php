<?php

/**
 * Class Dintero_Hp_Model_Api_Client responsible for communication with the Payment Gateway
 */
class Dintero_Hp_Model_Api_Client
{
    /*
     * Dintero api endpoint
     */
    const API_BASE_URL = 'https://api.dintero.com/v1';

    /*
     * Checkout api endpoint
     */
    const CHECKOUT_API_BASE_URL = 'https://checkout.dintero.com/v1';

    /*
     * Status captured
     */
    const STATUS_CAPTURED = 'CAPTURED';

    /*
     * Status authorized
     */
    const STATUS_AUTHORIZED = 'AUTHORIZED';

    /*
     * Status partially captured
     */
    const STATUS_PARTIALLY_CAPTURED = 'PARTIALLY_CAPTURED';

    /**
     * Building api endpoint
     *
     * @param string $endpoint
     * @return string
     */
    private function getApiUri($endpoint)
    {
        return rtrim(self::API_BASE_URL, '/') . '/' . trim($endpoint, '/');
    }

    /**
     * Building checkout api uri
     *
     * @param string $endpoint
     * @return string
     */
    private function getCheckoutApiUri($endpoint)
    {
        return rtrim(self::CHECKOUT_API_BASE_URL, '/') . '/' . trim($endpoint, '/');
    }

    /**
     * Retrieving helper
     *
     * @return Dintero_Hp_Helper_Data
     */
    private function _helper()
    {
        return Mage::helper('dintero');
    }

    /**
     * Initializing request
     *
     * @param string $endpoint
     * @param string|null $token
     * @return $this
     * @throws Zend_Http_Client_Exception
     */
    protected function _initRequest($endpoint, $token = null)
    {
        $client = new Zend_Http_Client($endpoint);
        $defaultHeaders = [
            'Content-type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ];

        if ($token && $token instanceof Dintero_Hp_Model_Payment_Token) {
            $defaultHeaders['Authorization'] = $token->getTokenType() . ' ' . $token->getToken();
        }

        return $client->setHeaders($defaultHeaders)->setMethod(Zend_Http_Client::POST);
    }

    /**
     * Checking whether transaction can be captured or not
     *
     * @param array $transaction
     * @return bool
     */
    private function canCaptureTransaction($transaction)
    {
        return isset($transaction['status']) &&
            in_array($transaction['status'], [self::STATUS_AUTHORIZED, self::STATUS_PARTIALLY_CAPTURED]);
    }

    /**
     * Initializing checkout session
     *
     * @param Mage_Sales_Model_Order $order
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function initCheckout(Mage_Sales_Model_Order $order)
    {
        $client = $this->_initRequest($this->getCheckoutApiUri('sessions-profile'), $this->getToken())
            ->setRawData($this->_helper()->jsonEncode($this->prepareData($order)));
        return $this->_helper()->jsonDecode($client->request()->getBody());
    }

    /**
     * Retrieving token
     *
     * @return false|Mage_Core_Model_Abstract
     * @throws \Exception
     */
    private function getToken()
    {
        $token = Mage::getModel('dintero/payment_token', $this->getAccessToken());
        if (!$token->getToken()) {
            throw new \Exception('Failed to get access token');
        }
        return $token;
    }

    /**
     * Retrieving response
     *
     * @return array|string
     * @throws Zend_Http_Client_Exception
     */
    private function getAccessToken()
    {
        $accountsUrl = $this->getApiUri(sprintf('accounts/%s', $this->_helper()->getFullAccountId()));
        $accessTokenUrl = $this->getApiUri(
            sprintf('accounts/%s/auth/token', $this->_helper()->getFullAccountId())
        );
        $client = $this->_initRequest($accessTokenUrl)
            ->setAuth($this->_helper()->getClientId(), $this->_helper()->getClientSecret())
            ->setRawData($this->_helper()->jsonEncode(array(
                'grant_type' => 'client_credentials',
                'audience' => $accountsUrl
            )));

        try {

            $response = Mage::helper('core')->jsonDecode($client->request()->getBody());

            if (!isset($response['access_token'])) {
                throw new \Exception('Could not retrieve the access token');
            }

            return $response;
        } catch (\Exception $e) {
            Mage::logException($e);
        }

        return [];
    }

    /**
     * Preparing data
     *
     * @param Mage_Sales_Model_Order $order
     * @param null $salesDocument
     * @return array
     */
    private function prepareData(Mage_Sales_Model_Order $order, $salesDocument = null)
    {
        $customerEmail = $order->getCustomerIsGuest() ?
            $order->getBillingAddress()->getEmail() :
            $order->getCustomerEmail();
        $baseOrderTotal = $salesDocument ? $salesDocument->getBaseGrandTotal() : $order->getBaseGrandTotal();
        $orderData = array(
            'profile_id' => $this->_helper()->getProfileId(),
            'url' => array(
                'return_url' => $this->_helper()->getReturnUrl(),
                'callback_url' => $this->_helper()->getCallbackUrl(),
            ),
            'customer' => array(
                'email' => $customerEmail,
                'phone_number' => $order->getBillingAddress()->getTelephone()
            ),
            'order' => array(
                'amount' => $baseOrderTotal * 100,
                'currency' => $order->getBaseCurrencyCode(),
                'merchant_reference' => $order->getIncrementId(),
                'billing_address' => array(
                    'first_name' => $order->getBillingAddress()->getFirstname(),
                    'last_name' => $order->getBillingAddress()->getLastname(),
                    'address_line' => implode(',', $order->getBillingAddress()->getStreet()),
                    'postal_code' => $order->getBillingAddress()->getPostcode(),
                    'postal_place' => $order->getBillingAddress()->getCity(),
                    'country' => $order->getBillingAddress()->getCountryId(),
                ),
                'items' => $this->prepareItems($order),
            ),
            'configuration' => array(
                'auto_capture' => $this->_helper()->canAutoCapture()
            )
        );

        if ($order->getShippingAddress()) {
            $orderData['shipping_address'] = [
                'first_name' => $order->getShippingAddress()->getFirstname(),
                'last_name' => $order->getShippingAddress()->getLastname(),
                'address_line' => implode(',', $order->getShippingAddress()->getStreet()),
                'postal_code' => $order->getShippingAddress()->getPostcode(),
                'postal_place' => $order->getShippingAddress()->getCity(),
                'country' => $order->getShippingAddress()->getCountryId(),
            ];
        }
        $dataObject = new Varien_Object($orderData);
        return $dataObject->toArray();
    }

    /**
     * Preparing sales items for sending in API Call
     *
     * @param Mage_Sales_Model_Abstract $salesDocument
     * @return array
     */
    private function prepareSalesItems(Mage_Sales_Model_Abstract $salesDocument)
    {
        $items = [];

        foreach ($salesDocument->getAllItems() as $item) {
            array_push($items, [
                'id' => $item->getSku(),
                'line_id' => $item->getSku(),
                'amount' => ($item->getBasePrice() * $item->getQty() - $item->getBaseDiscountAmount() + $item->getBaseTaxAmount()) * 100,
            ]);
        }

        // adding shipping as a separate item
        if ($salesDocument->getBaseShippingAmount() > 0) {
            array_push($items, [
                'id' => 'shipping',
                'description' => 'Shipping',
                // 'quantity' => 1,
                'amount' => $salesDocument->getBaseShippingAmount() * 100,
                'line_id' => 'shipping',
            ]);
        }

        return $items;
    }

    /**
     * Preparing order items for sending in API call
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    private function prepareItems(Mage_Sales_Model_Order $order)
    {
        $items = array();

        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            array_push($items, array(
                'id' => $item->getSku(),
                'description' => sprintf('%s (%s)', $item->getName(), $item->getSku()),
                'quantity' => $item->getQtyOrdered() * 1,
                'amount' =>  ($item->getBaseRowTotalInclTax() - $item->getBaseDiscountAmount()) * 100,
                'line_id' => $item->getSku(),
                'vat_amount' => $item->getBaseTaxAmount() * 100, // NOK cannot be floating
                'vat' => $item->getTaxPercent() * 1,
            ));
        }

        // adding shipping as a separate item
        if (!$order->getIsVirtual() && $order->getBaseShippingAmount() > 0) {
            array_push($items, [
                'id' => 'shipping',
                'description' => 'Shipping',
                'quantity' => 1,
                'amount' => $order->getBaseShippingAmount() * 100,
                'line_id' => 'shipping',
            ]);
        }

        return $items;
    }

    /**
     * Retrieving transaction info
     *
     * @param $transactionId
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function getTransaction($transactionId)
    {
        $endpoint = $this->getCheckoutApiUri(sprintf('transactions/%s', $transactionId));
        $client = $this->_initRequest($endpoint, $this->getToken())
            ->setMethod(Zend_Http_Client::GET);

        return $this->_helper()->jsonDecode($client->request()->getBody());
    }

    /**
     * Capturing transaction
     *
     * @param string $transactionId
     * @param Varien_Object $payment
     * @param float $amount
     * @return mixed
     * @throws Zend_Http_Client_Exception
     */
    public function capture($transactionId, Varien_Object $payment, $amount)
    {
        $transaction = $this->getTransaction($transactionId);

        if (!$this->canCaptureTransaction($transaction)) {
            throw new \Exception('This transaction cannot be captured');
        }

        $requestData = [
            'id' => $transactionId,
            'amount' => $amount * 100,
            'items' => $payment->getSalesDocument() ?
                $this->prepareSalesItems($payment->getSalesDocument()) : $this->prepareItems($payment->getOrder())
        ];

        $endpoint = $this->getCheckoutApiUri(sprintf('transactions/%s/capture', $transactionId));
        $response = $this->_initRequest($endpoint, $this->getToken())
            ->setRawData($this->_helper()->jsonEncode($requestData))
            ->request();
        return $this->_helper()->jsonDecode($response->getBody());
    }

    /**
     * Refunding transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param $amount
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function refund(\Mage_Sales_Model_Order_Payment $payment, $amount)
    {
        $transactionId = str_replace(
            '-' . Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE,
            '',
            $payment->getParentTransactionId()
        );

        $requestData = array(
            'id' => $transactionId,
            'amount' => $amount * 100,
            'items' => $this->prepareSalesItems($payment->getSalesDocument())
        );

        $endpoint = $this->getCheckoutApiUri(sprintf('transactions/%s/refund', $transactionId));

        $response = $this->_initRequest($endpoint, $this->getToken())
            ->setRawData($this->_helper()->jsonEncode($requestData))
            ->request()->getBody();
        return $this->_helper()->jsonDecode($response);
    }

    /**
     * Voiding transaction by id
     *
     * @param string $transactionId
     * @return mixed
     * @throws Exception
     */
    public function void($transactionId)
    {
        $endpoint = $this->getCheckoutApiUri(sprintf('transactions/%s/void', $transactionId));
        return $this->_helper()->jsonDecode($this->_initRequest($endpoint, $this->getToken())->request()->getBody());
    }
}
