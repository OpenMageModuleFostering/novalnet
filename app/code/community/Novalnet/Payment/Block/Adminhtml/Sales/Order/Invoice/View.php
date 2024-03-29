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
class Novalnet_Payment_Block_Adminhtml_Sales_Order_Invoice_View extends Mage_Adminhtml_Block_Sales_Order_Invoice_View
{

    public function __construct()
    {
        parent::__construct();
        $payment = $this->getInvoice()->getOrder()->getPayment();
        $paymentCode = $payment->getMethodInstance()->getCode();
        $helper = Mage::helper('novalnet_payment');

        if (preg_match("/novalnet/i", $paymentCode) && $paymentCode == Novalnet_Payment_Model_Config::NN_INVOICE) {
            $this->_removeButton('print');
            $this->_removeButton('capture');
            $baseGrandTotal = $payment->getOrder()->getBaseGrandTotal();
            $orderPayment = $this->getInvoice()->getOrder()->getPayment();
            $orderId = $this->getInvoice()->getOrder()->getIncrementId();
            $refundedAmount = $helper->getFormatedAmount($orderPayment->getAmountRefunded());
            $callbackTrans = $helper->loadCallbackValue($orderId);
            $callbackValue = $callbackTrans && $callbackTrans->getCallbackAmount()
                    != NULL ? $callbackTrans->getCallbackAmount() : '';
            if ($callbackValue && $callbackValue > (string) $refundedAmount && $baseGrandTotal
                    != $orderPayment->getAmountRefunded()) {
                $this->_addButton('capture', array(// capture?
                    'label' => Mage::helper('sales')->__('Credit Memo'),
                    'class' => 'go',
                    'onclick' => 'setLocation(\'' . $this->getCreditMemoUrl() . '\')'
                        )
                );
            }
            if ($this->getInvoice()->getId()) {
                $this->_addButton('print', array(
                    'label' => Mage::helper('sales')->__('Print'),
                    'class' => 'save',
                    'onclick' => 'setLocation(\'' . $this->getPrintUrl() . '\')'
                        )
                );
            }
        }
    }

}
