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
class Novalnet_Payment_Model_Service_Api_FraudPrevention extends Novalnet_Payment_Model_Service_Abstract
{

    /**
     * Initiate fraud prevention process
     *
     * @param  Varien_Object $info
     * @return none
     */
    public function fraudPreventionProcess($info)
    {
        $methodSession = $this->_helper->getMethodSession($this->code); // Get current payment method session
        $callbackStatus = $this->getFraudPreventionStatus($info); // Check fraud prevention availability
        $this->_verifyCallbackOrderNo($info, $methodSession, $callbackStatus); // Check whether increment id is valid
        $this->_verifyCallbackInfo($info, $methodSession); // Validate fraud prevention informations
        $this->_verifyCallbackPinInfo($methodSession, $callbackStatus); // Validate the fraud prevention data
        // Send fraud prevention PIN (New/Forget) request
        $this->_performCallbackProcess($info, $methodSession, $callbackStatus);
        if ($this->_isPlaceOrder($info)) {
            $this->validateCallbackProcess($methodSession); // Validate fraud prevention response process
        }
    }

    /**
     * Check fraud prevention availability
     *
     * @param  Varien_Object $info
     * @param  string|null   $code
     * @return boolean
     */
    public function getFraudPreventionStatus($info, $code = null)
    {
        $this->code = ($code !== null) ? $code : $this->code; // Payment method code
        $orderAmount = (string) $this->_helper->getFormatedAmount($this->_getAmount($info)); // Get order amount
        $callBackMinimum = (string) $this->getNovalnetConfig('callback_minimum_amount');
        $countryCode = strtoupper($this->_getInfoObject($info)->getBillingAddress()->getCountryId());
        $callbackPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('fraudCheckPayment');
        $methodSession = $this->_helper->getMethodSession($this->code);

        if ((!$this->getNovalnetConfig('callback') || !$this->isCallbackTypeAllowed($countryCode))
            || ($callBackMinimum && $orderAmount < $callBackMinimum)
            || (!in_array($this->code, $callbackPayment))
        ) {
            return false;
        } elseif ($this->code == Novalnet_Payment_Model_Config::NN_SEPA
            && $methodSession->hasSepaNewForm() && !$methodSession->getSepaNewForm()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Check whether increment id is valid
     *
     * @param  Varien_Object $info
     * @param  Varien_Object $methodSession
     * @param  boolean       $callbackStatus
     * @return none
     */
    protected function _verifyCallbackOrderNo($info, $methodSession, $callbackStatus)
    {
        $callbackOrderNo = "getCallbackOrderNo" . ucfirst($this->code);
        $callbackIncrementId = $methodSession->$callbackOrderNo();
        $incrementId = $this->_getIncrementId(); // Get order increment id

        // Check whether increment id is valid
        if (!$this->_isPlaceOrder($info) && $callbackStatus && $incrementId
            && $callbackIncrementId && ($incrementId != $callbackIncrementId)
        ) {
            $this->_helper->unsetMethodSession($this->code); // Unset the payment method session
        }
    }

    /**
     * Validate order amount is getting changed after callback initiation
     *
     * @param  Varien_Object $info
     * @param  Varien_Object $methodSession
     * @return throw Mage Exception|none
     */
    protected function _verifyCallbackInfo($info, $methodSession)
    {
        // Callback initiated transaction id
        $callbackTid = "hasCallbackTid" . ucfirst($this->code);

        if ($methodSession->$callbackTid()) {
            // Get order total amount
            $checkoutSession = $this->_helper->getCheckoutSession();
            $amount = $checkoutSession->getQuote()->hasNominalItems()
                ? $this->_helper->getFormatedAmount($checkoutSession->getNnRowAmount())
                : $this->_helper->getFormatedAmount($this->_getAmount($info));
            // Get payment method order amount if available
            $orderAmount = $methodSession->getOrderAmount();
            // Get payment method disable time if available
            $paymentDisableTime = "getPaymentDisableTime" . ucfirst($this->code);

            if ($checkoutSession->$paymentDisableTime()
                && (time() > $checkoutSession->$paymentDisableTime())
            ) {
                $this->_helper->unsetMethodSession($this->code); // Unset the payment method session
            } elseif ($orderAmount && $orderAmount != $amount) {
                $this->_helper->unsetMethodSession($this->code); // Unset the payment method session
                if (!$this->_isPlaceOrder($info)) {
                    $this->_helper->showException('The order amount has been changed');
                }
            }
        }
    }

    /**
     * Validate the fraud prevention data
     *
     * @param  Varien_Object $methodSession
     * @param  boolean       $callbackStatus
     * @return throw Mage Exception|none
     */
    protected function _verifyCallbackPinInfo($methodSession, $callbackStatus)
    {
        $paymentCode = ucfirst($this->code);
        $callbackTid = "getCallbackTid" . $paymentCode;
        $callbackPin = "getCallbackPin" . $paymentCode;

        if ($callbackStatus && $methodSession->getCallbackPinFlag()
            && $methodSession->$callbackTid()
        ) {
            $callbackPin = $methodSession->$callbackPin();
            $callbackNewPin = "getCallbackNewPin" . $paymentCode;

            if (!$methodSession->$callbackNewPin() && empty($callbackPin)) {
                $this->_helper->showException('Enter your PIN');
            } elseif (!$methodSession->$callbackNewPin() && !$this->checkIsValid($callbackPin)) {
                $this->_helper->showException('The PIN you entered is incorrect');
            }
        }
    }

    /**
     * Send fraud prevention PIN (New/Forget) request
     *
     * @param  Varien_Object $info
     * @param  Varien_Object $methodSession
     * @param  boolean       $callbackStatus
     * @return none
     */
    protected function _performCallbackProcess($info, $methodSession, $callbackStatus)
    {
        if ($callbackStatus && !$this->_isPlaceOrder($info)) {
            $paymentCode = ucfirst($this->code);
            $callbackTid = "getCallbackTid" . $paymentCode;

            if ($methodSession->$callbackTid() && $this->getNovalnetConfig('callback') != 3) {
                $callbackPin = "getCallbackPin" . $paymentCode;
                $callbackNewPin = "getCallbackNewPin" . $paymentCode;
                $setcallbackPin = "setCallbackPin" . $paymentCode;
                $methodSession->$callbackNewPin() ? $this->_regenerateCallbackPin($methodSession)
                : $methodSession->$setcallbackPin($methodSession->$callbackPin());
            } elseif (!$methodSession->$callbackTid()) {
                $this->_generateCallbackPin($info, $paymentCode, $methodSession);
            }
        }
    }

    /**
     * Make callback request and validate response
     *
     * @param  Varien_Object $info
     * @param  string        $paymentCode
     * @param  Varien_Object $methodSession
     * @return throw Mage Exception|none
     */
    protected function _generateCallbackPin($info, $paymentCode, $methodSession)
    {
        $callbackTid = "setCallbackTid" . $paymentCode;
        $callbackOrderNo = "setCallbackOrderNo" . $paymentCode;
        $nominalItem = $this->_helper->getCheckoutSession()->getQuote()->hasNominalItems();

        // Prepare payport request params
        $requestModel = $this->_helper->getModel('Service_Api_Request');
        $request = $requestModel->getPayportParams($info);
        $requestModel->setFraudModuleParams($request, $methodSession);
        if ($nominalItem) {
            $requestModel->getCallbackProfileParams($request);
        }
        $methodSession->setPaymentReqData($request);
        // Receive payport response params
        $paymentModel = $this->_helper->getPaymentModel($this->code);
        $response = $paymentModel->postRequest($request);
        $methodSession->setPaymentResData($response);
        // Novalnet successful transaction status
        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $methodSession->$callbackTid(trim($response->getTid()))
                ->$callbackOrderNo(trim($response->getOrderNo()))
                ->setNnTestMode(trim($response->getTestMode()))
                ->setOrderAmount($request->getAmount())
                ->setCallbackSuccessState(true);
            if ($this->getNovalnetConfig('callback') == 1) {
                $text = $this->_helper->showException('You will shortly receive a transaction PIN');
            } elseif ($this->getNovalnetConfig('callback') == 2) {
                $text = $this->_helper->showException('You will shortly receive an SMS');
            }
        } else {  // Novalnet unsuccessful transaction status
            $text = $this->_helper->htmlEscape($response->getStatusDesc());
        }
        $this->_helper->showException($text, false);
    }

    /**
     * Regenerate new PIN for fraud prevention process
     *
     * @param  Varien_Object $methodSession
     * @return throw Mage Exception
     */
    protected function _regenerateCallbackPin($methodSession)
    {
        // Callback request data re-assign
        $request = $methodSession->getPaymentReqData();
        $response = $methodSession->getPaymentResData();

        $responseModel = $this->_helper->getModel('Service_Api_Response'); // Get Novalnet api response model
        $response = $responseModel->pinStatusCall($request, $response->getTid(), Novalnet_Payment_Model_Config::TRANSMIT_PIN_AGAIN);
        // Novalnet successful transaction status
        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $text = $this->_helper->showException('You will shortly receive an SMS');
        } else {  // Novalnet unsuccessful transaction status
            $text = $this->_helper->htmlEscape($response->getStatusMessage()); // Status_message
            if ($response->getStatus() == Novalnet_Payment_Model_Config::MAXPIN_DISABLE_CODE) {
                $this->_helper->unsetMethodSession($this->code);
            }
        }

        $this->_helper->showException($text, false);
    }

