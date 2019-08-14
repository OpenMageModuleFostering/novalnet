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
class Novalnet_Payment_Block_Adminhtml_Transactiontraces_View extends Mage_Adminhtml_Block_Widget_Form_Container
{

    /**
     * Transaction traces view
     */
    public function __construct()
    {
        $this->_objectId = 'nnlog_id';
        $this->_mode = 'view';
        $this->_blockGroup = 'novalnet_payment';
        $this->_controller = 'adminhtml_transactiontraces';

        parent::__construct();

        $this->setId('transactiontraces_view');
        $this->setUseAjax(true);
        $this->setDefaultSort('created_date');
        $this->setDefaultDir('DESC');

        $this->_removeButton('reset');
        $this->_removeButton('delete');
        $this->_removeButton('save');
    }

    /**
     * Get Novalnet transaction traces
     *
     * @param  none
     * @return string
     */
    public function getTransactionTraces()
    {
        return Mage::registry('novalnet_payment_transactiontraces');
    }

    /**
     * Get payment method title
     *
     * @param  none
     * @return string
     */
    public function getPaymentTitle()
    {
        $title = '';
        $order = Mage::getModel("sales/order")->loadByIncrementId(trim($this->getTransactionTraces()->getOrderId()));
        if ($order->getPayment()) {
            $paymentMethod = $order->getPayment()->getMethod();
            $title = Mage::helper("novalnet_payment")->getPaymentModel($paymentMethod)->getConfigData('title');
        }
        return $title;
    }

    /**
     * Get header text of transaction traces
     *
     * @param  none
     * @return string
     */
    public function getHeaderText()
    {
        $transStatus = $this->getTransactionTraces();
        $text = Mage::helper('novalnet_payment')->__(
            'Order #%s | TID : %s ', $transStatus->getOrderId(), $transStatus->getTransactionId()
        );
        return $text;
    }

}
