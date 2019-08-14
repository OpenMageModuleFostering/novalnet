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


class Mage_Novalnet_Block_Invoice_Form extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnet/invoice/form.phtml');
    }
    protected function _getConfig()
    {
        return Mage::getSingleton('payment/config');
    }
/*	public function getInvoiceAvailableCountries()
    {
        if ($method = $this->getMethod()) {
            $availableCountries = $method->getConfigData('invoicecountries');
            if ($availableCountries) {
                $availableCountries = explode(',', $availableCountries);
            }
        }
        return $availableCountries;
    }*/
	public function getUserGroupExcluded()
	{
		$method = $this->getMethod();
		return$method->getConfigData('user_group_excluded');
	}
	public function getUserGroupId($id)
	{
		if (!$id){
			return'';
			#Mage::throwException(__FUNCTION__.': '.Mage::helper('novalnet')->__('Parameter missing').'!');
		}
		$sql = "select customer_group_id from sales_flat_quote where customer_id = '$id'";
		$data = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
		return$data[0]['customer_group_id'];
	}
	public function checkUserGroupAccess($user_group_id, $user_group_name)
	{
		if (!$user_group_id or !$user_group_name){
			return'';
			#Mage::throwException(__FUNCTION__.': '.Mage::helper('novalnet')->__('Parameter missing').'!');
		}
		$sql = "select customer_group_id from customer_group where customer_group_id = '$user_group_id' and customer_group_code = '$user_group_name'";
		$data = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
		if ($data and count($data) >= 1){
			return false;
		}else {
			return true;
		}
	}
}
