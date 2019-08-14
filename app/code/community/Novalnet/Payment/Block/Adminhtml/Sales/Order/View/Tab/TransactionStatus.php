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
class Novalnet_Payment_Block_Adminhtml_Sales_Order_View_Tab_TransactionStatus extends Mage_Adminhtml_Block_Widget_Grid
        implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('novalnet_payment_block_adminhtml_sales_order_view_tab_transactionstatus');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setSkipGenerateContent(true);
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('novalnet_payment')->__('Novalnet - Transaction Overview');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('novalnet_payment')->__('Novalnet - Transaction Overview');
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
     * Return tab url
     *
     * @return string
     */
    public function getTabUrl()
    {
        return $this->getUrl('novalnet_payment/adminhtml_sales_order/transactionStatusGrid', array(
                    '_current' => true
                        )
        );
    }

    /**
     * Return grid url
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('novalnet_payment/adminhtml_sales_order/transactionStatusGrid', array(
                    '_current' => true
                        )
        );
    }

    /**
     * Return row url
     *
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('novalnet_payment/adminhtml_transaction/view', array(
                    'nntxn_id' => $row->getId()
                        )
        );
    }

    /**
     * Prepare order Collection for transaction status
     *
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
     * @return mixed
     */
    public function getTransactionStatusCollection()
    {
        $order = $this->getOrder();

        // @var $transStatCollection Novalnet_Payment_Model_TransactionStatus_Collection
        $transStatCollection = $this->helperNovalnetPayment()->getModelTransactionStatus()->getCollection();
        $transStatCollection->getByOrder($order);
        return $transStatCollection;
    }

    /**
     * Define transaction status grid
     *
     * @return Novalnet_Payment_Model_TransactionStatus
     */
    protected function _prepareColumns()
    {
        $helperNnPayment = $this->helperNovalnetPayment();
        $this->setColumn('txid', array(
            'header' => $helperNnPayment->__('Transaction no'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'transaction_no',
                )
        );
        $this->setColumn('order_id', array(
            'header' => $helperNnPayment->__('Order no'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'order_id',
                )
        );
        $this->setColumn('transaction_status', array(
            'header' => $helperNnPayment->__('Transaction Status'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'transaction_status',
                )
        );
        $this->setColumn('ncno', array(
            'header' => $helperNnPayment->__('NC No'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'nc_no',
                )
        );
        $this->setColumn('customer_id', array(
            'header' => $helperNnPayment->__('Customer ID'),
            'width' => '200px',
            'type' => 'text',
            'index' => 'customer_id',
                )
        );
        $this->setColumn('store_id', array(
            'header' => $helperNnPayment->__('Store ID'),
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
     * @return
     */
    private function setColumn($field, $fieldColumnMap)
    {
        $this->addColumn($field, $fieldColumnMap);
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
