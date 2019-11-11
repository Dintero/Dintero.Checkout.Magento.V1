<?php

/**
 * Class Dintero_Checkout_Model_Source_PaymentAction
 */
class Dintero_Checkout_Model_Source_PaymentAction
{
    /**
     * Payment actions
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Dintero_Checkout_Model_Dintero::ACTION_AUTHORIZE,
                'label' => Mage::helper('paygate')->__('Authorize Only')
            ),
            array(
                'value' => Dintero_Checkout_Model_Dintero::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('paygate')->__('Authorize and Capture')
            ),
        );
    }
}