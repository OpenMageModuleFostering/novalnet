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
class Novalnet_Payment_Helper_AssignData extends Novalnet_Payment_Helper_Data
{
    /**
     * Name library directory.
     */
    const NAME_DIR_JS = 'novalnet/';

    /**
     * Novalnet script files
     *
     * @return array
     */
    protected $_files = array(
        'novalnetJquery.js',
        'novalnetcc.js',
        'novalnetsepa.js'
    );

    /**
     * Return path file.
     *
     * @param $file
     * @return string
     */
    public function getJQueryPath($file)
    {
        return self::NAME_DIR_JS . $file;
    }

    /**
     * Return list files.
     *
     * @return array
     */
    public function getFiles()
    {
        return $this->_files;
    }

    /**
     * Assign Form Data in quote instance based on payment method
     *
     * $param string $paymentCode
     * $param varien_object $data
     * $param instance $infoInstance
     * @return Mage_Payment_Model_Abstract Object
     */
    public function assignNovalnetData($paymentCode, $data, $infoInstance)
    {
        if ($paymentCode) {
            if (!($data instanceof Varien_Object)) {
                $data = new Varien_Object($data);
            }
            $this->getCheckout()->setRefilldatavalues($data);
            switch ($paymentCode) {
                case Novalnet_Payment_Model_Config::NN_CC:
                    $infoInstance->setNnCallbackTelNovalnetCc($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetCc(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetCc($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetCc($data->getCallbackEmail());
                    if ($this->getModel($paymentCode)->getNovalnetConfig('callback')
                            != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }
                    $this->getCheckout()->setCcPanHash($this->novalnetCardDetails('novalnet_cc_hash'))
                            ->setCcUniqueId($this->novalnetCardDetails('novalnet_cc_unique_id'))
                            ->setNnCcCvc($data->getNnCcCid())
                            ->setNnPaymentCode($paymentCode);

                    break;
                case Novalnet_Payment_Model_Config::NN_SEPA:
                    $infoInstance->setNnCallbackTelNovalnetSepa($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetSepa(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetSepa($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetSepa($data->getCallbackEmail())
                            ->setSepaDuedate($this->getModel($paymentCode)->getNovalnetConfig('sepa_due_date'));

                    if ($this->getModel($paymentCode)->getNovalnetConfig('callback')
                            != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }

                    $this->getCheckout()->setSepaHash($this->novalnetCardDetails('result_sepa_hash'))
                            ->setSepaUniqueId($this->novalnetCardDetails('result_mandate_unique'))
                            ->setNnPaymentCode($paymentCode);
                    break;
                case Novalnet_Payment_Model_Config::NN_INVOICE:
                    $infoInstance->setNnCallbackTelNovalnetInvoice($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetInvoice(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetInvoice($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetInvoice($data->getCallbackEmail());
                    if ($this->getModel($paymentCode)->getNovalnetConfig('callback')
                            != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }
                    $this->getCheckout()->setNnPaymentCode($paymentCode);
                    break;
            }
        }
    }

    /**
     * validate Novalnet form data
     *
     * $param string $paymentCode
     * $param instance $infoInstance
     * throw Mage Exception
     */
    public function validateNovalnetData($paymentCode, $infoInstance)
    {
        switch ($paymentCode) {
            case Novalnet_Payment_Model_Config::NN_CC:
                $accessKey = $this->getModel($paymentCode)->loadAffAccDetail();
                $creditCardSecure = $this->getModel($paymentCode)->getNovalnetConfig('active_cc3d');
                $callbackVal = $this->getModel($paymentCode)->getNovalnetConfig('callback');

                if (!$this->checkIsAdmin() && $creditCardSecure && !$accessKey) {
                    Mage::throwException($this->__('Basic parameter not valid') . '!');
                } elseif (!$this->getCheckout()->getCcPanHash() || !$this->getCheckout()->getCcUniqueId()) {
                    Mage::throwException($this->__('Your credit card details are invalid'));
                } elseif (!$creditCardSecure && $this->checkCallbackAmount($paymentCode)
                        && $callbackVal == '1' && !$infoInstance->getNnCallbackTelNovalnetCc()
                        && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Please enter your telephone number') . '!');
                } elseif (!$creditCardSecure && $this->checkCallbackAmount($paymentCode)
                        && $callbackVal == '2' && !$infoInstance->getNnCallbackTelNovalnetCc()
                        && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Please enter your mobile number') . '!');
                } elseif (!$creditCardSecure && $this->checkCallbackAmount($paymentCode)
                        && $callbackVal == '3' && !$this->validateEmail($infoInstance->getNnCallbackEmailNovalnetCc())
                        && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Your E-mail address is invalid') . '!');
                }
                break;
            case Novalnet_Payment_Model_Config::NN_SEPA:
                $paymentInfo = $this->novalnetCardDetails('payment');
                $sepaHolder = $paymentInfo['account_holder'];
                $sepaDueDate = $infoInstance->getSepaDuedate();
                $callbackVal = $this->getModel($paymentCode)->getNovalnetConfig('callback');

                if (strlen($sepaDueDate) > 0 && ($sepaDueDate < 7 || !$this->checkIsNumeric($sepaDueDate))) {
                    Mage::throwException($this->__('SEPA Due date is not valid') . '!');
                } elseif (!$this->novalnetCardDetails('nnsepa_iban_confirmed')) {
                    Mage::throwException($this->__('Please accept the SEPA direct debit mandate'));
                } elseif (!$this->getCheckout()->getSepaHash() || !$this->getCheckout()->getSepaUniqueId()) {
                    Mage::throwException($this->__('Your account details are invalid'));
                } elseif (!$sepaHolder || preg_match('/[#%\^<>@$=*!]/', $sepaHolder)) {
                    Mage::throwException($this->__('Your account details are invalid') . '!');
                } elseif ($this->checkCallbackAmount($paymentCode)
                        && $callbackVal == '1' && !$infoInstance->getNnCallbackTelNovalnetSepa()
                        && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Please enter your telephone number') . '!');
                } elseif ($this->checkCallbackAmount($paymentCode)
                        && $callbackVal == '2' && !$infoInstance->getNnCallbackTelNovalnetSepa()
                        && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Please enter your mobile number') . '!');
                } elseif ($this->checkCallbackAmount($paymentCode)
                        && $callbackVal == '3' && !$this->validateEmail($infoInstance->getNnCallbackEmailNovalnetSepa())
                        && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Your E-mail address is invalid') . '!');
                }
                break;
            case Novalnet_Payment_Model_Config::NN_INVOICE:
            case Novalnet_Payment_Model_Config::NN_PREPAYMENT:
                $paymentRefOne = $this->getModel($paymentCode)->getNovalnetConfig('payment_ref_one');
                $paymentRefTwo = $this->getModel($paymentCode)->getNovalnetConfig('payment_ref_two');
                $paymentRefThree = $this->getModel($paymentCode)->getNovalnetConfig('payment_ref_three');

                if (!$paymentRefOne && !$paymentRefTwo && !$paymentRefThree) {
                    Mage::throwException('Please select atleast one payment reference.');
                }
                break;
            case Novalnet_Payment_Model_Config::NN_IDEAL:
            case Novalnet_Payment_Model_Config::NN_PAYPAL:
            case Novalnet_Payment_Model_Config::NN_SOFORT:
            case Novalnet_Payment_Model_Config::NN_EPS:
                $accessKey = $this->getModel($paymentCode)->loadAffAccDetail();

                if (!$accessKey) {
                    Mage::throwException($this->__('Basic parameter not valid') . '!');
                }
                break;
        }
    }

    /**
     * Assign Novalnet return params for redirect payments
     *
     * $param varien_object $request
     * $param string $paymentCode
     * @return Mage_Payment_Model_Abstract Object
     */
    public function assignNovalnetReturnData(Varien_Object $request, $paymentCode)
    {
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
        array_push($redirectPayment, Novalnet_Payment_Model_Config::NN_CC);
        $request->setUserVariable_0(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB))
                ->setReturnMethod(Novalnet_Payment_Model_Config::NOVALNET_RETURN_METHOD)
                ->setErrorReturnMethod(Novalnet_Payment_Model_Config::NOVALNET_RETURN_METHOD);
        if (in_array($paymentCode, $redirectPayment)) {
            $request->setReturnUrl($this->getUrl(Novalnet_Payment_Model_Config::GATEWAY_RETURN_URL))
                    ->setErrorReturnUrl($this->getUrl(Novalnet_Payment_Model_Config::GATEWAY_ERROR_RETURN_URL));
        }
    }

    /**
     * To get encode data
     *
     * @param varien_object $dataObj
     * @param string $key
     * @return mixed
     */
    public function importNovalnetEncodeData(Varien_Object $dataObj, $key)
    {
        $encoding = $this->getPciEncodedParam($dataObj, $key);
        if ($encoding != true) {
            $this->getCoresession()->addError(
                    $this->__('The methods for the handling of character sets are not available!'));
            $url = Mage::getModel('core/url')->getUrl("checkout/onepage/failure");
            Mage::app()->getResponse()->setRedirect($url);
            Mage::app()->getResponse()->sendResponse();
            exit;
        }
        $this->importNovalnetHashData($dataObj, $key);
        return $dataObj;
    }

    /**
     * To get Hash data
     *
     * @param varien_object $dataObj
     * @param string $key
     * @return mixed
     */
    public function importNovalnetHashData(Varien_Object $dataObj, $key)
    {
        $hash = $this->generateHash($dataObj, $key);
        if ($hash == false) {
            $this->getCoresession()->addError($this->__('The hash functions are not available!'));
            $url = Mage::getModel('core/url')->getUrl("checkout/onepage/failure");
            Mage::app()->getResponse()->setRedirect($url);
            Mage::app()->getResponse()->sendResponse();
            exit;
        }
        $dataObj->setHash($hash);
        return $dataObj;
    }

    /**
     * Do XML call request to server
     *
     * @param varien_object $requestData
     * @param string $requestUrl
     * @param varien_object $paymentObj
     * @return Mage_Payment_Model_Abstract Object
     */
    public function setRawCallRequest($requestData, $requestUrl, $paymentObj)
    {
        $httpClientConfig = array('maxredirects' => 0);

        if ($paymentObj->getNovalnetConfig('use_proxy',true)) {
            $proxyHost = $paymentObj->getNovalnetConfig('proxy_host',true);
            $proxyPort = $paymentObj->getNovalnetConfig('proxy_port',true);
            if ($proxyHost && $proxyPort) {
                $httpClientConfig['proxy'] = $proxyHost. ':' . $proxyPort;
                $httpClientConfig['httpproxytunnel'] = true;
                $httpClientConfig['proxytype'] = CURLPROXY_HTTP;
                $httpClientConfig['SSL_VERIFYHOST'] = false;
                $httpClientConfig['SSL_VERIFYPEER'] = false;
            }
        }

        $gatewayTimeout = (int) $paymentObj->getNovalnetConfig('gateway_timeout',true);
        if ($gatewayTimeout > 0) {
            $httpClientConfig['timeout'] = $gatewayTimeout;
        }

        $client = new Varien_Http_Client($requestUrl, $httpClientConfig);
        $client->setRawData($requestData)->setMethod(Varien_Http_Client::POST);
        $response = $client->request();
        if (!$response->isSuccessful()) {
            Mage::throwException($this->__('Gateway request error: %s', $response->getMessage()));
        }
        $result = new Varien_Object();
        parse_str($response->getBody(), $data);
        $result->addData($data);
        return $result;
    }

    /**
     * Retrieve Credit Card Details
     *
     * @param string $param
     * @return string
     */
    public function novalnetCardDetails($param)
    {
        return Mage::app()->getRequest()->getPost($param);
    }

    /**
     * Remove sensitive data form Novalnet log
     *
     * @param varien_object $request
     * @param string $paymentCode
     * @return mixed
     */
    public function doRemoveSensitiveData($request = NULL, $paymentCode)
    {
        if ($paymentCode) {
            switch ($paymentCode) {
                case Novalnet_Payment_Model_Config::NN_CC:
                    if ($this->getModel($paymentCode)->getNovalnetConfig('active_cc3d')
                            == 1) {
                        unset($request['cc_cvc2'], $request['pan_hash'], $request['unique_id']);
                    } else {
                        $request->unsCcCvc2()
                                ->unsPanHash()
                                ->unsUniqueId();
                    }
                    break;
                case Novalnet_Payment_Model_Config::NN_SEPA:
                    if ($request) {
                        $request->unsBankAccountHolder()
                                ->unsSepaHash()
                                ->unsSepaUniqueId();
                    }
                    break;
            }
        }
        return $request;
    }

    /**
     * Set Novalnet payment note for Invoice & Prepayment
     *
     * @return string
     */
    public function getNoteDescription()
    {
        return "<br /><b>" . $this->__(
                        'Please transfer the invoice amount with the following information to our payment provider Novalnet AG') . "</b><br />";
    }

    /**
     * Set Novalnet Due Date
     *
     * @param array $result
     * @param int $invoiceDuedate
     * @return mixed
     */
    public function getDueDate($result = NULL, $invoiceDuedate = NULL)
    {
        if ($result) {
            $dueDate = $result->getDueDate();
        } else if($invoiceDuedate) {
            $dueDate = $invoiceDuedate;
        }

        return ($dueDate) ? ($this->__('Due Date') . ' : <b>' . Mage::helper('core')->formatDate($dueDate) . "</b><br />")
                    : NULL;
    }

    /**
     * Get Novalnet Bank Account Details
     *
     * @param array $result
     * @return mixed
     */
    public function getNote($result)
    {
        $dueDate = $result->getDueDate();
        $note = NULL;
        $note .= $dueDate
                ? 'Due Date: <b>' . Mage::helper('core')->formatDate($dueDate) . '</b>|NN Account Holder: <b>NOVALNET AG</b>'
                : 'NN Account Holder: <b>NOVALNET AG</b>';
        $note .= '|IBAN: <b> ' . $result->getInvoiceIban() . '</b>';
        $note .= '|BIC: <b>' . $result->getInvoiceBic() . '</b>';
        $note .= '|NN_Bank: <b>' . $result->getInvoiceBankname() . ' ' . trim($result->getInvoiceBankplace()) . '</b>';
        return $note;
    }

    /**
     * Return bank details amount
     *
     * @param float $amount
     * @return string
     */
    public function getBankDetailsAmount($amount)
    {
        return 'NN_Amount: <b>' . Mage::helper('core')->currency($amount, true, false) . '</b>';
    }


    /**
     * Return bank details transaction id
     *
     * @param int $tid
     * @param array $data
     * @param string $paymentCode
     * @return string
     */
    public function getReferenceDetails($tid, $data, $paymentCode)
    {
        $paymentReference = array();
        $note = NULL;
        $productId = $data['product'] ? $data['product'] : '';
        $orderNo = $data['orderNo'] ? $data['orderNo'] : '';
        $paymentRefOne = $this->getModel($paymentCode)->getNovalnetConfig('payment_ref_one');
        $paymentRefTwo = $this->getModel($paymentCode)->getNovalnetConfig('payment_ref_two');
        $paymentRefThree = $this->getModel($paymentCode)->getNovalnetConfig('payment_ref_three');
        $paymentRefConfig = array($paymentRefOne, $paymentRefTwo, $paymentRefThree);

        foreach ($paymentRefConfig as $key => $value) {
            if ($value == 1) {
                $paymentReference[] .= $value;
            }
        }

        $refCount = count($paymentReference);
        $note .= ($refCount > 1) ? "NN_Reference_desc1:" : "NN_Reference_desc2:";

        $i = 0;
        if (!empty($paymentRefOne)) {
            $i = ($refCount == 1) ? '' : $i + 1;
            $note .= "|NN_Reference$i:<b>BNR-$productId-$orderNo</b>";
        }

        if (!empty($paymentRefTwo)) {
            $i = ($refCount == 1) ? '' : $i + 1;
            $note .= "|NN_Reference$i:<b>TID $tid</b>";
        }

        if (!empty($paymentRefThree)) {
            $i = ($refCount == 1) ? '' : $i + 1;
            $note .= "|NN_Reference$i:Order No&$orderNo";
        }
        return $note;
    }


    /**
     * Check whether callback option is enabled
     *
     * @param string $paymentCode
     * @return boolean
     */
    public function checkCallbackAmount($paymentCode)
    {
        $grandTotal = $this->getCheckoutSession()->getQuote()->getBaseGrandTotal();
        $grandTotal = $this->getFormatedAmount($grandTotal);
        $callBackMinimum = (int) $this->getModel($paymentCode)->getNovalnetConfig('callback_minimum_amount');

        return ($callBackMinimum ? $grandTotal >= $callBackMinimum : true);
    }

    /**
     * Get checkout session
     *
     * @return Mage_Sales_Model_Order
     */
    public function getCheckout()
    {
        if ($this->checkIsAdmin()) {
            return $this->getAdminCheckoutSession();
        } else {
            return $this->getCheckoutSession();
        }
    }
}
