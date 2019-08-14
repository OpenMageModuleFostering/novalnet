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
class Novalnet_Payment_Block_Adminhtml_Configuration_Wizard_Page_Save extends Mage_Adminhtml_Block_Widget_Form_Container
{

    public function __construct()
    {
        $this->_mode = '';
        $this->_blockGroup = 'novalnet_payment';
        $this->_controller = 'adminhtml_configuration_wizard_page_save';

        $this->_removeButton('delete')
             ->_removeButton('reset')
             ->_removeButton('back')
             ->_removeButton('save');

        $this->_addButton('back', array(
            'label' => $this->helperWizard()->__('Back'),
            'onclick' => 'setLocation(\'' . $this->getBackUrl() . '\')',
            'class' => 'default',
            'style' => 'margin-top: 10px;',
        ));

        $this->_addButton('save', array(
            'label' => $this->helperWizard()->__('Save'),
            'onclick' => 'saveForm.submit();',
            'class' => 'default',
        ));
    }

    /**
     * Get header text of configuration wizard page
     *
     * @return string
     */
    public function getHeaderText()
    {
        $session = Mage::getSingleton('admin/session');
        $storeConfig = $session->getNnStoreConfig();

        $headerText = $this->helperWizard()->__($this->getConfigPage('header_text'));
        $text = $this->helperWizard()->__('%s for %s', $headerText, $storeConfig);
        $session->unsNnStoreConfig();
        return $text;
    }

    /**
     * Get configuration wizard page back url
     *
     * @return string
     */
    public function getBackUrl()
    {
        $url = $this->helperWizard()->getPreviousPageUrlAsString();
        return $this->getUrl($url, array('_current' => true));
    }

    /**
     * Get configuration page path
     *
     * @param string $path
     * @return mixed
     */
    public function getConfigPage($path)
    {
        $config = $this->helperWizard()->getConfigPage();
        return $config->getData($path);
    }

    /**
     * Get Novalnet payment helper
     *
     * @return Novalnet_Payment_Helper_Data
     */
    public function helperWizard()
    {
        return Mage::helper('novalnet_payment');
    }

}
