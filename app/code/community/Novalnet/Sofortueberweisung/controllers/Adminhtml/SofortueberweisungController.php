<?php
class Novalnet_Sofortueberweisung_Adminhtml_SofortueberweisungController extends Mage_Adminhtml_Controller_Action
{

	protected function _initAction() {
		$this->loadLayout()
			->_setActiveMenu('sofortueberweisung/items')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}
	
	public function saveConfigAction() {
		$params = $this->getRequest()->getParams();
		$session = Mage::getSingleton('adminhtml/session');
		if($this->getRequest()->getParams()){
			$groups = Array();
			$groups['sofortueberweisung']['fields']['customer']['value'] = $params["user_id"];
			$groups['sofortueberweisung']['fields']['project']['value']  = $params["project_id"];
			$groups['sofortueberweisung']['fields']['check_input_yesno']['value'] = 1;
			$groups['sofortueberweisung']['fields']['project_pswd']['value'] = $session->getData('projectssetting_project_password');
			$session->unsetData('projectssetting_project_password');
			$groups['sofortueberweisung']['fields']['notification_pswd']['value'] = $session->getData('project_notification_password');
			$session->unsetData('project_notification_password');
			
			try {
				Mage::getModel('adminhtml/config_data')
	                ->setSection('payment')
	                ->setWebsite($this->getRequest()->getParam('website'))
	                ->setStore($this->getRequest()->getParam('store'))
	                ->setGroups($groups)
	                ->save();
			}catch (Mage_Core_Exception $e) {
	            foreach(split("\n", $e->getMessage()) as $message) {
	                $session->addError($message);
	            }
	        }
	        catch (Exception $e) {
	            $session->addException($e, Mage::helper('adminhtml')->__('Error while saving this configuration: '.$e->getMessage()));
	        }
			
			Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('sofortueberweisung')->__('Item was successfully saved'));
			Mage::getSingleton('adminhtml/session')->setFormData(false);
		}

		$this->_redirect('adminhtml/system_config/edit', array('section'=>'payment'));
		return;
	}
	
	public function saveConfigPcAction() {

		$params = $this->getRequest()->getParams();
		$session = Mage::getSingleton('adminhtml/session');
		if($this->getRequest()->getParams()){
			$groups = Array();
			$groups['paycode']['fields']['customer']['value'] = $params["user_id"];
			$groups['paycode']['fields']['project']['value']  = $params["project_id"];
			$groups['paycode']['fields']['check_input_yesno']['value'] = 1;
			
			try {
				Mage::getModel('adminhtml/config_data')
	                ->setSection('payment')
	                ->setWebsite($this->getRequest()->getParam('website'))
	                ->setStore($this->getRequest()->getParam('store'))
	                ->setGroups($groups)
	                ->save();
			}catch (Mage_Core_Exception $e) {
	            foreach(split("\n", $e->getMessage()) as $message) {
	                $session->addError($message);
	            }
	        }
	        catch (Exception $e) {
	            $session->addException($e, Mage::helper('adminhtml')->__('Error while saving this configuration: '.$e->getMessage()));
	        }
			
			Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('sofortueberweisung')->__('Item was successfully saved'));
			Mage::getSingleton('adminhtml/session')->setFormData(false);
		}

		$this->_redirectUrl('/index.php/admin/system_config/edit/section/payment');
		return;
	}
}