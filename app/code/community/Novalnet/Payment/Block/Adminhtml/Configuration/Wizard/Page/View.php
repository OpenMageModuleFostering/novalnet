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
class Novalnet_Payment_Block_Adminhtml_Configuration_Wizard_Page_View extends Mage_Adminhtml_Block_Widget_View_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'novalnet_payment';
        $this->_controller = 'adminhtml_configuration_wizard_page';
        $this->_headerText = $this->helperWizard()->__('Novalnet Payment Configuration');

        $this->_removeButton('edit')
             ->_removeButton('back');

        $this->_addButton('save', array(
            'label' => Mage::helper('adminhtml')->__('Continue'),
            'class' => 'default',
            'onclick' => 'window.location.href=\'' . $this->getNextUrl() . '\'',
        ));
    }

    /**
     * Get configuration wizard page view
     *
     * @return mixed
     */
    public function getViewHtml()
    {
        $html = '';
        foreach ($this->getSortedChildren() as $childName) {

            $child = $this->getChild($childName);

            $html .= $child->toHtml();
        }
        return $html;
    }

    /**
     * Get configuration wizard next page url
     *
     * @return string
     */
    protected function getNextUrl()
    {
        $url = $this->helperWizard()->getNextPageUrlAsString();
        return $this->getUrl($url, array('_current' => true));
    }

    /**
     * Get header text of configuration wizard page
     *
     * @return string
     */
    public function getHeaderText()
    {
        $headerText = $this->helperWizard()->__($this->getConfigPage('header_text'));
        return $headerText;
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

    /**
     * Prepare layout
     *
     * @return null
     */
    protected function _prepareLayout()
    {
        $this->unsetChild('', '');
    }

}
