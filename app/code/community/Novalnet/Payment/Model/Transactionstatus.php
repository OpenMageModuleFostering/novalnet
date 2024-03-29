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
class Novalnet_Payment_Model_Transactionstatus extends Mage_Core_Model_Abstract
{

    /**
     * Constructor
     *
     * @see lib/Varien/Varien_Object#_construct()
     * @return Novalnet_Payment_Model_Transactionstatus
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('novalnet_payment/transactionstatus');
    }

    /**
     * Load order transaction status by custom attribute value. Attribute value should be unique
     *
     * @param string $attribute
     * @param string $value
     * @return Novalnet_Payment_Model_Source_Transactionstatus
     */
    public function loadByAttribute($attribute, $value)
    {
        $this->load($value, $attribute);
        return $this;
    }

    /**
     * Load order transaction status by transaction id
     *
     * @param mixed $transactionStatus
     * @return Novalnet_Payment_Model_Transactionstatus
     */
    public function loadByTransactionStatusId(Novalnet_Payment_Model_Transactionstatus $transactionStatus)
    {
        $this->load($transactionStatus->getNnTxnId(), 'nn_txn_id');
        return $this;
    }

}
