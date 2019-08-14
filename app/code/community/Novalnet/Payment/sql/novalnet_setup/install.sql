DROP TABLE IF EXISTS `{{novalnet_order_log}}` ;
CREATE TABLE `{{novalnet_order_log}}` (
  `nn_log_id`			int(11) UNSIGNED NOT NULL auto_increment,
  `request_data`		TEXT NOT NULL DEFAULT '',
  `response_data`		TEXT NOT NULL DEFAULT '',
  `order_id`			VARCHAR(50) NOT NULL DEFAULT '',
  `customer_id`			int(11) NOT NULL,
  `status`				VARCHAR(20) NOT NULL DEFAULT '',
  `failed_reason`		TEXT NOT NULL DEFAULT '',
  `store_id`			int(11) UNSIGNED NOT NULL,
  `shop_url`			VARCHAR(255) NOT NULL DEFAULT '',
  `transaction_id`		VARCHAR(50) NOT NULL,
  `additional_data`		TEXT NOT NULL DEFAULT '',
  `created_date`		datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (`nn_log_id`)
) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;


DROP TABLE IF EXISTS `{{novalnet_transaction_status}}` ;
CREATE TABLE `{{novalnet_transaction_status}}` (
  `nn_txn_id`			int(11) UNSIGNED NOT NULL auto_increment,
  `transaction_no`		VARCHAR(50) NOT NULL,
  `order_id`			VARCHAR(50) NOT NULL DEFAULT '',
  `transaction_status`	VARCHAR(20) NOT NULL DEFAULT 0,
  `nc_no`				VARCHAR(11) NOT NULL,
  `customer_id`			int(11) NOT NULL,
  `payment_name`		VARCHAR(50) NOT NULL DEFAULT '',
  `amount`				decimal(12,4) NOT NULL,
  `remote_ip`			VARCHAR(20) NOT NULL,
  `store_id`			int(11) UNSIGNED NOT NULL,
  `shop_url`			VARCHAR(255) NOT NULL DEFAULT '',
  `additional_data`		TEXT NOT NULL DEFAULT '',
  `created_date`		datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (`nn_txn_id`)
) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
