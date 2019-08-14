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
 * @category  js
 * @package   Novalnet_Payment
 * @copyright Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
var $nncc_j = jQuery.noConflict();

function isNumberKey(event, allowstring)
{
    var charCode = (event.which) ? event.which : event.keyCode;
    event = event || window.event;
    var reg = ( allowstring == 'holder' ) ? /^[a-z-.&\s]+$/i : ( ( ( event.target || event.srcElement ).id == 'novalnetCc_cc_number' ) ? /^[0-9\s]+$/i : /^[0-9]+$/i );
    return ( reg.test(String.fromCharCode(charCode)) || charCode == 0 || charCode == 8 || charCode == 9 || (event.ctrlKey == true && charCode == 114 ) );
}

function ccPaymentDescription()
{
    if ($nncc_j('#cc_oneclick_shopping').val() == 1
        && $nncc_j('#cc_enter_data').val() == 1
    ) {
        $nncc_j('#cc_direct_desc').css('display','block');
        $nncc_j('#cc_redirect_desc').css('display','none');
    } else {
        $nncc_j('#cc_redirect_desc').css('display','block');
        $nncc_j('#cc_direct_desc').css('display','none');
    }
}

function formChange(type)
{
    if(type == 'given') {
        $nncc_j('#cc_enter_data').val(1);
        $nncc_j('#cc_title_given').css('display','none');
        $nncc_j('#cc_oneclick_given, #cc_title_new').css('display','block');
    } else if(type == 'new') {
        $nncc_j('#cc_enter_data').val(0);
        $nncc_j('#cc_title_given').css('display','block');
        $nncc_j('#cc_oneclick_given, #cc_title_new').css('display','none');
    }
    ccPaymentDescription();
}

function ccOneClickShopping()
{
    ccPaymentDescription();
    if($nncc_j('#cc_oneclick_shopping').val() ==undefined) {
        return false;
    } else if($nncc_j('#cc_oneclick_shopping').val() == 1) {
        $nncc_j('#cc_oneclick_link, #cc_title_new, #cc_oneclick_given').css('display','block');
        $nncc_j('#cc_title_given').css('display','none');
    } else {
        $nncc_j('#cc_oneclick_link, #cc_oneclick_given').css('display','none');
    }
}

function novalnetCcIframe() {
    $nncc_j('#cc_loading').hide();
}

$nncc_j(document).ready(
    function() {
        ccOneClickShopping();

        Ajax.Responders.register(
            { onComplete: function() {
                ccOneClickShopping();
            }
            }
        );

        $nncc_j(document).on(
            'click', '#co-payment-form input[type="radio"]', function(event) {
                if (this.value == "novalnetCc") {
                    $nncc_j(this).addClass('active');
                    ccOneClickShopping();
                }
            }
        );
    }
);
