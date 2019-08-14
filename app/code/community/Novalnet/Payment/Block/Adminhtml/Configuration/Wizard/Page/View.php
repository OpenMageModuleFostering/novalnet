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
class Novalnet_Payment_Block_Adminhtml_Configuration_Wizard_Page_View extends Mage_Adminhtml_Block_Widget_View_Container {

    public function __construct() {
        $this->_blockGroup = 'novalnet_payment';
        $this->_controller = 'adminhtml_configuration_wizard_page';
        $this->_headerText = Mage::helper('novalnet_payment')->__('Novalnet Payment Configuration');

        $this->_removeButton('edit');
        $this->_removeButton('back');

        $this->_addButton('save', array(
            'label' => Mage::helper('adminhtml')->__('Continue'),
            'class' => 'default',
            'onclick' => 'window.location.href=\'' . $this->getNextUrl() . '\'',
        ));
    }

    public function getViewHtml() {
        $html = '';
        foreach ($this->getSortedChildren() as $childName) {

            $child = $this->getChild($childName);

            $html .= $child->toHtml();
        }
        return $html;
    }

    protected function getNextUrl() {
        $url = $this->helperWizard()->getNextPageUrlAsString();
        return $this->getUrl($url, array('_current' => true));
    }

    public function getHeaderText() {
        $headerText = $this->getConfigPage('header_text');
        $text = Mage::helper('novalnet_payment')->__($headerText);
        return $text;
    }

    public function getConfigPage($path) {
        $config = $this->helperWizard()->getConfigPage();
        return $config->getData($path);
    }

    public function helperWizard() {
        return Mage::helper('novalnet_payment');
    }
    
	protected function _prepareLayout()	{
		$this->unsetChild('', '');
	}
}
