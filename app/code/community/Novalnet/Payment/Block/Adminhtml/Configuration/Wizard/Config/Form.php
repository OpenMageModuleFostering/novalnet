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
class Novalnet_Payment_Block_Adminhtml_Configuration_Wizard_Config_Form extends Mage_Adminhtml_Block_System_Config_Form {

    /**
     * @var string
     */
    protected $groupName = '';

    protected function _prepareLayout() {
        $return = parent::_prepareLayout();
        $this->initForm();
        return $return;
    }

    /**
     *
     * @return Mage_Adminhtml_Block_System_Config_Form
     */
    public function initForm() {
        $this->_initObjects();
        $form = $this->_initForm();

        $sections = $this->_configFields->getSection(
                $this->getSectionCode(), $this->getWebsiteCode(), $this->getStoreCode()
        );

		$session = Mage::getSingleton('admin/session');

        if (!$this->getWebsiteCode() && !$this->getStoreCode()) {
            $session->setNnStoreConfig(Mage::helper('novalnet_payment')->__('Standard'));
        } else if ($this->getWebsiteCode() != '' && !$this->getStoreCode()) {
            $storeConfig = Mage::getModel('core/website')->load($this->getWebsiteCode())->getName();
            Mage::register('webConfig', Mage::getModel('core/website')->load($this->getWebsiteCode())->getId());
            $session->setNnStoreConfig($storeConfig);
        } else {
            $storeConfig = Mage::getModel('core/store')->load($this->getStoreCode())->getName();
            Mage::register('storeConfig', Mage::getModel('core/store')->load($this->getStoreCode())->getId());
            $session->setNnStoreConfig($storeConfig);
        }
		
        $groups = $sections->groups;
        $groupName = $this->getGroupName();
        $group = $groups->$groupName;

        $fieldsetRenderer = Mage::getBlockSingleton('Mage_Adminhtml_Block_Widget_Form_Renderer_Fieldset');
        $fieldsetConfig = array(
            'legend' => Mage::helper('novalnet_payment')->__((string) $group->label),
        );
        $fieldset = $form->addFieldset($sections->getName() . '_' . $group->getName(), $fieldsetConfig);
        $fieldsetRenderer->setForm($this);

        $this->initFields($fieldset, $group, $sections);

        $fieldset->addField(
            'page_code', 'hidden', array(
            'name' => 'page_code',
            'value' => $this->getPageCode()
                )
        );
        $form->setUseContainer(true);
        $this->setForm($form);
        return $this;
    }

    /**
     * @return Varien_Data_Form
     */
    protected function _initForm() {
        $form = new Varien_Data_Form(
                        array(
                            'id' => 'edit_form',
                            'action' => $this->getSaveUrl(),
                            'method' => 'post',
                            'enctype' => 'multipart/form-data'
                        )
        );
        return $form;
    }

    protected function getSaveUrl() {
        return $this->getUrl('*/*/save', array('_current' => true));
    }

    /**
     * @return string
     */
    public function getGroupName() {
        return $this->getConfigPage('group_name');
    }

    /**
     * @return string
     */
    public function getPageCode() {
        return $this->getConfigPage('codes/page');
    }

    /**
     * @return string
     */
    public function getSectionCode() {
        return $this->getConfigPage('codes/section');
    }

    /**
     * @return string
     */
    public function getStoreCode() {
        return $this->getConfigPage('codes/store');
    }

    /**
     * @return string
     */
    public function getWebsiteCode() {
        return $this->getConfigPage('codes/website');
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function getConfigPage($path) {
        $config = $this->helperWizard()->getConfigPage();
        return $config->getData($path);
    }

    /**
     * @return Novalnet_Payment_Helper_Wizard
     */
    public function helperWizard() {
        return Mage::helper('novalnet_payment');
    }

}
