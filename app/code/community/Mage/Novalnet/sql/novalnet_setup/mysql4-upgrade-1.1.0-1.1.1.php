<?php
$installer = $this;

$installer->startSetup();
$installer->addAttribute('order_payment', 'nn_account_holder', array('type'=>'varchar'));
$installer->addAttribute('order_payment', 'nn_account_number', array('type'=>'varchar'));
$installer->addAttribute('order_payment', 'nn_bank_sorting_code', array('type'=>'varchar'));
$installer->addAttribute('order_payment', 'nn_elv_country', array('type'=>'varchar'));

$installer->addAttribute('quote_payment', 'nn_account_holder', array('type'=>'varchar'));
$installer->addAttribute('quote_payment', 'nn_account_number', array('type'=>'varchar'));
$installer->addAttribute('quote_payment', 'nn_bank_sorting_code', array('type'=>'varchar'));
$installer->addAttribute('quote_payment', 'nn_elv_country', array('type'=>'varchar'));


if (Mage::getVersion() >= 1.1) {
    $installer->startSetup();    
	$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_account_holder', 'VARCHAR(255) NOT NULL');
	$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_account_number', 'VARCHAR(255) NOT NULL');
	$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_bank_sorting_code', 'VARCHAR(255) NOT NULL');
	$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_elv_country', 'VARCHAR(255) NOT NULL'); 
    $installer->endSetup();
} 

?>