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


class Mage_Novalnet_Block_Elvaustria_Form extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnet/elvaustria/form.phtml');
    }
    protected function _getConfig()
    {
        return Mage::getSingleton('payment/config');
    }
	public function acdc_check()
	{
		$method = $this->getMethod();
		return$method->getConfigData('acdc_check');
	}
	public function show_comment()
	{
		$method = $this->getMethod();
		return$method->getConfigData('comment');
	}
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
	public function getAccountData($customer_id){
		if (!$customer_id){
			return'';
			#Mage::throwException(__FUNCTION__.': '.Mage::helper('novalnet')->__('Parameter missing').'!');
		}
		#cc_type, cc_last4, cc_owner, cc_exp_month, cc_exp_year, nn_account_holder #nn_account_number, nn_bank_sorting_code, nn_elv_country
		$sql = "select a.nn_account_holder, a.nn_account_number, a.nn_bank_sorting_code, a.nn_elv_country from sales_flat_quote_payment a, sales_flat_quote b where b.customer_id = '$customer_id' and b.entity_id = a.quote_id and a.nn_account_number != '' and a.method = 'novalnetElvaustria' order by a.created_at desc limit 1";
		$data = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
		if ($data and count($data)>0)return $data[0];
		return'';
	}
}
