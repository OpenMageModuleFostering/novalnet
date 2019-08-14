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
 * Novalnet tables
*/
$transactionStatus = $this->getTable('novalnet_payment/transaction_status');

$installer = $this;

$installer->startSetup();

// ----------------------------------------------------------------------
// -- Drop Table novalnet_payment_amountchanged
// ----------------------------------------------------------------------

$installer->run(
    "
    DROP TABLE IF EXISTS novalnet_payment_amountchanged;
"
);

// ---------------------------------------
// -- Drop Table novalnet_payment_separefill
// ---------------------------------------

$installer->run(
    "
    DROP TABLE IF EXISTS novalnet_payment_separefill;
"
);

$connection = $installer->getConnection();

// -----------------------------------------------------------------
// -- Alter Table novalnet_payment_transaction_status
// -----------------------------------------------------------------

$connection->addColumn(
    $transactionStatus, 'novalnet_acc_details', array(
    'TYPE' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'NULLABLE' => true,
    'COMMENT' => 'novalnet_acc_details')
);

$connection->addColumn(
    $transactionStatus, 'reference_transaction', array(
    'TYPE' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
    'LENGTH' => 6,
    'NULLABLE' => false,
    'COMMENT' => 'reference_transaction',
    'DEFAULT' => 0)
);

$installer->endSetup();
