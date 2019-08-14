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
class Novalnet_Payment_Model_Config
{
    /* ******************************************** */
     /*      NOVALNET GLOBAL PARAMS STARTS         */
    /* ******************************************* */
    const RESPONSE_CODE_APPROVED = 100; //On Payment Success
    const PAYMENT_VOID_STATUS = 103; //On Payment void
    const NOVALNET_RETURN_METHOD = 'POST';
    const NOVALNET_REDIRECT_BLOCK = 'novalnet_payment/payment_method_novalnetRedirect';
    const GATEWAY_REDIRECT_URL = 'novalnet_payment/gateway/redirect';
    const GATEWAY_DIRECT_URL = 'novalnet_payment/gateway/payment';
    const GATEWAY_RETURN_URL = 'novalnet_payment/gateway/return';
    const GATEWAY_ERROR_RETURN_URL = 'novalnet_payment/gateway/error';
    const CC_IFRAME_URL = 'novalnet_payment/cc/index';
    const CC_PCI_PAYPORT_URL = 'https://payport.novalnet.de/pci_payport';
    const PAYPORT_URL = 'https://payport.novalnet.de/paygate.jsp';
    const INFO_REQUEST_URL = 'https://payport.novalnet.de/nn_infoport.xml';
    const INVOICE_PAYMENT_METHOD = 'Invoice';
    const PREPAYMENT_PAYMENT_METHOD = 'Prepayment';
    const TRANS_STATUS = 'TRANSACTION_STATUS';
    const SUBS_PAUSE = 'SUBSCRIPTION_PAUSE';
    const TRANSMIT_PIN_AGAIN = 'TRANSMIT_PIN_AGAIN';
    const PIN_STATUS = 'PIN_STATUS';
    const METHOD_DISABLE_CODE = '0529006';
    const PAYPAL_PENDING_CODE = 90;
    const POST_NORMAL = 'normal';
    const POST_CALLBACK = 'callback';

    static protected $_instance;
    protected $_novalnetPaymentKey = array('novalnetCc' => 6, 'novalnetInvoice' => 27,
        'novalnetPrepayment' => 27, 'novalnetPaypal' => 34, 'novalnetBanktransfer' => 33,
        'novalnetIdeal' => 49, 'novalnetEps' => 50, 'novalnetSepa' => 37, 'novalnetGiropay' => 69);
    protected $_novalnetPaymentMethods = array('novalnetCc' => 'Novalnet Credit Card', 'novalnetInvoice' => 'Novalnet Invoice',
        'novalnetPrepayment' => 'Novalnet Prepayment',
        'novalnetPaypal' => 'Novalnet PayPal', 'novalnetBanktransfer' => 'Novalnet Instant Bank Transfer',
        'novalnetIdeal' => 'Novalnet iDEAL', 'novalnetEps' => 'Novalnet Eps', 'novalnetSepa' => 'Novalnet Direct Debit SEPA',
        'novalnetGiropay' => 'Novalnet Giropay');
    protected $_novalnetPaymentTypes = array('novalnetCc' => 'CREDITCARD', 'novalnetInvoice' => 'INVOICE',
        'novalnetPrepayment' => 'PREPAYMENT', 'novalnetPaypal' => 'PAYPAL', 'novalnetBanktransfer' => 'ONLINE_TRANSFER',
         'novalnetIdeal' => 'IDEAL', 'novalnetEps' => 'EPS', 'novalnetSepa' => 'DIRECT_DEBIT_SEPA', 'novalnetGiropay' => 'GIROPAY');
    protected $_redirectPayportUrl = array('novalnetPaypal' => 'https://payport.novalnet.de/paypal_payport',
        'novalnetBanktransfer' => 'https://payport.novalnet.de/online_transfer_payport',
        'novalnetIdeal' => 'https://payport.novalnet.de/online_transfer_payport',
        'novalnetEps' => 'https://payport.novalnet.de/giropay',
        'novalnetGiropay' => 'https://payport.novalnet.de/giropay');
    protected $_redirectPayments = array('novalnetPaypal', 'novalnetBanktransfer',
        'novalnetIdeal', 'novalnetEps', 'novalnetGiropay');
    protected $_subscriptionPayments = array('novalnetSepa',
        'novalnetPrepayment', 'novalnetInvoice');
    protected $_setonholdPayments = array('novalnetCc', 'novalnetSepa', 'novalnetInvoice');
    protected $_callbackAllowedCountry = array('AT', 'DE', 'CH');
    protected $_paymentOnholdStaus = array('91', '98', '99');
    protected $_pciHashParams = array('vendor_authcode', 'product_id', 'tariff_id', 'amount',
        'test_mode', 'uniqid');
    protected $_novalnetHashParams = array('auth_code', 'product', 'tariff', 'amount',
        'test_mode', 'uniqid');
    protected $_fraudCheckPayment = array('novalnetInvoice', 'novalnetSepa');

    /* ******************************************** */
     /*         NOVALNET CC PARAMS                 */
    /* ******************************************* */

