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
class Novalnet_Payment_Model_Quote_Address_Total_Nominal extends Mage_Sales_Model_Quote_Address_Total_Nominal
{
    /**
     * Invoke collector for nominal items
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param Mage_Sales_Model_Quote_Address_Total_Nominal
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $collector = Mage::getSingleton('sales/quote_address_total_nominal_collector', array(
                    'store' => $address->getQuote()->getStore())
        );

        // invoke nominal totals
        foreach ($collector->getCollectors() as $model) {
            $model->collect($address);
        }

        // aggregate collected amounts into one to have sort of grand total per item
        $total = 0;
        foreach ($address->getAllNominalItems() as $item) {
            $rowTotal = 0;
            $baseRowTotal = 0;
            $totalDetails = array();
            foreach ($collector->getCollectors() as $model) {
                $itemRowTotal = $model->getItemRowTotal($item);
                if ($model->getIsItemRowTotalCompoundable($item)) {
                    $rowTotal += $itemRowTotal;
                    $baseRowTotal += $model->getItemBaseRowTotal($item);
                    $isCompounded = true;
                } else {
                    $isCompounded = false;
                }
                
                if ((float) $itemRowTotal > 0) {
					$label = $model->getLabel();
                    $helper = Mage::helper('novalnet_payment');
                    $regularPayment = $helper->__('Regular Payment');
                    $shipping = $helper->__('Shipping');
                    $tax = $helper->__('Tax');
                    if ($label == $regularPayment || $label == $shipping || $label
                            == $tax) {
                        $total = $total + $itemRowTotal;
                    }
                    $totalDetails[] = new Varien_Object(array(
                        'label' => $label,
                        'amount' => $itemRowTotal,
                        'is_compounded' => $isCompounded,
                    ));
                }
            }
            $session = Mage::getSingleton('checkout/session');
            $session->setNnRegularAmount($total);
            $session->setNnRowAmount($rowTotal);

            $item->setNominalRowTotal($rowTotal);
            $item->setBaseNominalRowTotal($baseRowTotal);
            $item->setNominalTotalDetails($totalDetails);
        }

        return $this;
    }

    /**
     * Fetch collected nominal items
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Mage_Sales_Model_Quote_Address_Total_Nominal
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $items = $address->getAllNominalItems();
        if ($items) {
            $address->addTotal(array(
                'code' => $this->getCode(),
                'title' => Mage::helper('sales')->__('Nominal Items'),
                'items' => $items,
                'area' => 'footer',
            ));
        }
        return $this;
    }
}