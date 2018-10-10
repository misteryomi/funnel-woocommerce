jQuery(document).ready(function(){
 
    //Get shipping cost from API
    jQuery("input[name='billing_city'], select[name='billing_state']").change(function(){
        var val = jQuery(this).val();
        var field = jQuery(this).attr('name');
        var data = {'action': 'fst_shipping_classes'};

        if(field == 'billing_state') {
            data.state =  val;
            data.city = jQuery("#billing_city").val();
        } 
        if(field  == 'billing_city') {
            data.state = jQuery("#billing_state").find(':selected').val();
            data.city =  val;
        }

       // console.log(data);
        jQuery('#fst_plugin_data').html("Fetching data...");
        update_checkout();
 
        jQuery.post(ajax_object.ajax_url, data, function(response) {
            jQuery('#fst_plugin_data').html(response);
        });
    });


    jQuery('#fst_plugin_data').on('change', '.fst_package', function() {
        $_this = this;

        jQuery('#fst_plugin_data .fst_package').each(function(i, el) {
            if(el.getAttribute('data-counter') != $_this.getAttribute('data-counter')) {
                el.value = '';
            }
        });
        var data = {'action': 'fst_calculate_costs', 'cost': jQuery(this).find(':selected').attr('data-price'), 'id': jQuery(this).attr('data-id') };

        jQuery.post(ajax_object.ajax_url, data, function(response) {
            update_checkout();
        });
    });


    function update_checkout() {
        jQuery(document.body).trigger("update_checkout");
    }


    var checkout_form = jQuery( 'form.checkout' );
    checkout_form.on( 'checkout_place_order', function() {
      checkout_form.append('<input type="hidden" name="m_prevent_submit" value="1">');
    });    

    jQuery( document.body ).on( 'checkout_error', function() {
        var error_text = jQuery('.woocommerce-error').find('li').first().text();
//        console.log(error_text);
        if ( error_text=='Please select a shipping method' ) {
            jQuery('#fst_plugin_data').css('border-left', '3px solid #e2401c');
        }
    
    });
});
