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
class Novalnet_Payment_Model_Payment_Method_NovalnetSecure extends Novalnet_Payment_Model_Payment_Method_Abstract {

    protected $_code = Novalnet_Payment_Model_Config::NN_CC3D;
    protected $_canAuthorize = Novalnet_Payment_Model_Config::NN_CC3D_CAN_AUTHORIZE;
    protected $_canCapture = Novalnet_Payment_Model_Config::NN_CC3D_CAN_CAPTURE;
    protected $_canUseInternal = Novalnet_Payment_Model_Config::NN_CC3D_CAN_USE_INTERNAL;
    protected $_canUseForMultishipping = Novalnet_Payment_Model_Config::NN_CC3D_CAN_USE_MULTISHIPPING;
    protected $_formBlockType = Novalnet_Payment_Model_Config::NN_CC3D_FORM_BLOCK;
    protected $_infoBlockType = Novalnet_Payment_Model_Config::NN_CC3D_INFO_BLOCK;

    /**
     * Get redirect URL
     *
     * @return Mage_Payment_Helper_Data
     */
    public function getOrderPlaceRedirectUrl() {
        return $this->_getHelper()->getUrl(Novalnet_Payment_Model_Config::GATEWAY_REDIRECT_URL);
    }
}
