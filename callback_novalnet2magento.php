<?php
/**
 * Novalnet Callback Script for Magento
 *
 * NOTICE
 *
 * This script is used for real time capturing of parameters passed
 * from Novalnet AG after Payment processing of customers.
 *
 * This script is only free to the use for Merchants of Novalnet AG
 *
 * If you have found this script useful a small recommendation as well
 * as a comment on merchant form would be greatly appreciated.
 *
 * Please contact sales@novalnet.de for enquiry or info
 *
 * ABSTRACT:
 * This script is called from Novalnet, as soon as a payment is finished for
 * payment methods, e.g. Prepayment, Invoice.
 *
 * This script is adapted for those cases where the money for Prepayment /
 * Invoice has been transferred to Novalnet.
 *
 * An e-mail will be sent if an error occurs.
 *
 * If you also want to handle other payment methods you have to change the logic
 * accordingly.
 *
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @notice     1. This script must be placed in basic Magento folder
 *                to avoid rewrite rules (mod_rewrite)
 *             2. You have to adapt the value of all the variables
 *                commented with 'adapt ...'
 *             3. Set $test/$debug to false for live system
 */
require_once 'app/Mage.php';
$storeId = Mage_Core_Model_App::ADMIN_STORE_ID;
Mage::app()->setCurrentStore($storeId);
Mage::app('admin');
umask(0);
Mage::getModel('novalnet_payment/callbackscript')->Callback();
