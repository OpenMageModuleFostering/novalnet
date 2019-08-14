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
class Novalnet_Payment_Block_Payment_Method_Form_Elvaustria extends Mage_Payment_Block_Form {

    protected function _construct() {
        parent::_construct();
        $this->setTemplate('novalnet/payment/method/form/Elvaustria.phtml');
    }

    /**
     * Get ACDC details from config
     *
     * @return bool
     */
    public function getAcdc() {
        $method = $this->getMethod();
        return $method->getConfigData('acdc_check');
    }

    /**
     * Check whether Callback type allowed
     *
     * @return bool
     */
    public function isCallbackTypeCall() {
        return $this->getMethod()->isCallbackTypeCall();
        //return false;
    }

    /**
     * Novalnet Callback data getter
     *
     * @return string
     */
    public function getCallbackConfigData() {
        return $this->getMethod()->_getConfigData('callback');
    }

    /**
     * Get Customer Telephone no
     *
     * @return int
     */
    public function getCustomersTelephone() {
        $customer = Mage::getSingleton('customer/session')
                        ->getCustomer()->getDefaultBillingAddress();
        if ($customer) {
            return $customer->getTelephone();
        }
    }

    /**
     * Get Customer Email address
     *
     * @return string
     */
    public function getCustomersEmail() {
        $customer = Mage::getSingleton('customer/session')
                        ->getCustomer()->getEmail();
        if ($customer) {
            return $customer;
        }
    }

    /**
     * Get information to end user from config
     *
     * @return string
     */
    public function getUserInfo() {
        return $this->getMethod()->getConfigData('booking_reference');
    }

    /**
     * Retrieve Novalnet Mode in Configuration
     *
     * @return bool
     */
    public function getLiveMode() {
        return $this->getMethod()->_getConfigData('live_mode');
    }

}
