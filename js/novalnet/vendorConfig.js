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
var $nnConfig_j = jQuery.noConflict();

function checkVendorConfig()
{
    var lang = $nnConfig_j('#nn_lang').val();
    var serverIp = $nnConfig_j('#nn_server_ip').val();
    var publicKey = $nnConfig_j('#novalnet_global_novalnet_public_key').val();

    if (serverIp == '' || serverIp ==undefined || publicKey == '' || publicKey ==undefined) {
        return false;
    }

    var vendorConfigReq = {"api_config_hash": publicKey, "system_ip": serverIp, "lang": lang};
    var reqParams = $nnConfig_j.param(vendorConfigReq);
    vendorConfigCrossDomainAjax(reqParams);
}

function vendorConfigCrossDomainAjax(vendorConfigReq)
{
    var payportUrl = "https://payport.novalnet.de/autoconfig";
    // IE8 & 9 only Cross domain JSON POST request
    if ('XDomainRequest' in window && window.XDomainRequest !== '') {
        var xdr = new XDomainRequest(); // Use Microsoft XDR
        xdr.open('POST', payportUrl);
        xdr.onload = function() {
            getConfigResultValues($nnConfig_j.parseJSON(this.responseText), reqCall);
        };

        xdr.send(vendorConfigReq);
    } else {
        $nnConfig_j.ajax(
            {
                type: 'POST',
                url: payportUrl,
                data: vendorConfigReq,
                dataType: 'json',
                success: function(data) {
                    getConfigResultValues(data);
                },
            }
        );
    }
}

function getConfigResultValues(response)
{
    if(response.vendor_id !=undefined && response.product_id !=undefined) {
        var savedTariff = $nnConfig_j('#nn_tariff_id').val();
        var savedSubsTariff = $nnConfig_j('#nn_subsTariff_id').val();
        var vendorScriptUrl = $nnConfig_j('#nn_vendorscript_url').val();
        $nnConfig_j('#novalnet_global_novalnet_merchant_id').val(response.vendor_id);
        $nnConfig_j('#novalnet_global_novalnet_auth_code').val(response.auth_code);
        $nnConfig_j('#novalnet_global_novalnet_product_id').val(response.product_id);
        $nnConfig_j('#novalnet_global_novalnet_password').val(response.access_key);

        $nnConfig_j('#novalnet_global_novalnet_merchant_id').attr('readonly', true);
        $nnConfig_j('#novalnet_global_novalnet_auth_code').attr('readonly', true);
        $nnConfig_j('#novalnet_global_novalnet_product_id').attr('readonly', true);
        $nnConfig_j('#novalnet_global_novalnet_password').attr('readonly', true);

        if (vendorScriptUrl != '') {
            $nnConfig_j('#novalnet_global_merchant_script_vendor_script_url').val(vendorScriptUrl);
        }

        var tariffId = response.tariff_id.split(',');
        var tariffName = response.tariff_name.split(',');
        var tariffType = response.tariff_type.split(',');
        $nnConfig_j("#novalnet_global_novalnet_tariff_id option").remove();
        $nnConfig_j("#novalnet_global_novalnet_subscrib_tariff_id option").remove();

        for(i=0; i< tariffId.length; i++) {
            var tariffIdVal  = tariffId[i].split(':');
            var tariffNameVal = tariffName[i].split(':');
            var tariffTypeVal = tariffType[i].split(':');
            var tariffText = (tariffNameVal[2] !=undefined) ? tariffNameVal[1].trim()+':'+tariffNameVal[2].trim() : tariffNameVal[1];

            if (tariffTypeVal[1].trim() != 4) {
                $nnConfig_j('#novalnet_global_novalnet_tariff_id').append(
                    $nnConfig_j(
                        '<option>', {
                            value: tariffIdVal[1].trim(),
                            text : tariffText
                        }
                    )
                );

                if (savedTariff != undefined && savedTariff == tariffIdVal[1].trim()) {
                    $nnConfig_j('#novalnet_global_novalnet_tariff_id option[value='+tariffIdVal[1].trim()+']').attr("selected", "selected");
                }
            }

            if (tariffTypeVal[1].trim() == 1 || tariffTypeVal[1].trim() == 4) {
                $nnConfig_j('#novalnet_global_novalnet_subscrib_tariff_id').append(
                    $nnConfig_j(
                        '<option>', {
                            value: tariffIdVal[1].trim(),
                            text : tariffText
                        }
                    )
                );

                if (savedSubsTariff != undefined && savedSubsTariff == tariffIdVal[1].trim()) {
                    $nnConfig_j('#novalnet_global_novalnet_subscrib_tariff_id option[value='+tariffIdVal[1].trim()+']').attr("selected", "selected");
                }
            }
        }
    } else {
        alert(response.config_result);
        return false;
    }
}

$nnConfig_j(document).ready(
    function() {
        checkVendorConfig();
        $nnConfig_j('#novalnet_global_novalnet_public_key').change(
            function() {
                checkVendorConfig();
            }
        );
    }
);
