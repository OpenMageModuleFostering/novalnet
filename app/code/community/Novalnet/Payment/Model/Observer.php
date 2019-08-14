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
class Novalnet_Payment_Model_Observer
{
    /* @var Magento_Sales_Model_Order_Invoice */

    public function sendInvoiceEmail($observer)
    {
        try {
            /* @var $order Magento_Sales_Model_Order_Invoice */
            $invoice = $observer->getEvent()->getInvoice();
            $paymentCode = $invoice->getOrder()->getPayment()->getMethodInstance()->getCode();
            $tidPayment = Mage::app()->getRequest()->getParam('tid_payment');

            if ($paymentCode == Novalnet_Payment_Model_Config::NN_INVOICE && $tidPayment
                    == '') {
                $invoice->setState(1);
                $invoice->save();
                $invoice->sendEmail($invoice);
            } else {
                $invoice->save();
                $invoice->sendEmail($invoice);
            }
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e->getMessage());
        }

        return $this;
    }

    public function sendCreditmemoEmail($observer)
    {
        try {
            /* @var $order Magento_Sales_Model_Order_Creditmemo */
            $refund = $observer->getEvent()->getCreditmemo();
            $refund->save();
            $refund->sendEmail($refund);
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e->getMessage());
        }

        return $this;
    }

    public function prepareLayoutBefore(Varien_Event_Observer $observer)
    {
        /* @var $block Mage_Page_Block_Html_Head */
        if (!Mage::app()->getStore()->isCurrentlySecure()) {
            $baseUrl = Mage::getBaseUrl();
        } else {
            $baseUrl = Mage::getUrl('', array('_secure' => true));
        }
        $currentlUrl = Mage::helper('core/url')->getCurrentUrl();
        $block = $observer->getEvent()->getBlock();
        if ("head" == $block->getNameInLayout() && $currentlUrl != $baseUrl) {
            foreach (Mage::helper('novalnet_payment/AssignData')->getFiles() as $file) {
                $block->addJs(Mage::helper('novalnet_payment/AssignData')->getJQueryPath($file));
            }
        }

        return $this;
    }

    public function customerLogin()
    {
        Mage::getSingleton('core/session')->setGuestloginvalue('');
        return;
    }

}
