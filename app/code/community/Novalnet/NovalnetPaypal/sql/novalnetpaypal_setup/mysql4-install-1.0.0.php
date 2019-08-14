<?php
$installer = $this;
$setup = new Mage_Sales_Model_Mysql4_Setup('core_setup');
$installer->startSetup();
$setup->addAttribute('order_payment', 'pn_su_transaction_id', array('type'=>'varchar'));
$setup->addAttribute('quote_payment', 'nn_testorder',       array('type'=>'tinyint'));
$installer->endSetup();

if (Mage::getVersion() >= 1.1) {
    $installer->startSetup();
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'nn_testorder',  'TINYINT NULL DEFAULT NULL');
	$setup->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'pn_su_transaction_id',       'VARCHAR(255) NOT NULL');	
    $installer->endSetup();
}	

?>