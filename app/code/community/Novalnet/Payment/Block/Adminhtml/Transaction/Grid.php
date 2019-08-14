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
 * Part of the Paymentmodule of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Block_Adminhtml_Transaction_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    var $novalnetPayments = array();

    /**
     *
     */
    public function __construct() {
        parent::__construct();

        $this->setId('novalnet_transaction_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('order_id');
        $this->setDefaultDir('desc');
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection() {
        $collection = Mage::getModel('novalnet_payment/transactionstatus')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return
     */
    public function _prepareColumns() {
        $this->addColumn('order_id', array(
            'header' => Mage::helper('sales')->__('Order no #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'order_id',
        ));
        $this->addColumn('transaction_no', array(
            'header' => Mage::helper('sales')->__('Transaction #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'transaction_no',
        ));
        $this->addColumn('store_id', array(
            'header' => Mage::helper('sales')->__('Store ID #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'store_id',
        ));
        $this->addColumn('nc_no', array(
            'header' => Mage::helper('sales')->__('NC No #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'nc_no',
        ));
        $this->addColumn('transaction_status', array(
            'header' => Mage::helper('sales')->__('Status #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'transaction_status',
        ));
        parent::_prepareColumns();
        return $this;
    }

    /**
     * @param $row
     * @return bool|string
     */
    public function getRowUrl($row) {
        return $this->getUrl('novalnet_payment/adminhtml_transaction/view', array(
                    'nntxn_id' => $row->getId()
                        )
        );
    }

    public function getGridUrl() {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

}
