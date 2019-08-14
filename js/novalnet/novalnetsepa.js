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
var $nnsepa_j = jQuery.noConflict();

function generate_sepa_iban_bic(value)
{
    if (!$nnsepa_j('#novalnetSepa_mandate_confirm').is(':checked')) {
        unsetHashRelatedElements();
        return false;
    } else {
        $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', true);
        $nnsepa_j('#novalnetSepa_iban').remove();
        $nnsepa_j('#novalnetSepa_bic').remove();
    }

    var merchantVendor = $nnsepa_j('#process_vendor_id').val();
    var merchantAuthcode = $nnsepa_j('#auth_code').val();

    if (merchantVendor == undefined || merchantVendor == '' || merchantAuthcode == undefined
        || merchantAuthcode == '') {
        alert($nnsepa_j('#nn_sepa_merchant_validate_error_message').val());
        $nnsepa_j('#novalnetSepa_mandate_confirm').removeAttr('checked');
        $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
        return false;
    }

    var sepaBankCountry = $nnsepa_j('#novalnetSepa_bank_country').val();
    var sepaAccountNumber = $nnsepa_j.trim($nnsepa_j('#novalnetSepa_account_number').val());
    var sepaBankCode = $nnsepa_j.trim($nnsepa_j('#novalnetSepa_bank_code').val());

    if ((sepaAccountNumber && sepaBankCode) &&
        ((isNaN(sepaAccountNumber) && !isNaN(sepaBankCode))
        || (!isNaN(sepaAccountNumber) && isNaN(sepaBankCode)))) {
        alert($nnsepa_j('#nn_sepa_validate_error_message').val());
        $nnsepa_j('#novalnetSepa_mandate_confirm').removeAttr("checked");
        $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
        return false;
    } else if ((/[\/\\|\]\[|#,+()@&$~%.`'":;*?<>!^{}=_-]/g).test(sepaAccountNumber)
        || (/[\/\\|\]\[|#,+()@&$~%.`'":;*?<>!^{}=_-]/g).test(sepaBankCode)) {
        alert($nnsepa_j('#nn_sepa_validate_error_message').val());
        $nnsepa_j('#novalnetSepa_mandate_confirm').removeAttr("checked");
        $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
        return false;
    } else if ((sepaBankCountry == 'DE' && sepaBankCode == '' && isNaN(sepaAccountNumber))
        || (isNaN(sepaAccountNumber) && isNaN(sepaBankCode))) {
        generateSepaHash();
    } else if (!isNaN(sepaAccountNumber) && !isNaN(sepaBankCode) && sepaBankCode != 0
        && sepaAccountNumber != 0) {
        generateSepaIbanBic();
    } else if (sepaAccountNumber == '' || sepaBankCode == '') {
        alert($nnsepa_j('#nn_sepa_validate_error_message').val());
        $nnsepa_j('#novalnetSepa_mandate_confirm').removeAttr("checked");
        $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
        return false;
    }
}

function getSepaFormValues()
{
    var merchantVendor = $nnsepa_j('#process_vendor_id').val();
    var merchantAuthcode = $nnsepa_j('#auth_code').val();
    var sepaAccountHolder = removeUnwantedSpecialChars($nnsepa_j.trim($nnsepa_j('#novalnetSepa_account_holder').val()));
    var sepaBankCountry = $nnsepa_j('#novalnetSepa_bank_country').val();
    var sepaAccountNumber = removeUnwantedSpecialChars($nnsepa_j.trim($nnsepa_j('#novalnetSepa_account_number').val()));
    var sepaUniqueId = generateUniqueId();
    var requestParams = {'sepaAccountHolder':sepaAccountHolder, 'sepaBankCountry':sepaBankCountry,
                         'sepaAccountNumber':sepaAccountNumber, 'sepaUniqueId':sepaUniqueId,
                         'merchantVendor':merchantVendor, 'merchantAuthcode':merchantAuthcode};
    return requestParams;
}

function generateSepaHash()
{
    var params = getSepaFormValues();
    var isNotEmpty = true;
    $nnsepa_j.each(params, function( value ) {
      if (params[value] == undefined || params[value] == '') {
        alert($nnsepa_j('#nn_sepa_validate_error_message').val());
        $nnsepa_j('#novalnetSepa_mandate_confirm').removeAttr("checked");
        $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
        isNotEmpty = false;
        return isNotEmpty;
      }
    });

    if (isNotEmpty) {
        var sepaBankCode = removeUnwantedSpecialChars($nnsepa_j.trim($nnsepa_j('#novalnetSepa_bank_code').val()));

        if((params['sepaBankCountry'] != 'DE' && (sepaBankCode == '' || sepaBankCode == undefined))
            || (params['sepaBankCountry'] == 'DE' && (sepaBankCode == '' || sepaBankCode == undefined)
            && !isNaN(params['sepaAccountNumber'])))  {
            alert($nnsepa_j('#nn_sepa_validate_error_message').val());
            unsetHashRelatedElements();
            return false;
        }

        if (params['sepaBankCountry'] == 'DE' && (sepaBankCode == '' || sepaBankCode == undefined)
            && isNaN(params['sepaAccountNumber'])) {
            sepaBankCode = '123456';
        }

        var sepaIban = $nnsepa_j('#sepaiban').val();
        var sepaBic = $nnsepa_j('#sepabic').val();

        if (sepaIban != '' && sepaBic != '') {
            var accountNumber = params['sepaAccountNumber'];
            var bankCode = sepaBankCode;
        } else {
            sepaIban = params['sepaAccountNumber'];
            sepaBic = sepaBankCode;
            accountNumber = bankCode = '';
        }

        $nnsepa_j('#sepa_loading').show();
        var sepaPayportParams = {"account_holder" : params['sepaAccountHolder'], "bank_account" : accountNumber, "bank_code" : bankCode, "vendor_id" : params['merchantVendor'], "vendor_authcode" : params['merchantAuthcode'], "bank_country" : params['sepaBankCountry'], "unique_id" : params['sepaUniqueId'], "sepa_data_approved" : 1,"mandate_data_req" : 1, "iban" : sepaIban, "bic" : sepaBic};

        sepaPayportParams = $nnsepa_j.param(sepaPayportParams);

        sepaCrossDomainAjax(sepaPayportParams, 'hash_call');
    }
}

function generateSepaIbanBic()
{
    var params = getSepaFormValues();
    var isNotEmpty = true;
    $nnsepa_j.each(params, function( value ) {
      if (params[value] == undefined || params[value] == '') {
        alert($nnsepa_j('#nn_sepa_validate_error_message').val());
        $nnsepa_j('#novalnetSepa_mandate_confirm').removeAttr("checked");
        $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
        isNotEmpty = false;
        return isNotEmpty;
      }
    });

    if (isNotEmpty) {
        var sepaBankCode = removeUnwantedSpecialChars($nnsepa_j.trim($nnsepa_j('#novalnetSepa_bank_code').val()));
        $nnsepa_j('#sepa_loading').show();

        var sepaPayportParams = {"account_holder" : params['sepaAccountHolder'], "bank_account" : params['sepaAccountNumber'], "bank_code" : sepaBankCode,"vendor_id": params['merchantVendor'], "vendor_authcode" : params['merchantAuthcode'], "bank_country" : params['sepaBankCountry'],"get_iban_bic": 1, "unique_id" : params['sepaUniqueId']};
        sepaPayportParams = $nnsepa_j.param(sepaPayportParams);
        sepaCrossDomainAjax(sepaPayportParams, 'iban_call');
    }
}

function sepaRefillcall()
{
    var sepaPanHash = $nnsepa_j('#result_sepa_hash').val();

    if (sepaPanHash == '' || sepaPanHash == undefined) {
        return false;
    }

    $nnsepa_j('#novalnetSepa_account_holder').val('');
    $nnsepa_j('#novalnetSepa_bank_country').val('');
    $nnsepa_j('#novalnetSepa_account_number').val('');
    $nnsepa_j('#novalnetSepa_bank_code').val('');

    var merchantVendor = $nnsepa_j('#process_vendor_id').val();
    var merchantAuthcode = $nnsepa_j('#auth_code').val();
    var sepaUniqueId = generateUniqueId();

    if (merchantVendor == undefined  || merchantVendor == '' || merchantAuthcode == undefined || merchantAuthcode == ''
        || sepaUniqueId == undefined || sepaUniqueId == '') {
        return false;
    }

    $nnsepa_j('#sepa_loading').show();
    var sepaPayportParams = "vendor_id="+merchantVendor+"&vendor_authcode="+merchantAuthcode+"&unique_id="+sepaUniqueId+"&sepa_data_approved=1&mandate_data_req=1&sepa_hash="+sepaPanHash;
    sepaCrossDomainAjax(sepaPayportParams, 'refill_call');
}

function sepaCrossDomainAjax(reqData, reqCall)
{
    // IE8 & 9 only Cross domain JSON POST request
    if ('XDomainRequest' in window && window.XDomainRequest !== '') {
        var xdr = new XDomainRequest(); // Use Microsoft XDR
        var payportUrl = getSepaHttpProtocol();
        xdr.open('POST', payportUrl);
        xdr.onload = function() {
            getSepaHashResult($nnsepa_j.parseJSON(this.responseText), reqCall);
        };

        xdr.onerror = function() {
            $nnsepa_j('#sepa_loading').hide();
            $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
        };

        xdr.send(reqData);
    } else {
        var payportUrl = getSepaHttpProtocol();
        $nnsepa_j.ajax({
            type: 'POST',
            url: payportUrl,
            data: reqData,
            dataType: 'json',
            success: function(data) {
                getSepaHashResult(data, reqCall);
            },
            error: function (error) {
                $nnsepa_j('#sepa_loading').hide();
                $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
            }
        });
    }
}

function getSepaHashResult(response, reqCall)
{
    $nnsepa_j('#sepa_loading').hide();
    if (response.hash_result == 'success') {
        if (reqCall == 'iban_call') {
            $nnsepa_j('#sepaiban').val(response.IBAN);
            $nnsepa_j('#sepabic').val(response.BIC);
            if(response.IBAN != '' && response.BIC != '') {
                $nnsepa_j('<span id="novalnetSepa_iban"></span>').insertAfter($nnsepa_j("#novalnetSepa_account_number"));
                $nnsepa_j('#novalnetSepa_iban').html('<b>IBAN:</b> '+response.IBAN);
                $nnsepa_j('#nn_sepa_overlay_iban_tr').show(60);
                $nnsepa_j('<span id="novalnetSepa_bic"></span>').insertAfter($nnsepa_j("#novalnetSepa_bank_code"));
                $nnsepa_j('#novalnetSepa_bic').html('<b>BIC:</b> '+response.BIC);
                $nnsepa_j('#nn_sepa_overlay_bic_tr').show(60);
                generateSepaHash();
                return true;
            } else {
                alert($nnsepa_j('#nn_sepa_validate_error_message').val());
                $nnsepa_j('#sepa_mandate_overlay_block_first').css("display", "none");
                $nnsepa_j('#novalnetSepa_mandate_confirm').removeAttr("checked");
                $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
                $nnsepa_j('#nnsepa_iban_confirmed').val(0);
                $nnsepa_j('#nn_sepa_overlay_iban_tr').hide(60);
                $nnsepa_j('#nn_sepa_overlay_bic_tr').hide(60);
                closeMandateOverlay(0);
                return false;
            }
        } else if (reqCall == 'hash_call') {
            var sepaUniqueId = generateUniqueId();
            $nnsepa_j('#result_sepa_hash').val(response.sepa_hash);
            $nnsepa_j('#result_sepa_hash').attr('disabled',false);
            $nnsepa_j('#result_mandate_ref').val(response.mandate_ref);
            $nnsepa_j('#result_mandate_ref').attr('disabled',false);
            $nnsepa_j('#result_mandate_date').val(response.mandate_date);
            $nnsepa_j('#result_mandate_date').attr('disabled',false);
            $nnsepa_j('#result_mandate_unique').val(sepaUniqueId);
            $nnsepa_j('#result_mandate_unique').attr('disabled',false);
            $nnsepa_j('#nnsepa_iban_confirmed').val(1);
            $nnsepa_j('#nnsepa_iban_confirmed').attr('disabled',false);
            showMandateOverlay();
        } else if (reqCall == 'refill_call') {
            var params = response.hash_string+"&";
            params = params.split("=");

            var arrayResult={};
            $nnsepa_j.each( params, function( i, keyVal ){
                var rkey = rval ="";
            if(i >0 ){
            rkey = params[i -1].substring(params[i -1].lastIndexOf("&") + 1, params[i -1].length);
            rval = keyVal.substring(0, keyVal.lastIndexOf("&") + 0);
            arrayResult[rkey] = rval;
            }
            });

            try
            {
                $nnsepa_j('#novalnetSepa_account_holder').val(removeUnwantedSpecialChars($nnsepa_j.trim(decodeURIComponent(escape(arrayResult.account_holder)))));
            } catch(e) {
                $nnsepa_j('#novalnetSepa_account_holder').val(removeUnwantedSpecialChars($nnsepa_j.trim((arrayResult.account_holder))));
            }

            $nnsepa_j('#novalnetSepa_bank_country').val(arrayResult.bank_country);
            $nnsepa_j('#novalnetSepa_account_number').val(removeUnwantedSpecialChars(arrayResult.iban));
            if (arrayResult.bic != '123456') { $nnsepa_j('#novalnetSepa_bank_code').val(removeUnwantedSpecialChars(arrayResult.bic)); }
        } else {
            alert(response.hash_result);
        }
    }
}

function unsetHashRelatedElements()
{
    $nnsepa_j('#sepaiban').val('');
    $nnsepa_j('#sepabic').val('');
    $nnsepa_j('#result_mandate_unique').val('');
    $nnsepa_j('#result_sepa_hash').val('');
    $nnsepa_j('#result_mandate_date').val('');
    $nnsepa_j('#result_mandate_ref').val('');
    $nnsepa_j('#novalnetSepa_iban').remove();
    $nnsepa_j('#novalnetSepa_bic').remove();
    $nnsepa_j('#nnsepa_iban_confirmed').val(0);
    $nnsepa_j('#novalnetSepa_mandate_confirm').removeAttr("checked");
}

function showMandateOverlay()
{
    $nnsepa_j('.bgCover').css({
        display: 'block',
        width: $nnsepa_j(document).width(),
        height: $nnsepa_j(document).height()
    });
    $nnsepa_j('.bgCover').css({opacity: 0}).animate({opacity: 0.5, backgroundColor: '#878787'});
    $nnsepa_j('#sepa_overlay_iban_span').html(removeUnwantedSpecialChars($nnsepa_j('#sepaiban').val()));
    $nnsepa_j('#sepa_overlay_bic_span').html(removeUnwantedSpecialChars($nnsepa_j('#sepabic').val()));
    if ($nnsepa_j('#sepaiban').val() != '' && $nnsepa_j('#sepabic').val() != '') {
        $nnsepa_j('#label_iban').css('display', 'table-row');
        $nnsepa_j('#label_bic').css('display', 'table-row');
    }

    if (isNaN($nnsepa_j('#novalnetSepa_account_number').val()) && $nnsepa_j('#novalnetSepa_bank_code').val() == '') {
        $nnsepa_j('#sepa_overlay_iban_span').html(removeUnwantedSpecialChars($nnsepa_j('#novalnetSepa_account_number').val()));
        $nnsepa_j('#nn_sepa_overlay_bic_tr').hide(60);
    } else if (isNaN($nnsepa_j('#novalnetSepa_account_number').val()) && isNaN($nnsepa_j('#novalnetSepa_bank_code').val())) {
        $nnsepa_j('#sepa_overlay_iban_span').html(removeUnwantedSpecialChars($nnsepa_j('#novalnetSepa_account_number').val()));
        $nnsepa_j('#sepa_overlay_bic_span').html(removeUnwantedSpecialChars($nnsepa_j('#novalnetSepa_bank_code').val()));
    }

    $nnsepa_j('#sepa_overlay_payee_span').html('Novalnet AG');
    $nnsepa_j('#sepa_overlay_creditoridentificationnumber_span').html('DE53ZZZ00000004253');
    $nnsepa_j('#sepa_overlay_enduserfullname_span').html(removeUnwantedSpecialChars($nnsepa_j('#novalnetSepa_account_holder').val()));
    $nnsepa_j('#sepa_overlay_enduserfullname_span1').html(removeUnwantedSpecialChars($nnsepa_j('#novalnetSepa_account_holder').val()));
    $nnsepa_j('#sepa_overlay_endusercountry_span').html($nnsepa_j('#novalnetSepa_bank_country').val());
    $nnsepa_j('#sepa_overlay_mandatedate_span').html(normalizeDate($nnsepa_j('#result_mandate_date').val()));
    $nnsepa_j('#sepa_overlay_mandatereference_span').html($nnsepa_j('#result_mandate_ref').val());
    $nnsepa_j('#sepa_mandate_overlay_block_first').css({display: 'none', position: 'fixed'});
    $nnsepa_j('#sepa_mandate_overlay_block').css({display: 'block', position: 'fixed'});

    if ($nnsepa_j(window).width() < 650) {
        $nnsepa_j('#sepa_mandate_overlay_block').css({left: ($nnsepa_j(window).width() / 2), top: ($nnsepa_j(window).height() / 2), width: 0, height: 0}).animate({left: (($nnsepa_j(window).width() - ($nnsepa_j(window).width() - 10)) / 2), top: 5, width: ($nnsepa_j(window).width() - 10), height: ($nnsepa_j(window).height() - 10)});
        $nnsepa_j('#overlay_window_block_body').css({'height': ($nnsepa_j(window).height() - 95)});
    } else {
        $nnsepa_j('#sepa_mandate_overlay_block').css({left: (($nnsepa_j(window).height() - (490 / 2))), top: (($nnsepa_j(window).height() - 490) / 2), width: (600), height: (490)});
        $nnsepa_j('#overlay_window_block_body').css({'height': (400)});
    }

    return true;
}

function normalizeDate(input)
{
    var parts = input.split('-');
    return(parts[2] + '.' + parts[1] + '.' + parts[0]);
}

function closeMandateOverlay(mandate)
{
    $nnsepa_j('#sepa_mandate_overlay_block').hide(60);
    $nnsepa_j('.bgCover').css({display: 'none'});
    return true;
}

function mandate_confirm_btn_submit()
{
    $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
    $nnsepa_j('#nnsepa_iban_confirmed').val(1);
    closeMandateOverlay(0);
}

function mandate_cancel_btn_submit()
{
    $nnsepa_j('#nnsepa_iban_confirmed').val(0);
    $nnsepa_j('#novalnetSepa_mandate_confirm').removeAttr("checked");
    $nnsepa_j('#novalnetSepa_mandate_confirm').attr('disabled', false);
    closeMandateOverlay(0);
    $nnsepa_j('#novalnetSepa_iban').remove();
    $nnsepa_j('#novalnetSepa_bic').remove();
}

function removeUnwantedSpecialChars(value)
{
    if (value != 'undefined' || value != '') {
        value.replace(/^\s+|\s+$/g, '');
        return value.replace(/[\/\\|\]\[|#@,+()`'$~%.":;*?<>!^{}=_-]/g,'');
    }
}

function ibanbicValidate(event)
{
    var keycode = ('which' in event) ? event.which : event.keyCode;
    var reg = /^(?:[A-Za-z0-9]+$)/;
    if(event.target.id == 'novalnetSepa_account_holder') var reg = /^(?:[A-Za-z0-9&\s]+$)/;
    return (reg.test(String.fromCharCode(keycode)) || keycode == 0 || keycode == 8 || (event.ctrlKey == true && keycode == 114))? true : false;
}

function generateUniqueId()
{
    var length = 30;        //Maximum Hash Limit
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

function getSepaHttpProtocol()
{
    var url = location.href;
    var urlArr = url.split('://');
    var urlPrefix = ((urlArr[0] != '' && urlArr[0] == 'https') ? 'https' : 'http');
    return urlPrefix + "://payport.novalnet.de/sepa_iban";
}

sepaRefillcall();
$nnsepa_j(document).ready(function() {
    Ajax.Responders.register({ onComplete: function() {
        if (Ajax.activeRequestCount == 0 && $nnsepa_j('input[name="payment[method]"]:checked').val() == 'novalnetSepa'
            && $nnsepa_j('#nnsepa_iban_confirmed').val() == 0) {
            sepaRefillcall();
        }
      }
    });
});
