/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

+ function ($) {

$(function () {
jQuery('#advance_gateway_accounts').on('click', 'a.add', function () {
  
var size = jQuery('#advance_gateway_accounts').find('tbody .account').length;
jQuery('<tr class="account">\
<td class="sort"></td>\
<td><input type="text" name="advance_gateway_account_name[' + size + ']" /></td>\
<td><input type="text" name="advance_gateway_account_number[' + size + ']" /></td>\
<td><input type="text" name="advance_gateway_bank_name[' + size + ']" /></td>\
<td><input type="text" name="advance_gateway_sort_code[' + size + ']" /></td>\
<td><input type="text" name="advance_gateway_iban[' + size + ']" /></td>\
<td><input type="text" name="advance_gateway_bic[' + size + ']" /></td>\
</tr>').appendTo('#advance_gateway_accounts table tbody');
        return false;
});
});
        }(jQuery);
