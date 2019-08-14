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
class Novalnet_Payment_Block_Adminhtml_Information extends Mage_Adminhtml_Block_Template
{

    /**
     * Assign the template to load the novalnet magento module updates
     *
     */
    public function _construct()
    {
        $this->setTemplate("novalnet/information/iframe.phtml");
    }

    /**
     * Set the Novalnet Magento module path
     *
     * @return string
     */
    public function getNovalnetUrl()
    {
        $protocol = Mage::app()->getStore()->isCurrentlySecure() ? 'https' : 'http';
        $url = $this->escapeUrl($protocol . '://www.novalnet.de/modul/magento-payment-module');
        return $url;
    }

}