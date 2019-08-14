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
 * @category   js
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
var $nncc_j = jQuery.noConflict();

function cchashcall()
{
    $nncc_j('#novalnet_cc_hash').val('');
    $nncc_j('#novalnet_cc_unique_id').val('');
    var merchantVendor = $nncc_j('#original_vendor_id').val();
    var merchantAuthcode = $nncc_j('#original_vendor_authcode').val();

    if (merchantVendor == undefined || merchantVendor == '' || merchantAuthcode == undefined || merchantAuthcode == '') {
        alert($nncc_j('#nn_cc_merchant_validate_error_message').val());
        return false;
    }

    var ccParams = {'ccHolder':'cc_owner', 'ccNo':'cc_number', 'ccExpMonth':'expiration', 'ccExpYear':'expiration_yr'};

    var isNotEmpty = true;
    $nncc_j.each(ccParams, function( key, value ) {
        if (key == 'ccHolder' && (/[\/\\|\]\[|#,+()$@~%.`'":;*?<>!^{}=_]/g).test($nncc_j('#novalnetCc_'+value).val())) {
            alert($nncc_j('#nn_cc_validate_error_message').val());
            isNotEmpty = false;
            return isNotEmpty;
        }
        ccParams[key] = $nncc_j.trim($nncc_j('#novalnetCc_'+value).val());
        if (ccParams[key] == undefined || ccParams[key] == '') {
           isNotEmpty = false;
           return isNotEmpty;
        }
    });

    if (isNotEmpty) {
        var currentDateVal = new Date();
        if (ccParams['ccExpYear'] == currentDateVal.getFullYear() && ccParams['ccExpMonth'] < (currentDateVal.getMonth()+1)) {
            alert($nncc_j('#nn_cc_validate_error_message').val());
            return false;
        }

        $nncc_j('#cc_loading').show();
        var ccUniqueId = generateUniqueId();
        var ccNum = ccParams['ccNo'].replace(/\s+/g, '');

        var ccPayportParams = {"noval_cc_exp_month" : ccParams['ccExpMonth'], "noval_cc_exp_year" : ccParams['ccExpYear'], "noval_cc_holder" : ccParams['ccHolder'], "noval_cc_no" : ccNum, "noval_cc_type" : "VI", "unique_id": ccUniqueId, "vendor_authcode" : merchantAuthcode,"vendor_id" : merchantVendor};

        ccPayportParams = $nncc_j.param(ccPayportParams);

        ccCrossDomainAjax(ccPayportParams, 'hash_call');
    }
}

function ccCrossDomainAjax(reqData, reqCall)
{
    // IE8 & 9 only Cross domain JSON POST request
    if ('XDomainRequest' in window && window.XDomainRequest !== '') {
        var xdr = new XDomainRequest(); // Use Microsoft XDR
        var payportUrl = getCcHttpProtocol();
        xdr.open('POST', payportUrl);
        xdr.onload = function() {
            getCcHashResult($nncc_j.parseJSON(this.responseText), reqCall);
        };

        xdr.onerror = function() {
            $nncc_j('#cc_loading').hide();
        };

        xdr.send(reqData);
    } else {
        var payportUrl = getCcHttpProtocol();
        $nncc_j.ajax({
            type: 'POST',
            url: payportUrl,
            data: reqData,
            dataType: 'json',
            success: function(data) {
                getCcHashResult(data, reqCall);
            },
            error: function (error) {
                $nncc_j('#cc_loading').hide();
            }
        });
    }
}

function getCcHashResult(response, reqCall)
{
    $nncc_j('#cc_loading').hide();
    if(response.hash_result == 'success') {
        if (reqCall == 'hash_call') {
            $nncc_j('#novalnet_cc_hash').val(response.pan_hash);
            $nncc_j('#novalnet_cc_hash').attr('disabled',false);
            $nncc_j('#novalnet_cc_unique_id').val(response.unique_id);
            $nncc_j('#novalnet_cc_unique_id').attr('disabled',false);
        } else if (reqCall == 'refill_call') {
            var params = response.hash_string+"&";
            params = params.split("=");
            var arrayResult={};
            $nncc_j.each( params, function( i, keyVal ){
                var rkey = rval ="";
            if(i >0 ){
            rkey = params[i -1].substring(params[i -1].lastIndexOf("&") + 1, params[i -1].length);
            rval = keyVal.substring(0, keyVal.lastIndexOf("&") + 0);
            arrayResult[rkey] = rval;
            }
            });

            try
            {
                $nncc_j('#novalnetCc_cc_owner').val(removeUnwantedSpecialCharsForCc($nncc_j.trim(decodeURIComponent(escape(arrayResult.cc_holder)))));
            } catch(e) {
                $nncc_j('#novalnetCc_cc_owner').val(removeUnwantedSpecialCharsForCc($nncc_j.trim(arrayResult.cc_holder)));
            }

            $nncc_j('#novalnetCc_cc_number').val(arrayResult.cc_no);
            $nncc_j('#novalnetCc_expiration').val(arrayResult.cc_exp_month);
            $nncc_j('#novalnetCc_expiration_yr').val(arrayResult.cc_exp_year);
            $nncc_j('#novalnet_cc_hash').val(response.pan_hash);
        }
    } else {
        alert(response.hash_result);
        return false;
    }
}

function removeUnwantedSpecialCharsForCc(value)
{
    if (value != 'undefined' || value != '') {
        value.replace(/^\s+|\s+$/g, '');
        return value.replace(/[\/\\|\]\[|#@,+()'`$~%.":;*?<>!^{}=_-]/g,'');
    }
}

function getNumbersOnly(value)
{
    return value.replace(/[^0-9]/g, '');
}

function ccRefillCall()
{
    var ccPanhash = '';
    var ccPanhash = $nncc_j('#novalnet_cc_pan_hash').val();
    if(ccPanhash == '' || ccPanhash == undefined) {return false;}

    var merchantVendor = $nncc_j('#original_vendor_id').val();
    var merchantAuthcode = $nncc_j('#original_vendor_authcode').val();
    var ccUniqueid = $nncc_j('#novalnet_cc_unique_id').val();
    if (merchantVendor == undefined || merchantVendor == '' || merchantAuthcode == undefined || merchantAuthcode == ''
        || ccUniqueid == undefined || ccUniqueid == '') {
        return false;
    }

    $nncc_j('#cc_loading').show();
    var ccPayportParams = "pan_hash="+ccPanhash+"&unique_id="+ccUniqueid+"&vendor_authcode="+merchantAuthcode+"&vendor_id="+merchantVendor;
    ccCrossDomainAjax(ccPayportParams, 'refill_call');
}

function getCcHttpProtocol()
{
    var url = location.href;
    var urlArr = url.split('://');
    var urlPrefix = ((urlArr[0] != '' && urlArr[0] == 'https') ? 'https' : 'http');
    return urlPrefix + "://payport.novalnet.de/payport_cc_pci";
}

function isNumberKey(evt, allowspace)
{
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (String.fromCharCode(evt.which) == '.' || String.fromCharCode(evt.which) == "'" || String.fromCharCode(evt.which) == '#') return false;

    if ((charCode == 32 && allowspace == true) || (charCode == 35 || charCode == 36 || charCode == 37 || charCode == 39 || charCode == 46) && evt.shiftKey == false) {
        return true;
    } else if (evt.ctrlKey == true && charCode == 114) {
        return true;
    } else if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    } else {
        return true;
    }

    return true;
}

function accHolderValidate(event)
{
    var keycode = ('which' in event) ? event.which : event.keyCode;
    var reg = /^(?:[A-Za-z0-9&\s-]+$)/;
    return (reg.test(String.fromCharCode(keycode)) || keycode == 0 || keycode == 8 || (event.ctrlKey == true && keycode == 114))? true : false;
}

function generateUniqueId()
{
    var length = 30; //Maximum Hash Limit
    var str = "";
    for (var i = 0; i < length; ++i) {
        str += String.fromCharCode(Math.floor(Math.random() * (90 - 65 + 1)) + 65); //Uppercase Char
        if (Math.floor(Math.random() * (122 * Math.random())) > 5) {
            str += String.fromCharCode(Math.floor(Math.random() * (97 - 122 + 1)) + 122); //Lowercase Char
        }
        if (Math.floor(Math.random() * (122 * Math.random())) > 30) {
            str += Math.floor(Math.random() * (97 - 122 + 1)) + 122; //Random number from 97 to 122
        }
    }
    fromLimit = Math.floor(Math.random() * (5 - 30 + 1)) + 20; //Random split from limit
    return str.substring(fromLimit, fromLimit + length);
}

ccRefillCall();
$nncc_j(document).ready(function() {
    Ajax.Responders.register({ onComplete: function() {
        if (Ajax.activeRequestCount == 0 && $nncc_j('input[name="payment[method]"]:checked').val() == 'novalnetCc'
            && $nncc_j('#novalnetCc_cc_cid').val() == '') {
            ccRefillCall();
        }
      }
    });
});
