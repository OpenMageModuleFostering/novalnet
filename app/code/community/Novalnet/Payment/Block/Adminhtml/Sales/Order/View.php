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
class Novalnet_Payment_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View
{

    public function __construct()
    {
        parent::__construct();
        $order = $this->getOrder();
        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance()->getCode();
        $helper = Mage::helper('novalnet_payment');
        $getTid = $helper->makeValidNumber($payment->getLastTransId());


        if (preg_match("/novalnet/i", $paymentMethod)) {
            $this->_removeButton('order_creditmemo');
            $getTransactionStatus = $helper->loadTransactionStatus($getTid);

            $this->_updateButton('order_invoice', 'label', Mage::helper('novalnet_payment')->__('Capture'));

            if ($paymentMethod == Novalnet_Payment_Model_Config::NN_PAYPAL) {
                $this->_removeButton('order_invoice');
            }

            if ($getTransactionStatus->getTransactionStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $this->_removeButton('void_payment');
            }

            if ($getTransactionStatus->getTransactionStatus() == Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS) {
                $this->_removeButton('order_invoice');
            }

            if (in_array($paymentMethod, array(Novalnet_Payment_Model_Config::NN_INVOICE,
                        Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {
                $this->_removeButton('order_invoice');
                if ($getTransactionStatus->getTransactionStatus() == 91) {
                    $this->_removeButton('order_invoice');
                    $this->_addButton('novalnet_confirm', array(
                        'label' => Mage::helper('novalnet_payment')->__('Novalnet Capture'),
                        'onclick' => 'setLocation(\'' . $this->getUrl('*/*/novalnetconfirm') . '\')',
                            ), 0);
                }
            }
            if ($order->canCancel() && $getTransactionStatus->getTransactionStatus()
                    < Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $this->_removeButton('void_payment');
                $message = Mage::helper('sales')->__('Are you sure you want to void the payment?');
                $this->addButton('void_payment', array(
                    'label' => Mage::helper('sales')->__('Void'),
                    'onclick' => "confirmSetLocation('{$message}', '{$this->getVoidPaymentUrl()}')",
                ));
            }
            if ($this->_isAllowedAction('ship') && $order->canShip()
                && !$order->getForcedDoShipmentWithInvoice()) {
                $this->_addButton('order_ship', array(
                    'label'     => Mage::helper('sales')->__('Ship'),
                    'onclick'   => 'setLocation(\'' . $this->getShipUrl() . '\')',
                    'class'     => 'go'
                ));
            }
        }
    }
}
