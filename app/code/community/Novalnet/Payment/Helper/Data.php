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
class Novalnet_Payment_Helper_Data extends Mage_Core_Helper_Abstract {

	/**
     * Array values to configure global configuration
     */

    protected $config = array(
        'index' => array(
            'header_text' => 'Novalnet Global Configuration',
            'previous_page' => '',
            'next_page' => 'generalGlobal',
        ),
        'generalGlobal' => array(
            'group_name' => 'novalnet',
            'header_text' => 'Novalnet Global Configuration',
            'codes' => array(
                'page' => 'generalGlobal',
                //'section' => 'payment_config',
				'section' => 'novalnet_global',
            ),
            'previous_page' => 'index',
        )
    );

    /**
     * Initiate configuration
     *
     * @param $actionName
     * @param $request
     * @return Varien_Object
     */
    public function initConfig($actionName, $request) {
        $config = new Varien_Object($this->config);
        $this->registerConfig($config);

        $configPages = $this->initConfigPage($actionName);
        $configPages = new Varien_Object($configPages);

        $codes = $configPages->getData('codes');

        $codes['website'] = $request->getParam('website');
        $codes['store'] = $request->getParam('store');

        $configPages->setData('codes', $codes);

        $this->registerConfigPage($configPages);
        return $configPages;
    }

    /**
     * Get novalnet configure wizard details
     *
     * @return Varien_Object
     */
    public function getConfig() {
        return Mage::registry('novalnet_wizard_config');
    }

    /**
     * Register novalnet configurtion wizard details
     *
     * @param Varien_Object $config
     */
    public function registerConfig(Varien_Object $config) {
        Mage::register('novalnet_wizard_config', $config);
    }

    /**
     * Get configuration page
     *
     * @return Varien_Object
     */
    public function getConfigPage() {
        /** @var $config Varien_Object */
        return Mage::registry('novalnet_wizard_config_page');
    }

    /**
     * Register configuration page
     *
     * @param Varien_Object $config
     */
    public function registerConfigPage(Varien_Object $config) {
        Mage::register('novalnet_wizard_config_page', $config);
    }

    /**
     * Initiate configuration page process
     *
     * @param $page
     * @return array|null
     */
    public function initConfigPage($page) {
        if (!array_key_exists($page, $this->config)) {
            return null;
        }
        return $this->config[$page];
    }

    /**
     * @return array
     */
    public function initConfigArray() {
        return $this->config;
    }

    /**
     * Get configuration wizard next page
     *
     * @return string
     */
    public function getNextPageUrlAsString() {
        $pageName = $this->getConfigPage()->getData('next_page');
        $url = $this->getPageUrlAsString($pageName);
        return $url;
    }

    /**
     * Get configuration wizard previous page
     *
     * @return string
     */
    public function getPreviousPageUrlAsString() {
        $pageName = $this->getConfigPage()->getData('previous_page');
        $url = $this->getPageUrlAsString($pageName);
        return $url;
    }

    /**
     * Get current page url
     *
     * @return string
     */
    public function getPageUrlAsString($nextPageName) {
        $config = $this->getConfig();
        $nextPage = $config->getData($nextPageName);
        if ($nextPage && array_key_exists('url', $nextPage)) {
            $url = $nextPage['url'];
        } else {
            $url = '*/adminhtml_configuration_wizard_page/' . $nextPageName;
        }
        return $url;
    }

    /**
     * Get configuration wizard page code
     *
     * @return string
     */
    public function getNextPageCode($pageCode) {
        $pages = $this->initConfigPage($pageCode);
        if ($pages === null) {
            return 'index';
        }
        if (!array_key_exists('next_page', $pages)) {
            return 'index';
        }
        $nextPageKey = $pages['next_page'];
        return $nextPageKey;
    }

    /**
     * Check whether logged in as admin
     *
	 * @return bool
     */
    public function checkIsAdmin() {
        return (Mage::app()->getStore()->isAdmin()) ? true : false;
    }

    /**
     * Retrieve Magento version
     *
     * @return mixed
     */
    public function getMagentoVersion() {
        return Mage::getVersion();
    }

