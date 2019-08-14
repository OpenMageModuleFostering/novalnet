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
class Novalnet_Payment_Block_Payment_Method_Info_Ccsecure extends Mage_Payment_Block_Info {

    /**
     * Init default template for block
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('novalnet/payment/method/info/Ccsecure.phtml');
    }

    /**
     * Retrieve credit card type name
     *
     * @return string
     */
    public function getCcTypeName() {
        $types = Mage::getSingleton('payment/config')->getCcTypes();
        if (isset($types[$this->getInfo()->getCcType()])) {
            return $types[$this->getInfo()->getCcType()];
        }
        return $this->getInfo()->getCcType();
    }

    /**
     * Retrieve CC expiration month
     *
     * @return string
     */
    public function getCcExpMonth() {
        $month = $this->getInfo()->getCcExpMonth();
        if ($month < 10) {
            $month = '0' . $month;
        }
        return $month;
    }

    /**
     * Retrieve CC expiration date
     *
     * @return Zend_Date
     */
    public function getCcExpDate() {
        $date = Mage::app()->getLocale()->date(0);
        $date->setYear($this->getInfo()->getCcExpYear());
        $date->setMonth($this->getInfo()->getCcExpMonth());
        return $date;
    }

    /**
     * Render as PDF
     * @return string
     */
    public function toPdf() {
        $this->setTemplate('novalnet/payment/method/pdf/Ccsecure.phtml');
        return $this->toHtml();
    }

    /**
     * Retrieve payment method model
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function getMethod() {
        return $this->getInfo()->getMethodInstance();
        // return Mage::helper('novalnet_payment')->getModel('novalnetSecure');
    }

    /**
     * @return string
     */
    public function getPaymentMethod() {
        return $this->htmlEscape($this->getMethod()->getConfigData('title'));
    }

    /**
     * Get some specific information
     *
     * @return array
     */
    public function getAdditionalData($key) {
        return Mage::helper('novalnet_payment')->getAdditionalData($this->getInfo(), $key);
    }

}
