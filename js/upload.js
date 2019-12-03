/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

+function ($) {

    $(function () {
        jQuery('body').on('click', 'div.remove_img', function () {
            var ajax_url = $("#ajax_url").val();
            var form_data = new FormData();
            var imgurl = jQuery(this).attr('data-url');
            form_data.append('url', imgurl);
            form_data.append('action', 'remove_upload');
            jQuery.ajax({
                url: ajax_url, // there on the admin side, do-it-yourself on the front-end
                data: form_data,
                type: 'POST',
                contentType: false,
                processData: false,
                success: function (response) {
                    jQuery("#show_uploaded_file").html('');
                }
            });
        });
    });
}(jQuery);
