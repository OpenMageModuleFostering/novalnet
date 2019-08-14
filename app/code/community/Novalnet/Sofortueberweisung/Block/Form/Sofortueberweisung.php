<?php
class Novalnet_Sofortueberweisung_Block_Form_Sofortueberweisung extends Mage_Payment_Block_Form
{
    /**
     * Init default template for block
     */
	protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('sofortueberweisung/form/sofortueberweisung.phtml');
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
	
	public function getNetherlandBanks($selected)
	{
		$bankArray = Array(
				'NL_101' => 'ING Calculator',
				'NL_102' => 'Fortis Bank',
				'NL_103' => 'ABN Amro Bank',
				'NL_104' => 'SNS Bank',
				'NL_105' => 'Rabobank',
				'NL_106' => 'ING Wachtwoord',
				'NL_109' => 'SNS Regio Bank',
				);
		
		foreach($bankArray AS $key => $val){
			$selectArray[$key] = Array('value' => $val,'selected' => 0);
			if($key == $selected){
				$selectArray[$key]['selected'] = 1;
			}
		}
		
		return $selectArray;
	}
}