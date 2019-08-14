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
 * Part of the payment module of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Block_Adminhtml_Configuration_Wizard_Config_Form extends Mage_Adminhtml_Block_System_Config_Form
{
    /**
     * Prepare configuration wizard form layout
     *
     */
    protected function _prepareLayout()
    {
        $return = parent::_prepareLayout();
        $this->initForm();
        return $return;
    }

    /**
     * Initialize configuration wizard form
     *
     * @return Mage_Adminhtml_Block_System_Config_Form
     */
    public function initForm()
    {
        $this->_initObjects();
        $form = $this->_initForm();
        $getWebsiteCode = $this->getConfigPage('codes/website');
        $getStoreCode = $this->getConfigPage('codes/store');
        $sections = $this->_configFields->getSection(
                $this->getConfigPage('codes/section'), $getWebsiteCode, $getStoreCode
        );

        $session = Mage::getSingleton('admin/session');

        if (!$getWebsiteCode && !$getStoreCode) {
            $session->setNnStoreConfig('Standard');
        } else if ($getWebsiteCode != '' && !$getStoreCode) {
            $storeConfig = Mage::getModel('core/website')->load($getWebsiteCode)->getName();
            Mage::register('webConfig', Mage::getModel('core/website')->load($getWebsiteCode)->getId());
            $session->setNnStoreConfig($storeConfig);
        } else {
            $storeConfig = Mage::getModel('core/store')->load($getStoreCode)->getName();
            Mage::register('storeConfig', Mage::getModel('core/store')->load($getStoreCode)->getId());
            $session->setNnStoreConfig($storeConfig);
        }

        $groups = $sections->groups;
        $gropupArray = $this->getConfigPage('group_name');
        foreach ($gropupArray as $gropupArrayvalue) {
            $group = $groups->$gropupArrayvalue;

            $fieldsetRenderer = Mage::getBlockSingleton('Mage_Adminhtml_Block_Widget_Form_Renderer_Fieldset');
            $fieldsetConfig = array(
                'legend' => Mage::helper('novalnet_payment')->__((string) $group->label),
            );
            $fieldset = $form->addFieldset($sections->getName() . '_' . $group->getName(), $fieldsetConfig);
            $fieldsetRenderer->setForm($this);

            $this->initFields($fieldset, $group, $sections);
        }
        $fieldset->addField(
                'page_code', 'hidden', array(
            'name' => 'page_code',
            'value' => $this->getConfigPage('codes/page')
                )
        );
        $form->setUseContainer(true);
        $this->setForm($form);
        return $this;
    }

    /**
     * Initialize configuration wizard form
     *
     * @return Varien_Data_Form
     */
    protected function _initForm()
    {
        $form = new Varien_Data_Form(
                array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', array('_current' => true)),
            'method' => 'post',
            'enctype' => 'multipart/form-data'
                )
        );
        return $form;
    }

    /**
     * Get configuration page path
     *
     * @param string $path
     * @return mixed
     */
    public function getConfigPage($path)
    {
        $config = Mage::helper('novalnet_payment')->getConfigPage();
        return $config->getData($path);
    }

}
