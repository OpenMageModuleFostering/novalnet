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
 * Part of the Paymentmodule of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Adminhtml_Configuration_Wizard_PageController extends Mage_Adminhtml_Controller_Action {

    protected function _initAction() {
        $this->loadLayout();
        $this->setUsedModuleName('novalnet_payment');
        $this->_setActiveMenu('novalnet/configuration');

        $this->_title($this->__('Novalnet'));
        $this->_title($this->__('Configuration'));

        return $this;
    }

    public function indexAction() {
        $this->initConfig('index');

        $this->_initAction();
        $this->renderLayout();
    }

    protected function initConfig($actionName) {
        return $this->helperWizard()->initConfig($actionName, $this->getRequest());
    }

    public function helperWizard() {
        return Mage::helper('novalnet_payment');
    }

    public function generalGlobalAction() {
        $this->_editAction('generalGlobal');
    }

    protected function _editAction($actionName) {
        $this->initConfig($actionName);

        $configPage = Mage::registry('novalnet_wizard_config_page');

        Mage::getSingleton('adminhtml/config_data')
                ->setSection($configPage->getData('codes/section'))
                ->setWebsite($configPage->getData('codes/website'))
                ->setStore($configPage->getData('codes/store'));

        $this->_initAction();
        $this->renderLayout();
    }

    public function _redirectByPageConfig() {
        $url = $this->helperWizard()->getNextPageUrlAsString();
        $this->_redirect($url, array('_current' => true));
    }

    public function saveAction() {
        $pageCode = $this->getRequest()->getParam('page_code');

        $config = $this->initConfig($pageCode);


        $session = Mage::getSingleton('adminhtml/session');

        try {

            $section = $config->getData('codes/section');
            $website = $this->getRequest()->getParam('website');
            $store = $this->getRequest()->getParam('store');
            $groups = $this->getRequest()->getPost('groups');
            $configData = Mage::getModel('adminhtml/config_data');
            $configData->setSection($section)
                    ->setWebsite($website)
                    ->setStore($store)
                    ->setGroups($groups)
                    ->save();

            $session->addSuccess(Mage::helper('novalnet_payment')->__('The configuration has been saved.'));
        } catch (Mage_Core_Exception $e) {
            foreach (explode("\n", $e->getMessage()) as $message) {
                $session->addError($message);
            }
        } catch (Exception $e) {
            $msg = Mage::helper('novalnet_payment')->__('An error occurred while saving:') . ' ' . $e->getMessage();
            $session->addException($e, $msg);
        }

        $this->_redirectByPageConfig();
    }

}