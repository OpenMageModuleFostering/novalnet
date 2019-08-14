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
/**
* 
 * novalnet tables  
*/
$orderTraces = $this->getTable('novalnet_payment/transaction_traces');
$transactionStatus = $this->getTable('novalnet_payment/transaction_status');
$callback = $this->getTable('novalnet_payment/callback');
$recurring = $this->getTable('novalnet_payment/recurring');
$affiliateInfo = $this->getTable('novalnet_payment/affiliate_info');
$affiliateUserInfo = $this->getTable('novalnet_payment/affiliate_user');

/**
* 
 * magento tables 
*/
$tableOrderPayment = $this->getTable('sales/order_payment');
$tableConfigData = $this->getTable('core/config_data');
$magentoVersion = Mage::getVersion();

$installer = $this;

$installer->startSetup();

// -----------------------------------------------------------------
// -- Create Table novalnet_payment_order_log
// -----------------------------------------------------------------
$installer->run(
    "
        CREATE TABLE IF NOT EXISTS `{$orderTraces}` (
            `nn_log_id`         int(11) UNSIGNED NOT NULL auto_increment,
            `request_data`      TEXT NOT NULL DEFAULT '',
            `response_data`     TEXT NOT NULL DEFAULT '',
            `order_id`          VARCHAR(50) NOT NULL DEFAULT '',
            `customer_id`           VARCHAR(10) NOT NULL DEFAULT '',
            `status`                VARCHAR(20) NOT NULL DEFAULT '',
            `failed_reason`     TEXT NOT NULL DEFAULT '',
            `store_id`          int(11) UNSIGNED NOT NULL,
            `shop_url`          VARCHAR(255) NOT NULL DEFAULT '',
            `transaction_id`        VARCHAR(50) NOT NULL,
            `additional_data`       TEXT NOT NULL DEFAULT '',
            `created_date`      datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (`nn_log_id`),
        INDEX `NOVALNET_ORDER_LOG` (`order_id` ASC, `transaction_id` ASC)
        );
"
);

// -----------------------------------------------------------------
// -- Create Table novalnet_payment_transaction_status
// -----------------------------------------------------------------
$installer->run(
    "
        CREATE TABLE IF NOT EXISTS `{$transactionStatus}` (
            `nn_txn_id`         int(11) UNSIGNED NOT NULL auto_increment,
            `transaction_no`        VARCHAR(50) NOT NULL,
            `order_id`          VARCHAR(50) NOT NULL DEFAULT '',
            `transaction_status`    VARCHAR(20) NOT NULL DEFAULT 0,
            `nc_no`             VARCHAR(11) NOT NULL,
            `customer_id`           VARCHAR(10) NOT NULL DEFAULT '',
            `payment_name`      VARCHAR(50) NOT NULL DEFAULT '',
            `amount`                decimal(12,4) NOT NULL,
            `remote_ip`         VARCHAR(20) NOT NULL,
            `store_id`          int(11) UNSIGNED NOT NULL,
            `shop_url`          VARCHAR(255) NOT NULL DEFAULT '',
            `additional_data`       TEXT NOT NULL DEFAULT '',
            `novalnet_acc_details`       TEXT NULL DEFAULT '',
            `reference_transaction`       SMALLINT NOT NULL DEFAULT 0,
            `created_date`      datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (`nn_txn_id`),
        INDEX `NOVALNET_TRANSACTION_STATUS` (`order_id` ASC, `transaction_no` ASC)
        );
"
);

// -----------------------------------------------------------------
// -- Create Table novalnet_payment_callback
// -----------------------------------------------------------------
$installer->run(
    "
        CREATE TABLE IF NOT EXISTS `{$callback}` (
            `id` int(11) UNSIGNED NOT NULL auto_increment,
            `order_id` VARCHAR(50) NOT NULL DEFAULT '',
            `callback_amount` int(11) UNSIGNED NOT NULL,
            `reference_tid` VARCHAR(50) NOT NULL,
            `callback_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            `callback_tid` VARCHAR(50) NOT NULL,
            `callback_log` TEXT NOT NULL DEFAULT '',
        PRIMARY KEY  (`id`),
        INDEX `NOVALNET_CALLBACK` (`order_id` ASC)
        );
"
);

// -----------------------------------------------------------------
// -- Create Table novalnet_payment_recurring
// -----------------------------------------------------------------
$installer->run(
    "
        CREATE TABLE IF NOT EXISTS `{$recurring}` (
          `id` int(11) UNSIGNED NOT NULL auto_increment,
          `profile_id` VARCHAR(50) NOT NULL DEFAULT '',
          `signup_tid` VARCHAR(50) NOT NULL DEFAULT '',
          `billingcycle` VARCHAR(50) NOT NULL,
          `callbackcycle` VARCHAR(50) NOT NULL,
          `cycle_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
          PRIMARY KEY  (`id`),
          INDEX `NOVALNET_RECURRING` (`profile_id` ASC)
        );
"
);

