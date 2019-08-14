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


class Mage_Novalnet_Block_Phonepayment_Form extends Mage_Payment_Block_Form
{
	private $_localConfig;
	
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnet/phonepayment/form.phtml');
    }
	
    protected function _getConfig()
    {
        if(empty($this->_localConfig)) {
			$this->_localConfig = Mage::getSingleton('payment/config');
		}
		return $this->_localConfig;
    }
	
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
