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
class Novalnet_Payment_Helper_AssignData extends Novalnet_Payment_Helper_Data {

    /**
     * Assign Form Data in quote instance based on payment method
     *
     * @return Mage_Payment_Model_Abstract Object
     */
    public function assignNovalnetData($paymentCode, $data, $infoInstance) {
        if ($paymentCode) {
            if (!($data instanceof Varien_Object)) {
                $data = new Varien_Object($data);
            }
            switch ($paymentCode) {
                case Novalnet_Payment_Model_Config::NN_ELVDE:
                    $infoInstance->setNnAccountHolder(trim($data->getAccountHolder()))
                            ->setNnAccountNumber(trim($data->getAccountNumber()))
                            ->setNnBankSortingCode(trim($data->getBankSortingCode()))
                            ->setNnAcdc($data->getAcdc())
                            ->setNnElvCountry($data->getElvCountry())
                            ->setNnCallbackTelNovalnetElvgerman($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetElvgerman(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetElvgerman($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetElvgerman($data->getCallbackEmail());
                    if ($this->getModel($paymentCode)->_getConfigData('acdc_check')) {
                        $infoInstance->setAcdcValidationFlag(true);
                    }
                    if ($this->getModel($paymentCode)->_getConfigData('callback') != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }
                    break;
                case Novalnet_Payment_Model_Config::NN_ELVAT:
                    $infoInstance->setNnAccountHolder(trim($data->getAccountHolderAt()))
                            ->setNnAccountNumber(trim($data->getAccountNumberAt()))
                            ->setNnBankSortingCode(trim($data->getBankSortingCodeAt()))
                            ->setNnCallbackTelNovalnetElvaustria($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetElvaustria(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetElvaustria($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetElvaustria($data->getCallbackEmail());
                    if ($this->getModel($paymentCode)->_getConfigData('callback') != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }
                    break;
                case Novalnet_Payment_Model_Config::NN_CC:
                    $infoInstance->setPanHash($this->novalnetCardDetails('novalnet_cc_pan_hash'))
                            ->setUniqueId($this->novalnetCardDetails('novalnet_cc_unique_id'))
                            ->setNnCallbackTelNovalnetCc($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetCc(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetCc($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetCc($data->getCallbackEmail());
                    if ($this->getModel($paymentCode)->_getConfigData('callback') != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }
                    break;
                case Novalnet_Payment_Model_Config::NN_CC3D:
					$this->_getCheckoutSession()->setNnCcNumber(Mage::helper('core')->encrypt($data->getNnCcNumber()))
												->setNnCcCvc(Mage::helper('core')->encrypt($data->getNnCcCid()))
												->setNnCcOwner(Mage::helper('core')->encrypt($data->getNnCcOwner()))
												->setNnCcExpMonth(Mage::helper('core')->encrypt($data->getNnCcExpMonth()))
												->setNnCcExpYear(Mage::helper('core')->encrypt($data->getNnCcExpYear()));
                    break;
                case Novalnet_Payment_Model_Config::NN_INVOICE:
                    $infoInstance->setNnCallbackTelNovalnetInvoice($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetInvoice(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetInvoice($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetInvoice($data->getCallbackEmail());
                    if ($this->getModel($paymentCode)->_getConfigData('callback') != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }
                    break;
            }
        }
    }

    /**
     * validate novalnet form data
     *
     * throw Mage Exception
     */
    public function validateNovalnetData($paymentCode, $infoInstance) {
        switch ($paymentCode) {
            case Novalnet_Payment_Model_Config::NN_ELVDE:
            case Novalnet_Payment_Model_Config::NN_ELVAT:
                $nnAccountHolder = $infoInstance->getNnAccountHolder();
                $nnAccountNumber = preg_replace('/[\-\s]+/', '', $infoInstance->getNnAccountNumber());
                $nnBankSortingCode = preg_replace('/[\-\s]+/', '', $infoInstance->getNnBankSortingCode());

                if (!$this->checkIsValid($nnAccountHolder)
                        || !$this->checkIsNumeric($nnAccountNumber) || !$this->checkIsNumeric($nnBankSortingCode)
                        || strlen($nnAccountNumber) < Novalnet_Payment_Model_Config::ACCNO_MIN_LENGTH
                        || strlen($nnBankSortingCode) < Novalnet_Payment_Model_Config::BANK_SORTCODE_LENGTH) {
                    Mage::throwException($this->__('Please enter valid account details') . '!');
                }

                if ($paymentCode == Novalnet_Payment_Model_Config::NN_ELVDE
                        && $infoInstance->getAcdcValidationFlag() && !$infoInstance->getNnAcdc()) {
                    Mage::throwException($this->__('Please enable ACDC Check') . '!');
                }
                break;
            case Novalnet_Payment_Model_Config::NN_CC:
				$ccType = $this->novalnetCardDetails('novalnet_cc_type');
				$ccOwner = $this->novalnetCardDetails('novalnet_cc_owner');
				$expYear = $this->novalnetCardDetails('novalnet_cc_exp_year');
				$expMonth = $this->novalnetCardDetails('novalnet_cc_exp_month');
				$ccid = $this->novalnetCardDetails('novalnet_cc_cid');
				$verifcationRegEx = $this->_getVerificationRegExp();
				$regExp = isset($verifcationRegEx[$ccType]) ? $verifcationRegEx[$ccType] : '';
                if (!$infoInstance->getPanHash() || !$infoInstance->getUniqueId()) {
                    Mage::throwException($this->__('Authentication Failed'));
                } elseif (!$this->checkIsValid($ccOwner) || !$this->_validateExpDate($expYear, $expMonth)
							|| !$this->checkIsNumeric($ccid)) {
                    Mage::throwException($this->__('Please enter valid credit card details') . '!');
                } elseif($this->getModel($paymentCode)->_getConfigData('callback') == '1' && !$infoInstance->getNnCallbackTelNovalnetCc() && !$this->checkIsAdmin()){
					Mage::throwException($this->__('Please enter the Telephone / Mobilenumber') . '!');
				} elseif($this->getModel($paymentCode)->_getConfigData('callback') == '3' && !$this->validateEmail($infoInstance->getNnCallbackEmailNovalnetCc()) && !$this->checkIsAdmin()){
					Mage::throwException($this->__('Please enter the E-Mail Address') . '!');
				}
                break;
            case Novalnet_Payment_Model_Config::NN_CC3D:
				$ccOwner = Mage::helper('core')->decrypt($this->_getCheckoutSession()->getNnCcOwner());
				$expYear = Mage::helper('core')->decrypt($this->_getCheckoutSession()->getNnCcExpYear());
				$expMonth = Mage::helper('core')->decrypt($this->_getCheckoutSession()->getNnCcExpMonth());

                if (!$this->checkIsValid($ccOwner) || !$this->_validateExpDate($expYear, $expMonth)) {
                    Mage::throwException($this->__('Please enter valid credit card details') . '!');
                }
                break;
            case Novalnet_Payment_Model_Config::NN_IDEAL:
            case Novalnet_Payment_Model_Config::NN_PAYPAL:
            case Novalnet_Payment_Model_Config::NN_SOFORT:
                if (!$this->getModel($paymentCode)->_getConfigData('password', true)) {
                    Mage::throwException($this->__('Basic Parameter not valid') . '!');
                }

                if ($paymentCode == Novalnet_Payment_Model_Config::NN_PAYPAL &&
                        (!$this->getModel($paymentCode)->_getConfigData('api_sign', true)
                        || !$this->getModel($paymentCode)->_getConfigData('api_user', true)
                        || !$this->getModel($paymentCode)->_getConfigData('api_password', true))) {
                    Mage::throwException($this->__('Basic Parameter not valid') . '!');
                }

                break;
        }
    }

    /**
     * Assign novalnet return params for redirect payments
     *
     * @return Mage_Payment_Model_Abstract Object
     */
    public function assignNovalnetReturnData(Varien_Object $request, $paymentCode) {
        $redirectPayment = array(
            Novalnet_Payment_Model_Config::NN_SOFORT,
            Novalnet_Payment_Model_Config::NN_PAYPAL,
            Novalnet_Payment_Model_Config::NN_IDEAL,
            Novalnet_Payment_Model_Config::NN_CC3D
        );
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
     * @param Varien_Object $dataObj
     * @param String $key
     * @return String
     */
    public function importNovalnetEncodeData(Varien_Object $dataObj, $key) {
        $encoding = $this->getPciEncodedParam($dataObj, $key);
        if ($encoding != true) {
            Mage::getSingleton('core/session')->addError('Die Methoden f&uuml;r die Verarbeitung von Zeichens&auml;tzen sind nicht verf&uuml;gbar!');
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
     * @param Varien_Object $dataObj
     * @param String $key
     * @return String
     */
    public function importNovalnetHashData(Varien_Object $dataObj, $key) {
        $hash = $this->generateHash($dataObj, $key);
        if ($hash == false) {
            Mage::getSingleton('core/session')->addError('Die Hashfunktionen sind nicht verf&uuml;gbar!');
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
     * @param Varien_Object $request_data
     * @param String $request_url
     * @return Mage_Payment_Model_Abstract Object
     */
    public function setRawCallRequest($request_data, $request_url) {
        $httpClientConfig = array('maxredirects' => 0);
        $client = new Varien_Http_Client($request_url, $httpClientConfig);
        $client->setRawData($request_data)->setMethod(Varien_Http_Client::POST);
        $response = $client->request();
        if (!$response->isSuccessful()) {
            Mage::throwException($this->_getHelper()->__('Gateway request error: %s', $response->getMessage()));
        }
        $result = new Varien_Object();
        $result->addData($this->deformatNvp('&', $response->getBody()));
        return $result;
    }

    /**
     * Retrieve Credit Card Details
     *
     * @param string $param
     * @return string
     */
    public function novalnetCardDetails($param) {
        return Mage::app()->getRequest()->getPost($param);
    }

    /**
     * remove sensitive data form novalnet log
     *
     * @param Varien_Object $request
     * @param integer $paymentCode
     * @return Mage_Payment_Model_Abstract Object
     */
    public function doRemoveSensitiveData($request, $paymentCode) {
        if ($paymentCode) {
            switch ($paymentCode) {
                case Novalnet_Payment_Model_Config::NN_ELVDE:
                case Novalnet_Payment_Model_Config::NN_ELVAT:
                    $request->unsBankAccountHolder()
                            ->unsBankAccount()
                            ->unsBankCode();
                    break;
                case Novalnet_Payment_Model_Config::NN_CC3D:
                    unset($request['cc_holder'], $request['cc_no'], $request['cc_exp_month'], $request['cc_exp_year'], $request['cc_cvc2']);
                    break;
                case Novalnet_Payment_Model_Config::NN_CC:
                    $request->unsCcHolder()
                            ->unsCcNo()
                            ->unsCcExpMonth()
                            ->unsCcExpYear()
                            ->unsCcCvc2()
                            ->unsCcType();
                    break;
            }
        }
        return $request;
    }

    /**
     * validate credit card expiry date and month
     *
     * @param integer $expYear
     * @param integer $expMonth
     * @return bool
     */
    protected function _validateExpDate($expYear, $expMonth) {
        $date = Mage::app()->getLocale()->date();
        if (!$expYear || !$expMonth || ($date->compareYear($expYear) == 1)
                || ($date->compareYear($expYear) == 0 && ($date->compareMonth($expMonth) == 1))
        ) {
            return false;
        }
        return true;
    }

    /**
     * Get credit card type regular expression
     *
     * @return array
     */
	protected function _getVerificationRegExp(){
	  $verificationExpList = array(
				'VI' => '/^[0-9]{3}$/', // Visa
				'MC' => '/^[0-9]{3}$/',       // Master Card
				'AE' => '/^[0-9]{4}$/',        // American Express
				'DI' => '/^[0-9]{3}$/',          // Discovery
				'SS' => '/^[0-9]{3,4}$/',
				'SM' => '/^[0-9]{3,4}$/', // Switch or Maestro
				'SO' => '/^[0-9]{3,4}$/', // Solo
				'OT' => '/^[0-9]{3,4}$/',
				'JCB' => '/^[0-9]{3,4}$/' //JCB
			);
			return $verificationExpList;
	 }

    /**
     * Get Novalnet Bank Account Details
     *
     * @param array $result
     * @return mixed
     */
    public function getNote($result, $paymentDuration) {
        $dueDate = $this->setDueDate($paymentDuration);
        $note = NULL;
        $note .= "<br /><b>" . $this->__('Please transfer the invoice amount with the following information to our payment provider Novalnet AG') . "</b><br />";
        $note .= ($dueDate) ? ($this->__('Due Date') . ' : <b>' . $dueDate . "</b><br />") : NULL;
        $note .= $this->__('Account Holder2') . " : <b>NOVALNET AG</b><br />";
        $note .= $this->__('Account Number') . " : <b>" . $result->getInvoiceAccount() . "</b><br />";
        $note .= $this->__('Bank Sorting Code') . " : <b>" . $result->getInvoiceBankcode() . "</b><br />";
        $note .= $this->__('NN_Bank') . " : <b>" . $result->getInvoiceBankname() . "  " . trim($result->getInvoiceBankplace()) . "</b><br />";
        return $note;
    }

    /**
     * Return bank details amount
     *
     * @param float $amount
     * @return string
     */
    public function getBankDetailsAmount($amount) {
        return $this->__('NN_Amount') . " : <b>" . Mage::helper('core')->currency($amount, true, false) . "</b><br />";
    }

    /**
     * Return bank details transaction id
     *
     * @param integer $tid
     * @return string
     */
    public function getBankDetailsTID($tid) {
        return $this->__('NN_Reference') . " : <b>TID " . $tid . "</b><br /><br />";
    }

    /**
     * Return foreign transfer details
     *
     * @param object $result
     * @return string
     */
    public function getBankDetailsTransfer($result) {
        $note = NULL;
        $note .= "<b>" . $this->__('Only for foreign transfers') . ":</b><br />";
        $note .= "IBAN : <b> " . $result->getInvoiceIban() . " </b><br />";
        $note .= "SWIFT/BIC : <b>" . $result->getInvoiceBic() . " </b><br />";
        return $note;
    }

}
