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
class Novalnet_Payment_Model_Sales_Creditmemo_Total extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    /**
     * Collect credit memo subtotal
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return Mage_Sales_Model_Order_Creditmemo_Total_Abstract
     */
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $totalPaid = $order->getTotalPaid();
        $helper = Mage::helper('novalnet_payment');
        $orderItems = $order->getAllItems();
        $nominalItem = $helper->checkNominalItem($orderItems);

        $paymentname = array(
            Novalnet_Payment_Model_Config::NN_CC,
            Novalnet_Payment_Model_Config::NN_IDEAL,
            Novalnet_Payment_Model_Config::NN_EPS,
            Novalnet_Payment_Model_Config::NN_PAYPAL,
            Novalnet_Payment_Model_Config::NN_SOFORT
        );
        if ($nominalItem) {
            array_shift($paymentname);
        }
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        if (preg_match("/novalnet/i", $paymentMethod) && in_array($paymentMethod, $paymentname)) {
            return $this;
        }
        $getrefundvalue = $order->getTotalRefunded();
        $orderId = $order->getId();

        if ($nominalItem) {
            $order->setTotalInvoiced($totalPaid);
        }
        $amountvalue = $helper->getAmountCollection($orderId, 1, NULL);
        if ($amountvalue != '') {
            $order->setTotalInvoiced($amountvalue);
            $totalQtyOrder = $order->getTotalQtyOrdered();
            $totalRefunded = $order->getTotalRefunded();

            $creditmemoBaseGrandTotal = $creditmemo->getBaseGrandTotal();
            $creditmemoGrandTotal = $creditmemo->getGrandTotal();
            $creditmemoBaseTaxAmount = $creditmemo->getBaseTaxAmount();
            $creditmemoTaxAmount = $creditmemo->getTaxAmount();
            $captrueAmountfirst = 0;
            $captrueAmount = $amountvalue;
            if ($totalRefunded == 0) {
                $captrueAmountfirst = $captrueAmount;
            } else {
                $captrueAmountfirst = 0;
            }
            $creditmemo->setSubtotal($captrueAmountfirst)
                    ->setShippingAmount(0)
                    ->setBaseShippingAmount(0)
                    ->setTaxAmount(0)
                    ->setBaseTaxAmount(0)
                    ->setBaseSubtotal($captrueAmountfirst)
                    ->setBaseGrandTotal($captrueAmountfirst)
                    ->setGrandTotal($captrueAmountfirst);
        } else {
            return false;
        }

        return $this;
    }
}