    /**
     * Get current store id
     *
     * @return int
     */
    public function getMagentoStoreId() {
        $storeId = Mage::getModel('sales/quote')->getStoreId();
        if ($this->checkIsAdmin()) {
            $storeId = $this->_getAdminCheckoutSession()->getStoreId();
        }
        return $storeId;
    }

    /**
     * Get current store details
     *
     * @return Mage_Core_Model_Store
     */
    public function getMagentoStore() {
        return Mage::app()->getStore();
    }

    /**
     * Get current novalnet version
     *
     * @return string
     */
    public function getNovalnetVersion() {
        $versionInfo = (string) Mage::getConfig()->getNode('modules/Novalnet_Payment/version');
        return "Novalnet CE (v{$versionInfo})";
    }

    /**
     * Get customer Ip address
     *
     * @return string
     */
    public function getRealIpAddr() {
		$ipAddr = Mage::helper('core/http')->getRemoteAddr();
		if($ipAddr == '::1') {//IPv6 Issue
			return '127.0.0.1';
		}
		return $ipAddr;
    }

    /**
     * Novalnet web URL generic getter
     *
     * @return string
     */
    public function getNovalnetUrl() {
        if (strtoupper($this->getDefaultLanguage()) == 'DE') {
            $siteUrl = 'https://www.novalnet.de';
        } else {
            $siteUrl = 'http://www.novalnet.com';
        }
        return $siteUrl;
    }

