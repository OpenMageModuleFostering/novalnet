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
     * List files for include.
     *
     * @var array
     */
    protected $_files = array(
        'jquery-1.10.2.min.js',
        'novalnetcc.js',
        'novalnetsepa.js',
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
            $methodSession = $this->_getCheckout()->getData($paymentCode);

            switch ($paymentCode) {
                case Novalnet_Payment_Model_Config::NN_CC:

                    $paymentInfo = $this->novalnetCardDetails('payment');
                    $methodSession->setCcPanHash($this->novalnetCardDetails('novalnet_cc_hash'))
                            ->setCcUniqueId($this->novalnetCardDetails('novalnet_cc_unique_id'))
                            ->setNnCcCvc($paymentInfo['nn_cc_cid'])
                            ->setNnCallbackTelNovalnetCc($data->getCallbackTel())
                            ->setNnCallbackEmailNovalnetCc($data->getCallbackEmail());
                    $this->_getCheckout()->setNnPaymentCode($paymentCode);
                    $infoInstance->setNnCallbackTelNovalnetCc($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetCc(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetCc($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetCc($data->getCallbackEmail());
                    if ($this->getModel($paymentCode)->_getConfigData('callback')
                            != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }
                    break;
                case Novalnet_Payment_Model_Config::NN_SEPA:

                    $paymentInfo = $this->novalnetCardDetails('payment');
                    $methodSession->setSepaHash($this->novalnetCardDetails('result_sepa_hash'))
                            ->setSepaUniqueId($this->novalnetCardDetails('result_mandate_unique'))
                            ->setSepaHolder($paymentInfo['account_holder'])
                            ->setIbanConfirmed($this->novalnetCardDetails('nnsepa_iban_confirmed'))
                            ->setSepaDuedate($this->getModel($paymentCode)->_getConfigData('sepa_due_date'))
                            ->setNnCallbackTelNovalnetSepa($data->getCallbackTel())
                            ->setNnCallbackEmailNovalnetSepa($data->getCallbackEmail());
                    $this->_getCheckout()->setNnPaymentCode($paymentCode);
                    $infoInstance->setNnCallbackTelNovalnetSepa($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetSepa(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetSepa($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetSepa($data->getCallbackEmail());
                    if ($this->getModel($paymentCode)->_getConfigData('callback')
                            != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }
                    break;
                case Novalnet_Payment_Model_Config::NN_INVOICE:
                    $infoInstance->setNnCallbackTelNovalnetInvoice($data->getCallbackTel())
                            ->setNnCallbackPinNovalnetInvoice(trim($data->getCallbackPin()))
                            ->setNnNewCallbackPinNovalnetInvoice($data->getNewCallbackPin())
                            ->setNnCallbackEmailNovalnetInvoice($data->getCallbackEmail());
                    if ($this->getModel($paymentCode)->_getConfigData('callback')
                            != 3) {
                        $infoInstance->setCallbackPinValidationFlag(true);
                    }
                    break;
            }
        }
    }

    /**
     * validate novalnet form data
     *
     * $param string $paymentCode
     * $param instance $infoInstance
     * throw Mage Exception
     */
    public function validateNovalnetData($paymentCode, $infoInstance)
    {
        switch ($paymentCode) {
            case Novalnet_Payment_Model_Config::NN_CC:
                $getCardValues = $this->_getCheckout()->getData($paymentCode);
                $creditCardSecure = $this->getModel($paymentCode)->_getConfigData('active_cc3d');

                if ($creditCardSecure && !$this->getModel($paymentCode)->_getConfigData('password', true)) {
                    Mage::throwException($this->__('Basic parameter not valid') . '!');
                } elseif (!$getCardValues->getCcPanHash() || !$getCardValues->getCcUniqueId()) {
                    Mage::throwException($this->__('Please enter valid credit card details') . '!');
                } elseif (!$this->checkIsNumeric($getCardValues->getNnCcCvc())) {
                    Mage::throwException($this->__('Please enter valid credit card details') . '!');
                } elseif (!$creditCardSecure && $this->checkCallbackAmount($paymentCode)
                        && $this->getModel($paymentCode)->_getConfigData('callback') == '1'
                        && !$infoInstance->getNnCallbackTelNovalnetCc() && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Please enter the Telephone / Mobilenumber') . '!');
                } elseif (!$creditCardSecure && $this->checkCallbackAmount($paymentCode)
                        && $this->getModel($paymentCode)->_getConfigData('callback') == '3'
                        && !$this->validateEmail($infoInstance->getNnCallbackEmailNovalnetCc())
                        && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Please enter the E-Mail Address') . '!');
                }
                break;
            case Novalnet_Payment_Model_Config::NN_SEPA:
                $methodSession = $this->_getCheckout()->getData($paymentCode);
                $sepaDueDate = $methodSession->getSepaDuedate();
                $sepaHolder = trim($methodSession->getSepaHolder());

                if (strlen($sepaDueDate) > 0
                        && ($sepaDueDate < 7 || !$this->checkIsNumeric($sepaDueDate))) {
                    Mage::throwException($this->__('SEPA Due date is not valid') . '!');
                } elseif (!$methodSession->getIbanConfirmed()) {
                    Mage::throwException($this->__('Please confirm IBAN & BIC'));
                } elseif (!$methodSession->getSepaHash() || !$methodSession->getSepaUniqueId()) {
                    Mage::throwException($this->__('Please enter valid account details'));
                } elseif (!$sepaHolder || preg_match('/[#%\^<>@$=*!]/', $sepaHolder)) {
                    Mage::throwException($this->__('Please enter valid account details') . '!');
                } elseif ($this->checkCallbackAmount($paymentCode)
                        && $this->getModel($paymentCode)->_getConfigData('callback') == '1'
                        && !$infoInstance->getNnCallbackTelNovalnetSepa() && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Please enter the Telephone / Mobilenumber') . '!');
                } elseif ($this->checkCallbackAmount($paymentCode)
                        && $this->getModel($paymentCode)->_getConfigData('callback') == '3'
                        && !$this->validateEmail($infoInstance->getNnCallbackEmailNovalnetSepa())
                        && !$this->checkIsAdmin()) {
                    Mage::throwException($this->__('Please enter the E-Mail Address') . '!');
                }
                break;
            case Novalnet_Payment_Model_Config::NN_IDEAL:
            case Novalnet_Payment_Model_Config::NN_PAYPAL:
            case Novalnet_Payment_Model_Config::NN_SOFORT:
                if (!$this->getModel($paymentCode)->_getConfigData('password', true)) {
                    Mage::throwException($this->__('Basic parameter not valid') . '!');
                }

                if ($paymentCode == Novalnet_Payment_Model_Config::NN_PAYPAL
                        && (!$this->getModel($paymentCode)->_getConfigData('api_sign', true)
                        || !$this->getModel($paymentCode)->_getConfigData('api_user', true)
                        || !$this->getModel($paymentCode)->_getConfigData('api_password', true))) {
                    Mage::throwException($this->__('Basic parameter not valid') . '!');
                }

                break;
        }
    }

    /**
     * Assign novalnet return params for redirect payments
     *
     * $param varien_object $request
     * $param string $paymentCode
     * @return Mage_Payment_Model_Abstract Object
     */
    public function assignNovalnetReturnData(Varien_Object $request, $paymentCode)
    {
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
        $request->setUserVariable_0(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB))
                ->setReturnMethod(Novalnet_Payment_Model_Config::NOVALNET_RETURN_METHOD)
                ->setErrorReturnMethod(Novalnet_Payment_Model_Config::NOVALNET_RETURN_METHOD);
        if (in_array($paymentCode, $redirectPayment) || $paymentCode == Novalnet_Payment_Model_Config::NN_CC) {
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
            Mage::getSingleton('core/session')->addError($this->__('The methods for the handling of character sets are not available!'));
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
            Mage::getSingleton('core/session')->addError($this->__('The hash functions are not available!'));
            $url = Mage::getModel('core/url')->getUrl("checkout/onepage/failure");
            Mage::app()->getResponse()->setRedirect($url);
            Mage::app()->getResponse()->sendResponse();
            exit;
        }
        $dataObj->setHash($hash);
        return $dataObj;
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
     * remove sensitive data form novalnet log
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
                    if ($this->getModel($paymentCode)->_getConfigData('active_cc3d')
                            == 1) {
                        unset($request['cc_holder'], $request['cc_exp_month'], $request['cc_exp_year'], $request['cc_cvc2'], $request['cc_type'], $request['cc_no'], $request['pan_hash']);
                    } else {
                        $request->unsCcHolder()
                                ->unsCcNo()
                                ->unsCcExpMonth()
                                ->unsCcExpYear()
                                ->unsCcCvc2()
                                ->unsCcType()
                                ->unsPanHash();
                    }
                    break;
                case Novalnet_Payment_Model_Config::NN_SEPA:
                    $request->unsBankAccountHolder()
                            ->unsBankAccount()
                            ->unsBankCode()
                            ->unsBic()
                            ->unsIban()
                            ->unsSepaHash();
                    break;
            }
        }
        return $request;
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
        if ($dueDate) {
            $note .= 'Due Date: <b>' . Mage::helper('core')->formatDate($dueDate) . '</b>|NN Account Holder: <b>NOVALNET AG</b>';
        } else {
            $note .= 'NN Account Holder: <b>NOVALNET AG</b>';
        }
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
     * @return string
     */
    public function getBankDetailsTID($tid, $data)
    {
        $productId = $data['product'] ? $data['product'] : '';
        $orderNo = $data['orderNo'] ? $data['orderNo'] : '';

        $note = NULL;
        $note .= "NN_Reference1:<b>BNR-$productId-$orderNo</b>";
        $note .= "|NN_Reference2:<b>TID $tid</b>";
        $note .= "|NN_Reference3:Order No&$orderNo";

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
        $grandTotal = $this->_getCheckoutSession()->getQuote()->getBaseGrandTotal();
        $grandTotal = $this->getFormatedAmount($grandTotal);
        $callBackMinimum = (int) $this->getModel($paymentCode)->_getConfigData('callback_minimum_amount');

        return ($callBackMinimum ? $grandTotal >= $callBackMinimum : true);
    }

}
