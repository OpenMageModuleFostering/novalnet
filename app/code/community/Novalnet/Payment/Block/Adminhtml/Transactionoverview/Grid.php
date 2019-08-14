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
class Novalnet_Payment_Block_Adminhtml_Transactionoverview_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    /**
     *
     */
    public function __construct() {
        parent::__construct();
        $this->setId('novlanet_transactionoverview_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('order_id');
        $this->setDefaultDir('DESC');
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection() {
        $collection = Mage::getModel('novalnet_payment/transactionoverview')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return
     */
    protected function _prepareColumns() {
        $helperNovalnetPayment = $this->helperNovalnetPayment();

        $this->addColumn('order_id', array(
            'header' => $helperNovalnetPayment->__('Order no #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'order_id',
        ));
        $this->addColumn('transaction_no', array(
            'header' => $helperNovalnetPayment->__('Transaction #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'transaction_id',
        ));
        $this->addColumn('id', array(
            'header' => $helperNovalnetPayment->__('Store id #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'store_id',
        ));

        return parent::_prepareColumns();
    }

    /**
     *
     * @return Novalnet_Payment_Helper_Data
     */
    protected function helperNovalnetPayment() {
        return Mage::helper('novalnet_payment');
    }

    /**
     * @param $row
     * @return string
     */
    public function getRowUrl($row) {
        return $this->getUrl('novalnet_payment/adminhtml_transactionoverview/view', array(
                    'nnlog_id' => $row->getId()
                        )
        );
    }

    public function getGridUrl() {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

}
