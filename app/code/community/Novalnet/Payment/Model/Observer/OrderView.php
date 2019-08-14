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
class Novalnet_Payment_Model_Observer_OrderView
{

    /**
     * Add buttons to sales order view (single order)
     *
     * @param  Varien_Object $observer
     * @return Novalnet_Payment_Model_Observer_OrderView
     */
    public function addButton($observer)
    {
        $block = $observer->getEvent()->getBlock();

        // Add buttons to sales order view (single order)
        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_View) {
            $order = $block->getOrder(); // Get current order
            $paymentCode = $order->getPayment()->getMethodInstance()->getCode(); // Get payment method code

            // allow only for Novalnet payment methods
            if (preg_match("/novalnet/i", $paymentCode)) {

                $helper = Mage::helper('novalnet_payment'); // Novalnet payment helper
                $additionalData = unserialize($order->getPayment()->getAdditionalData());
                // Get payment Novalnet transaction id
                $transactionId = $helper->makeValidNumber($additionalData['NnTid']);

                // Get current transaction status information
                $transactionStatus = $helper->getModel('Mysql4_TransactionStatus')
                    ->loadByAttribute('transaction_no', $transactionId);
                $paymentStatus = $transactionStatus->getTransactionStatus(); // Get payment original transaction status
                // Get payment transaction amount
                $transactionAmount = (int) str_replace(array('.', ','), '', $transactionStatus->getAmount());

                if ($paymentStatus) {
                    // Rename invoice button for Novalnet payment orders
                    $block->updateButton('order_invoice', 'label', $helper->__('Capture'));
                    // Removes offline credit memo button in order history
                    $block->removeButton('order_creditmemo');
                }

                // Removes void button for success payment status
                if ($paymentStatus && (!$transactionAmount
                    || $paymentStatus == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                    || $paymentStatus == Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS)
                ) {
                    $block->removeButton('void_payment');
                }

                // Removes order invoice button
                if ($paymentStatus && ($paymentCode == Novalnet_Payment_Model_Config::NN_PAYPAL
                    || $paymentStatus == Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS
                    || in_array(
                        $paymentCode, array(Novalnet_Payment_Model_Config::NN_INVOICE,
                        Novalnet_Payment_Model_Config::NN_PREPAYMENT)
                    ) || !$transactionAmount)) {
                    $block->removeButton('order_invoice');
                }

                // Add confirm button for Novalnet invoice payments (Invoice/Prepayment)
                if ($paymentStatus && $paymentStatus == 91) {
                    $confirmUrl = $block->getUrl(
                        'adminhtml/novalnetpayment_sales_order/confirm', array('_current'=>true)
                    );
                    $message = Mage::helper('sales')->__('Are you sure you want to capture the payment?');
                    $block->addButton(
                        'novalnet_confirm', array(
                        'label' => Mage::helper('novalnet_payment')->__('Novalnet Capture'),
                        'onclick' => "confirmSetLocation('{$message}', '{$confirmUrl}')",
                            )
                    );
                }

                // Add void button
                if ($order->canCancel() && $transactionAmount
                    && $paymentStatus < Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                ) {
                    $block->removeButton('void_payment');
                    $message = Mage::helper('sales')->__('Are you sure you want to void the payment?');
                    $block->addButton(
                        'void_payment', array(
                        'label' => Mage::helper('sales')->__('Void'),
                        'onclick' => "confirmSetLocation('{$message}', '{$block->getVoidPaymentUrl()}')",
                        )
                    );
                }
            }
        }
    }

}
