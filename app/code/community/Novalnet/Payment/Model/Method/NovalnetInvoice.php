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
class Novalnet_Payment_Model_Method_NovalnetInvoice extends Novalnet_Payment_Model_Method_Abstract
{
    protected $_code = Novalnet_Payment_Model_Config::NN_INVOICE;
    protected $_canUseForMultishipping = Novalnet_Payment_Model_Config::NN_INVOICE_CAN_USE_MULTISHIPPING;
    protected $_formBlockType = Novalnet_Payment_Model_Config::NN_INVOICE_FORM_BLOCK;
    protected $_infoBlockType = Novalnet_Payment_Model_Config::NN_INVOICE_INFO_BLOCK;

    /**
     * Check whether payment method can be used
     *
     * @param  Mage_Sales_Model_Quote|null $quote
     * @return boolean
     */
    public function isAvailable($quote = null)
    {
        // Get Novalnet payment validation model
        $validateModel = $this->helper->getModel('Service_Validate_PaymentCheck');
        // Get Payment disable time (fraud prevention process)
        $paymentDisableTime = "getPaymentDisableTime" . ucfirst($this->_code);

        if (time() < $this->helper->getCheckoutSession()->$paymentDisableTime()) {
            return false;
        } elseif (!$validateModel->getPaymentGuaranteeStatus($quote, $this->_code)) {
            return false;
        } elseif (!empty($quote) && $quote->hasNominalItems() && $this->helper->checkIsAdmin()) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Assign data to info model instance
     *
     * @param  mixed $data
     * @return Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if ($data) {
            $methodSession = $this->helper->getMethodSession($this->_code); // Get payment method session
            $fraudPreventType = $this->getConfigData('callback'); // Get fraud prevention type

            if (!($data instanceof Varien_Object)) {
                $data = new Varien_Object($data);
            }
            // Assign customer DOB
            if ($methodSession->getPaymentGuaranteeFlag()) {
                $methodSession->setCustomerDob($data->getDob());
            }
            // Assign fraud prevention form values
            if ($fraudPreventType && $fraudPreventType != 3) {
                $methodSession->setCallbackTelNovalnetInvoice($data->getCallbackTel())
                    ->setCallbackPinNovalnetInvoice(trim($data->getCallbackPin()))
                    ->setCallbackNewPinNovalnetInvoice($data->getCallbackNewPin())
                    ->setCallbackPinFlag(true);
            } elseif ($fraudPreventType && $fraudPreventType == 3) {
                $methodSession->setCallbackEmailNovalnetInvoice($data->getCallbackEmail());
            }

            $this->helper->getCheckoutSession()->setNnPaymentCode($this->_code);
        }
        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @param  none
     * @return Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        parent::validate();
        $info = $this->getInfoInstance(); // Current payment instance

        if ($info instanceof Mage_Sales_Model_Quote_Payment) {
            $this->validateModel->validateFormInfo(); // Validate the form values
        }

        if ($this->preventionModel->getFraudPreventionStatus($info, $this->_code)) {
            $this->preventionModel->fraudPreventionProcess($info); // Fraud prevention process handling
        }

        return $this;
    }

    /**
     * Get Novalnet payment redirect URL
     *
     * @param  none
     * @return string $actionUrl
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_DIRECT_URL);
    }

}
