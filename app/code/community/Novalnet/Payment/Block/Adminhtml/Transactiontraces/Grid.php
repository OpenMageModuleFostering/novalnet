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
class Novalnet_Payment_Block_Adminhtml_Transactiontraces_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    /**
     * Transaction traces grid
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('novalnet_transactiontraces_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('order_id');
        $this->setDefaultDir('DESC');
    }

    /**
     * Prepare order Collection for novalnet transaction overview
     *
     * @param  none
     * @return Novalnet_Payment_Block_Adminhtml_Transactiontraces_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('novalnet_payment/Mysql4_TransactionTraces')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * prepare column for transaction overview grid
     *
     * @param  none
     * @return Novalnet_Payment_Block_Adminhtml_Transactionoverview_Grid
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('novalnet_payment');

        $this->addColumn(
            'order_id', array(
            'header' => $helper->__('Order No #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'order_id',
            )
        );
        $this->addColumn(
            'transaction_no', array(
            'header' => $helper->__('Transaction Id #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'transaction_id',
            )
        );
        $this->addColumn(
            'customer_id', array(
            'header' => Mage::helper('sales')->__('Customer Id #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'customer_id',
            )
        );
        $this->addColumn(
            'id', array(
            'header' => $helper->__('Store Id #'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'store_id',
            )
        );

        return parent::_prepareColumns();
    }

    /**
     * Return row url
     *
     * @param  mixed $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl(
            'adminhtml/novalnetpayment_transactiontraces/view', array(
                    'nnlog_id' => $row->getId()
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
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

}
