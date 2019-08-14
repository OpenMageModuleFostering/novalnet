<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * Part of the Paymentmodul of Novalnet AG
 * http://www.novalnet.de 
 * If you have found this script usefull a small        
 * recommendation as well as a comment on merchant form 
 * would be greatly appreciated.
 * 
 * @category   design_default
 * @package    Mage
 * @copyright  Copyright (c) 2008 Novalnet AG
 * @version    1.0.0
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Mage_Novalnet_Block_Cc_Form extends Mage_Payment_Block_Form
{
	private $_localConfig;
	
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnet/cc/form.phtml');
    }
/**
     * Retrieve payment configuration object
     *
     * @return Mage_Payment_Model_Config
     */
    protected function _getConfig()
    {
		if (!$this->_localConfig) {
			$this->_localConfig = Mage::getModel('payment/config');
		}
		return $this->_localConfig;
    }

     /**
     * Retrieve availables credit card types
     *
     * @return array
     */
    public function getCcAvailableTypes()
    {
        $types = $this->_getConfig()->getCcTypes();
        if ($method = $this->getMethod()) {
            $availableTypes = $method->getConfigData('cctypes');
            if ($availableTypes) {
                $availableTypes = explode(',', $availableTypes);
                foreach ($types as $code=>$name) {
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }
        return $types;
    }
    /**
     * Retrieve payment configuration object
     *
     * @return Mage_Payment_Model_Config
     */
	public function checkCustomerAccess() {
		
		$exludedGroupes = trim($this->getMethod()->getConfigData('user_group_excluded'));
		if( strlen( $exludedGroupes ) ) {
			$exludedGroupes = explode(',', $exludedGroupes);
			$custGrpId = Mage::getSingleton('customer/session')->getCustomerGroupId();
			return !in_array($custGrpId, $exludedGroupes);
		}
		return true;
	}


}
