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
class Novalnet_Payment_Model_Config
{

    /**     * ****************************************** */
    /*      NOVALNET GLOBAL PARAMS STARTS         */
    /*     * ******************************************* */

    const CALLBACK_PIN_LENGTH = 4;   //PIN Length
    const RESPONSE_CODE_APPROVED = 100; //On Payment Success
    const PAYMENT_VOID_STATUS = 103; //On Payment void
    const CVV_MIN_LENGTH = 3;   //MIN CVV No
    const PHONE_PAYMENT_AMOUNT_MIN = 99; //Min amt in cents
    const PHONE_PAYMENT_AMOUNT_MAX = 1000; //Max amt in cents
    const NOVALNET_RETURN_METHOD = 'POST';
    const NOVALNET_REDIRECT_BLOCK = 'novalnet_payment/payment_method_novalnetRedirect';
    const GATEWAY_REDIRECT_URL = 'novalnet_payment/gateway/redirect';
    const GATEWAY_DIRECT_URL = 'novalnet_payment/gateway/payment';
    const GATEWAY_RETURN_URL = 'novalnet_payment/gateway/return';
    const GATEWAY_ERROR_RETURN_URL = 'novalnet_payment/gateway/error';
    const PAYPORT_URL = '://payport.novalnet.de/paygate.jsp';
    const INFO_REQUEST_URL = '://payport.novalnet.de/nn_infoport.xml';
    const CC_URL = '://payport.novalnet.de/direct_form.jsp';
    const SEPA_URL = '://payport.novalnet.de/direct_form_sepa.jsp';
    const INVOICE_PAYMENT_METHOD = 'Invoice';
    const PREPAYMENT_PAYMENT_METHOD = 'Prepayment';
    const TELEPHONE_PAYMENT_METHOD = 'Telephone';
    const NOVALTEL_STATUS = 'NOVALTEL_STATUS';
    const TRANS_STATUS = 'TRANSACTION_STATUS';
    const TRANSMIT_PIN_AGAIN = 'TRANSMIT_PIN_AGAIN';
    const REPLY_EMAIL_STATUS = 'REPLY_EMAIL_STATUS';
    const PIN_STATUS = 'PIN_STATUS';
    const METHOD_DISABLE_CODE = '0529006';
    const PAYPAL_PENDING_CODE = 90;
    const POST_NORMAL = 'normal';
    const POST_CALLBACK = 'callback';

    static protected $_instance;
    protected $_novalnetPaymentKey = array('novalnetCc' => 6, 'novalnetInvoice' => 27,
        'novalnetPrepayment' => 27, 'novalnetPhonepayment' => 18, 'novalnetPaypal' => 34,
        'novalnetSofortueberweisung' => 33, 'novalnetIdeal' => 49, 'novalnetSepa' => 37);
    protected $_redirectPayportUrl = array('novalnetPaypal' => '://payport.novalnet.de/paypal_payport',
        'novalnetSofortueberweisung' => '://payport.novalnet.de/online_transfer_payport',
        'novalnetIdeal' => '://payport.novalnet.de/online_transfer_payport',
        'novalnetCc' => '://payport.novalnet.de/global_pci_payport');
    protected $_novalnetPaymentMethods = array('novalnetCc' => 'Novalnet Credit Card', 'novalnetInvoice' => 'Novalnet Invoice',
        'novalnetPrepayment' => 'Novalnet Prepayment', 'novalnetPhonepayment' => 'Novalnet Telephone Payment', 'novalnetPaypal' => 'Novalnet PayPal', 'novalnetSofortueberweisung' => 'Novalnet Instant Bank Transfer', 'novalnetIdeal' => 'Novalnet iDEAL', 'novalnetSepa' => 'Novalnet Direct Debit SEPA');
    protected $_callbackAllowed = array('AT', 'DE', 'CH');
    protected $_paymentOnholdStaus = array('91', '98', '99');
    protected $_redirectPayments = array('novalnetPaypal', 'novalnetSofortueberweisung',
        'novalnetIdeal');
    protected $_novalnetEncodeParams = array('auth_code', 'product', 'tariff', 'test_mode',
        'uniqid', 'amount');
    protected $_novalnetHashParams = array('auth_code', 'product', 'tariff', 'amount',
        'test_mode', 'uniqid');
    protected $_fraudCheckPayment = array('novalnetInvoice', 'novalnetSepa');

    /*     * ******************************************* */
    /*          NOVALNET CC PARAMS           */
    /*     * ******************************************* */

    const NN_CC = 'novalnetCc';
    const NN_CC_CAN_CAPTURE = true;
    const NN_CC_CAN_USE_INTERNAL = true;
    const NN_CC_CAN_USE_MULTISHIPPING = false;
    const NN_CC_FORM_BLOCK = 'novalnet_payment/payment_method_form_Cc';
    const NN_CC_INFO_BLOCK = 'novalnet_payment/payment_method_info_Cc';

