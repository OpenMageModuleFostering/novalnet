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
        return Mage::getSingleton('payment/config');
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
     * Retrieve credit card expire months
     *
     * @return array
     */
    public function getCcMonths()
    {
        $months = $this->getData('cc_months');
        if (is_null($months)) {
            $months[0] =  $this->__('Month');
            $months = array_merge($months, $this->_getConfig()->getMonths());
            $this->setData('cc_months', $months);
        }
        return $months;
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    public function getCcYears()
    {
        $years = $this->getData('cc_years');
        if (is_null($years)) {
            $years = $this->_getConfig()->getYears();
            $years = array(0=>$this->__('Year'))+$years;
            $this->setData('cc_years', $years);
        }
        return $years;
    }

    /**
     * Retrive has verification configuration
     *
     * @return boolean
     */
    public function hasVerification()
    {
        if ($this->getMethod()) {
            $configData = $this->getMethod()->getConfigData('useccv');
            if(is_null($configData)){
                return true;
            }
            return (bool) $configData;
        }
        return true;
    }
	public function getCcData($customer_id){
		if (!$customer_id){
			return'';
			#Mage::throwException(__FUNCTION__.': '.Mage::helper('novalnet')->__('Parameter missing').'!');
		}
		#cc_type, cc_last4, cc_owner, cc_exp_month, cc_exp_year, nn_account_holder #nn_account_number, nn_bank_sorting_code, nn_elv_country
		$sql = "select a.cc_type, a.cc_last4, a.cc_owner, a.cc_exp_month, a.cc_exp_year from sales_flat_quote_payment a, sales_flat_quote b where b.customer_id = '$customer_id' and b.entity_id = a.quote_id and a.method = 'novalnetCc' order by a.created_at desc limit 1";
		$data = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
		if ($data and count($data)>0)return $data[0];
		return'';
	}
	public function getUserGroupExcluded()
	{
		$method = $this->getMethod();
		return$method->getConfigData('user_group_excluded');
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
}