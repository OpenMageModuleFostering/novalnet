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
class Novalnet_Payment_Block_Adminhtml_Sales_Order_View_Tab_TransactionOverview extends Mage_Adminhtml_Block_Widget_Grid
        implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Transaction status view tab
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('novalnet_payment_block_adminhtml_sales_order_view_tab_transactionoverview');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setSkipGenerateContent(true);
    }

    /**
     * Return Tab label
     *
     * @param  none
     * @return string
     */
    public function getTabLabel()
    {
        return $this->novalnetHelper()->__('Novalnet - Transaction Overview');
    }

    /**
     * Return Tab title
     *
     * @param  none
     * @return string
     */
    public function getTabTitle()
    {
        return $this->novalnetHelper()->__('Novalnet - Transaction Overview');
    }

    /**
     * Can show tab in tabs
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
     * Return Tab class
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
     * Return tab url
     *
     * @param  none
     * @return string
     */
    public function getTabUrl()
    {
        return $this->getUrl(
            'adminhtml/novalnetpayment_sales_order/transactionOverviewGrid', array(
                    '_current' => true
                        )
        );
    }

    /**
     * Return grid url
     *
     * @param  none
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl(
            'adminhtml/novalnetpayment_sales_order/transactionOverviewGrid', array('_current' => true)
        );
    }

    /**
     * Return row url
     *
     * @param  mixed $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('adminhtml/novalnetpayment_transactionoverview/view', array('nntxn_id' => $row->getId()));
    }

    /**
     * Prepare order Collection for transaction status
     *
     * @param  none
     * @return Novalnet_Payment_Model_TransactionStatus
     */
    protected function _prepareCollection()
    {
        $collection = $this->getTransactionStatusCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare order Collection for transaction status
     *
     * @param  none
     * @return mixed $collection
     */
    public function getTransactionStatusCollection()
    {
        $collection = Mage::getModel('novalnet_payment/Mysql4_TransactionStatus')->getCollection();
        $collection->getByOrder($this->getOrder());
        return $collection;
    }

    /**
     * Define transaction status grid
     *
     * @param  none
     * @return mixed
     */
    protected function _prepareColumns()
    {
        $helper = $this->novalnetHelper();
        $this->setColumn(
            'order_id', array(
            'header' => $helper->__('Order No'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'order_id',
                )
        );
        $this->setColumn(
            'txid', array(
            'header' => $helper->__('Transaction Id'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'transaction_no',
                )
        );
        $this->setColumn(
            'transaction_status', array(
            'header' => $helper->__('Transaction Status'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'transaction_status',
                )
        );
        $this->setColumn(
            'customer_id', array(
            'header' => $helper->__('Customer Id'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'customer_id',
                )
        );
        $this->setColumn(
            'store_id', array(
            'header' => $helper->__('Store Id'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'store_id',
                )
        );

        return parent::_prepareColumns();
    }

    /**
     * Add coumn
     *
     * @param  none
     * @return null
     */
    protected function setColumn($field, $fieldColumnMap)
    {
        $this->addColumn($field, $fieldColumnMap);
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
