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
 * Part of the Paymentmodule of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
if (!defined('NOVALNET_CC_CUSTOM_CSS')) {
    define('NOVALNET_CC_CUSTOM_CSS', 'input~~~#novalnetCc_expiration_yr~~~#novalnetCc_expiration_yr.error');
}
if (!defined('NOVALNET_CC_CUSTOM_CSS_STYLE')) {
    define('NOVALNET_CC_CUSTOM_CSS_STYLE', 'background: none repeat scroll 0 0 #FFFFFF;
    border-color: #AAAAAA #C8C8C8 #C8C8C8 #AAAAAA;border-style: solid;border-width: 1px;font: 12px;width:224px;~~~width:75px;~~~color:#2f2f2f;font-weight:lighter;');
}

if (!defined('NOVALNET_SEPA_CUSTOM_CSS')) {
    define('NOVALNET_SEPA_CUSTOM_CSS', 'input~~~select');
}
if (!defined('NOVALNET_SEPA_CUSTOM_CSS_STYLE')) {
    define('NOVALNET_SEPA_CUSTOM_CSS_STYLE', 'background: none repeat scroll 0 0 #FFFFFF;
    border-color: #AAAAAA #C8C8C8 #C8C8C8 #AAAAAA;border-style: solid;border-width: 1px;font: 12px;width:224px;~~~background: none repeat scroll 0 0 #FFFFFF;border-color: #AAAAAA #C8C8C8 #C8C8C8 #AAAAAA;border-style: solid;border-width: 1px;font: 12px;width:224px;');
}
