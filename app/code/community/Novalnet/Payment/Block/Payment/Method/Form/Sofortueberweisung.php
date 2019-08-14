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
class Novalnet_Payment_Block_Payment_Method_Form_Sofortueberweisung extends Mage_Payment_Block_Form {

    /**
     * Init default template for block
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('novalnet/payment/method/form/Sofortueberweisung.phtml');
    }

    /**
     * Retrieve payment configuration object
     *
     * @return Mage_Payment_Model_Config
     */
    protected function _getConfig() {
        return Mage::getSingleton('payment/config');
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
