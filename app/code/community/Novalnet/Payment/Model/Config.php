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
class Novalnet_Payment_Model_Config
{

    /**********************************************/
    /*         NOVALNET GLOBAL PARAMS            */
    /*********************************************/

    static protected $_instance;
    protected $_paymentKey = array('novalnetCc' => 6, 'novalnetSepa' => 37, 'novalnetInvoice' => 27,
        'novalnetPrepayment' => 27, 'novalnetPaypal' => 34, 'novalnetBanktransfer' => 33,
        'novalnetIdeal' => 49, 'novalnetEps' => 50, 'novalnetGiropay' => 69);
    protected $_paymentMethods = array('novalnetCc' => 'Novalnet Credit Card',
        'novalnetSepa' => 'Novalnet Direct Debit SEPA', 'novalnetInvoice' => 'Novalnet Invoice',
        'novalnetPrepayment' => 'Novalnet Prepayment', 'novalnetPaypal' => 'Novalnet PayPal',
        'novalnetBanktransfer' => 'Novalnet Instant Bank Transfer', 'novalnetIdeal' => 'Novalnet iDEAL',
        'novalnetEps' => 'Novalnet eps', 'novalnetGiropay' => 'Novalnet giropay');
    protected $_paymentTypes = array('novalnetCc' => 'CREDITCARD', 'novalnetSepa' => 'DIRECT_DEBIT_SEPA',
        'novalnetInvoice' => 'INVOICE', 'novalnetPrepayment' => 'PREPAYMENT',
        'novalnetPaypal' => 'PAYPAL', 'novalnetBanktransfer' => 'ONLINE_TRANSFER',
        'novalnetIdeal' => 'IDEAL', 'novalnetEps' => 'EPS', 'novalnetGiropay' => 'GIROPAY');
    protected $_redirectPayportUrl = array('novalnetPaypal' => 'paypal_payport',
        'novalnetBanktransfer' => 'online_transfer_payport', 'novalnetIdeal' => 'online_transfer_payport',
        'novalnetEps' => 'giropay', 'novalnetGiropay' => 'giropay',
        'novalnetCc' => 'pci_payport');
    protected $_redirectPayments = array('novalnetPaypal', 'novalnetBanktransfer',
        'novalnetIdeal', 'novalnetEps', 'novalnetGiropay');
    protected $_recurringPayments = array('novalnetCc', 'novalnetSepa',
        'novalnetInvoice', 'novalnetPrepayment', 'novalnetPaypal');
    protected $_pciHashParams = array('vendor_authcode', 'product_id', 'tariff_id', 'amount', 'test_mode', 'uniqid');
    protected $_hashParams = array('auth_code', 'product', 'tariff', 'amount', 'test_mode', 'uniqid');
    protected $_fraudCheckPayment = array('novalnetInvoice', 'novalnetSepa');
    protected $_onHoldPayments = array('novalnetCc', 'novalnetSepa', 'novalnetInvoice');
    protected $_allowedCountry = array('AT', 'DE', 'CH');
    protected $_onHoldStaus = array('91', '98', '99');

    const RESPONSE_CODE_APPROVED = '100';
    const PAYPAL_PENDING_CODE = '90';
    const PAYMENT_VOID_STATUS = '103';
    const METHOD_DISABLE_CODE = '0529006';
    const MAXPIN_DISABLE_CODE = '0529008';
    const PIN_STATUS = 'PIN_STATUS';
    const TRANSMIT_PIN_AGAIN = 'TRANSMIT_PIN_AGAIN';
    const NOVALNET_RETURN_METHOD = 'POST';
    const NOVALNET_REDIRECT_BLOCK = 'novalnet_payment/method_redirect';
    const GATEWAY_REDIRECT_URL = 'novalnet_payment/gateway/redirect';
    const GATEWAY_DIRECT_URL = 'novalnet_payment/gateway/payment';
    const GATEWAY_RETURN_URL = 'novalnet_payment/gateway/return';
    const GATEWAY_ERROR_RETURN_URL = 'novalnet_payment/gateway/error';
    const CC_IFRAME_URL = 'novalnet_payment/cc/index';
    const PAYPORT_URL = 'paygate.jsp';
    const CC_PCI_PAYPORT_URL = 'https://payport.novalnet.de/pci_payport';
    const INFO_REQUEST_URL = 'nn_infoport.xml';
    const SUBS_PAUSE = 'SUBSCRIPTION_PAUSE';
    const PREPAYMENT_PAYMENT_TYPE = 'Prepayment';
    const INVOICE_PAYMENT_TYPE = 'Invoice';
    const INVOICE_PAYMENT_GUARANTEE_TYPE = 'GUARANTEED_INVOICE_START';
    const INVOICE_PAYMENT_GUARANTEE_KEY = '41';
    const SEPA_PAYMENT_GUARANTEE_TYPE = 'GUARANTEED_DIRECT_DEBIT_SEPA';
    const SEPA_PAYMENT_GUARANTEE_KEY = '40';

