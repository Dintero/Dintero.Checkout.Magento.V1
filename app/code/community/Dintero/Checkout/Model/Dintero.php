<?php

/**
 * Class Dintero_Checkout_Model_Dintero
 */
class Dintero_Checkout_Model_Dintero extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Dintero form block
     *
     * @var string
     */
    protected $_formBlockType = 'dintero/form';

    /**
     * API client
     *
     * @var Dintero_Checkout_Model_Api_Client $_api
     */
    private $_api;

    /**
     * Method code
     *
     * @var string $_code
     */
    protected $_code = 'dintero';

    /**
     * Availability options
     */
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;
    protected $_isInitializeNeeded = true;
    protected $_canFetchTransactionInfo = false;

    /**
     * Dintero_Checkout_Model_Dintero constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_api = Mage::getSingleton('dintero/api_client', []);
    }

    /**
     * Setting initialize needed
     *
     * @param bool $value
     * @return $this
     */
    public function setIsInitializeNeeded($value)
    {
        $this->_isInitializeNeeded = (bool) $value;
        return $this;
    }

    /**
     * Processing a payment
     *
     * @param string $merchantOrderId
     * @param string $transactionId
     * @throws Exception
     */
    public function process($merchantOrderId, $transactionId)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($merchantOrderId);
        $this->getResponse()->setData($this->_api->getTransaction($transactionId));

        $payment = $order->getPayment();
        if (!$payment || $payment->getMethod() != $this->getCode()) {
            Mage::throwException(
                $this->_getHelper()->__('This payment didn\'t work out because we can\'t find this order.')
            );
        }

        if ($order->getId()) {
            $this->processOrder($order);
        }
    }

    /**
     * Processing order
     *
     * @param Mage_Sales_Model_Order $order
     * @throws \Exception
     */
    public function processOrder($order)
    {
        try {
            $this->checkTransaction($order);
        } catch (\Exception $e) {
            //decline the order (in case of wrong response code) but don't return money to customer.
            $message = $e->getMessage();
            $this->declineOrder($order, $message, false);
        }

        $payment = $order->getPayment();
        $this->fillPaymentByResponse($payment);
        $payment->getMethodInstance()->setIsInitializeNeeded(false);
        $payment->getMethodInstance()->setResponseData($this->getResponse()->getData());
        $payment->place();
        $this->addStatusComment($payment);
        $order->save();
    }

    /**
     * Validating transaction
     *
     * @param Mage_Sales_Model_Order $order
     * @throws Exception
     */
    private function checkTransaction($order)
    {
        if (!$this->getResponse()->getId() ||
            $order->getIncrementId() !== $this->getResponse()->getMerchantReference()
        ) {
            Mage::throwException($this->_getHelper()->__('Invalid transaction or merchant reference'));
        }
    }

    /**
     * Register order cancellation. Return money to customer if needed.
     *
     * @param Mage_Sales_Model_Order $order
     * @param string $message
     * @param bool $voidPayment
     * @return void
     */
    protected function declineOrder($order, $message = '', $voidPayment = true)
    {
        try {
            $response = $this->getResponse();
            if ($voidPayment && $response->getId()) {
                $order->getPayment()
                    ->setTransactionId(null)
                    ->setParentTransactionId($response->getId())
                    ->void($response);
            }
            $order->registerCancellation($message)->save();
            Mage::dispatchEvent('order_cancel_after', array('order' => $order));
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Fill payment with credit card data from response from Authorize.net.
     *
     * @param Varien_Object $payment
     * @return void
     */
    protected function fillPaymentByResponse(Varien_Object $payment)
    {
        $response = $this->getResponse();
        $payment->setTransactionId($response->getId())
            ->setParentTransactionId(null)
            ->setIsTransactionClosed(0);
    }

    /**
     * Add status comment to history
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return $this
     */
    protected function addStatusComment(Mage_Sales_Model_Order_Payment $payment)
    {
        $transactionId = $this->getResponse()->getId();
        if ($payment->getIsTransactionPending()) {
            $message = 'Amount of %1 is pending approval on the gateway.<br/>'
                . 'Transaction "%2" status is "%3".';

            $message = $this->_getHelper()->__(
                $message,
                $payment->getOrder()->getBaseCurrency()->formatTxt($this->getResponse()->getAmount()),
                $transactionId,
                $this->getResponse()->getStatus()
            );

            $payment->getOrder()->addStatusHistoryComment($message);
        }

        return $this;
    }

    /**
     * Capturing a payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     * @throws Mage_Core_Exception
     * @throws Zend_Http_Client_Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for capture.'));
        }

        parent::capture($payment, $amount);

        $invoice = Mage::registry('current_invoice');

        $payment->setSalesDocument($invoice);

        $transactionId = $payment->getAuthorizationTransaction() ?
            $payment->getAuthorizationTransaction()->getTxnId() : $payment->getTransactionId();

        $response = $this->_api->capture($transactionId, $payment, $amount);

        if (isset($response['error'])) {
            throw new \Exception('Failed to capture the payment');
        }

        return $this;
    }

    /**
     * Refunding payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     * @throws Zend_Http_Client_Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        parent::refund($payment, $amount);

        $payment->setSalesDocument($payment->getCreditmemo());
        $result = $this->_api->refund($payment, $amount);
        if (isset($result['error'])) {
            throw new \Exception('Couldn\'t refund the transaction');
        }
        return $this;
    }

    /**
     * Voiding transcation
     *
     * @param Varien_Object $payment
     * @throws Exception
     * @return $this
     */
    public function void(Varien_Object $payment)
    {
        parent::void($payment);
        $response = $this->_api->void($payment->getParentTransactionId() ?: $payment->getLastTransId());

        if (isset($response['error'])) {
            throw new \Exception('Failed to void the transaction');
        }

        return $this;
    }

    /**
     * Canceling transaction
     *
     * @param Varien_Object $payment
     * @return $this|Mage_Payment_Model_Abstract
     * @throws Exception
     */
    public function cancel(Varien_Object $payment)
    {
        parent::cancel($payment);
        $this->void($payment);
        return $this;
    }

    /**
     * Fetching transaction information
     *
     * @param Mage_Payment_Model_Info $payment
     * @param string $transactionId
     * @return array|Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        return $this->_api->getTransaction($transactionId);
    }

    /**
     * Return response.
     *
     * @return Dintero_Checkout_Model_Payment_Response
     */
    public function getResponse()
    {
        return Mage::getSingleton('dintero/payment_response');
    }

    /**
     * Checkout redirect URL getter for onepage checkout
     *
     * @see Mage_Checkout_OnepageController::savePaymentAction()
     * @see Mage_Sales_Model_Quote_Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::helper('dintero')->getPlaceOrderUrl();
    }
}