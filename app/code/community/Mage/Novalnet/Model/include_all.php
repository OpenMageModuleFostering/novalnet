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
 * Part of the Paymentmodul of Novalnet AG
 * http://www.novalnet.de 
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 * 
 * @category   basic functions
 * @package    Mage
 * @copyright  Copyright (c) 2009 Novalnet AG
 * @version    1.0.0
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
$aShopSystems = array('oscommerce', 'xtcommerce', 'magento', 'zendcart');
$aPaymentTypes = array('cc', 'elvgerman', 'elvaustria', 'instantbanktransfer', 'invoice', 'phonepayment', 'prepayment', '3dsecure');
$aParamRequired4All = array('cgi_url', 'shopystem', 'paymenttype',  'merchant_id', 'auth_code', 'product_id', 'tariff_id');


$hParamRequiredPerPaymenttype['cc'] = array('', '', '', '');
$hParamOptionalPerPayment['cc']		= array(
					input1, #$order->getIncrementId();#order no.
					first_name,
                    last_name,
                    street,
                    house_no,
                    city,
                    zip,
                    country,
                    tel,
                    fax,
                    remote_ip,
                    gender,
                    email,
					cc_no,
                    cc_exp_month,
                    cc_exp_year,
                    cc_cvc2,
                    cc_holder,
					booking_reference,
                    );

function checkParamsRequired4All($hParams)
{
	global $aParamRequired4All, $aShopSystems, $aPaymentTypes, $aMsg;
	foreach ($aParamRequired4All as $paramRequired)
	{
		if (!in_array($paramRequired, array_keys($hParams)))
		{
			$§aMsg[] = $paramRequired;
		}
	}
}

function checkShopSystem($shopsystem)
{
	global $aParamRequired4All, $aShopSystems, $aMsg;
	if (!in_array($shopsystem, array_keys($aShopSystems)))
	{
		$§aMsg[] = $shopsystem.' unknown';
	}
}

function checkParamsByPaymentType($paymenttype)
{
	global $aShopSystems, $aPaymentTypes
}

function isPublicIP($value)
{
	if(!$value || count(explode('.',$value))!=4)
	{
		return false;
	}
	return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
}
function getRealIpAddr()
{
	if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) and $this->isPublicIP($_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) and $iplist=explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		if($this->isPublicIP($iplist[0])) return $iplist[0];
	}
	if (isset($_SERVER['HTTP_CLIENT_IP']) and $this->isPublicIP($_SERVER['HTTP_CLIENT_IP']))
	{
		return $_SERVER['HTTP_CLIENT_IP'];
	}
	if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) and $this->isPublicIP($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
	{
		return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
	}
	if (isset($_SERVER['HTTP_FORWARDED_FOR']) and $this->isPublicIP($_SERVER['HTTP_FORWARDED_FOR']) )
	{
		return $_SERVER['HTTP_FORWARDED_FOR'];
	}
	return $_SERVER['REMOTE_ADDR'];
}

function debug2($object, $filename)
{
	if (!$this->debug){return;}
	$fh = fopen("/tmp/$filename", 'a+');
	if (gettype($object) == 'object' or gettype($object) == 'array'){
		fwrite($fh, serialize($object));
	}else{
		fwrite($fh, date('H:i:s').' '.$object);
	}
	fwrite($fh, "<hr />\n");
	fclose($fh);
}