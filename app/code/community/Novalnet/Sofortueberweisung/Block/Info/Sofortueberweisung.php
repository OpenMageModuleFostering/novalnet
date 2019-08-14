<?php
class Novalnet_Sofortueberweisung_Block_Info_Sofortueberweisung extends Mage_Payment_Block_Info
{
    /**
     * Init default template for block
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('sofortueberweisung/info/sofortueberweisung.phtml');
    }
	
	 /**
     * Retrieve info model
     *
     * @return Mage_Sofortueberweisung_Model_Info
     */
    public function getInfo()
    {
        $info = $this->getData('info');
		
        if (!($info instanceof Mage_Payment_Model_Info)) {
            Mage::throwException($this->__('Can not retrieve payment info model object.'));
        }
        return $info;
    }
	
	 /**
     * Retrieve payment method model
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function getMethod()
    {
        return $this->getInfo()->getMethodInstance();
    }
	
	public function toPdf()
    {
        $this->setTemplate('sofortueberweisung/info/pdf/sofortueberweisung.phtml');
        return $this->toHtml();
    }
}