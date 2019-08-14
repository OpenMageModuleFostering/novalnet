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
class Novalnet_Payment_Block_Adminhtml_Configuration_Wizard_Page_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{

    public function __construct()
    {
        $this->_mode = '';
        $this->_blockGroup = 'novalnet_payment';
        $this->_controller = 'adminhtml_configuration_wizard_page_edit';

        $this->_removeButton('delete');
        $this->_removeButton('reset');
        $this->_removeButton('back');
        $this->_removeButton('save');

        $this->_addButton('back', array(
            'label' => Mage::helper('novalnet_payment')->__('Back'),
            'onclick' => 'setLocation(\'' . $this->getBackUrl() . '\')',
            'class' => 'default',
        ));

        $this->_addButton('save', array(
            'label' => Mage::helper('novalnet_payment')->__('Save'),
            'onclick' => 'editForm.submit();',
            'class' => 'default',
        ));
    }

    public function getHeaderText()
    {
        $session = Mage::getSingleton('admin/session');
        $storeConfig = $session->getNnStoreConfig();

        $headerText = $this->getConfigPage('header_text');
        $text = Mage::helper('novalnet_payment')->__($headerText . ' for ' . $storeConfig);
        $session->unsNnStoreConfig();
        return $text;
    }

    public function getBackUrl()
    {
        $url = $this->helperWizard()->getPreviousPageUrlAsString();
        return $this->getUrl($url, array('_current' => true));
    }

    public function getConfigPage($path)
    {
        $config = $this->helperWizard()->getConfigPage();
        return $config->getData($path);
    }

    public function helperWizard()
    {
        return Mage::helper('novalnet_payment');
    }

}
