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
class Novalnet_Payment_Block_Adminhtml_Sales_Order_Totals extends Mage_Adminhtml_Block_Sales_Order_Totals
{

    /**
     * Initialize order totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    protected function _initTotals()
    {
        parent::_initTotals();
        $helper = Mage::helper('novalnet_payment');
        $amountchangedvalue = $helper->getAmountCollection($this->getOrder()->getId(), 1, NULL);
        if ($amountchangedvalue) {
            $adjustmentamount = -($this->getOrder()->getGrandTotal() - $amountchangedvalue);
            $this->_totals['adjust_amount_changed'] = new Varien_Object(array(
                'code' => 'adjust_amount_changed',
                'strong' => true,
                'value' => $adjustmentamount,
                'base_value' => $adjustmentamount,
                'label' => Mage::helper('sales')->__('Novalnet Adjusted Amount'),
                'area' => 'footer'
            ));

            $this->_totals['amount_changed'] = new Varien_Object(array(
                'code' => 'amount_changed',
                'strong' => true,
                'value' => $amountchangedvalue,
                'base_value' => $amountchangedvalue,
                'label' => Mage::helper('sales')->__('Novalnet Transaction Amount'),
                'area' => 'footer'
            ));
        }
        return $this;
    }

}
