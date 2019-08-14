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
class Novalnet_Payment_Model_Recurring_Profile extends Mage_Sales_Model_Recurring_Profile
{
    /**
     * Initialize new order based on profile data
     *
     * Takes arbitrary number of Varien_Object instances to be treated as items for new order
     *
     * @return Mage_Sales_Model_Order
     */
    public function createOrder()
    {
        $items = array();
        $itemInfoObjects = func_get_args();

        $billingAmount = 0;
        $shippingAmount = 0;
        $taxAmount = 0;
        $isVirtual = 1;
        $weight = 0;
        foreach ($itemInfoObjects as $itemInfo) {
            $item = $this->_getItem($itemInfo);
            $billingAmount += $item->getPrice();
            $shippingAmount += $item->getShippingAmount();
            $taxAmount += $item->getTaxAmount();
            $weight += $item->getWeight();
            if (!$item->getIsVirtual()) {
                $isVirtual = 0;
            }
            $items[] = $item;
        }
        $qtyOrdered = $this->getInfoValue('order_info', 'items_qty');
        if ($qtyOrdered > 1) {
            $billingAmount = ($billingAmount * $qtyOrdered);
        }
        $grandTotal = $billingAmount + $shippingAmount + $taxAmount;

        $order = Mage::getModel('sales/order');

        $billingAddress = Mage::getModel('sales/order_address')
                ->setData($this->getBillingAddressInfo())
                ->setId(null);

        $shippingInfo = $this->getShippingAddressInfo();
        $shippingAddress = Mage::getModel('sales/order_address')
                ->setData($shippingInfo)
                ->setId(null);

        $payment = Mage::getModel('sales/order_payment')
                ->setMethod($this->getMethodCode());

        $transferDataKays = array(
            'store_id', 'store_name', 'customer_id', 'customer_email',
            'customer_firstname', 'customer_lastname', 'customer_middlename', 'customer_prefix',
            'customer_suffix', 'customer_taxvat', 'customer_gender', 'customer_is_guest',
            'customer_note_notify', 'customer_group_id', 'customer_note', 'shipping_method',
            'shipping_description', 'base_currency_code', 'global_currency_code',
            'order_currency_code',
            'store_currency_code', 'base_to_global_rate', 'base_to_order_rate', 'store_to_base_rate',
            'store_to_order_rate'
        );

        $orderInfo = $this->getOrderInfo();
        foreach ($transferDataKays as $key) {
            if (isset($orderInfo[$key])) {
                $order->setData($key, $orderInfo[$key]);
            } elseif (isset($shippingInfo[$key])) {
                $order->setData($key, $shippingInfo[$key]);
            }
        }

        $order->setStoreId($this->getStoreId())
                ->setState(Mage_Sales_Model_Order::STATE_NEW)
                ->setBaseToOrderRate($this->getInfoValue('order_info', 'base_to_quote_rate'))
                ->setStoreToOrderRate($this->getInfoValue('order_info', 'store_to_quote_rate'))
                ->setOrderCurrencyCode($this->getInfoValue('order_info', 'quote_currency_code'))
                ->setBaseSubtotal($billingAmount)
                ->setSubtotal($billingAmount)
                ->setBaseShippingAmount($shippingAmount)
                ->setShippingAmount($shippingAmount)
                ->setBaseTaxAmount($taxAmount)
                ->setTaxAmount($taxAmount)
                ->setBaseGrandTotal($grandTotal)
                ->setGrandTotal($grandTotal)
                ->setIsVirtual($isVirtual)
                ->setWeight($weight)
                ->setTotalQtyOrdered($this->getInfoValue('order_info', 'items_qty'))
                ->setBillingAddress($billingAddress)
                ->setShippingAddress($shippingAddress)
                ->setPayment($payment);

        foreach ($items as $item) {
            $order->addItem($item);
        }

        return $order;
    }

    /**
     * Get the reqular order items
     *
     * @param varien_object $itemInfo
     * @return mixed
     */
    protected function _getRegularItem($itemInfo)
    {
        $price = $itemInfo->getPrice() ? $itemInfo->getPrice() : $this->getBillingAmount();
        $shippingAmount = $itemInfo->getShippingAmount() ? $itemInfo->getShippingAmount()
                    : $this->getShippingAmount();
        $taxAmount = $itemInfo->getTaxAmount() ? $itemInfo->getTaxAmount() : $this->getTaxAmount();

        $qtyOrdered = $this->getInfoValue('order_info', 'items_qty');
        $pricevalue = ($price / $qtyOrdered);

        $item = Mage::getModel('sales/order_item')
                ->setData($this->getOrderItemInfo())
                ->setQtyOrdered($this->getInfoValue('order_item_info', 'qty'))
                ->setBaseOriginalPrice($this->getInfoValue('order_item_info', 'price'))
                ->setPrice($pricevalue)
                ->setBasePrice($pricevalue)
                ->setRowTotal($price)
                ->setBaseRowTotal($price)
                ->setTaxAmount($taxAmount)
                ->setShippingAmount($shippingAmount)
                ->setId(null);
        return $item;
    }
}
