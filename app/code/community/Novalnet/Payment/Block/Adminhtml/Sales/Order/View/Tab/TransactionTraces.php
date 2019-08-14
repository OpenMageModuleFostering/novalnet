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
class Novalnet_Payment_Block_Adminhtml_Sales_Order_View_Tab_TransactionTraces extends Mage_Adminhtml_Block_Widget
        implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Transaction traces view tab
     */
    public function __construct()
    {
        $this->setTemplate('novalnet/sales/order/view/tab/transactiontraces.phtml');
    }

    /**
     * Return tab label
     *
     * @param  none
     * @return string
     */
    public function getTabLabel()
    {
        return $this->novalnetHelper()->__('Novalnet - Transaction Log');
    }

    /**
     * Return tab title
     *
     * @param  none
     * @return string
     */
    public function getTabTitle()
    {
        return $this->novalnetHelper()->__('Novalnet - Transaction Log');
    }

    /**
     * Can show tab
     *
     * @param  none
     * @return boolean
     */
    public function canShowTab()
    {
        $order = $this->getOrder();
        $paymentCode = $order->getPayment()->getMethodInstance()->getCode();
        return (preg_match("/novalnet/i", $paymentCode)) ? true : false;
    }

    /**
     * Tab is hidden
     *
     * @param  none
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Return tab class
     *
     * @param  none
     * @return string
     */
    public function getTabClass()
    {
        return 'ajax novalnet-widget-tab';
    }

    /**
     * Get current order
     *
     * @param  none
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * Get transaction overview
     *
     * @param  none
     * @return mixed
     */
    public function getTransactionTraces()
    {
        if (!Mage::registry('novalnet_payment_transactiontraces_collection')) {
            $order = $this->getOrder();
            $collection = Mage::getModel('novalnet_payment/Mysql4_TransactionTraces')->getCollection();
            $collection->getByOrder($order);
            Mage::register('novalnet_payment_transactiontraces_collection', $collection);
        }
        return Mage::registry('novalnet_payment_transactiontraces_collection');
    }

    /**
     * Get Novalnet payment helper
     *
     * @param  none
     * @return Novalnet_Payment_Helper_Data
     */
    protected function novalnetHelper()
    {
        return Mage::helper('novalnet_payment');
    }

}
