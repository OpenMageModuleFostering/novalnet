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
 * @category   design_default
 * @package    Mage
 * @copyright  Copyright (c) 2008 Novalnet AG
 * @version    1.0.0
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Mage_Novalnet_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function isPublicIP($value) {
		return (count(explode('.', $value)) == 4 && !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value));
	}
	
  public function nn_url(){
   $url = (Mage::app()->getLocale()->getLocaleCode()=='de_DE')?"https://www.novalnet.de":"http://www.novalnet.com";
   return $url;
  }
 
	public function getRealIpAddr() {
		$_check = array(
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
		);
		foreach($_check as $_key) {
			if(isset($_SERVER[$_key])) {
				$ips = explode(',', $_SERVER[$_key]);
				if(isset($ips[0]) && $this->isPublicIP($ips[0])) {
					return $ips[0];
				}
			}
		}
		return $_SERVER['REMOTE_ADDR'];
	}
	
	public function encode(&$fields, $key) {
			if(!function_exists('base64_encode') || !function_exists('pack') || !function_exists('crc32')) {
				return false;
			}
			$toBeEncoded = array('vendor_authcode', 'product_id', 'tariff_id', 'test_mode', 'uniqid', 'amount');
			foreach($toBeEncoded as $_value ) {
				$data = $fields[$_value];
				if($this->isEmptyString($data)) {
					return false;
				}
				try {
					$crc = sprintf('%u', crc32($data));//%u is a must for ccrc32 returns a signed value
					$data = $crc."|".$data;
					$data = bin2hex($data.$key);
					$data = strrev(base64_encode($data));
					$fields[$_value] = $data;
				}catch(Exception $e) {
					return false;
				}
			}
			return true;
	}
	
	public function decode(&$fields, $key) {
		if(!function_exists('base64_decode') || !function_exists('pack') || !function_exists('crc32')) {
			return false;
		}
		$toBeEncoded = array('vendor_authcode', 'product_id', 'tariff_id', 'test_mode', 'uniqid', 'amount');
		foreach($toBeEncoded as $_value ) {
			$data = $fields[$_value];
			if($this->isEmptyString($data)) {
				return false;
			}
			try {
				$data =  base64_decode(strrev($data));
				$data = pack("H".strlen($data), $data);
				$data = substr($data, 0, stripos($data, $key));
				$pos  = strpos($data, "|");
				if($pos === false) {
				return false;
				}
				$crc   = substr($data, 0, $pos);
				$value = trim(substr($data, $pos+1));
				if( $crc != sprintf('%u', crc32($value)) ) {
					return false;
				}
				$fields[$_value] = $value;
			}catch(Exception $e) {
				return false;
			}
		}
		return true;
	}
	public function generateHash($data, $key) {
		if(!function_exists('md5') || $this->isEmptyString($key)) {
			return false;
		}
		$hashFields = array('vendor_authcode', 'product_id', 'tariff_id', 'amount', 'test_mode', 'uniqid' );
		$str = NULL;
		foreach( $hashFields as $_value ) {
			if($this->isEmptyString($data[$_value])) {
				return false;
			}
			$str .= $data[$_value];
		}
		return md5($str . strrev($key));
	}

	public function isEmptyString($str) {
			$str = trim($str);
			return !isset($str[0]);
	}
	
	public function checkHash($response, $key) {
		return isset($response['hash2'])
			&& !$this->isEmptyString($response['hash2'])
			&& (($tmp = $this->generateHash($response, $key)) !== false)
			&& ($response['hash2'] == $tmp);
	}
}