    const NN_CC = 'novalnetCc';
    const NN_CC_CAN_CAPTURE = true;
    const NN_CC_CAN_USE_MULTISHIPPING = false;
    const NN_CC_CAN_USE_INTERNAL = false;
    const NN_CC_FORM_BLOCK = 'novalnet_payment/payment_method_form_Cc';
    const NN_CC_INFO_BLOCK = 'novalnet_payment/payment_method_info_Cc';

    /* ******************************************** */
     /*         NOVALNET SEPA PARAMS               */
    /* ******************************************* */
    const NN_SEPA = 'novalnetSepa';
    const NN_SEPA_CAN_CAPTURE = true;
    const NN_SEPA_CAN_USE_INTERNAL = true;
    const NN_SEPA_CAN_USE_MULTISHIPPING = false;
    const NN_SEPA_FORM_BLOCK = 'novalnet_payment/payment_method_form_Sepa';
    const NN_SEPA_INFO_BLOCK = 'novalnet_payment/payment_method_info_Sepa';


    /* ******************************************** */
     /*         NOVALNET INVOICE PARAMS            */
    /* ******************************************* */
    const NN_INVOICE = 'novalnetInvoice';
    const NN_INVOICE_CAN_CAPTURE = true;
    const NN_INVOICE_CAN_USE_MULTISHIPPING = false;
    const NN_INVOICE_FORM_BLOCK = 'novalnet_payment/payment_method_form_Invoice';
    const NN_INVOICE_INFO_BLOCK = 'novalnet_payment/payment_method_info_Invoice';

    /* ******************************************** */
     /*         NOVALNET PREPAYMENT PARAMS         */
    /* ******************************************* */
    const NN_PREPAYMENT = 'novalnetPrepayment';
    const NN_PREPAYMENT_CAN_CAPTURE = true;
    const NN_PREPAYMENT_CAN_USE_MULTISHIPPING = false;
    const NN_PREPAYMENT_FORM_BLOCK = 'novalnet_payment/payment_method_form_Prepayment';
    const NN_PREPAYMENT_INFO_BLOCK = 'novalnet_payment/payment_method_info_Prepayment';

    /* ******************************************** */
     /*         NOVALNET IDEAL PARAMS              */
    /* ******************************************* */
    const NN_IDEAL = 'novalnetIdeal';
    const NN_IDEAL_CAN_CAPTURE = true;
    const NN_IDEAL_CAN_USE_INTERNAL = false;
    const NN_IDEAL_CAN_USE_MULTISHIPPING = false;
    const NN_IDEAL_FORM_BLOCK = 'novalnet_payment/payment_method_form_Ideal';
    const NN_IDEAL_INFO_BLOCK = 'novalnet_payment/payment_method_info_Ideal';
    /* ******************************************** */
     /*         NOVALNET EPS PARAMS                */
    /* ******************************************* */
    const NN_EPS = 'novalnetEps';
    const NN_EPS_CAN_CAPTURE = true;
    const NN_EPS_CAN_USE_INTERNAL = false;
    const NN_EPS_CAN_USE_MULTISHIPPING = false;
    const NN_EPS_FORM_BLOCK = 'novalnet_payment/payment_method_form_Eps';
    const NN_EPS_INFO_BLOCK = 'novalnet_payment/payment_method_info_Eps';

    /* ******************************************** */
     /*         NOVALNET GIROPAY PARAMS                */
    /* ******************************************* */
    const NN_GIROPAY = 'novalnetGiropay';
    const NN_GIROPAY_CAN_CAPTURE = true;
    const NN_GIROPAY_CAN_USE_INTERNAL = false;
    const NN_GIROPAY_CAN_USE_MULTISHIPPING = false;
    const NN_GIROPAY_FORM_BLOCK = 'novalnet_payment/payment_method_form_Giropay';
    const NN_GIROPAY_INFO_BLOCK = 'novalnet_payment/payment_method_info_Giropay';

    /* ******************************************** */
     /*         NOVALNET PAYPAL PARAMS             */
    /* ******************************************* */
    const NN_PAYPAL = 'novalnetPaypal';
    const NN_PAYPAL_CAN_CAPTURE = true;
    const NN_PAYPAL_CAN_USE_INTERNAL = false;
    const NN_PAYPAL_CAN_USE_MULTISHIPPING = false;
    const NN_PAYPAL_FORM_BLOCK = 'novalnet_payment/payment_method_form_Paypal';
    const NN_PAYPAL_INFO_BLOCK = 'novalnet_payment/payment_method_info_Paypal';

    /* ******************************************** */
     /*         NOVALNET SOFORT PARAMS             */
    /* ******************************************* */
    const NN_SOFORT = 'novalnetBanktransfer';
    const NN_SOFORT_CAN_CAPTURE = true;
    const NN_SOFORT_CAN_USE_INTERNAL = false;
    const NN_SOFORT_CAN_USE_MULTISHIPPING = false;
    const NN_SOFORT_FORM_BLOCK = 'novalnet_payment/payment_method_form_Banktransfer';
    const NN_SOFORT_INFO_BLOCK = 'novalnet_payment/payment_method_info_Banktransfer';

    /* ******************************************** */
     /*         NOVALNET ABSTRACT FUNCTIONS        */
    /* ******************************************* */

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
