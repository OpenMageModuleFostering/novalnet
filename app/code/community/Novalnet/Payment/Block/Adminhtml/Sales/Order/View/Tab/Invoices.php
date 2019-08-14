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
class Novalnet_Payment_Block_Adminhtml_Sales_Order_View_Tab_Invoices extends Mage_Adminhtml_Block_Widget_Grid
        implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('order_invoices');
        $this->setUseAjax(true);
    }

    /**
     * Retrieve collection class
     *
     * @return string
     */
    protected function _getCollectionClass()
    {
        return 'sales/order_invoice_grid_collection';
    }

    /**
     * Prepare order Collection for invoice
     *
     * @return Novalnet_Payment_Block_Adminhtml_Sales_Order_View_Tab_Invoices
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel($this->_getCollectionClass())
                ->addFieldToSelect('entity_id')
                ->addFieldToSelect('created_at')
                ->addFieldToSelect('order_id')
                ->addFieldToSelect('increment_id')
                ->addFieldToSelect('state')
                ->addFieldToSelect('grand_total')
                ->addFieldToSelect('base_grand_total')
                ->addFieldToSelect('store_currency_code')
                ->addFieldToSelect('base_currency_code')
                ->addFieldToSelect('order_currency_code')
                ->addFieldToSelect('billing_name')
                ->setOrderFilter($this->getOrder())
        ;
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Define order invoice tab grid
     *
     * @return Novalnet_Payment_Block_Adminhtml_Sales_Order_View_Tab_Invoices
     */
    protected function _prepareColumns()
    {
        $this->addColumn('increment_id', array(
            'header' => Mage::helper('sales')->__('Invoice #'),
            'index' => 'increment_id',
            'width' => '120px',
        ));

        $this->addColumn('billing_name', array(
            'header' => Mage::helper('sales')->__('Bill to Name'),
            'index' => 'billing_name',
        ));

        $this->addColumn('created_at', array(
            'header' => Mage::helper('sales')->__('Invoice Date'),
            'index' => 'created_at',
            'type' => 'datetime',
        ));

        $this->addColumn('state', array(
            'header' => Mage::helper('sales')->__('Status'),
            'index' => 'state',
            'type' => 'options',
            'options' => Mage::getModel('sales/order_invoice')->getStates(),
        ));

        $this->addColumn('base_grand_total', array(
            'header' => Mage::helper('customer')->__('Amount'),
            'index' => 'base_grand_total',
            'type' => 'currency',
            'currency' => 'base_currency_code',
        ));
        $helper = Mage::helper('novalnet_payment');
        $countofvalues = $helper->getAmountCollection($this->getOrder()->getId(), NULL, NULL);
        if ($countofvalues > 0) {
            $this->addColumn('novalnet_amount', array(
                'header' => Mage::helper('sales')->__('Novalnet Transaction Amount'),
                'width' => '2px',
                'sortable' => false,
                'filter' => false,
                'renderer' => 'novalnet_payment/adminhtml_sales_order_view_tab_renderer_invoices',
            ));
        }
        return parent::_prepareColumns();
    }

    /**
     * Retrieve order model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * Return row url
     *
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/sales_order_invoice/view', array(
                    'invoice_id' => $row->getId(),
                    'order_id' => $row->getOrderId()
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
        return $this->getUrl('*/*/invoices', array('_current' => true));
    }

    /**
     * Return tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('sales')->__('Invoices');
    }

    /**
     * Return tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('sales')->__('Order Invoices');
    }

    /**
     * Can show tab
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

}
