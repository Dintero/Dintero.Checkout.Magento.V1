<?php

/**
 * Class Dintero_Checkout_Block_Form
 */
class Dintero_Checkout_Block_Form extends Mage_Payment_Block_Form
{
    /**
     * Dintero_Checkout_Block_Form constructor.
     *
     * @param array $args
     */
    public function __construct(array $args = array())
    {
        parent::__construct($args);

        $paymentMethodsImage = Mage::app()->getLayout()->createBlock('core/template');
        $paymentMethodsImage->setTemplate('dintero/checkout/checkout-logo.phtml');
        $this->setMethodTitle('');
        $this->setMethodLabelAfterHtml($paymentMethodsImage->toHtml());
    }
}
