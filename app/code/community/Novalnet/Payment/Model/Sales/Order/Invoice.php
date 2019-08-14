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
class Novalnet_Payment_Model_Sales_Order_Invoice extends Mage_Sales_Model_Order_Invoice
{
    /**
     * Pay invoice
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function pay()
    {
        if ($this->_wasPayCalled) {
            return $this;
        }
        $this->_wasPayCalled = true;

        $invoiceState = self::STATE_PAID;
        if ($this->getOrder()->getPayment()->hasForcedState()) {
            $invoiceState = $this->getOrder()->getPayment()->getForcedState();
        }

        $paymentCode = $this->getOrder()->getPayment()->getMethodInstance()->getCode();
        $helper = Mage::helper('novalnet_payment');
        $countofvalue = $helper->getAmountCollection($this->getOrder()->getId(), NULL, NULL);
        $tidPayment = Mage::app()->getRequest()->getParam('tid_payment');
        if ($paymentCode == Novalnet_Payment_Model_Config::NN_INVOICE && $countofvalue
                == 0 && !$tidPayment) {
            $this->setState(1);
        } else {
            $this->setState($invoiceState);
        }

        $captrueAmount = Mage::app()->getRequest()->getParam('amount');
        $subsBilling = Mage::app()->getRequest()->getParam('subs_billing');
        $paymentType = Mage::app()->getRequest()->getParam('payment_type');

        $invoicePayments = array(
            Novalnet_Payment_Model_Config::NN_PREPAYMENT,
            Novalnet_Payment_Model_Config::NN_INVOICE
        );
        $directPayment = array(
            Novalnet_Payment_Model_Config::NN_SEPA,
        );
        $subproduct = 0;
        if ($helper->checkIsAdmin()) {
            $orderItems = $this->getOrder()->getAllItems();
            $nominalItem = $helper->checkNominalItem($orderItems);
            $subproduct = $nominalItem ? 1 : 0;
        }

        $this->getOrder()->getPayment()->pay($this);
        if ($captrueAmount && in_array($paymentCode, $invoicePayments) && $countofvalue
                != 0) {
            $amount = $helper->getAmountCollection($this->getOrder()->getId(), 1, NULL);
            $this->getOrder()->setTotalPaid($amount);
            $this->getOrder()->setBaseTotalPaid($amount);
        } else if ($countofvalue == 1 && in_array($paymentCode, $directPayment)) {
            $amount = $helper->getAmountCollection($this->getOrder()->getId(), 1, NULL);
            $this->getOrder()->setTotalPaid($amount);
            $this->getOrder()->setBaseTotalPaid($amount);
        } else if ($subproduct || $subsBilling || $paymentType == "INVOICE_CREDIT") {
            $lastTranId = $this->getOrder()->getPayment()->getLastTransId();
            $amount = $helper->getModelRecurring()->getRecurringCaptureTotal($lastTranId,$this->getOrder());
            $this->getOrder()->setTotalPaid($amount);
            $this->getOrder()->setBaseTotalPaid($amount);

        } else {
            $this->getOrder()->setTotalPaid($this->getOrder()->getTotalPaid() + $this->getGrandTotal());
            $this->getOrder()->setBaseTotalPaid(
                    $this->getOrder()->getBaseTotalPaid() + $this->getBaseGrandTotal()
            );
        }
        Mage::dispatchEvent('sales_order_invoice_pay', array($this->_eventObject => $this));
        return $this;
    }
}