    /**
     * Getter for Payment form logo images
     *
     * @return string
     */
    public function getNovalnetPaymentFormLogoUrl() {
        $baseUrl = Mage::getBaseUrl('skin');
        $imageUrl = $baseUrl . "frontend/default/default/images/novalnet/" . strtoupper($this->getDefaultLanguage()) . "/";
        return $imageUrl;
    }

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function _getCheckoutSession() {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get singleton of admin Checkout Session
     *
     * @return Mage_adminhtml_Model_Session_quote
     */
    public function _getAdminCheckoutSession() {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Get shop's date and time
     *
     * @return date/time
     */
    public function getCurrentDateTime() {
        return Mage::getModel('core/date')->date('Y-m-d H:i:s');
    }

    /**
     * Get singleton of customer Session
     *
     * @return Mage_customer_Model_Session
     */
    public function getCustomerSession() {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Check customer_no for Logged in user
     *
     * @return bool
     */
    public function customerNumberValidation() {
        $getCoreData = $this->getCurrentDateTime();
        try {
            $getCustomerSession = $this->getCustomerSession();
            $order_details = Mage::getModel('checkout/cart')->getQuote()->getData();

            //Checking custommer loggin status
            $login_check = $getCustomerSession->isLoggedIn();
            if ($login_check) {
                $customer_no = $getCustomerSession->getCustomer()->getId();
                if (empty($customer_no)) {
                    $customer_no = $_SESSION['core']['visitor_data']['customer_id']; // Used Only customer id is not assigned in mage session
                }
                //Log customer Order details
                if ($customer_no == "") {
                    Mage::log($getCustomerSession->getCustomer(), NULL, "Customerid_Missing_" . $getCoreData . ".log");
                    Mage::log("Below are Order Details : ", NULL, "Customerid_Missing_" . $getCoreData . ".log");
                    Mage::log($order_details, NULL, "Customerid_Missing_" . $getCoreData . ".log");
                    Mage::throwException($this->__('Basic Parameter Missing. Please contact Shop Admin') . '!');
                }
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage(), NULL, "Customerid_Missing_" . $getCoreData . ".log");
        }
    }

    /**
     * Check whether payment method can be used
     * for Zero subtotal Checkout
     * @return bool
     */
    public function isModuleActive($grandTotal = NULL) {
        if (!$grandTotal || $grandTotal == 0) {
            return false;
        }
        return true;
    }

    /**
     * Check whether the CallbackTypeCall can be used
     *
     * @return bool
     */
    public function isCallbackTypeAllowed($countryCode) {
        $allowedCountryCode = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('callbackAllowedCountry');
        if (in_array($countryCode, $allowedCountryCode)
                && !(Mage::app()->getStore()->isAdmin())) {
            return true;
        }
        return false;
    }

    /**
     * Encode special characters
     * @param String $input
     *
     * @return String
     */
    public function nn_utf8_encode($input) {
        return (mb_detect_encoding($input, 'UTF-8', true) == 'UTF-8') ? utf8_decode($input) : $input;
    }

    /**
     * @return string
     */
    public function getDefaultLanguage() {
        $locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
        if (is_array($locale) && !empty($locale)) {
            $locale = $locale[0];
        } else {
            $locale = 'en';
        }
        return $locale;
    }

    /**
     * Retrieve customer id from current session
     *
     * @return int|null
     */
    public function getCustomerId() {
        $customerNo = '';
        if ($this->checkIsAdmin()) {
            $adminuser = Mage::getSingleton('admin/session');
            $customerNo = $adminuser->getUser()->getUserId();
        } else {
            $loginCheck = $this->getCustomerSession()->isLoggedIn();
            if ($loginCheck) {
                $customerNo = $this->getCustomerSession()->getCustomerId();
            } else {
                $customerNo = 'guest';
            }
        }
        return $customerNo;
    }

    /**
     * Set secure url checkout is secure for current store.
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl($route, $params = array()) {
        $params['_type'] = Mage_Core_Model_Store::URL_TYPE_LINK;
        if (isset($params['is_secure'])) {
            $params['_secure'] = (bool) $params['is_secure'];
        } elseif ($this->getMagentoStore()->isCurrentlySecure()) {
            $params['_secure'] = true;
        }
        return parent::_getUrl($route, $params);
    }

    /**
     * Extract data from additional data array
     *
     * @param string $info
     * @param string $key
     * @return array
     */
    public function getAdditionalData($info, $key = null) {
        $data = array();
        if ($info->getAdditionalData()) {
            $data = unserialize($info->getAdditionalData());
        }
        if (!empty($key) && isset($data[$key])) {
            return $data[$key];
        } else {
            return '';
        }
    }

    /**
     * Check whether current user have access to the payment method
     *
     * @return bool
     */
    public function checkCustomerAccess($userGroupId = NULL) {
        $exludedGroupes = trim($userGroupId);
        if (strlen($exludedGroupes)) {
            $exludedGroupes = explode(',', $exludedGroupes);
            $custGrpId = $this->getCustomerSession()->getCustomerGroupId();
            return !in_array($custGrpId, $exludedGroupes);
        }
        return true;
    }

    /**
     * Do encode for novalnet params
     *
     */
    public function setNovalnetEncodedParam(Varien_Object $request, $key) {
        $request->setAuthCode($this->getEncodedParam($request->getAuthCode(), $key))
                ->setProduct($this->getEncodedParam($request->getProduct(), $key))
                ->setTariff($this->getEncodedParam($request->getTariff(), $key))
                ->setTestMode($this->getEncodedParam($request->getTestMode(), $key))
                ->setAmount($this->getEncodedParam($request->getAmount(), $key))
                ->setUniqid($this->getEncodedParam(uniqid(), $key));
    }

    /**
     * Function to Encode Novalnet data
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    public function getEncodedParam($data, $key) {
        $data = trim($data);
        if ($data == '')
            return'Error: no data';
        if (!function_exists('base64_decode') or !function_exists('pack') or !function_exists('crc32')) {
            return'Error: func n/a';
        }
        try {
            $crc = sprintf('%u', crc32($data)); // %u is a must for ccrc32 returns a signed value
            $data = $crc . "|" . $data;
            $data = bin2hex($data . $key);
            $data = strrev(base64_encode($data));
        } catch (Exception $e) {
            echo('Error: ' . $e);
        }
        return $data;
    }

    /**
     * Function to Encode Novalnet PCI data
     *
     * @param string $data

     * @param string $key
     * @return string
     */
    public function getPciEncodedParam(&$fields, $key) {
        if (!function_exists('base64_encode') || !function_exists('pack') || !function_exists('crc32')) {
            return false;
        }
        $toBeEncoded = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('novalnetEncodeParams');
        foreach ($toBeEncoded as $_value) {
            $data = $fields->$_value;
            if ($this->isEmptyString($data)) {
                return false;
            }
            try {
                $crc = sprintf('%u', crc32($data)); //%u is must
                $data = $crc . "|" . $data;
                $data = bin2hex($data . $key);
                $data = strrev(base64_encode($data));
                $fields->$_value = $data;
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * Function to Decode Novalnet data
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    public function getDecodedParam($data, $key) {
        $data = trim($data);
        if ($data == '') {
            return'Error: no data';
        }
        if (!function_exists('base64_decode') || !function_exists('pack') || !function_exists('crc32')) {
            return'Error: func n/a';
        }
        try {
            $data = base64_decode(strrev($data));
            $data = pack("H" . strlen($data), $data);
            $data = substr($data, 0, stripos($data, $key));
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
            echo('Error: ' . $e);
        }
    }

    /**
     * Check whether there is an Empty string
     *
     * @param string $str
     * @return bool
     */
    public function isEmptyString($str) {
        $str = trim($str);
        return !isset($str[0]);
    }

    /**
     * Generate Hash value for PCI
     *
     * @param array $data
     * @param string $key
     * @return string
     */
    public function generateHash($data, $key) {
        if (!function_exists('md5') || $this->isEmptyString($key)) {
            return false;
        }
        $hashFields = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('novalnetHashParams');
        $str = NULL;
        foreach ($hashFields as $_value) {
            if ($this->isEmptyString($data->$_value)) {
                return false;
            }
            $str .= $data->$_value;
        }
        return md5($str . strrev($key));
    }

    /**
     * Generate return Hash value for PCI
     *
     * @param array $data
     * @param string $key
     * @return string
     */
    public function generateHashReturn($data, $key) {
        if (!function_exists('md5') || $this->isEmptyString($key)) {
            return false;
        }
        $hashFields = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('novalnetHashParams');
        $str = NULL;
        foreach ($hashFields as $_value) {
            if ($this->isEmptyString($data[$_value])) {
                return false;
            }
            $str .= $data[$_value];
        }
        return md5($str . strrev($key));
    }

    /**
     * Hash value getter
     *
     * @param array $h
     * @param string $key
     * @return string
     */
    public function getHash($h, $key) {
        if (empty($h)) {
            return'Error: no data';
        }
        if (!function_exists('md5')) {
            return'Error: func n/a';
        }
        return md5($h['auth_code'] . $h['product'] . $h['tariff'] . $h['amount'] . $h['test_mode'] . $h['uniqid'] . strrev($key));
    }

    /**
     * Check Hash value
     *
     * @param array $request
     * @param string $key
     * @return bool
     */
    public function checkHash($request, $key) {
        if (!$request)
            return false;
        if ($request['hash2'] != $this->getHash($request, $key)) {
            return false;
        }
        return true;
    }

    /**
     * Check PCI hash value
     *
     * @param array $response
     * @param string $key
     * @return bool
     */
    public function checkPciHash($response, $key) {
        return isset($response['hash2'])
                && !$this->isEmptyString($response['hash2'])
                && (($tmp = $this->generateHashReturn($response, $key)) !== false)
                && ($response['hash2'] == $tmp);
    }

    /**
     * Check Novalnet response params
     *
     * @param array $response
     * @param string $password
     * @return int
     */
    public function checkParams($response, $password) {
        $status = $response['status'];
        if (!$response['hash2']) {
            $status = '94';
        }if (!$this->checkHash($response, $password)) {
            $status = '91';
        }
        $response['amount'] = $this->getDecodedParam($response['amount'], $password);
        if (!$this->checkIsNumeric($response['amount'])) {
            $status = '92';
        }
        $this->_status = $status;
        return $status;
    }

    /**
     * Retrieve Novalnet Payment Key
     * Novalnet Payment Key, is a fixed value, DO NOT CHANGE!!!!!
     *
     * @param string $code
     *
     * @return int
     */
    public function getPaymentId($code) {
		if($code == 'novalnetSepa' && $this->getModel($code)->_getConfigData('sepatypes') == 'DD_SEPA_SIGNED') {
			$code = $code.'Signed';
		}
        $arrPaymentId = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('novalnetPaymentKey');
        return $arrPaymentId[$code];
    }

    public function checkOrdersCount($minOrderCount) {
		$customerId = $this->getCustomerId();
        // Load orders and check
        $orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('customer_id',$customerId);
		$OrdersCount = $orders->count();
        if (trim($OrdersCount) < $minOrderCount) {
            return true;
        }
        return false;

    }

    /**
     * Parse an NVP response string into an associative array
     *
     * @param string $nvpstr
     * @return array
     */
    public function deformatNvp($delim_char, $query) {
        $deformated = array();
        if (strlen($query)) {
            $tmp = explode($delim_char, $query);
            foreach ($tmp as $k) {
                $k = explode('=', $k);
                $deformated[urldecode($k[0])] = isset($k[1]) ? urldecode($k[1]) : NULL;
            }
        }
        return $deformated;
    }

    /**
     * set due date for invoice payment
     *
     * @return bool
     */
    public function setDueDate($paymentDuration) {
        $dueDate = '';
        if ($paymentDuration && $this->checkIsNumeric($paymentDuration)) {
            $dueDate = date('Y-m-d', strtotime('+' . (int) $paymentDuration . ' days'));
        } elseif ($paymentDuration == "0") {
            $dueDate = date('Y-m-d');
        }
        return $dueDate;
    }

    /**
     * Get novalnet model class
     *
     * @return Novalnet_Payment_Model_Payment_Method_Abstract
     */
    public function getModel($modelclass) {
        return Mage::getModel('novalnet_payment/payment_method_' . $modelclass);
    }

    /**
     * Get novalnet transaction status model
     *
     * @return Novalnet_Payment_Model_Transactionstatus_Collection
     */
    public function getModelTransactionStatus() {
        return Mage::getModel('novalnet_payment/transactionstatus');
    }

    /**
     * Get novalnet transaction overview model
     *
     * @return Novalnet_Payment_Model_Transactionoverview_Collection
     */
    public function getModelTransactionOverview() {
        return Mage::getModel('novalnet_payment/transactionoverview');
    }

    /**
     * Check the value is numeric
     *
     * @param mixed $value
     * @return bool
     */
    public function checkIsNumeric($value) {
        return preg_match('/^\d+$/', $value) ? true : false;
    }

    /**
     * Check the value contains special characters
     *
     * @param mixed $value
     * @return bool
     */
    public function checkIsValid($value) {
        return (!$value || preg_match('/[#%\^<>@$=*!]/', $value)) ? false : true;
    }

    /**
     * Check the email id is valid
     *
     * @param mixed $value
     * @return bool
     */
    public function validateEmail($emailId) {
        return preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $emailId) ? true : false;
    }
    /**
     * Replace strings from the value passed
     *
     * @param mixed $value
     * @return integer
     */
    public function makeValidNumber($value) {
        return preg_replace('/[^0-9]+/', '', $value);
    }

    /**
     * Get the formated amount in cents/euro
     *
     * @param float $amount
     * @param string $type
     * @return mixed
     */
    public function getFormatedAmount($amount, $type = 'CENT') {
        return ($type == 'RAW') ? $amount / 100 : round($amount, 2) * 100;
    }

    /**
     * Load novalnet transaction status based on tid
     *
     * @param integer $txnId
     * @return object Novalnet_Payment_Model_Transactionstatus
     */
    public function loadTransactionStatus($txnId) {
        return $this->getModelTransactionStatus()->loadByAttribute('transaction_no', $txnId);
    }

    /**
     * Mask payment data
     *
     * @param integer $txnId
     * @return varchar
     */
    public function doMaskPaymentData($data) {
        return substr($data, 0, -4) . '****';
    }

    /**
     * Get novalnet payment configuration global path
     *
     * @return string
     */
    public function getNovalnetGlobalPath() {
        return 'novalnet_global/novalnet/';
    }

    /**
     * Get current site url
     *
     * @return string
     */

    public function getCurrentSiteUrl() {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
    }
}
