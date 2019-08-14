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
class Novalnet_Payment_Block_Adminhtml_Transactionoverview_View extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Transaction status view
     */
    public function __construct()
    {
        $this->_objectId = 'nntxn_id';
        $this->_mode = 'view';
        $this->_blockGroup = 'novalnet_payment';
        $this->_controller = 'adminhtml_transactionoverview';

        parent::__construct();

        $this->setId('transaction_view');
        $this->_removeButton('reset');
        $this->_removeButton('delete');
        $this->_removeButton('save');
    }

    /**
     * Retrieve invoice model instance
     *
     * @param  none
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * Get Novalnet transaction status
     *
     * @param  none
     * @return string
     */
    public function getTransactionStatus()
    {
        return Mage::registry('novalnet_payment_transactionoverview');
    }

    /**
     * Get order currency code
     *
     * @param  none
     * @return string
     */
    public function getCurrencyCode()
    {
        $order = Mage::getModel("sales/order")->loadByIncrementId(
            trim($this->getTransactionStatus()->getOrderId())
        );
        return $order->getOrderCurrencyCode();
    }

    /**
     * Get payment method title
     *
     * @param  none
     * @return string
     */
    public function getPaymentTitle()
    {
        $transactionStatus = $this->getTransactionStatus();
        $title = Mage::helper("novalnet_payment")
            ->getPaymentModel($transactionStatus->getPaymentName())
            ->getConfigData('title');
        return $title;
    }

    /**
     * Get header text of transaction status
     *
     * @param  none
     * @return string
     */
    public function getHeaderText()
    {
        $transStatus = $this->getTransactionStatus();
        $text = Mage::helper('novalnet_payment')->__(
            'Order #%s | TID : %s ', $transStatus->getOrderId(), $transStatus->getTransactionNo()
        );
        return $text;
    }

}
