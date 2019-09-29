<?php

/**
 * Class Dintero_Hp_PaymentController
 */
class Dintero_Hp_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Retrieving checkout session
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Retrieving customer session
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Retrieving api client
     *
     * @return Dintero_Hp_Model_Api_Client
     */
    protected function _getApi()
    {
        return Mage::getSingleton('dintero/api_client');
    }

    /**
     * Response Action
     *
     * @throws Exception
     */
    public function responseAction()
    {
        $merchantOrderId = $this->getRequest()->getParam('merchant_reference');
        $transactionId = $this->getRequest()->getParam('transaction_id');

        /** @var Dintero_Hp_Model_Dintero $paymentMethod */
        $paymentMethod = Mage::getModel('dintero/dintero');
        $paymentMethod->process($merchantOrderId, $transactionId);
    }

    /**
     * Placing order
     */
    public function placeAction()
    {
        try {

            /**
             * @var $order Mage_Sales_Model_Order
             */
            if (!$order = Mage::getModel('sales/order')->load($this->_getCheckout()->getLastOrderId())) {
                Mage::throwException('Order not found');
            }

            if ($order->getPayment()->getMethod() !== 'dintero') {
                Mage::throwException('Invalid payment method');
            }

            $response = $this->_getApi()->initCheckout($order);

            if ($response['error']) {
                Mage::throwException('Failed to initialize checkout');
            }

            return $this->_redirectUrl($response['url']);

        } catch (\Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError('Failed to initialize checkout');
            return $this->_redirect('checkout/cart');
        }
    }

    /**
     * Routing to success or cart page depending on response from Dintero
     *
     * @return Mage_Core_Controller_Varien_Action
     * @throws Mage_Core_Exception
     */
    public function successAction()
    {
        if ($this->getRequest()->getParam('transaction_id')) {
            return $this->_redirect('checkout/onepage/success');
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('merchant_reference'));

        if ($order->getId() && $order->canCancel()) {
            $order->getPayment()->setTransactionId(null);
            $order->cancel();
        }
        $this->_getSession()->addError(Mage::helper('core')->__('Payment failed'));
        return $this->_redirect('checkout/cart');
    }
}