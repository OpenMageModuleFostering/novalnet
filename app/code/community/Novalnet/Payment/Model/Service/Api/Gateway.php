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
class Novalnet_Payment_Model_Service_Api_Gateway extends Novalnet_Payment_Model_Service_Abstract
{
    /**
     * Send API request to Novalnet gateway
     *
     * @param  array  $requestData
     * @param  string $requestUrl
     * @param  string $type
     * @return mixed
     */
    public function payportRequestCall($requestData, $requestUrl, $type = "")
    {
        if (!$requestUrl) {
            $this->_helper->showException('Server Request URL is Empty');
            return;
        }

        $httpClientConfig = array('maxredirects' => 0);
        // Assign proxy whether if configured
        if ($this->getNovalnetConfig('use_proxy', true)) {
            $proxyHost = $this->getNovalnetConfig('proxy_host', true);
            $proxyPort = $this->getNovalnetConfig('proxy_port', true);
            if ($proxyHost && $proxyPort) {
                $httpClientConfig['proxy'] = $proxyHost . ':' . $proxyPort;
                $httpClientConfig['httpproxytunnel'] = true;
                $httpClientConfig['proxytype'] = CURLPROXY_HTTP;
                $httpClientConfig['SSL_VERIFYHOST'] = false;
                $httpClientConfig['SSL_VERIFYPEER'] = false;
            }
        }
        // Assign gateway timeout whether if configured
        $gatewayTimeout = $this->getNovalnetConfig('gateway_timeout', true);
        if ($gatewayTimeout > 0) {
            $httpClientConfig['timeout'] = $gatewayTimeout;
        }

        $client = new Varien_Http_Client($requestUrl, $httpClientConfig);
        // Assign post payport params
        if ($type == 'XML') {
            $client->setUri($requestUrl);
            $client->setRawData($requestData)->setMethod(Varien_Http_Client::POST);
        } else {
            $client->setParameterPost($requestData)->setMethod(Varien_Http_Client::POST);
        }
        // Get response from payment gatway
        try {
            $response = $client->request();
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'nn_exception.log', true);
        }
        // Show exception if payment unsuccessful
        if (!$response->isSuccessful()) {
            $helper = Mage::helper('novalnet_payment');
            $helper->showException($helper->__('Gateway request error: %s', $response->getMessage()), false);
        }
        // Convert xml response to array
        if ($type == 'XML') {
            $result = new Varien_Simplexml_Element($response->getRawBody());
            $response = new Varien_Object($result->asArray());
        }

        return $response;
    }

}
