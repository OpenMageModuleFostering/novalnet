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
 * @category   Paymentnetwork
 * @package    Paymentnetwork_Sofortueberweisung
 * @copyright  Copyright (c) 2008 [m]zentrale GbR, 2010 Payment Network AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @version	$Id: Linkpnso.php 199 2010-06-10 14:56:03Z thoma $
 */
  
class Varien_Data_Form_Element_Linkpnso extends Varien_Data_Form_Element_Abstract
{
	var $pnSu;
	public function __construct($attributes=array())
    {
        parent::__construct($attributes);
        $this->setType('label');
		$this->pnSu =  Mage::helper('sofortueberweisung');
		$this->pnSu->classSofortueberweisung();
    }

    public function getElementHtml()
    {

		#echo $this->getConfigDataWeb('base_url');
		#echo "<pre>";		
		#print_r($params = $this->getParams()->toArray());
		#echo "</pre>";

		$params = $this->getParams()->toArray();
		foreach($params AS $key => $val){
			switch($key){
				case 'backlink':
					$backurl = Mage::getSingleton('adminhtml/url')->getUrl($val);
					$params[$key] = $backurl;
				break;
				case 'projectssetting_interface_success_link':
					$params[$key] = $this->getConfigDataWeb('base_url').$val;
				break;
				case 'projectsnotification_http_url':
					$params[$key] = $this->getConfigDataWeb('base_url').$val;
				break;
				case 'projectssetting_interface_cancel_link':
					$params[$key] = $this->getConfigDataWeb('base_url').$val;
				break;
				case 'projectssetting_interface_timeout_link':
					$params[$key] = $this->getConfigDataWeb('base_url').$val;
				break;
				case 'projectssetting_project_password':
					$params[$key] = $this->pnSu->generateRandomValue();
					//store pwd in session so we can save it later
					Mage::getSingleton('adminhtml/session')->setData('projectssetting_project_password', $params[$key]);
				break;
				case 'project_notification_password':
					$params[$key] = $this->pnSu->generateRandomValue();
					Mage::getSingleton('adminhtml/session')->setData('project_notification_password', $params[$key]); 
				break;
				case 'projectsnotification_email_email':
					$params[$key] = Mage::getStoreConfig('trans_email/ident_general/email');
				break;
				case 'project_name':
					$params[$key] = Mage::getStoreConfig('general/store_information/name');
				break;
				default:
					$params[$key] = $val;
				break;		
			}
		}
		$queryString = http_build_query($params);
		
		$html = $this->getBold() ? '<strong>' : '';
    	$html.= sprintf($this->getValue(),$this->getConfigDataPayment('url_new').'?'.$queryString);
    	$html.= $this->getBold() ? '</strong>' : '';
    	$html.= $this->getAfterElementHtml();
    	return $html;
    }
	
	public function getConfigDataPayment($field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getStore();
        }
        $path = 'payment/sofortueberweisung/'.$field;
        return Mage::getStoreConfig($path, $storeId);
    }
	
	public function getConfigDataWeb($field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getStore();
        }
        $path = 'web/unsecure/'.$field;
        return Mage::getStoreConfig($path, $storeId);
    }
	
	public function getParams()
    {

		$_types = Mage::getConfig()->getNode('global/params_pnso/types')->asArray();
		$params = Mage::getModel('sofortueberweisung/params');		
        foreach ($_types as $data) {            
			$params->setData($data["param"],$data["value"]);
        }		
		return $params;
    }
}