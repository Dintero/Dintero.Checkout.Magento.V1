<?php

/**
 * Class Dintero_Hp_Model_Source_Config_ColorPicker
 */
class Dintero_Hp_Block_Source_Config_ColorPicker
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Element html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $color = new Varien_Data_Form_Element_Text();
        $data = array(
            'name'      => $element->getName(),
            'html_id'   => $element->getId(),
        );
        $color->setData( $data );
        $color->setValue( $element->getValue());
        $color->setForm( $element->getForm() );
        $color->addClass( 'color ' . $element->getClass() );

        return $color->getElementHtml();
    }
}