<?php
$installer = $this;

$setup = new Mage_Sales_Model_Mysql4_Setup('core_setup');

$installer->startSetup();
$setup->addAttribute('order_payment', 'pn_su_transaction_id', array('type'=>'varchar'));
$installer->endSetup();

?>