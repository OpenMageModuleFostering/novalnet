<?php
class Novalnet_NovalnetPaypal_Block_Form_NovalnetPaypal extends Mage_Payment_Block_Form
{
    /**
     * Init default template for block
     */
	protected function _construct()
    {
	
        parent::_construct();
        $this->setTemplate('novalnetpaypal/form/novalnetpaypal.phtml');
    }  
	
	/**
     * Retrieve payment configuration object
     *
     * @return Mage_Payment_Model_Config
     */
    protected function _getConfig()
    {
        return Mage::getSingleton('payment/config');
    }
}