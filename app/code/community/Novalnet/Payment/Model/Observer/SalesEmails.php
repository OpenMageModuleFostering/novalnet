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
 * @category  Novalnet
 * @package   Novalnet_Payment
 * @copyright Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Observer_SalesEmails
{
    /**
     * Send order mail
     *
     * @param  Varien_Object $observer
     * @return Novalnet_Payment_Model_Observer_SalesEmails
     */
    public function sendOrderEmail($observer)
    {
        /* $order Magento_Sales_Model_Order */
        $order = $observer->getEvent()->getOrder();
        if (!$order->getEmailSent() && $order->getId()) {
            try {
                $order->sendNewOrderEmail()
                    ->setEmailSent(true)
                    ->save();
            } catch (Mage_Core_Exception $e) {
                Mage::log($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Send order invoice mail
     *
     * @param  Varien_Object $observer
     * @return Novalnet_Payment_Model_Observer_SalesEmails
     */
    public function sendInvoiceEmail($observer)
    {
        try {
            /* $order Magento_Sales_Model_Order_Invoice */
            $invoice = $observer->getEvent()->getInvoice();
            // Get payment method code
            $paymentCode = $invoice->getOrder()->getPayment()->getMethodInstance()->getCode();
            $currentUrl = Mage::helper('core/url')->getCurrentUrl();
            // Set capture status for Novalnet payments
            if (Mage::app()->getStore()->isAdmin()
                && !preg_match("/sales_order_create/i", $currentUrl)
            ) {
                $this->setCaptureOrderStatus($invoice->getOrder(), $paymentCode);
            }
            // Set order invoice status as pending for Novalnet invoice payment
            if ($paymentCode == Novalnet_Payment_Model_Config::NN_INVOICE
                && !Mage::app()->getRequest()->getParam('tid_payment')
            ) {
                $invoice->setState(1);
            }
            $invoice->save();
            $invoice->sendEmail($invoice);
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage());
        }

        return $this;
    }

    /**
     * Send order creditmemo mail
     *
     * @param  Varien_Object $observer
     * @return Novalnet_Payment_Model_Observer_SalesEmails
     */
    public function sendCreditmemoEmail($observer)
    {
        try {
            /* $order Magento_Sales_Model_Order_Creditmemo */
            $refund = $observer->getEvent()->getCreditmemo();
            $refund->save();
            $refund->sendEmail($refund);
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e->getMessage());
        }

        return $this;
    }

    /**
     * Set canceled/VOID status for Novalnet payments
     *
     * @param Varien_Object $observer
     * @param none
     */
    public function setVoidOrderStatus($observer)
    {
        /* $order Magento_Sales_Model_Order */
        $payment = $observer->getEvent()->getPayment();
        $order = $payment->getOrder(); // Get order object

        if (preg_match("/novalnet/i", $payment->getMethodInstance()->getCode())) {
            $voidOrderStatus = Mage::getStoreConfig(
                'novalnet_global/order_status_mapping/void_status', $order->getStoreId()
            )
                ? Mage::getStoreConfig(
                    'novalnet_global/order_status_mapping/void_status', $order->getStoreId()
                )
                : Mage_Sales_Model_Order::STATE_CANCELED;
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                $voidOrderStatus,
                Mage::helper('novalnet_payment')->__('Order was canceled'),
                true
            )->save();
        }
    }

    /**
     * Set capture status for Novalnet payments
     *
     * @param Varien_Object $order
     * @param none
     */
    protected function setCaptureOrderStatus($order, $paymentCode)
    {
        if (preg_match("/novalnet/i", $paymentCode)) {
            $captureOrderStatus = Mage::getStoreConfig(
                'novalnet_global/order_status_mapping/order_status', $order->getStoreId()
            )
                ? Mage::getStoreConfig(
                    'novalnet_global/order_status_mapping/order_status', $order->getStoreId()
                )
                : Mage_Sales_Model_Order::STATE_PROCESSING;
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                $captureOrderStatus,
                Mage::helper('novalnet_payment')->__('The transaction has been confirmed'),
                true
            )->save();
        }
    }

}
