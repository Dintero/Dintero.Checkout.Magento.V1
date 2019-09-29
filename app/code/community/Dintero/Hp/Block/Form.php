<?php

/**
 * Class Dintero_Hp_Block_Form
 */
class Dintero_Hp_Block_Form extends Mage_Payment_Block_Form
{

    /**
     * Set method info
     *
     * @return Mage_Authorizenet_Block_Directpost_Form
     */
    public function setMethodInfo()
    {
        $payment = Mage::getSingleton('checkout/type_onepage')
            ->getQuote()
            ->getPayment();
        $this->setMethod($payment->getMethodInstance());

        return $this;
    }

    protected function _toHtml()
    {
        if ($this->getMethod()->getCode() != Mage::getSingleton('dintero/dintero')->getCode()) {
            return null;
        }

        return parent::_toHtml();
    }

    /**
     * Retrieving place order url
     *
     * @return mixed
     */
    public function getPlaceOrderUrl()
    {
        return $this->helper('dintero')->getPlaceOrderUrl();
    }
}