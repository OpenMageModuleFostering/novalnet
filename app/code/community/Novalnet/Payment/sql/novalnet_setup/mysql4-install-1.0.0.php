<?php

/** novalnet tables  */
$tableOrderLog = $this->getTable('novalnet_payment/order_log');
$tableTransactionStatus = $this->getTable('novalnet_payment/transaction_status');

$installer = $this;
$installer->startSetup();
$helper = Mage::helper('novalnet_payment');

$magentoVersion = Mage::getVersion();

$useOldVersion = false;
if (version_compare($magentoVersion, '1.6', '<'))
    $useOldVersion = true;

if ($useOldVersion) {

    $datetime = 'datetime';
    $sql = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'install.sql');

    $installSqlConfig = array(
        '{{novalnet_order_log}}' => $tableOrderLog,
        '{{novalnet_transaction_status}}' => $tableTransactionStatus
    );

    $installSql = str_replace(array_keys($installSqlConfig), array_values($installSqlConfig), $sql);
    $installer->run($installSql);
} else {
    $connection = $installer->getConnection();
    $table = $connection->newTable($tableOrderLog);

    $table->addColumn('nn_log_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, array(
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
        'identity' => true,
        'auto_increment' => true)
    );
    $table->addColumn('request_data', Varien_Db_Ddl_Table::TYPE_TEXT, NULL, array(
        'nullable' => false)
    );
    $table->addColumn('response_data', Varien_Db_Ddl_Table::TYPE_TEXT, NULL, array(
        'nullable' => false)
    );
    $table->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, array(
        'unsigned' => false,
        'nullable' => false)
    );
    $table->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array('nullable' => false)
    );
    $table->addColumn('failed_reason', Varien_Db_Ddl_Table::TYPE_TEXT, NULL, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('shop_url', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array('nullable' => false)
    );
    $table->addColumn('transaction_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, array('nullable' => false)
    );
    $table->addColumn('additional_data', Varien_Db_Ddl_Table::TYPE_TEXT, NULL, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('created_date', Varien_Db_Ddl_Table::TYPE_DATETIME, NULL, array(
        'unsigned' => true,
        'nullable' => false)
    );

    //Set Engine to MyISAM
    $table->setOption('type', 'MyISAM');

    // Create table 'novalnet_order_log'
    $connection->createTable($table);

    // Create table 'novalnet_transaction_status'
    $table = $connection->newTable($tableTransactionStatus);

    $table->addColumn('nn_txn_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, array(
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
        'identity' => true,
        'auto_increment' => true)
    );
    $table->addColumn('transaction_no', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, array(
        'nullable' => false)
    );
    $table->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, array(
        'unsigned' => false,
        'nullable' => false)
    );
    $table->addColumn('transaction_status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('nc_no', Varien_Db_Ddl_Table::TYPE_VARCHAR, 10, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('payment_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('amount', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('remote_ip', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_INTEGER, NULL, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('shop_url', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('additional_data', Varien_Db_Ddl_Table::TYPE_TEXT, NULL, array(
        'unsigned' => true,
        'nullable' => false)
    );
    $table->addColumn('created_date', Varien_Db_Ddl_Table::TYPE_DATETIME, NULL, array(
        'unsigned' => true,
        'nullable' => false)
    );
    //Set Engine to InnoDB
    $table->setOption('type', 'InnoDB');
    // Create table 'novalnet_transaction_status'
    $connection->createTable($table);
}

$installer->endSetup();
