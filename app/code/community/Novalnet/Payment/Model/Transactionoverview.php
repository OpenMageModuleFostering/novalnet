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
class Novalnet_Payment_Model_Transactionoverview extends Mage_Core_Model_Abstract {

    /**
     *
     */
    public function _construct() {
        parent::_construct();
        $this->_init('novalnet_payment/transactionoverview');
    }

    /**
     * Load order transaction status by custom attribute value. Attribute value should be unique
     *
     * @param string $attribute
     * @param string $value
     * @return Novalnet_Payment_Model_Source_Transactionoverview
     */
    public function loadByAttribute($attribute, $value) {
        $this->load($value, $attribute);
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Novalnet_Payment_Model_Transactionoverview
     */
    public function loadByPayment(Mage_Sales_Model_Order_Payment $payment) {
        if ($payment->getOrder()->getIncrementId()) {            
            $this->load($payment->getOrder()->getIncrementId(), 'order_id');
        }
        return $this;
    }

    public function loadByOrderLogId(Novalnet_Payment_Model_Transactionoverview $orderLog) {
        $this->load($orderLog->getNnLogId(), 'nn_log_id');
        return $this;
    }

}