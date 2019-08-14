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
 * https://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   js
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

function novalnet_sepa_iframe(iframe)
{
    document.getElementById('nnsepa_loading').style.display = 'none';
    var frameObj = (iframe.contentWindow || iframe.contentDocument);
    if (frameObj.document)
        frameObj = frameObj.document;

    var account_holder = frameObj.getElementById("novalnet_sepa_owner");

    var account_no = frameObj.getElementById("novalnet_sepa_accountno");
    var bank_code = frameObj.getElementById("novalnet_sepa_bankcode");
    var sepa_iban = frameObj.getElementById("novalnet_sepa_iban");
    var sepa_swiftbic = frameObj.getElementById("novalnet_sepa_swiftbic");
    var nnsepa_unique_id = frameObj.getElementById("nnsepa_unique_id");
    var nnsepa_hash = frameObj.getElementById("nnsepa_hash");
    var sepa_country = frameObj.getElementById("novalnet_sepa_country");
    var iban_confirmed = frameObj.getElementById("novalnet_sepa_swiftbic_confirm");
    var nnsepa_iban_confirmed = frameObj.getElementById("nnsepa_iban_confirmed")

    account_holder.onkeyup = function()
    {
        document.getElementById('novalnet_sepa_owner').value = account_holder.value;
        document.getElementById('novalnet_sepa_owner').disabled = false;
    }

    account_no.onkeyup = function()
    {
        set_sepa_panhash_uniquid(nnsepa_unique_id, nnsepa_hash, account_holder, nnsepa_iban_confirmed, account_no, bank_code, sepa_iban, sepa_swiftbic, sepa_country);
    }

    bank_code.onkeyup = function()
    {
        set_sepa_panhash_uniquid(nnsepa_unique_id, nnsepa_hash, account_holder, nnsepa_iban_confirmed, account_no, bank_code, sepa_iban, sepa_swiftbic, sepa_country);
    }

    iban_confirmed.onclick = function()
    {
       setInterval(function(){set_sepa_panhash_uniquid(nnsepa_unique_id, nnsepa_hash, account_holder, nnsepa_iban_confirmed, account_no, bank_code, sepa_iban, sepa_swiftbic, sepa_country);},1000);
    }
}


function set_sepa_panhash_uniquid(uniquid, panhash, account_holder, nnsepa_iban_confirmed, account_no, bank_code, sepa_iban, sepa_swiftbic, sepa_country)
{

    document.getElementById('novalnet_sepa_pan_hash').value = panhash.value;
    document.getElementById('novalnet_sepa_pan_hash').disabled = false;

    document.getElementById('novalnet_sepa_unique_id').value = uniquid.value;
    document.getElementById('novalnet_sepa_unique_id').disabled = false;

    document.getElementById('novalnet_sepa_owner').value = account_holder.value;
    document.getElementById('novalnet_sepa_owner').disabled = false;

    document.getElementById('novalnet_sepa_iban_confirmed').value = nnsepa_iban_confirmed.value;
    document.getElementById('novalnet_sepa_iban_confirmed').disabled = false;

    var sepa_owner = 0;
    var sepa_accountno = 0;
    var sepa_bankcode = 0;
    var nn_sepa_iban = 0;
    var nn_sepa_swiftbic = 0;
    var sepa_hash = 0;
    var nn_sepa_country = 0;

    if (account_holder.value != '')
        sepa_owner = 1;
    if (account_no.value != '')
        sepa_accountno = 1;
    if (bank_code.value != '')
        sepa_bankcode = 1;
    if (sepa_iban.value != '')
        nn_sepa_iban = 1;
    if (sepa_swiftbic.value != '')
        nn_sepa_swiftbic = 1;
    if (panhash.value != '')
        sepa_hash = 1;
    if (sepa_country.value != '') {
        var element_country = sepa_country;
        nn_sepa_country = 1 + '-' + element_country.options[element_country.selectedIndex].value;
    }

    var novalnet_sepa_field_validator = sepa_owner + ',' + sepa_accountno + ',' + sepa_bankcode + ',' + nn_sepa_iban + ',' + nn_sepa_swiftbic + ',' + sepa_hash + ',' + nn_sepa_country;

    document.getElementById('novalnet_sepa_field_validator').value = novalnet_sepa_field_validator;
    document.getElementById('novalnet_sepa_field_validator').disabled = false;

}
