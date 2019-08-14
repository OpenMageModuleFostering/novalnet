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
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Sales
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Sales_Order_Creditmemo_Total_Grand extends Mage_Sales_Model_Order_Creditmemo_Total_Grand
{
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();

        if (preg_match("/novalnet/i", $paymentMethod)) {
            $helper = Mage::helper('novalnet_payment');
            $orderItems = $order->getAllItems();
            $nominalItem = $helper->checkNominalItem($orderItems);
            $redirectPayments = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
            !$nominalItem ? array_push($redirectPayments, Novalnet_Payment_Model_Config::NN_CC) : $redirectPayments;

            if (in_array($paymentMethod, $redirectPayments)) {
                return parent::collect($creditmemo);
            }

            if ($nominalItem) {
                $order->setTotalInvoiced($order->getTotalPaid());
            }

            $amountvalue = $helper->getAmountCollection($order->getId(), 1, NULL);

            if (!empty($amountvalue)) {
                $order->setTotalInvoiced($amountvalue);
                $totalRefunded = (int)$order->getTotalRefunded();
                $captureAmount = empty($totalRefunded) ? $amountvalue : 0;

                $creditmemo->setSubtotal($captureAmount)
                        ->setShippingAmount(0)
                        ->setBaseShippingAmount(0)
                        ->setTaxAmount(0)
                        ->setBaseTaxAmount(0)
                        ->setBaseSubtotal($captureAmount)
                        ->setBaseGrandTotal($captureAmount)
                        ->setGrandTotal($captureAmount);
            }
        }

        return parent::collect($creditmemo);
    }
}
