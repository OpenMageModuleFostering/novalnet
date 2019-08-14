<?php
$installer = $this;
$setup = new Mage_Sales_Model_Mysql4_Setup('core_setup');

$installer->startSetup();

$setup->addAttribute('quote_payment', 'nn_account_holder',    array('type'=>'varchar'));
$setup->addAttribute('quote_payment', 'nn_account_number',    array('type'=>'varchar'));
$setup->addAttribute('quote_payment', 'nn_bank_sorting_code', array('type'=>'varchar'));
$setup->addAttribute('quote_payment', 'nn_elv_country',       array('type'=>'varchar'));

$setup->addAttribute('quote_payment', 'nn_account_holder_at',    array('type'=>'varchar'));
$setup->addAttribute('quote_payment', 'nn_account_number_at',    array('type'=>'varchar'));
$setup->addAttribute('quote_payment', 'nn_bank_sorting_code_at', array('type'=>'varchar'));
$setup->addAttribute('quote_payment', 'nn_elv_country_at',       array('type'=>'varchar'));

$setup->addAttribute('quote_payment', 'nn_testorder',       array('type'=>'smallint'));
// Save the payment information for prepayment & invoice
$setup->addAttribute('quote_payment', 'nn_comments',       array('type'=>'text'));

if (Mage::getVersion() >= '1.4.1.0') {

$setup->addAttribute('order_payment', 'nn_account_holder',    array('type'=>'varchar'));
$setup->addAttribute('order_payment', 'nn_account_number',    array('type'=>'varchar'));
$setup->addAttribute('order_payment', 'nn_bank_sorting_code', array('type'=>'varchar'));
$setup->addAttribute('order_payment', 'nn_elv_country',       array('type'=>'varchar'));

$setup->addAttribute('order_payment', 'nn_account_holder_at',    array('type'=>'varchar'));
$setup->addAttribute('order_payment', 'nn_account_number_at',    array('type'=>'varchar'));
$setup->addAttribute('order_payment', 'nn_bank_sorting_code_at', array('type'=>'varchar'));
$setup->addAttribute('order_payment', 'nn_elv_country_at',       array('type'=>'varchar'));
$setup->addAttribute('order_payment', 'nn_testorder',       array('type'=>'smallint'));
// Save the payment information for prepayment & invoice
$setup->addAttribute('order_payment', 'nn_comments',       array('type'=>'text'));

}

$installer->endSetup();

if (Mage::getVersion() >= 1.1) {
    $installer->startSetup();
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_account_holder',    'VARCHAR(255) NOT NULL');
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_account_number',    'VARCHAR(255) NOT NULL');
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_bank_sorting_code', 'VARCHAR(255) NOT NULL');
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_elv_country',       'VARCHAR(255) NOT NULL'); 

	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_account_holder_at',    'VARCHAR(255) NOT NULL');
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_account_number_at',    'VARCHAR(255) NOT NULL');
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_bank_sorting_code_at', 'VARCHAR(255) NOT NULL');
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_elv_country_at',       'VARCHAR(255) NOT NULL');
	
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_testorder',  'SMALLINT NULL DEFAULT NULL');
	
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_comments',  'TEXT NULL DEFAULT NULL');
    $installer->endSetup();
}
?>