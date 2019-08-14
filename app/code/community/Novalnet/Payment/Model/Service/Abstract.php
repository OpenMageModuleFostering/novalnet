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
class Novalnet_Payment_Model_Service_Abstract
{
    /**
     * Helper
     */
    protected $_helper;

    /**
     * Storeid
     */
    protected $_storeId;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Payment method code
        $this->code = Mage::registry('payment_code');
        // Assign Novalnet helper
        $this->assignUtilities();
        // Assign basic vendor informations
        $this->setVendorInfo();
    }

    /**
     * Assign utilities (Novalnet payment helper)
     *
     * @param  none
     * @return Novalnet helper
     */
    public function assignUtilities()
    {
        // Assign helper
        if (!$this->_helper) {
            $this->_helper = Mage::helper('novalnet_payment');
        }
        // Assign store id
        if (!$this->_storeId) {
            $this->_storeId = $this->_helper->getMagentoStoreId();
        }
    }

    /**
     * Set merchant configuration details
     *
     * @param  none
     * @return none
     */
    protected function setVendorInfo()
    {
        $this->vendorId = $this->getNovalnetConfig('merchant_id', true);
        $this->authcode = $this->getNovalnetConfig('auth_code', true);
        $this->productId = $this->getNovalnetConfig('product_id', true);
        $this->tariffId = $this->getNovalnetConfig('tariff_id', true);
        $this->recurringTariffId = $this->getNovalnetConfig('subscrib_tariff_id', true);
        $this->accessKey = $this->getNovalnetConfig('password', true);
        $this->manualCheckLimit = (int) $this->getNovalnetConfig('manual_checking_amount', true);
        $this->loadAffiliateInfo(); // Re-assign merchant params based on affiliate
    }

    /**
     * Get affiliate account/user detail
     *
     * @param  null
     * @return mixed
     */
    public function loadAffiliateInfo()
    {
        $affiliateId = $this->_helper->getCoreSession()->getAffiliateId(); // Get affiliate user id if exist
        $customerId = $this->_helper->getCustomerId(); // Get current customer id

        if (!$affiliateId && $customerId != 'guest') { // Get affiliate id for existing customer (if available)
            $collection = $this->_helper->getModel('Mysql4_AffiliateUser')->getCollection()
                ->addFieldToFilter('customer_no', $customerId)
                ->addFieldToSelect('aff_id');
            $affiliateId = $collection->getLastItem()->getAffId() ? $collection->getLastItem()->getAffId() : null;
            $this->_helper->getCoreSession()->setAffiliateId($affiliateId);
        }

        if ($affiliateId) { // Get affiliate configuration values (if affiliate user id exist)
            $orderCollection = $this->_helper->getModel('Mysql4_AffiliateInfo')->getCollection()
                ->addFieldToFilter('aff_id', $affiliateId)
                ->addFieldToSelect('aff_id')
                ->addFieldToSelect('aff_authcode')
                ->addFieldToSelect('aff_accesskey');
            $this->vendorId = $orderCollection->getLastItem()->getAffId()
                ? $orderCollection->getLastItem()->getAffId() : $this->vendorId;
            $this->authcode = $orderCollection->getLastItem()->getAffAuthcode()
                ? $orderCollection->getLastItem()->getAffAuthcode() : $this->authcode;
            $this->accessKey = $orderCollection->getLastItem()->getAffAccesskey()
                ? $orderCollection->getLastItem()->getAffAccesskey() : $this->accessKey;
        }

    }

    /**
     * Get the Novalnet configuration (global/payment)
     *
     * @param  string  $field
     * @param  boolean $global
     * @return mixed|null
     */
    public function getNovalnetConfig($field, $global = false)
    {
        $path = 'novalnet_global/novalnet/' . $field; // Global config value path

        if ($field == 'live_mode') { // Novalnet payment mode
            $paymentMethod = Mage::getStoreConfig($path, $this->_storeId);
            return (!preg_match('/' . $this->code . '/i', $paymentMethod)) ? false : true;
        } elseif ($field !== null) {  // Get Novalnet payment/global configuration
            return ($global != false) ? trim(Mage::getStoreConfig($path, $this->_storeId))
                    : trim(Mage::getStoreConfig('payment/'. $this->code . '/' . $field, $this->_storeId));
        }
        return null;
    }

    /**
     * Whether current operation is order placement
     *
     * @param  Varien_Object $info
     * @return boolean
     */
    protected function _isPlaceOrder($info)
    {
        if ($info instanceof Mage_Sales_Model_Quote_Payment) {
            return false;
        } elseif ($info instanceof Mage_Sales_Model_Order_Payment) {
            return true;
        }
    }

    /**
     * Get current order/quote object
     *
     * @param  Varien_Object $info
     * @return Mage_Payment_Model_Method_Abstract
     */
    protected function _getInfoObject($info)
    {
        return ($this->_isPlaceOrder($info)) ? $info->getOrder() : $info->getQuote();
    }

    /**
     * Check the value is numeric
     *
     * @param  mixed $value
     * @return boolean
     */
    public function checkIsNumeric($value)
    {
        return preg_match('/^\d+$/', $value) ? true : false;
    }

    /**
     * Check the email id is valid
     *
     * @param  mixed $emailId
     * @return boolean
     */
    public function validateEmail($emailId)
    {
        $validatorEmail = new Zend_Validate_EmailAddress();
        return $validatorEmail->isValid($emailId) ? true : false;
    }

    /**
     * Check the value contains special characters
     *
     * @param  mixed $value
     * @return boolean
     */
    public function checkIsValid($value)
    {
        return (!$value || preg_match('/[#%\^<>@$=*!]/', $value)) ? false : true;
    }

    /**
     * Get grand total amount
     *
     * @param  Varien_Object $info
     * @return double
     */
    protected function _getAmount($info)
    {
        return ($this->_isPlaceOrder($info))
            ? (double) $info->getOrder()->getBaseGrandTotal()
            : (double) $info->getQuote()->getBaseGrandTotal();
    }

    /**
     * Check whether the fraud prevention available
     *
     * @param  string $countryCode
     * @return boolean
     */
    public function isCallbackTypeAllowed($countryCode)
    {
        $allowedCountryCode = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('allowedCountry');
        if (in_array($countryCode, $allowedCountryCode) && !$this->_helper->checkIsAdmin()) {
            return true;
        }
        return false;
    }

    /**
     * Get order increment id
     *
     * @param  none
     * @return int
     */
    protected function _getIncrementId()
    {
        $storeId = $this->_helper->getMagentoStoreId(); // Get store id
        $orders = Mage::getModel('sales/order')->getCollection()
                ->addAttributeToFilter('store_id', $storeId)
                ->setOrder('entity_id', 'DESC')
                ->setPageSize(1); // Get order collection
        $lastIncrementId = $orders->getFirstItem()->getIncrementId();
        return !empty($lastIncrementId) ? ++$lastIncrementId
            : $storeId . Mage::getModel('eav/entity_increment_numeric')->getNextId();
    }

    /**
     * Encoded the requested data
     *
     * @param  string $data
     * @return string
     */
    public function encode($data)
    {
        $data = trim($data);
        if ($data == null) {
            return'Error: no data';
        }
        if (!function_exists('base64_decode') or ! function_exists('pack') or ! function_exists('crc32')) {
            return'Error: func n/a';
        }
        try {
            $crc = sprintf('%u', crc32($data));
            $data = $crc . "|" . $data;
            $data = bin2hex($data . $this->accessKey);
            $data = strrev(base64_encode($data));
        } catch (Exception $e) {
            Mage::logException('Error: ' . $e);
        }
        return $data;
    }

    /**
     * Decoded the requested data
     *
     * @param  string $data
     * @return string
     */
    public function decode($data)
    {
        $data = trim($data);
        if ($data == null) {
            return'Error: no data';
        }
        if (!function_exists('base64_decode') || !function_exists('pack') || !function_exists('crc32')) {
            return'Error: func n/a';
        }

        try {
            $data = base64_decode(strrev($data));
            $data = pack("H" . strlen($data), $data);
            $data = substr($data, 0, stripos($data, $this->accessKey));
            $pos = strpos($data, "|");
            if ($pos === false) {
                return("Error: CKSum not found!");
            }
            $crc = substr($data, 0, $pos);
            $value = trim(substr($data, $pos + 1));
            if ($crc != sprintf('%u', crc32($value))) {
                return("Error; CKSum invalid!");
            }
            return $value;
        } catch (Exception $e) {
            Mage::logException('Error: ' . $e);
        }
    }

    /**
     * Hash value getter
     *
     * @param  array $request
     * @return string
     */
    public function getHash($request)
    {
        if (empty($request)) {
            return'Error: no data';
        }

        if (!function_exists('md5')) {
            return'Error: func n/a';
        }

        if ($request->getImplementation() == 'PHP_PCI') {
            $hash = md5(
                $request->getVendorAuthcode() . $request->getProductId() . $request->getTariffId() .
                $request->getAmount() . $request->getTestMode() . $request->getUniqid() . strrev($this->accessKey)
            );
        } else {
            $hash = md5(
                $request->getAuthCode() . $request->getProduct() . $request->getTariff() .
                $request->getAmount() . $request->getTestMode() . $request->getUniqid() . strrev($this->accessKey)
            );
        }

        return $hash;
    }

    /**
     * Check Hash value
     *
     * @param  array $request
     * @return boolean
     */
    public function checkHash($response)
    {
        if (!$response) {
            return false;
        }

        if ($response->hasHash2()
            && $response->getHash2() != $this->getHash($response)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get encoded payment data
     *
     * @param  Varien_Object $request
     * @return none
     */
    public function getEncodedParams($request)
    {
        $params = ($request->getImplementation() == 'PHP_PCI')
            ? Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('pciHashParams')
            : Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('hashParams');
        foreach ($params as $value) {
            $data = $request->$value;
            $data = $this->encode($data);
            $request->$value = $data;
        }
        $request->setHash($this->getHash($request));
    }

    /**
     * Get decoded payment data
     *
     * @param  Varien_Object $response
     * @param  int|null      $storeId
     * @return none
     */
    public function getDecodedParams($response, $storeId = null)
    {
        $this->_storeId = ($storeId !== null) ? $storeId : $this->_storeId;
        $params = ($response->getImplementation() == 'PHP_PCI')
            ? Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('pciHashParams')
            : Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('hashParams');
        foreach ($params as $value) {
            $data = $response->$value;
            $data = $this->decode($data);
            $response->$value = $data;
        }
    }

}
