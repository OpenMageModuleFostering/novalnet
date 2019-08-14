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

/** novalnet table */
$tableCallback = $this->getTable('novalnet_payment/callback');

/** magento table */
$tableOrderPayment = $this->getTable('sales/order_payment');

$installer = $this;

$installer->startSetup();

#-----------------------------------------------------------------
#-- Create Table novalnet_order_callback
#-----------------------------------------------------------------
$installer->run("
		CREATE TABLE IF NOT EXISTS `{$tableCallback}` (
		  `id` int(11) UNSIGNED NOT NULL auto_increment,
		  `order_id` VARCHAR(50) NOT NULL DEFAULT '',
		  `callback_amount` int(11) UNSIGNED NOT NULL,
		  `reference_tid` VARCHAR(50) NOT NULL,
		  `callback_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  `callback_tid` VARCHAR(50) NOT NULL,
		  `callback_log` TEXT NOT NULL DEFAULT '',
		  PRIMARY KEY  (`id`)
		) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
");

$installer->run("
		UPDATE `{$tableOrderPayment}` SET `method` = 'novalnetSofortueberweisung'
			WHERE `method` = 'novalnetsofortueberweisung';
		UPDATE `{$tableOrderPayment}` SET `method` = 'novalnetPaypal'
			WHERE `method` = 'novalnetpaypal';
		UPDATE `{$tableOrderPayment}` SET `method` = 'novalnetIdeal'
			WHERE `method` = 'novalnetideal';
	");
$installer->endSetup();
