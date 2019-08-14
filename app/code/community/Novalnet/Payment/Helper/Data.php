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
class Novalnet_Payment_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get current store id
     *
     * @param  none
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
     * Get current admin scope id
     *
     * @return int
     */
    public function getScopeId()
    {
        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) {
            $scopeId = Mage::getModel('core/store')->load($code)->getId();
        } elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) {
            $websiteId = Mage::getModel('core/website')->load($code)->getId();
            $scopeId = Mage::app()->getWebsite($websiteId)->getDefaultStore()->getId();
        } else {
            $scopeId = 0;
        }
        return $scopeId;
    }

    /**
     * Get customer id from current session
     *
     * @param  none
     * @return int|string
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
     * Get checkout session
     *
     * @param  none
     * @return Mage_Sales_Model_Order
     */
    public function getCheckout()
    {
        if ($this->checkIsAdmin()) {
            return $this->getAdminCheckoutSession(); // Get admin checkout session
        } else {
            return $this->getCheckoutSession(); // Get frontend checkout session
        }
    }

    /**
     * Get Magento core session
     *
     * @param  none
     * @return Mage_Core_Model_Session
     */
    public function getCoreSession()
    {
        return Mage::getSingleton('core/session');
    }

    /**
     * Get frontend checkout session
     *
     * @param  none
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get admin checkout session
     *
     * @param  none
     * @return Mage_adminhtml_Model_Session_quote
     */
    public function getAdminCheckoutSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Get customer session
     *
     * @param  none
     * @return Mage_customer_Model_Session
     */
    public function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Check whether logged in as admin
     *
     * @param  none
     * @return boolean
     */
    public function checkIsAdmin()
    {
        return (Mage::app()->getStore()->isAdmin()) ? true : false;
    }

    /**
     * Get shop default language
     *
     * @param  none
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
     * Get shop base url
     *
     * @param  none
     * @return string
     */
    public function getBaseUrl()
    {
        $protocol = Mage::app()->getStore()->isCurrentlySecure() ? 'https' : 'http'; // Get protocol
        $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB); // Get base URL
        $secureBaseUrl = Mage::getStoreConfig(Mage_Core_Model_Url::XML_PATH_SECURE_URL);
        return ($protocol == 'https' && $secureBaseUrl)
                ? str_replace('index.php/', '', $secureBaseUrl)
                : str_replace('index.php/', '', $baseUrl);
    }

    /**
     * Get Remote IP address
     *
     * @param  none
     * @return int
     */
    public function getRealIpAddr()
    {
        $ipAddr = Mage::helper('core/http')->getRemoteAddr();
        if (filter_var($ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            || $ipAddr == '::1') {//IPv6 Issue
            return '127.0.0.1';
        }
        return $ipAddr;
    }

    /**
     * Get Server IP address
     *
     * @param  none
     * @return int
     */
    public function getServerAddr()
    {
        $serverAddress = Mage::helper('core/http')->getServerAddr();
        if (filter_var($serverAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            || $serverAddress == '::1') {//IPv6 Issue
            return '127.0.0.1';
        }
        return $serverAddress;
    }

    /**
     * Get Novalnet payment model
     *
     * @param  string $modelclass
     * @return Varien_Object
     */
    public function getPaymentModel($modelclass)
    {
        return Mage::getModel('novalnet_payment/method_' . $modelclass);
    }

    /**
     * Get Novalnet model
     *
     * @param  string $model
     * @return Varien_Object
     */
    public function getModel($model)
    {
        return Mage::getModel('novalnet_payment/' . $model);
    }

    /**
     * Get Novalnet URL based on language
     *
     * @param  none
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
     * Get payment logo URL
     *
     * @param  none
     * @return string $imageUrl
     */
    public function getPaymentLogoUrl()
    {
        $baseUrl = Mage::getBaseUrl('skin');
        $imageUrl = $baseUrl . "frontend/base/default/images/novalnet/";
        return $imageUrl;
    }

    /**
     * Get current Magento version
     *
     * @param  none
     * @return int
     */
    public function getMagentoVersion()
    {
        return Mage::getVersion();
    }

    /**
     * Get current Novalnet version
     *
     * @param  none
     * @return string
     */
    public function getNovalnetVersion()
    {
        $versionInfo = (string) Mage::getConfig()->getNode('modules/Novalnet_Payment/version');
        return "NN({$versionInfo})";
    }

    /**
     * Show expection
     *
     * @param  string  $text
     * @param  boolean $lang
     * @return Mage_Payment_Model_Info_Exception
     */
    public function showException($text, $lang = true)
    {
        if ($lang) {
            $text = $this->__($text);
        }
        // Exception log for reference
        Mage::log($text, null, 'nn_exception.log', true);
        // Show payment exception
        return Mage::throwException($text);
    }

    /**
     * Get Novalnet payment key
     *
     * @param  string $code
     * @return int
     */
    public function getPaymentId($code)
    {
        $getPaymentId = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('paymentKey');
        return $getPaymentId[$code];
    }

    /**
     * Get the formated amount in Cents/Euro
     *
     * @param  float  $amount
     * @param  string $type
     * @return int
     */
    public function getFormatedAmount($amount, $type = 'CENT')
    {
        return ($type == 'RAW') ? $amount / 100 : round($amount, 2) * 100;
    }

    /**
     * Get the respective payport url
     *
     * @param  string $reqType
     * @param  string $paymentCode
     * @return string
     */
    public function getPayportUrl($reqType, $paymentCode = null)
    {
        if ($paymentCode && $reqType == 'redirect') { // For redirect payment methods
            $redirectUrl = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayportUrl');
            $payportUrl = $redirectUrl[$paymentCode];
        } else {  // For direct payment methods
            $urlType = array(
                'paygate' => Novalnet_Payment_Model_Config::PAYPORT_URL,
                'infoport' => Novalnet_Payment_Model_Config::INFO_REQUEST_URL
            );
            $payportUrl = $urlType[$reqType];
        }

        return 'https://payport.novalnet.de/' . $payportUrl;
    }

    /**
     * Get payment method session
     *
     * @param  string $paymentCode
     * @return mixed
     */
    public function getMethodSession($paymentCode = null)
    {
        $checkoutSession = $this->getCheckout();
        if ($paymentCode != null && !$checkoutSession->hasData($paymentCode)) {
            $checkoutSession->setData($paymentCode, new Varien_Object());
        }
        return $checkoutSession->getData($paymentCode);
    }

    /**
     * Unset payment method session
     *
     * @param  string $paymentCode
     * @return none
     */
    public function unsetMethodSession($paymentCode)
    {
        $checkoutSession = $this->getCheckout();
        $checkoutSession->unsetData($paymentCode);
    }

    /**
     * Extract data from additional data array
     *
     * @param  string $info
     * @param  string $key
     * @return mixed
     */
    public function getAdditionalData($info, $key = null)
    {
        $data = array();
        if ($info->getAdditionalData()) {
            $data = unserialize($info->getAdditionalData());
        }
        return (!empty($key) && isset($data[$key])) ? $data[$key] : '';
    }

    /**
     * Replace strings from the value passed
     *
     * @param  mixed $value
     * @return integer
     */
    public function makeValidNumber($value)
    {
        return preg_replace('/[^0-9]+/', '', $value);
    }

    /**
     * Set secure url checkout is secure for current store.
     *
     * @param  string $route
     * @param  array  $params
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
     * Get current store details
     *
     * @param  none
     * @return Mage_Core_Model_Store
     */
    public function getMagentoStore()
    {
        return Mage::app()->getStore();
    }

    /**
     * Get shop's date and time
     *
     * @param  none
     * @return mixed
     */
    public function getCurrentDateTime()
    {
        return Mage::getModel('core/date')->date('Y-m-d H:i:s');
    }

    /**
     * Check the order item is nominal or not
     *
     * @param  Varien_Object $order
     * @return boolean $nominalItem
     */
    public function checkNominalItem($order)
    {
        $orderItems = $order->getAllItems();

        foreach ($orderItems as $orderItemsValue) {
            if ($orderItemsValue) {
                $nominalItem = $orderItemsValue->getIsNominal();
                break;
            }
        }
        return $nominalItem;
    }

}