    /*     * ******************************************* */
    /*          NOVALNET SEPA PARAMS             */
    /*     * ******************************************* */
    const NN_SEPA = 'novalnetSepa';
    const NN_SEPA_CAN_CAPTURE = true;
    const NN_SEPA_CAN_USE_INTERNAL = true;
    const NN_SEPA_CAN_USE_MULTISHIPPING = false;
    const NN_SEPA_FORM_BLOCK = 'novalnet_payment/payment_method_form_Sepa';
    const NN_SEPA_INFO_BLOCK = 'novalnet_payment/payment_method_info_Sepa';

    /*     * ******************************************* */
    /*          NOVALNET INVOICE PARAMS      */
    /*     * ******************************************* */
    const NN_INVOICE = 'novalnetInvoice';
    const NN_INVOICE_CAN_CAPTURE = true;
    const NN_INVOICE_CAN_USE_MULTISHIPPING = true;
    const NN_INVOICE_FORM_BLOCK = 'novalnet_payment/payment_method_form_Invoice';
    const NN_INVOICE_INFO_BLOCK = 'novalnet_payment/payment_method_info_Invoice';

    /*     * ******************************************* */
    /*          NOVALNET PREPAYMENT PARAMS   */
    /*     * ******************************************* */
    const NN_PREPAYMENT = 'novalnetPrepayment';
    const NN_PREPAYMENT_CAN_CAPTURE = true;
    const NN_PREPAYMENT_CAN_USE_MULTISHIPPING = true;
    const NN_PREPAYMENT_FORM_BLOCK = 'novalnet_payment/payment_method_form_Prepayment';
    const NN_PREPAYMENT_INFO_BLOCK = 'novalnet_payment/payment_method_info_Prepayment';

    /*     * ******************************************* */
    /*          NOVALNET IDEAL PARAMS        */
    /*     * ******************************************* */
    const NN_IDEAL = 'novalnetIdeal';
    const NN_IDEAL_CAN_CAPTURE = true;
    const NN_IDEAL_CAN_USE_INTERNAL = false;
    const NN_IDEAL_CAN_REFUND = false;
    const NN_IDEAL_CAN_USE_MULTISHIPPING = false;
    const NN_IDEAL_FORM_BLOCK = 'novalnet_payment/payment_method_form_Ideal';
    const NN_IDEAL_INFO_BLOCK = 'novalnet_payment/payment_method_info_Ideal';

    /*     * ******************************************* */
    /*          NOVALNET PAYPAL PARAMS       */
    /*     * ******************************************* */
    const NN_PAYPAL = 'novalnetPaypal';
    const NN_PAYPAL_CAN_CAPTURE = true;
    const NN_PAYPAL_CAN_USE_INTERNAL = false;
    const NN_PAYPAL_CAN_REFUND = false;
    const NN_PAYPAL_CAN_USE_MULTISHIPPING = false;
    const NN_PAYPAL_FORM_BLOCK = 'novalnet_payment/payment_method_form_Paypal';
    const NN_PAYPAL_INFO_BLOCK = 'novalnet_payment/payment_method_info_Paypal';

    /*     * ****************************************** */
    /*      NOVALNET SOFORT PARAMS                  */
    /*     * ****************************************** */
    const NN_SOFORT = 'novalnetSofortueberweisung';
    const NN_SOFORT_CAN_CAPTURE = true;
    const NN_SOFORT_CAN_USE_INTERNAL = false;
    const NN_SOFORT_CAN_REFUND = false;
    const NN_SOFORT_CAN_USE_MULTISHIPPING = false;
    const NN_SOFORT_FORM_BLOCK = 'novalnet_payment/payment_method_form_Sofortueberweisung';
    const NN_SOFORT_INFO_BLOCK = 'novalnet_payment/payment_method_info_Sofortueberweisung';

    /*     * ****************************************** */
    /*      NOVALNET TELEPHONE PARAMS               */
    /*     * ****************************************** */
    const NN_TELEPHONE = 'novalnetPhonepayment';
    const NN_TELEPHONE_CAN_CAPTURE = true;
    const NN_TELEPHONE_CAN_USE_INTERNAL = false;
    const NN_TELEPHONE_CAN_REFUND = false;
    const NN_TELEPHONE_CAN_USE_MULTISHIPPING = true;
    const NN_TELEPHONE_FORM_BLOCK = 'novalnet_payment/payment_method_form_Phonepayment';
    const NN_TELEPHONE_INFO_BLOCK = 'novalnet_payment/payment_method_info_Phonepayment';

    /*     * ****************************************** */
    /*      NOVALNET ABSTARCT FUNCTIONS             */
    /*     * ****************************************** */

    static public function getInstance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    public function getNovalnetVariable($key)
    {
        return $this->{'_' . $key};
    }

}
