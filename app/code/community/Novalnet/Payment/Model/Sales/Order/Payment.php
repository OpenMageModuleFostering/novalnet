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
class Novalnet_Payment_Model_Sales_Order_Payment extends Mage_Sales_Model_Order_Payment
{
    /**
     * Register capture notification
     *
     * @param float $amount
     * @param mixed $skipFraudDetection
     * @return Mage_Sales_Model_Order_Payment
     */
    public function registerCaptureNotification($amount, $skipFraudDetection = false)
    {
        $this->_generateTransactionId(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, $this->getAuthorizationTransaction()
        );

        $order = $this->getOrder();
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $magentoVersion = Mage::helper('novalnet_payment')->getMagentoVersion();
        $captureMode = (version_compare($magentoVersion, '1.6', '<')) ? false : true;

        $txnId = $this->getTransactionId();
        $amount = (float) $amount;
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        if (!preg_match("/novalnet/i", $paymentMethod)) {
            return parent::registerCaptureNotification($amount);
        }
        $statuscode = Mage::getSingleton('core/session')->getStatusCode();
        $invoice = false;
        if (!$this->getIsTransactionPending()) {
            $invoice = $this->_getInvoiceForTransactionId($this->getTransactionId());
            // register new capture
            if (!$invoice && $statuscode == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
				&& $paymentMethod != Novalnet_Payment_Model_Config::NN_PREPAYMENT) {
                if ($this->_isCaptureFinal($amount)) {
                    $payment->setTransactionId($txnId) // Add capture text to make the new transaction
                            ->setIsTransactionClosed($captureMode) // Close the transaction
                            ->capture(null)
                            ->save();
                } else {
                    $this->setIsFraudDetected(true);
                    $this->_updateTotals(array('base_amount_paid_online' => $amount));
                }
            }
        }
        $status = true;
        $setOrderAfterStatus = $paymentObj->getNovalnetConfig('order_status') ? $paymentObj->getNovalnetConfig('order_status')
                    : Mage_Sales_Model_Order::STATE_PROCESSING; // If after status is empty set default status
        $state = Mage_Sales_Model_Order::STATE_PROCESSING;
        $payment->setTransactionId($txnId)
                ->setLastTransId($txnId)
                ->setParentTransactionId(null)
                ->save();
		$message = '';
        if ($this->getIsTransactionPending()) {
            $message = Mage::helper('sales')->__('Capturing amount of %s is pending approval on gateway.', $this->_formatPrice($amount));
            $state = Mage_Sales_Model_Order::STATE_NEW;
            if ($this->getIsFraudDetected()) {
                $message = Mage::helper('sales')->__('Order is suspended as its capture amount %s is suspected to be fraudulent.', $this->_formatPrice($amount));
                $status = Mage_Sales_Model_Order::STATUS_FRAUD;
            }
        } else if ($statuscode == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $message = Mage::helper('sales')->__('Registered notification about captured amount of %s.', $this->_formatPrice($amount));
            $state = Mage_Sales_Model_Order::STATE_PROCESSING;
            if ($this->getIsFraudDetected()) {
                $state = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
                $message = Mage::helper('sales')->__('Order is suspended as its capture amount %s is suspected to be fraudulent.', $this->_formatPrice($amount));
                $status = Mage_Sales_Model_Order::STATUS_FRAUD;
            }
            // register capture for an existing invoice
            if ($invoice && Mage_Sales_Model_Order_Invoice::STATE_OPEN == $invoice->getState()) {
                $invoice->pay();
                $this->_updateTotals(array('base_amount_paid_online' => $amount));
                $order->addRelatedObject($invoice);
            }
        }
        $order->setState($state, $setOrderAfterStatus, $message);
        Mage::getSingleton('core/session')->setStatusCode('');
        return $this;
    }

    /**
     * Void payment either online or offline (process void notification)
     * NOTE: that in some cases authorization can be voided after a capture. In such case it makes sense to use
     *       the amount void amount, for informational purposes.
     * Updates payment totals, updates order status and adds proper comments
     *
     * @param bool $isOnline
     * @param float $amount
     * @param string $gatewayCallback
     * @return Mage_Sales_Model_Order_Payment
     */
    protected function _void($isOnline, $amount = null, $gatewayCallback = 'void')
    {
        $order = $this->getOrder();
        $storeId = $order->getStoreId();
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $setOrderAfterStatus = $paymentObj->getNovalnetConfig('void_status',true,$storeId);
        $authTransaction = $this->getAuthorizationTransaction();
        $this->_generateTransactionId(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, $authTransaction);
        $this->setShouldCloseParentTransaction(true);

        // attempt to void
        if ($isOnline) {
            $this->getMethodInstance()->setStore($order->getStoreId())->$gatewayCallback($this);
        }
        if ($this->_isTransactionExists()) {
            return $this;
        }

        // if the authorization was untouched, we may assume voided amount = order grand total
        // but only if the payment auth amount equals to order grand total
        if ($authTransaction && ($order->getBaseGrandTotal() == $this->getBaseAmountAuthorized())
            && (0 == $this->getBaseAmountCanceled())) {
            if ($authTransaction->canVoidAuthorizationCompletely()) {
                $amount = (float)$order->getBaseGrandTotal();
            }
        }

        if ($amount) {
            $amount = $this->_formatAmount($amount);
        }

        // update transactions, order state and add comments
        $transaction = $this->_addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, null, true);
        $message = $this->hasMessage() ? $this->getMessage() : Mage::helper('sales')->__('Voided authorization.');
        $message = $this->_prependMessage($message);
        if ($amount) {
            $message .= ' ' . Mage::helper('sales')->__('Amount: %s.', $this->_formatPrice($amount));
        }
        $setOrderAfterStatus = $setOrderAfterStatus ? $setOrderAfterStatus : true;
        $message = $this->_appendTransactionToMessage($transaction, $message);
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $setOrderAfterStatus, $message);
        return $this;
    }
}