    /**
     * Validate fraud prevention response process
     *
     * @param  Varien_Object $methodSession
     * @return none
     */
    public function validateCallbackProcess($methodSession)
    {
        if ($methodSession->getCallbackSuccessState()) { // Fraud prevention process success state
            $paymentCode = ucfirst($this->code);
            $callbackPin = "getCallbackPin" . $paymentCode;
            $type = Novalnet_Payment_Model_Config::PIN_STATUS;
            $extraOption = '<pin>' . $methodSession->$callbackPin() . '</pin>';

            // Verify entered PIN via API request
            $responseModel = $this->_helper->getModel('Service_Api_Response');
            $tid = $methodSession->getPaymentResData()->getTid();
            $result = $responseModel->pinStatusCall($methodSession->getPaymentReqData(), $tid, $type, $extraOption);
            $callbackTid = "getCallbackTid" . $paymentCode;
            $result->setTid($methodSession->$callbackTid());
            $result->setTestMode($methodSession->getNnTestMode());
            $methodSession->getPaymentResData()->setTidStatus($result->getTidStatus());

            // Verify the response from Novalnet
            if ($result->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $methodSession->setCallbackSuccessState(false);
            } else {  // Novalnet unsuccessful transaction status
                if ($result->getStatus() == Novalnet_Payment_Model_Config::METHOD_DISABLE_CODE) {
                    $paymentDisableTime = "setPaymentDisableTime" . $paymentCode;
                    $this->_helper->getCheckoutSession()->$paymentDisableTime(time() + (30 * 60));
                }
                $statusMessage = $this->_helper->htmlEscape($result->getStatusMessage());
                $this->_helper->showException($statusMessage, false);
            }
        }
    }

}
