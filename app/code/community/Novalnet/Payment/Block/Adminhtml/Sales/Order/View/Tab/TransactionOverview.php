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
class Novalnet_Payment_Block_Adminhtml_Sales_Order_View_Tab_TransactionOverview extends Mage_Adminhtml_Block_Widget
        implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    public function __construct()
    {
        $this->setTemplate('novalnet/sales/order/transactionoverview.phtml');
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('novalnet_payment')->__('Novalnet - Transaction Log');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('novalnet_payment')->__('Novalnet - Transaction Log');
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Return Tab class
     *
     * @return string
     */
    public function getTabClass()
    {
        return 'ajax novalnet-widget-tab';
    }

    /**
     * get current order
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * Get transaction overview
     *
     * @return Novalnet_Payment_Model_TransactionOverview
     */
    public function getTransactionOverview()
    {
        if (!Mage::registry('novalnet_payment_transactionoverview_collection')) {
            $order = $this->getOrder();

            // @var $transactionOverview Novalnet_Payment_Model_TransactionOverview
            $transactionOverview = $this->helperNovalnetPayment()->getModelTransactionOverview()->getCollection();
            $transactionOverview->getByOrder($order);
            Mage::register('novalnet_payment_transactionoverview_collection', $transactionOverview);
        }
        return Mage::registry('novalnet_payment_transactionoverview_collection');
    }

    /**
     * Get Novalnet payment helper
     *
     * @return Novalnet_Payment_Helper_Data
     */
    protected function helperNovalnetPayment()
    {
        return Mage::helper('novalnet_payment');
    }

}
