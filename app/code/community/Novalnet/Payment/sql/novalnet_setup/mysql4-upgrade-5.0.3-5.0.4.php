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
/** novalnet table */
$tableAffAccountDetail = $this->getTable('novalnet_payment/aff_account_detail');
$tableAffUserDetail = $this->getTable('novalnet_payment/aff_user_detail');

$installer = $this;

$installer->startSetup();

#-----------------------------------------------------------------
#-- Create Table novalnet_affiliate_account_info
#-----------------------------------------------------------------
$installer->run("
        CREATE TABLE IF NOT EXISTS `{$tableAffAccountDetail}` (
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
          KEY `aff_id` (`aff_id`)
        ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
");

#-----------------------------------------------------------------
#-- Create Table novalnet_aff_user_detail
#-----------------------------------------------------------------
$installer->run("
        CREATE TABLE IF NOT EXISTS `{$tableAffUserDetail}` (
          `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
          `aff_id` int(11) unsigned NULL,
          `customer_no` varchar(40) NULL,
          `aff_order_no` varchar(40) NULL,
          PRIMARY KEY (`id`),
          KEY `aff_id` (`aff_id`),
          KEY `customer_no` (`customer_no`),
          KEY `aff_order_no` (`aff_order_no`)
        ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
");

$installer->endSetup();