// -----------------------------------------------------------------
// -- Create Table novalnet_payment_aff_account_detail
// -----------------------------------------------------------------
$installer->run(
    "
        CREATE TABLE IF NOT EXISTS `{$affiliateInfo}` (
          `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
          `vendor_id` int(11) unsigned NOT NULL,
          `vendor_authcode` varchar(40) NOT NULL,
          `product_id` int(11) unsigned NOT NULL,
          `product_url` varchar(200) NOT NULL,
          `activation_date` datetime NOT NULL,
          `aff_id` int(11) unsigned DEFAULT NULL,
          `aff_authcode` varchar(40) DEFAULT NULL,
          `aff_accesskey` varchar(40) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `vendor_id` (`vendor_id`),
          KEY `product_id` (`product_id`),
          KEY `aff_id` (`aff_id`),
          INDEX `NOVALNET_AFFILIATE` (`aff_id` ASC)
        );
"
);

// -----------------------------------------------------------------
// -- Create Table novalnet_payment_aff_user_detail
// -----------------------------------------------------------------
$installer->run(
    "
        CREATE TABLE IF NOT EXISTS `{$affiliateUserInfo}` (
          `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
          `aff_id` int(11) unsigned NULL,
          `customer_no` varchar(40) NULL,
          `aff_order_no` varchar(40) NULL,
          PRIMARY KEY (`id`),
          KEY `aff_id` (`aff_id`),
          KEY `customer_no` (`customer_no`),
          KEY `aff_order_no` (`aff_order_no`),
          INDEX `NOVALNET_AFFILIATE_USER` (`customer_no` ASC)
        );
"
);

$methodFields = array();
$methodData = array(
    'sofortueberweisung' => 'novalnetSofortueberweisung',
    'novalnetsofortueberweisung' => 'novalnetSofortueberweisung',
    'novalnetpaypal' => 'novalnetPaypal',
    'novalnetCcpci' => 'novalnetCc',
    'novalnet_secure' => 'novalnetCc',
    'novalnetSecure' => 'novalnetCc',
    'novalnetElvatpci' => 'novalnetSepa',
    'novalnetElvdepci' => 'novalnetSepa',
    'novalnetElvaustria' => 'novalnetSepa',
    'novalnetElvgerman' => 'novalnetSepa',
    'novalnetSofortueberweisung' => 'novalnetBanktransfer'
);

foreach ($methodData as $variableId => $value) {
    $methodFields['method'] = $value;
    $installer->getConnection()->update(
        $tableOrderPayment, $methodFields, array('method = ?' => $variableId)
    );
}

if (version_compare($magentoVersion, '1.6', '<')) {
    $nnPaypalFields = array();
    $pathData = array(
        'payment/novalnetpaypal/active' => 'payment/novalnetPaypal/active',
        'payment/novalnetpaypal/title' => 'payment/novalnetPaypal/title',
        'payment/novalnetpaypal/order_status' => 'payment/novalnetPaypal/order_status',
        'payment/novalnetpaypal/booking_reference' => 'payment/novalnetPaypal/booking_reference',
        'payment/novalnetpaypal/order_status_after_payment' => 'payment/novalnetPaypal/order_status_after_payment',
        'payment/novalnetpaypal/user_group_excluded' => 'payment/novalnetPaypal/user_group_excluded',
        'payment/novalnetpaypal/gateway_timeout' => 'payment/novalnetPaypal/gateway_timeout',
        'payment/novalnetpaypal/allowspecific' => 'payment/novalnetPaypal/allowspecific',
        'payment/novalnetpaypal/min_order_total' => 'payment/novalnetPaypal/min_order_total',
        'payment/novalnetpaypal/max_order_total' => 'payment/novalnetPaypal/max_order_total',
        'payment/novalnetpaypal/orderscount' => 'payment/novalnetPaypal/orderscount',
        'payment/novalnetpaypal/sort_order' => 'payment/novalnetPaypal/sort_order'
    );


    foreach ($pathData as $variableId => $value) {
        $nnPaypalFields['path'] = $value;
        $installer->getConnection()->update(
            $tableConfigData, $nnPaypalFields, array('path = ?' => $variableId)
        );
    }
}

$installer->endSetup();
