jQuery(document).ready(function(){
  
    var loadData = (el) => {
        var val = jQuery(el.target).val() == undefined ? jQuery(el).val() : jQuery(el.target).val(); //jQuery(this).val();
        var field = jQuery(el.target).attr('name') == undefined ? jQuery(el).attr('name') : jQuery(el.target).attr('name');
        var data = {'action': 'fst_shipping_classes'};
		console.log(val);

        if(field == 'billing_state') {
            data.state =  val;
            data.city = jQuery("#billing_city").val();
            data.payment_method = jQuery('input[name^="payment_method"]:checked').val();
        } 
        if(field  == 'billing_city') {
            data.state = jQuery("#billing_state").find(':selected').val();
            data.city =  val;
            data.payment_method = jQuery('input[name^="payment_method"]:checked').val();
         }
        if(field == 'payment_method') {
            data.payment_method =  jQuery('input[name^="payment_method"]:checked').val(); //can't access 'val' directly
            data.state = jQuery("#billing_state").find(':selected').val();
            data.city = jQuery("#billing_city").val();
        }

        jQuery('#fst_plugin_data').html("<br/><i class='fa fa-spinner fa-spin'></i> Fetching data...");
        update_checkout();
 
        jQuery.post(ajax_object.ajax_url, data, function(response) {
            jQuery('#fst_plugin_data').html(response);
        });
    }
    
    //Get shipping cost from API
    if(jQuery("input[name='billing_city']").length > 0) {
        loadData(jQuery("input[name='billing_city']"));
    }
    if(jQuery("input[name='billing_state']").length > 0) {
        loadData(jQuery("input[name='billing_state']"));
    }
    loadData(jQuery("input[name^='payment_method']"));
    
    jQuery("input[name='billing_city'], select[name='billing_state']").change(loadData);

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
    
    checkout_form.on( 'change', 'input[name^="payment_method"]', loadData);

    jQuery( document.body ).on( 'checkout_error', function() {
        var error_text = jQuery('.woocommerce-error').find('li').first().text();
        if ( error_text=='Please select a shipping method' ) {
            jQuery('#fst_plugin_data').css('border-left', '3px solid #e2401c');
        }
    
    });
});