    /**********************************************/
    /*         NOVALNET CREDIT CARD PARAMS       */
    /*********************************************/

    const NN_CC = 'novalnetCc';
    const NN_CC_CAN_USE_INTERNAL = false;
    const NN_CC_CAN_USE_MULTISHIPPING = false;
    const NN_CC_FORM_BLOCK = 'novalnet_payment/method_form_Cc';
    const NN_CC_INFO_BLOCK = 'novalnet_payment/method_info_Cc';

    /**********************************************/
    /*      NOVALNET DIRECT DEBIT SEPA PARAMS    */
    /*********************************************/

    const NN_SEPA = 'novalnetSepa';
    const NN_SEPA_CAN_USE_MULTISHIPPING = false;
    const NN_SEPA_FORM_BLOCK = 'novalnet_payment/method_form_Sepa';
    const NN_SEPA_INFO_BLOCK = 'novalnet_payment/method_info_Sepa';

    /**********************************************/
    /*         NOVALNET INVOICE PARAMS           */
    /*********************************************/

    const NN_INVOICE = 'novalnetInvoice';
    const NN_INVOICE_CAN_USE_MULTISHIPPING = false;
    const NN_INVOICE_FORM_BLOCK = 'novalnet_payment/method_form_Invoice';
    const NN_INVOICE_INFO_BLOCK = 'novalnet_payment/method_info_Invoice';

    /**********************************************/
    /*         NOVALNET PREPAYMENT PARAMS        */
    /*********************************************/

    const NN_PREPAYMENT = 'novalnetPrepayment';
    const NN_PREPAYMENT_CAN_USE_MULTISHIPPING = false;
    const NN_PREPAYMENT_FORM_BLOCK = 'novalnet_payment/method_form_Prepayment';
    const NN_PREPAYMENT_INFO_BLOCK = 'novalnet_payment/method_info_Prepayment';

    /**********************************************/
    /*        NOVALNET PAYPAL PARAMS             */
    /*********************************************/

    const NN_PAYPAL = 'novalnetPaypal';
    const NN_PAYPAL_CAN_USE_INTERNAL = false;
    const NN_PAYPAL_CAN_USE_MULTISHIPPING = false;
    const NN_PAYPAL_FORM_BLOCK = 'novalnet_payment/method_form_Paypal';
    const NN_PAYPAL_INFO_BLOCK = 'novalnet_payment/method_info_Paypal';

    /**********************************************/
    /*   NOVALNET ONLINE BANK TRANSFER PARAMS    */
    /*********************************************/

    const NN_BANKTRANSFER = 'novalnetBanktransfer';
    const NN_BANKTRANSFER_CAN_USE_INTERNAL = false;
    const NN_BANKTRANSFER_CAN_USE_MULTISHIPPING = false;
    const NN_BANKTRANSFER_FORM_BLOCK = 'novalnet_payment/method_form_Banktransfer';
    const NN_BANKTRANSFER_INFO_BLOCK = 'novalnet_payment/method_info_Banktransfer';

    /**********************************************/
    /*        NOVALNET IDEAL PARAMS              */
    /*********************************************/

    const NN_IDEAL = 'novalnetIdeal';
    const NN_IDEAL_CAN_USE_INTERNAL = false;
    const NN_IDEAL_CAN_USE_MULTISHIPPING = false;
    const NN_IDEAL_FORM_BLOCK = 'novalnet_payment/method_form_Ideal';
    const NN_IDEAL_INFO_BLOCK = 'novalnet_payment/method_info_Ideal';

    /**********************************************/
    /*        NOVALNET EPS PARAMS                */
    /*********************************************/

    const NN_EPS = 'novalnetEps';
    const NN_EPS_CAN_USE_INTERNAL = false;
    const NN_EPS_CAN_USE_MULTISHIPPING = false;
    const NN_EPS_FORM_BLOCK = 'novalnet_payment/method_form_Eps';
    const NN_EPS_INFO_BLOCK = 'novalnet_payment/method_info_Eps';

    /**********************************************/
    /*        NOVALNET GIROPAY PARAMS            */
    /*********************************************/

    const NN_GIROPAY = 'novalnetGiropay';
    const NN_GIROPAY_CAN_USE_INTERNAL = false;
    const NN_GIROPAY_CAN_USE_MULTISHIPPING = false;
    const NN_GIROPAY_FORM_BLOCK = 'novalnet_payment/method_form_Giropay';
    const NN_GIROPAY_INFO_BLOCK = 'novalnet_payment/method_info_Giropay';

    /**********************************************/
    /*         NOVALNET ABSTRACT FUNCTIONS       */
    /*********************************************/

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
