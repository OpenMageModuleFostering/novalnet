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
class Novalnet_Payment_Model_Adminhtml_Sales_Order_Create extends Mage_Adminhtml_Model_Sales_Order_Create
{
    /**
     * Create new order
     *
     * @return Mage_Sales_Model_Order
     */
    public function createOrder()
    {
        $this->_prepareCustomer();
        $this->_validate();
        $quote = $this->getQuote();
        $this->_prepareQuoteItems();

        $service = Mage::getModel('sales/service_quote', $quote);

        if ($this->getSession()->getOrder()->getId()) {
            $oldOrder = $this->getSession()->getOrder();
            $originalId = $oldOrder->getOriginalIncrementId();
            if (!$originalId) {
                $originalId = $oldOrder->getIncrementId();
            }
            $orderData = array(
                'original_increment_id' => $originalId,
                'relation_parent_id' => $oldOrder->getId(),
                'relation_parent_real_id' => $oldOrder->getIncrementId(),
                'edit_increment' => $oldOrder->getEditIncrement() + 1,
                'increment_id' => $originalId . '-' . ($oldOrder->getEditIncrement()
                + 1)
            );
            $quote->setReservedOrderId($orderData['increment_id']);
            $service->setOrderData($orderData);
        }

        $order = $service->submit();
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();

        if (preg_match("/novalnet/i", $paymentMethod)) {
            $payment = $order->getPayment();
            $paymentObj = $payment->getMethodInstance();
            $request = $this->getSession()->getPaymentReqData();
            $response = $paymentObj->postRequest($request);
            $status = $paymentObj->validateNovalnetResponse($payment, $response);
            $this->getSession()->unsPaymentReqData()
                               ->unsPaymentResData();
        }

        if ((!$quote->getCustomer()->getId() || !$quote->getCustomer()->isInStore($this->getSession()->getStore()))
                && !$quote->getCustomerIsGuest()
        ) {
            $quote->getCustomer()->setCreatedAt($order->getCreatedAt());
            $quote->getCustomer()
                    ->save()
                    ->sendNewAccountEmail('registered', '', $quote->getStoreId());
        }
        if ($this->getSession()->getOrder()->getId()) {
            $oldOrder = $this->getSession()->getOrder();

            $this->getSession()->getOrder()->setRelationChildId($order->getId());
            $this->getSession()->getOrder()->setRelationChildRealId($order->getIncrementId());
            $this->getSession()->getOrder()->cancel()
                    ->save();
            $order->save();
        }
        if ($this->getSendConfirmation()) {
            $order->sendNewOrderEmail();
        }

        Mage::dispatchEvent('checkout_submit_all_after', array('order' => $order,
            'quote' => $quote));

        return $order;
    }
}
