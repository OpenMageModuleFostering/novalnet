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
class Novalnet_Payment_Model_Observer
{
    /**
     * Send order invoice mail
     *
     * @param varien_object $observer
     * @return Novalnet_Payment_Model_Observer
     */
    public function sendInvoiceEmail($observer)
    {
        try {
            /* @var $order Magento_Sales_Model_Order_Invoice */
            $invoice = $observer->getEvent()->getInvoice();
            if(Mage::app()->getStore()->isAdmin()){
            $this->onHoldOrderStatus($invoice);
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
     * @param varien_object $observer
     * @return Novalnet_Payment_Model_Observer
     */
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

    /**
     * Load novalnet script files while prpareing layout
     *
     * @param varien_object $observer
     * @return Novalnet_Payment_Model_Observer
     */
    public function prepareLayoutBefore(Varien_Event_Observer $observer)
    {
        $nnAffId = Mage::app()->getRequest()->getParam('nn_aff_id');
        if ($nnAffId) {
            Mage::getSingleton('core/session')->setNnAffId(trim($nnAffId));
        }
        /* @var $block Mage_Page_Block_Html_Head */
        if (!Mage::app()->getStore()->isCurrentlySecure()) {
            $baseurl = Mage::getBaseUrl();
        } else {
            $baseurl = Mage::getUrl('', array('_secure' => true));
        }
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        $block = $observer->getEvent()->getBlock();
        if ("head" == $block->getNameInLayout() && $currentUrl != $baseurl) {
            foreach (Mage::helper('novalnet_payment/AssignData')->getFiles() as $file) {
                $block->addJs(Mage::helper('novalnet_payment/AssignData')->getJQueryPath($file));
            }
        }

        return $this;
    }

    /**
     * Set customer login session
     *
     * @param varien_object $observer
     * @return Novalnet_Payment_Model_Observer
     */
    public function customerLogin($observer)
    {
        $customer = $observer->getCustomer();
        Mage::getSingleton('core/session')->setGuestloginvalue('');
        return;
    }

    /**
     * Set onhold status for Novalnet payments
     *
     * @param varien_object $invoice
     */
    private function onHoldOrderStatus($invoice)
    {
        $order = $invoice->getOrder();
        $storeId = $order->getStoreId();
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $paymentCode = $paymentObj->getCode();
        if (preg_match("/novalnet/i", $paymentCode)) {
            $setOrderAfterStatus = $paymentObj->getNovalnetConfig('order_status',true,$storeId);
            $setOrderAfterStatus = $setOrderAfterStatus ? $setOrderAfterStatus : Mage_Sales_Model_Order::STATE_PROCESSING;
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $setOrderAfterStatus, Mage::helper('novalnet_payment')->__('Invoice Created Successfully'), true)->save();
        }
    }

    /**
     * Get recurring product custom option values
     *
     * @param null
     * @return null
     */
    public function getProfilePeriodValues(Varien_Event_Observer $observer) {
        $quote = $observer->getEvent()->getCart()->getQuote();

        foreach($quote->getAllItems() as $items) {
            if($items->getProduct()->isRecurring()) {
                $recurringProfile = $items->getProduct()->getRecurringProfile();
                $profileInfo = array('period_unit' => $recurringProfile['period_unit'],
                                     'period_frequency' => $recurringProfile['period_frequency']);
                Mage::getSingleton('checkout/session')->setNnPeriodUnit($recurringProfile['period_unit'])
                                                      ->setNnPeriodFrequency($recurringProfile['period_frequency']);
            }
        }
    }
}
