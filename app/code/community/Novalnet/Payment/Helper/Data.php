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
class Novalnet_Payment_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Array values to configure global configuration
     */
    protected $_config = array(
        'index' => array(
            'header_text' => 'Novalnet Global Configuration',
            'previous_page' => '',
            'next_page' => 'generalGlobal',
        ),
        'generalGlobal' => array(
            'group_name' => array('novalnet', 'novalnetsetting'),
            'header_text' => 'Novalnet Global Configuration',
            'codes' => array(
                'page' => 'generalGlobal',
                'section' => 'novalnet_global',
            ),
            'previous_page' => 'index',
            'next_page' => 'save',
        ),
        'paymentSave' => array(
            'group_name' => array('novalnetCc', 'novalnetSepa', 'novalnetInvoice',
                'novalnetPrepayment',
                'novalnetBanktransfer', 'novalnetPaypal', 'novalnetEps', 'novalnetIdeal', 'novalnetGiropay'),
            'header_text' => 'Novalnet Global Configuration',
            'codes' => array(
                'page' => 'paymentSave',
                'section' => 'novalnet_paymethods',
            ),
            'previous_page' => 'generalGlobal',
            'next_page' => '',
        )
    );

    /**
     * Initiate configuration
     *
     * @param $actionName
     * @param $request
     * @return varien_object
     */
    public function initConfig($actionName, $request)
    {
        $config = new Varien_Object($this->_config);
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
     * Get Novalnet configure wizard details
     *
     * @return varien_object
     */
    public function getConfig()
    {
        return Mage::registry('novalnet_wizard_config');
    }

    /**
     * Register Novalnet configurtion wizard details
     *
     * @param Varien_Object $config
     */
    public function registerConfig(Varien_Object $config)
    {
        Mage::register('novalnet_wizard_config', $config);
    }

    /**
     * Get configuration page
     *
     * @return varien_object
     */
    public function getConfigPage()
    {
        return Mage::registry('novalnet_wizard_config_page');
    }

    /**
     * Register configuration page
     *
     * @param Varien_Object $config
     */
    public function registerConfigPage(Varien_Object $config)
    {
        Mage::register('novalnet_wizard_config_page', $config);
    }

    /**
     * Initiate configuration page process
     *
     * @param string $page
     * @return array|null
     */
    public function initConfigPage($page)
    {
        if (!array_key_exists($page, $this->_config)) {
            return null;
        }
        return $this->_config[$page];
    }

    /**
     * Get configuration wizard next page
     *
     * @return string
     */
    public function getNextPageUrlAsString()
    {
        $pageName = $this->getConfigPage()->getData('next_page');
        $url = $this->getPageUrlAsString($pageName);
        return $url;
    }

    /**
     * Get configuration wizard previous page
     *
     * @return string
     */
    public function getPreviousPageUrlAsString()
    {
        $pageName = $this->getConfigPage()->getData('previous_page');
        $url = $this->getPageUrlAsString($pageName);
        return $url;
    }

    /**
     * Get current page url
     *
     * @return string
     */
    public function getPageUrlAsString($nextPageName)
    {
        $config = $this->getConfig();
        $nextPage = $config->getData($nextPageName);
        if ($nextPage && array_key_exists('url', $nextPage)) {
            $url = $nextPage['url'];
        } else {
            $url = '*/novalnetpayment_configuration_wizard_page/' . $nextPageName;
        }
        return $url;
    }

    /**
     * Check whether logged in as admin
     *
     * @return boolean
     */
    public function checkIsAdmin()
    {
        return (Mage::app()->getStore()->isAdmin()) ? true : false;
    }

    /**
     * Retrieve Magento version
     *
     * @return int
     */
    public function getMagentoVersion()
    {
        return Mage::getVersion();
    }

    /**
     * Get current store id
     *
     * @return int
     */
    public function getMagentoStoreId()
    {
        $storeId = Mage::getModel('sales/quote')->getStoreId();
        if ($this->checkIsAdmin()) {
            $storeId = $this->getAdminCheckoutSession()->getStoreId();
        }
        return $storeId;
    }

    /**
     * Get current store details
     *
     * @return Mage_Core_Model_Store
     */
    public function getMagentoStore()
    {
        return Mage::app()->getStore();
    }

    /**
     * Get current Novalnet version
     *
     * @return string
     */
    public function getNovalnetVersion()
    {
        $versionInfo = (string) Mage::getConfig()->getNode('modules/Novalnet_Payment/version');
        return "NN({$versionInfo})";
    }

    /**
     * Get customer Ip address
     *
     * @return int
     */
    public function getRealIpAddr()
    {
        $ipAddr = Mage::helper('core/http')->getRemoteAddr();
        if ($ipAddr == '::1') {//IPv6 Issue
            return '127.0.0.1';
        }
        return $ipAddr;
    }

    /**
     * Get Server Ip address
     *
     * @return int
     */
    public function getServerAddr()
    {
        $serverAddr = Mage::helper('core/http')->getServerAddr();
        if ($serverAddr == '::1') {//IPv6 Issue
            return '127.0.0.1';
        }
        return $serverAddr;
    }

    /**
     * Novalnet web URL generic getter
     *
     * @return string
     */
    public function getNovalnetUrl()
    {
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
    public function getNovalnetPaymentFormLogoUrl()
    {
        $baseUrl = Mage::getBaseUrl('skin');
        $imageUrl = $baseUrl . "frontend/base/default/images/novalnet/";
        return $imageUrl;
    }

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get singleton of admin Checkout Session
     *
     * @return Mage_adminhtml_Model_Session_quote
     */
    public function getAdminCheckoutSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Get shop's date and time
     *
     * @return mixed
     */
    public function getCurrentDateTime()
    {
        return Mage::getModel('core/date')->date('Y-m-d H:i:s');
    }

    /**
     * Get singleton of customer Session
     *
     * @return Mage_customer_Model_Session
     */
    public function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Get the core session
     *
     * @return Mage_Core_Model_Session
     */
    public function getCoresession()
    {
        return Mage::getSingleton('core/session');
    }

    /**
     * Check customerNo for Logged in user
     *
     */
    public function customerNumberValidation()
    {
        $getCoreData = $this->getCurrentDateTime();
        try {
            $getCustomerSession = $this->getCustomerSession();
            $orderDetails = Mage::getModel('checkout/cart')->getQuote()->getData();
            //Checking customer login status
            $loginCheck = $getCustomerSession->isLoggedIn();
            if ($loginCheck) {
                $customerNo = $getCustomerSession->getCustomer()->getId();
                if (empty($customerNo)) {
                    $visitorData = $this->getCoresession()->getVisitorData();
                    $customerNo = $visitorData['customer_id']; // Used Only customer id is not assigned in mage session
                }
                //Log customer Order details
                if (!$customerNo) {
                    Mage::log($getCustomerSession->getCustomer(), NULL, "Customerid_Missing_" . $getCoreData . ".log");
                    Mage::log("Below are Order Details : ", NULL, "Customerid_Missing_" . $getCoreData . ".log");
                    Mage::log($orderDetails, NULL, "Customerid_Missing_" . $getCoreData . ".log");
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
     *
     * $param int $grandTotal
     * @return boolean
     */
    public function isModuleActive($grandTotal = NULL)
    {
        if (!$grandTotal || $grandTotal == 0) {
            return false;
        }
        return true;
    }

    /**
     * Check whether the CallbackTypeCall can be used
     *
     * $param string $countryCode
     * @return boolean
     */
    public function isCallbackTypeAllowed($countryCode)
    {
        $allowedCountryCode = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('callbackAllowedCountry');
        if (in_array($countryCode, $allowedCountryCode) && !($this->checkIsAdmin())) {
            return true;
        }
        return false;
    }

    /**
     * Get shop default language
     *
     * @return string
     */
    public function getDefaultLanguage()
    {
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
    public function getCustomerId()
    {
        $customerNo = '';
        if ($this->checkIsAdmin()) {
            $quoteCustomerNo = $this->getAdminCheckoutSession()->getQuote()->getCustomerId();
            $customerNo = $quoteCustomerNo ? $quoteCustomerNo : 'guest';
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
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl($route, $params = array())
    {
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
     * @return mixed
     */
    public function getAdditionalData($info, $key = null)
    {
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
     * @param int $userGroupId
     * @return boolean
     */
    public function checkCustomerAccess($userGroupId = NULL)
    {
        $exludedGroupes = trim($userGroupId);
        if (strlen($exludedGroupes)) {
            $exludedGroupes = explode(',', $exludedGroupes);
            $custGrpId = $this->getCustomerSession()->getCustomerGroupId();     
            if ($this->checkIsAdmin()) {
				$custGrpId = $this->getAdminCheckoutSession()->getQuote()->getCustomerId();       
			}			
            return !in_array($custGrpId, $exludedGroupes);
        }
        return true;
    }

    /**
     * Function to Encode Novalnet PCI data
     *
     * @param string $data
     * @param string $key
     * @param string $type
     * @return boolean
     */
    public function getPciEncodedParam(&$fields, $key, $type = 'PHP')
    {
        if (!function_exists('base64_encode') || !function_exists('pack') || !function_exists('crc32')) {
            return false;
        }
        $toBeEncoded = ($type == 'PHP_PCI')
            ? Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('pciHashParams')
            : Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('novalnetHashParams');
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
    public function getDecodedParam($data, $key)
    {
        $data = trim($data);
        if (!$data) {
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
            Mage::logException('Error: ' . $e);
        }
    }

    /**
     * Check whether there is an Empty string
     *
     * @param string $str
     * @return boolean
     */
    public function isEmptyString($str)
    {
        $str = trim($str);
        return !isset($str[0]);
    }

    /**
     * Generate Hash value for PCI
     *
     * @param array $data
     * @param string $key
     * @param string $type
     * @return string
     */
    public function generateHash($data, $key, $type = 'PHP')
    {
        if (!function_exists('md5') || $this->isEmptyString($key)) {
            return false;
        }
        $hashFields = ($type == 'PHP_PCI')
            ? Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('pciHashParams')
            : Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('novalnetHashParams');
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
     * Hash value getter
     *
     * @param array $request
     * @param string $key
     * @param string $type
     * @return string
     */
    public function getHash($request, $key, $type)
    {
        if (empty($request)) {
            return'Error: no data';
        }
        if (!function_exists('md5')) {
            return'Error: func n/a';
        }

        if ($type == 'PHP_PCI') {
            $hash = md5($request['vendor_authcode'] . $request['product_id'] . $request['tariff_id'] . $request['amount'] . $request['test_mode'] . $request['uniqid'] . strrev($key));
        } else {
            $hash = md5($request['auth_code'] . $request['product'] . $request['tariff'] . $request['amount'] . $request['test_mode'] . $request['uniqid'] . strrev($key));
        }
        return $hash;
    }

    /**
     * Check Hash value
     *
     * @param array $request
     * @param string $key
     * @param string $type
     * @return boolean
     */
    public function checkHash($request, $key, $type)
    {
        if (!$request) return false;
        if ($request['hash2'] != $this->getHash($request, $key, $type)) {
            return false;
        }
        return true;
    }

    /**
     * Check Novalnet response params
     *
     * @param array $response
     * @param string $password
     * @param string $type
     * @return int
     */
    public function checkParams($response, $password, $type)
    {
        $status = $response['status'];
        if (!$response['hash2']) {
            $status = '94';
        }if (!$this->checkHash($response, $password, $type)) {
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
     * Retrieve Novalnet payment key
     *
     * @param string $code
     * @return int
     */
    public function getPaymentId($code)
    {
        $arrPaymentId = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('novalnetPaymentKey');
        return $arrPaymentId[$code];
    }

    /**
     * Retrieve Novalnet Payment Type
     *
     * @param string $code
     * @return string
     */
    public function getPaymentType($code)
    {
        $arrPaymentType = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('novalnetPaymentTypes');
        return $arrPaymentType[$code];
    }

    /**
     * Check orders count by customer id
     *
     * @param int $minOrderCount
     * @return boolean
     */
    public function checkOrdersCount($minOrderCount)
    {
        $customerId = $this->getCustomerId();
        // Load orders and check
        $orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('customer_id', $customerId);
        $ordersCount = $orders->count();
        if (trim($ordersCount) < $minOrderCount) {
            return true;
        }
        return false;
    }

    /**
     * set due date for invoice payment
     *
     * @param int $paymentDuration
     * @return int
     */
    public function setDueDate($paymentDuration)
    {
        $dueDate = '';
        if ($paymentDuration && $this->checkIsNumeric($paymentDuration)) {
            $dueDate = date('Y-m-d', strtotime('+' . (int) $paymentDuration . ' days'));
        } elseif ($paymentDuration == "0") {
            $dueDate = date('Y-m-d');
        }
        return $dueDate;
    }

    /**
     * Get Novalnet model class
     *
     * @param string $modelclass
     * @return Novalnet_Payment_Model_Payment_Method_Abstract
     */
    public function getModel($modelclass)
    {
        return Mage::getModel('novalnet_payment/payment_method_' . $modelclass);
    }

    /**
     * Get Novalnet transaction status model
     *
     * @return Novalnet_Payment_Model_Transactionstatus_Collection
     */
    public function getModelTransactionStatus()
    {
        return Mage::getModel('novalnet_payment/transactionstatus');
    }

    /**
     * Get Novalnet callback model
     *
     * @return Novalnet_Payment_Model_Callback_Collection
     */
    public function getModelCallback()
    {
        return Mage::getModel('novalnet_payment/callback');
    }

    /**
     * Get Novalnet Affiliate model
     *
     * @return Novalnet_Payment_Model_Affiliate_Collection
     */
    public function getModelAffiliate()
    {
        return Mage::getModel('novalnet_payment/affiliate');
    }

    /**
     * Get Novalnet Affiliate User model
     *
     * @return Novalnet_Payment_Model_Affiliateuser_Collection
     */
    public function getModelAffiliateuser()
    {
        return Mage::getModel('novalnet_payment/affiliateuser');
    }

    /**
     * Get Novalnet transaction overview model
     *
     * @return Novalnet_Payment_Model_Transactionoverview_Collection
     */
    public function getModelTransactionOverview()
    {
        return Mage::getModel('novalnet_payment/transactionoverview');
    }

    /**
     * Get Novalnet Separefill model
     *
     * @return Novalnet_Payment_Model_Separefill_Collection
     */
    public function getModelSepaRefill()
    {
        return Mage::getModel('novalnet_payment/separefill');
    }

    /**
     * Get Novalnet Amountchanged model
     *
     * @return Novalnet_Payment_Model_Amountchanged_Collection
     */
    public function getModelAmountchanged()
    {
        return Mage::getModel('novalnet_payment/amountchanged');
    }

    /**
     * Get Novalnet Recurring model
     *
     * @return Novalnet_Payment_Model_Recurring_Collection
     */
    public function getModelRecurring()
    {
        return Mage::getModel('novalnet_payment/recurring');
    }

    /**
     * Get Novalnet Factory model
     *
     * @return Novalnet_Payment_Model_Factory
     */
    public function getModelFactory()
    {
        return Mage::getModel('novalnet_payment/factory');
    }

    /**
     * Check the value is numeric
     *
     * @param mixed $value
     * @return boolean
     */
    public function checkIsNumeric($value)
    {
        return preg_match('/^\d+$/', $value) ? true : false;
    }

    /**
     * Check the value contains special characters
     *
     * @param mixed $value
     * @return boolean
     */
    public function checkIsValid($value)
    {
        return (!$value || preg_match('/[#%\^<>@$=*!]/', $value)) ? false : true;
    }

    /**
     * Check the email id is valid
     *
     * @param mixed $value
     * @return boolean
     */
    public function validateEmail($emailId)
    {
        $validatorEmail = new Zend_Validate_EmailAddress();
        return $validatorEmail->isValid($emailId) ? true : false;
    }

    /**
     * Replace strings from the value passed
     *
     * @param mixed $value
     * @return integer
     */
    public function makeValidNumber($value)
    {
        return preg_replace('/[^0-9]+/', '', $value);
    }

    /**
     * Get the formated amount in cents/euro
     *
     * @param float $amount
     * @param string $type
     * @return int
     */
    public function getFormatedAmount($amount, $type = 'CENT')
    {
        return ($type == 'RAW') ? $amount / 100 : round($amount, 2) * 100;
    }

    /**
     * Load Novalnet transaction status based on tid
     *
     * @param integer $txnId
     * @return object Novalnet_Payment_Model_Transactionstatus
     */
    public function loadTransactionStatus($txnId)
    {
        return $this->getModelTransactionStatus()->loadByAttribute('transaction_no', $txnId);
    }

    /**
     * Load Novalnet callback value based on order-id
     *
     * @param integer $orderId
     * @return object Novalnet_Payment_Model_Callback
     */
    public function loadCallbackValue($orderId)
    {
        return $this->getModelCallback()->loadByAttribute('order_id', $orderId);
    }

    /**
     * Get Novalnet payment configuration global path
     *
     * @return string
     */
    public function getNovalnetGlobalPath()
    {
        return 'novalnet_global/novalnet/';
    }

    /**
     * Get current site url
     *
     * @return string
     */
    public function getCurrentSiteUrl()
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
    }

    /**
     * Get Amount collection
     *
     * $param int $orderId
     * $param int $confirm
     * $param int $param
     * @return int
     */
    public function getAmountCollection($orderId, $confirm, $param)
    {
        $countofCollection = '';
        $modNovalAmountcollection = $this->getModelAmountchanged()->getCollection();
        $modNovalAmountcollection->addFieldToFilter('order_id', $orderId);
        $modNovalAmountcollection->addFieldToSelect('amount_changed');
        $modNovalAmountcollection->addFieldToSelect('amount_datetime');
        $countofCollectionvalue = count($modNovalAmountcollection);
        if ($confirm != 1 && $param != 1) {
            $countofCollection = count($modNovalAmountcollection);
        } else if ($confirm == 1 && $param == 1 && $countofCollectionvalue != 0) {
            foreach ($modNovalAmountcollection as $modNovalAmountcollectionValue) {
                $countofCollection .= $modNovalAmountcollectionValue->getAmountChanged();
                $countofCollection .= '<br>';
                $countofCollection .= $modNovalAmountcollectionValue->getAmountDatetime();
            }
        } else if ($confirm == 1 && $countofCollectionvalue != 0) {
            foreach ($modNovalAmountcollection as $modNovalAmountcollectionValue) {
                $countofCollection .= $modNovalAmountcollectionValue->getAmountChanged();
            }
        }
        return $countofCollection;
    }

    /**
     * Check Nominal item or not
     *
     * $param varien_object $orderItems
     * @return mixed
     */
    public function checkNominalItem($orderItems)
    {
        foreach ($orderItems as $orderItemsValue) {
            if ($orderItemsValue) {
                $nominalItem = $orderItemsValue->getIsNominal();
                break;
            }
        }
        return $nominalItem;
    }

    /**
     * Get the respective payport url
     *
     * @param string $reqType
     * @param string $paymentCode
     * @return string
     */
    public function getPayportUrl($reqType, $paymentCode = NULL)
    {
        if ($paymentCode && $reqType == 'redirect') {
            $redirectUrl = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayportUrl');
            $payportUrl = $redirectUrl[$paymentCode];
        } else {
            $urlType = array(
                'paygate' => Novalnet_Payment_Model_Config::PAYPORT_URL,
                'infoport' => Novalnet_Payment_Model_Config::INFO_REQUEST_URL
            );
            $payportUrl = $urlType[$reqType];
        }
        return $payportUrl;
    }

    /**
     * Get checkout session
     *
     * @return string
     */
    public function getCurrency()
    {
        $_order = new Mage_Sales_Model_Order();
        $orderId = Mage::app()->getRequest()->getParam('order_id');
        if (isset($orderId) && $orderId) {
            $order = $_order->load($orderId);
            $currency = $order->getOrderCurrency();
            $currencyCode = $currency->getCurrencyCode();
            return $currencyCode;
        }
    }
}
