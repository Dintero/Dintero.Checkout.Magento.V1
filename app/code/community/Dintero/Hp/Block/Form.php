<?php

/**
 * Class Dintero_Hp_Block_Form
 */
class Dintero_Hp_Block_Form extends Mage_Payment_Block_Form
{
    /**
     * Dintero_Hp_Block_Form constructor.
     *
     * @param array $args
     */
    public function __construct(array $args = array())
    {
        parent::__construct($args);

        $paymentMethodsImage = Mage::app()->getLayout()->createBlock('core/template');
        $paymentMethodsImage->setTemplate('dintero/hp/checkout-logo.phtml');
        $this->setMethodTitle('');
        $this->setMethodLabelAfterHtml($paymentMethodsImage->toHtml());
    }
}