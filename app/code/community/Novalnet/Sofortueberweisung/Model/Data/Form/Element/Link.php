<?php
class Varien_Data_Form_Element_Label extends Varien_Data_Form_Element_Abstract
{
	public function __construct($attributes=array())
    {
        parent::__construct($attributes);
        $this->setType('label');
    }

    public function getElementHtml()
    {
    	$html = $this->getBold() ? '<strong>' : '';
    	$html.= $this->getValue();
    	$html.= $this->getBold() ? '</strong>' : '';
    	$html.= $this->getAfterElementHtml();
    	return $html;
    }

}