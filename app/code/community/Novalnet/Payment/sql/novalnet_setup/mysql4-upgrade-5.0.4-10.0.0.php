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
 * magento table 
*/
$tableOrderPayment = $this->getTable('sales/order_payment');

/**
* 
 * Novalnet tables  
*/
$recurring = $this->getTable('novalnet_payment/recurring');

$installer = $this;

$installer->startSetup();

$paymentMethod = array(
    'method' => 'novalnetBanktransfer',
);
$installer->getConnection()->update(
    $tableOrderPayment, $paymentMethod, array('method = ?' => 'novalnetSofortueberweisung')
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

$installer->endSetup();
