<?php

defined('ABSPATH') or die('do not die please');
/*
* @wordpress-plugin
* Plugin Name:       Funnel WC
* Description:       Bring the power of the Funnel API to your WooCommerce merchant website and enjoy unlimited logistics possibilities
* Version:           1.0.2
* Author:            Funnel Logistics Technologies
* Author URI:        http://funnel.ng
* License:           GPL-2.0+
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:       fst-api-plugin
* Domain Path:       /languages
*/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    include 'inc/FSTWeightless.class.inc.php';

    define('FST_API_URL', 'https://api.funnel.ng/v1/');


    add_action('wp_loaded', 'fst_enqueue_swat');
    add_action( 'wp_ajax_set_update_api_key', 'prefix_ajax_set_update_api_key');
    add_action('woocommerce_review_order_before_payment', 'fst_dynamic_data_section');
    
    //WP Ajax
    add_action( 'wp_ajax_fst_shipping_classes', 'fst_process_shipping_classes' );
    add_action( 'wp_ajax_nopriv_fst_shipping_classes', 'fst_process_shipping_classes' );
    
    function fst_enqueue_swat()
    {
        wp_enqueue_style( 'style', plugin_dir_url( __FILE__ ) . '/assets/css/style.css' );
        wp_enqueue_script( 'custom', plugin_dir_url( __FILE__ ) . '/assets/js/custom.js', array ( 'jquery' ), 1.1, true);
        wp_localize_script( 'custom', 'ajax_object',
                 array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'we_value' => 1234 ) );
    }
    
    function fst_action_links( $links ) {
        $links = array_merge( array(
            '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=fst-shipping-method' ) ) . '">' . __( 'Settings', 'fst-shipping-api' ) . '</a>'
        ), $links );
        return $links;
    }
    add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fst_action_links' );


    /**
     * Set Section for jQuery to autopopulate anytime shipping data changes
     */
    function fst_dynamic_data_section() {
        echo "<div id='fst_plugin_data'></div><br/>";
    }

    function fst_get_api_key() {
        $shipping_methods = WC()->shipping->load_shipping_methods();
        return $shipping_methods['fst-shipping-method']->settings['api_key'];
    }

    function fst_get_excluded_categories() {
        $shipping_methods = WC()->shipping->load_shipping_methods();
        return $shipping_methods['fst-shipping-method']->settings['excluded_categories'];
    }

    function fst_process_shipping_classes() {
        $shipToData = [];        

        $shipToData['state'] =  sanitize_text_field($_POST['state']);
        $shipToData['city'] =  sanitize_text_field($_POST['city']);
        $shipToData['payment_method'] = sanitize_text_field($_POST['payment_method']);

        if(is_user_logged_in()){
            fst_registered_user_checkout($shipToData);
        }
        else{
            fst_guest_user_checkout($shipToData);
        }
        WC()->session->__unset( 'fst-shipping-cost' );        

        die();
    }


    function fst_guest_user_checkout($shipToData = '')
    {
        global $woocommerce;
    
        $cart = WC()->cart;        
    
        $customer = $cart->get_customer();
    
        $itemWeight = $woocommerce->cart->get_cart_contents_weight();
    
        if(!empty($shipToData) && isset($shipToData['state'])) {
            $shipTo = WC()->countries->get_states($customer->get_billing_country())[$shipToData['state']];
        } else {
            if($customer->get_billing_state()){
                $shipTo = WC()->countries->get_states($customer->get_shipping_country())[$customer->get_shipping_state()];
            }
            else{
                $shipTo = NULL;
            }
        }
        
        if(!empty($shipToData) && isset($shipToData['city'])) {
            $shipToArea = $shipToData['city'];
        } else {
            if($customer->get_billing_city()){
                $shipToArea = $customer->get_shipping_city();
            }
            else{
                $shipToArea = NULL;
            }
        }

        $orderItems = [];
        $items = $woocommerce->cart->get_cart();
        $count = 0;
        $totalWeight = 0;
        $all_weights = [];
        if(isset($items)){
            
            foreach($items as $item => $values) { 
            $_product =  wc_get_product( $values['data']->get_id()); 
            $price = get_post_meta($values['product_id'] , '_price', true);
            $orderItems[] = array(
                'title' => $_product->get_title(),
                'quantity' => $values['quantity'],
                'price' => get_post_meta($values['product_id'] , '_price', true),
                'weight' => $_product->get_weight()
            );
            
                $all_weights[] = ['weight' => $_product->get_weight(), 'quantity' => $values['quantity']];
                $count++;
            }
        }
    
        $cartInfo['items'] = $orderItems;
        $cartInfo['count'] = $count;
        $cartInfo['totalWeight'] = $itemWeight;
        
        WC()->session->set('cartItems', json_encode($cartInfo));
        
        // Create array of required values to calculate shipping costs        
        $fstOrder = [];
        $fstOrder['weight'] = $itemWeight;
        $fstOrder['to'] = $shipTo;
        $fstOrder['areaTo'] = $shipToArea;
        $fstOrder['items'] = json_encode($all_weights);

        $fstOrder['cod'] = 0;
        if($shipToData['payment_method'] == 'cod') {
            $fstOrder['cod'] = 1;
        }


        fst_checkout($fstOrder);

    }
    
    function fst_registered_user_checkout($shipToData = '')
    {
        global $woocommerce;
            
        $customer = $woocommerce->cart->get_customer();
    
        $itemWeight = $woocommerce->cart->get_cart_contents_weight();
    
    
        if(!empty($shipToData) && isset($shipToData['state'])) {
            $shipTo = WC()->countries->get_states($customer->get_billing_country())[$shipToData['state']];
        } else {
            if($customer->get_billing_state()){
                $shipTo = WC()->countries->get_states($customer->get_billing_country())[$customer->get_billing_state()];
            }
            else{
                $shipTo = NULL;
            }
        }

        if(!empty($shipToData) && isset($shipToData['city'])) {
            $shipToArea = $shipToData['city'];
        } else {
            if($customer->get_billing_city()){
                $shipToArea = $customer->get_billing_city();
            }
            else{
                $shipToArea = NULL;
            }
        }
    
        $orderItems = [];
        $items = $woocommerce->cart->get_cart();
        $count = 0;
        $totalWeight = 0;

        if(isset($items)){
            $all_weights = [];
            $excluded_categories = explode(', ', fst_get_excluded_categories());
            
            foreach($items as $item => $values) { 
            $_product =  wc_get_product( $values['data']->get_id()); 

            //Check if Item is in excluded list. 
            if(array_count_values($excluded_categories) > 0) {
                
                    $_existing = false;

                    foreach($_product->category_ids as $category) {
                        if(in_array($category, $excluded_categories)) {
                            $_existing = true;
                            continue;
                        }
                    }
                    
                    if($_existing) continue;
            }

                $price = get_post_meta($values['product_id'] , '_price', true);
                $orderItems[] = array(
                    'title' => $_product->get_title(),
                    'quantity' => $values['quantity'],
                    'price' => get_post_meta($values['product_id'] , '_price', true),
                    'weight' => $_product->get_weight()
                );
                $all_weights[] = ['weight' => $_product->get_weight(), 'quantity' => $values['quantity']];
                $count++;
            }
        } 
        
        $cartInfo['items'] = $orderItems;
        $cartInfo['count'] = $count;
        $cartInfo['totalWeight'] = $itemWeight;
        
        WC()->session->set('cartItems', json_encode($cartInfo));
        
        // Create array of required values to calculate shipping costs
        
        $fstOrder = [];
        $fstOrder['weight'] = $itemWeight;
        $fstOrder['to'] = $shipTo;
        $fstOrder['areaTo'] = $shipToArea;
        $fstOrder['items'] = json_encode($all_weights);

        $fstOrder['cod'] = 0;
        if($shipToData['payment_method'] == 'cod') {
            $fstOrder['cod'] = 1;
        }

        
        fst_checkout($fstOrder);

    }

    function fst_checkout($_fst_orders)
    {
        
        $shipping_methods = WC()->shipping->load_shipping_methods();
		
		$cod = $_fst_orders['cod'] ? '&cod=1' : '';
    
        if($shipping_methods['fst-shipping-method']->enabled == "yes")
        {
                echo "<div class='fst-title'><h4>Choose a courier <abbr class='required' title='required'>*</abbr></h4></div>";
                
                if(!$_fst_orders['to']){
                    echo '<p>Please select your <strong>shipping state</strong> to view shipping options</p>';
                }
                if(!$_fst_orders['areaTo']){
                    echo '<p>Please select your <strong>shipping city</strong> to view shipping options</p>';
                }
                
               $url = FST_API_URL . "shipping/calculate?weight=".$_fst_orders['weight']."&shipTo=".urlencode($_fst_orders['to'])."&areaTo=".urlencode($_fst_orders['areaTo'])."&items=".$_fst_orders['items'].$cod;
               $response = fst_http_get($url, fst_get_api_key());
			
                    if (!$response) {
                
                        echo "<p>Error retrieving shippi/ng rates</p>";
                
                    } else {
                        $data = wp_remote_retrieve_body( $response );  
                        $data = json_decode($data,true);	
					
                        WC()->session->set('requestSessionId', $data['requestSessionId']);
                
                        if($data["data"]) {
                
                        echo "
                        <div id='refreshing-box'></div>
                
                        <div class='shipping-loader'></div>";
                
                        echo "<div id='shippingElement' class='packagesBox'>";

                        WC()->session->set("data", $data["data"]);
                        $count = 0;
                
                        foreach($data["data"] as $information) {
                        echo "<div class='form-group'>";
                        echo "<label>".$information["Shipper"]["name"]."</label><br/>";
                
                        echo "<select name='fst_package' data-counter=".$count++." data-id =".$information["Shipper"]["id"]." class='fst_package form-control'>";
                
                                echo "<option selected value='' disabled class='packageSelect'>Select a delivery service</option>";
                        
                                foreach($information["ShippingClasses"] as $ShippingClass ) {
                        
                                    echo "<option value =".$ShippingClass["Id"]." data-price=".$ShippingClass["customerPrice"]." class='packageSelect'>";
                        
                                    echo $ShippingClass["Name"]." ";
                        
                                    echo "NGN" . number_format($ShippingClass["customerPrice"], 2);
                        
                                    echo "</option>";
                                    }
                            echo"</select>";
                            echo "</div>";

                        }
                } else {
                    echo "<h5><b>We do not offer delivery services your destination yet</b></h5> <div id='refreshing-box'></div>";
                }
                    echo "</div>";
                }
        }
    }

    /**
     * Ajax-called function to set a session for `fst-shipping-cost` if $_POST['cost'] is set. 
     */

    function fst_calculate_costs()
    {
        global $woocommerce;
        if(isset($_POST['id'])) {
            WC()->session->set('Shipper_shipperID', sanitize_text_field($_POST['id']));
        }

        if(isset($_POST['cost'])) {
            WC()->session->set('fst-shipping-cost', sanitize_text_field($_POST['cost']));
        } else {
            WC()->session->set('fst-shipping-cost', 0);
        }
        return true;
    }
    add_action( 'wp_ajax_fst_calculate_costs', 'fst_calculate_costs' );
    add_action( 'wp_ajax_nopriv_fst_calculate_costs', 'fst_calculate_costs' );


    function fst_m_prevent_submission($posted) {
        //Check if selected shipping method is `fst-shipping-method` and if a package is selected
        if ((isset($posted['shipping_method']) && ($posted['shipping_method'][0] == 'fst-shipping-method')) && !isset($_POST['fst_package']) && wc_notice_count( 'error' ) == 0 ) {
            wc_add_notice( __("Please choose a courier", 'fst-shipping-api' ), 'error');
        }         
        if (empty($_POST['billing_phone'])) {
            wc_add_notice( __("Phone number field is mandatory for shipping", 'fst-shipping-api' ), 'error');
        }  
        $notice = get_option( 'fst_checkout_form_response', false );       
        if($notice) {
            wc_add_notice( __($notice, 'fst-shipping-api' ), 'error');
            delete_option( 'fst_checkout_form_response' );
        }
     }
   add_action('woocommerce_after_checkout_validation', 'fst_m_prevent_submission');

   function fst_require_phone( $address_fields ) {
        if(!is_user_logged_in()) {
            unset($address_fields['billing_phone']['required']);
            $address_fields['billing_phone']['required'] = true;
        }
        return $address_fields;    
    }
    add_filter( 'woocommerce_billing_fields', 'fst_require_phone', 10, 1 );

    function fst_clear_wc_shipping_rates_cache(){
        $packages = WC()->cart->get_shipping_packages();
        foreach ($packages as $key => $value) {
            $shipping_session = "shipping_for_package_$key";
            unset(WC()->session->$shipping_session);
        }
    }
    add_filter('woocommerce_checkout_update_order_review', 'fst_clear_wc_shipping_rates_cache');    


    function fst_shipping_method() {
        require_once('inc/FstShippingMethodSetup.class.inc.php');
    }
    add_action( 'woocommerce_shipping_init', 'fst_shipping_method' );
 
    function fst_add_fst_shipping_method( $methods ) {
        $methods[] = 'FST_Shipping_Method';
        return $methods;
    } 
    add_filter( 'woocommerce_shipping_methods', 'fst_add_fst_shipping_method' );


    // Clear default shipping option.
    add_filter( 'woocommerce_shipping_chosen_method', '__return_false', 99);

    // Clear default payment option.
    add_filter( 'pre_option_woocommerce_default_gateway' . '__return_false', 99 );


    /**
     * Disable shipping calculator on the cart page
     */

    function fst_disable_shipping_calc_on_cart( $show_shipping ) {

        //NOTICE:: set condition to check if `fst-shipping-method` is empty before it hides here.
        if( is_cart() ) {
            return false;
        }
        return $show_shipping;
    }
    add_filter( 'woocommerce_cart_ready_to_calc_shipping', 'fst_disable_shipping_calc_on_cart', 99 );



    
    function fst_verify_api_key() {
        $api_key = $_REQUEST['woocommerce_fst-shipping-method_api_key'];


        $url = FST_API_URL . "shipping/verify";
        $response = fst_http_get($url, fst_get_api_key());

        
        if (!$response) {
            $message = 'Error connecting to host server';    
        } else {
            $data = wp_remote_retrieve_body( $response );  
            $response = json_decode($data,true);	
            
            update_option('fst_default_shop_state', $response->defaultShop->state);
            update_option('fst_default_shop_area', $response->defaultShop->area);

            if($response->status !== 'success') {
                $message = $response->message;                
            }
        }
        if(isset($message)) {
            update_option( 'fst_submit_form_response', $message);
        }

        return true;
    }
    add_filter('woocommerce_update_options_shipping_fst-shipping-method', 'fst_verify_api_key');

    /**
     * Hide shipping rates when fst-shipping-method shipping is available.
     *
     * @param array $rates Array of rates found for the package.
     * @return array
     */
    function fst_checkout_shipping_rates_options( $rates ) {
        $shipping_methods = WC()->shipping->load_shipping_methods();
        $override = $shipping_methods['fst-shipping-method']->settings['override'];
        $updated_rates = [];

        if($override == 'yes') {
            //if override is 'yes', display only `fst-shipping-method`, else, set chosen method as fst-shipping-method
            foreach ( $rates as $rate_id => $rate ) {
                if ( 'fst-shipping-method' === $rate->method_id ) {
                    $updated_rates[ $rate_id ] = $rate;
                    break;
                }
            }
        }
        return ! empty( $updated_rates ) ? $updated_rates : $rates;
    }
    add_filter( 'woocommerce_package_rates', 'fst_checkout_shipping_rates_options', 100 );


    function fst_before_shipping( $checkout ) {
        WC()->session->set('chosen_shipping_methods', ['fst-shipping-method'] );    
    }
    add_action( 'woocommerce_before_checkout_shipping_form', 'fst_before_shipping');

    /**
     * Display custom notices
     */
    function fst_custom_notice(){
        $notice  = get_option( 'fst_submit_form_response', false );
        if($notice) {
            delete_option( 'fst_submit_form_response' );
            fst_display_error_notice( $notice );
        }
    }
    add_action( 'admin_notices', 'fst_custom_notice');         

    function fst_display_error_notice($notice) {
        echo '<div class="notice notice-error is-dismissible">
            <p>'.$notice.'</p>
        </div>';
    }
    function fst_display_success_notice($notice) {
        echo '<div class="notice notice-success is-dismissible">
            <p>'.$notice.'</p>
        </div>';
    }

    function fst_woocommerce_order_complete_post_variable($data, $errors){
        global $woocommerce;
    
        $cart = WC()->cart;
    
        $customer = $cart->get_customer();

        $first_name = ($customer->get_billing_first_name() !== '')? $customer->get_billing_first_name(): sanitize_text_field($_POST['billing_first_name']);
        $last_name = ($customer->get_billing_last_name() !== '')? $customer->get_billing_last_name(): sanitize_text_field($_POST['billing_last_name']);

        $data=array();

        $state = WC()->countries->get_states($customer->get_shipping_country())[$customer->get_shipping_state()];

        $data["sessionId"]= WC()->session->get('requestSessionId');
        $data["shippingClass"]= sanitize_text_field($_POST['fst_package']);
        $data["shippingParther"]= WC()->session->get('Shipper_shipperID');
        $data["customerEmail"]= ($customer->get_billing_email() !== '')? $customer->get_billing_email() : sanitize_email($_POST['billing_email']);
        $data["customerName"]= $first_name." ".$last_name;
        $data["description"]= "transaction";
        $data["customerAddress"]= $customer->get_billing_address().', '.$customer->get_billing_city().', '.$state;
        $data["customerPhone"]= ($customer->get_billing_phone() !== '')? $customer->get_billing_phone(): sanitize_text_field($_POST['billing_phone']);
        
        $cod = '';
        $cod_price = WC()->cart->subtotal;

        $body = array(
            "sessionId" => $data["sessionId"],
            "shippingClass" => $data["shippingClass"],
            "shippingPartner" => WC()->session->get("Shipper_shipperID"),
            "customerEmail" => $data["customerEmail"],
            "customerName" => $data["customerName"],
            "description" => $data["description"],
            "customerAddress" => $data["customerAddress"],
            "customerPhone" => $data["customerPhone"],
            "items" => WC()->session->get('cartItems')          
        );

        if(WC()->session->get('chosen_payment_method') == 'cod') {
            $body['cod'] = 1;
            $body['cod_price'] = $cod_price;
        }

        $url = FST_API_URL . 'shipping/submit';
        
        $response = fst_http_post($url, $body, fst_get_api_key());
		
		
        delete_option( 'fst_submit_form_response' );

		if($response['response']['code'] != 200) {

//	        WC()->session->__unset( 'fst-shipping-cost' );        
            // update_option('fst_checkout_form_response', 'Error processing request. Please choose another shipping option');
//			m_prevent_submission();
//			return false;
        		wc_add_notice( __( 'Error processing request. Please choose another shipping option' ), 'error' );
    
		}

   }      
  add_action('woocommerce_after_checkout_validation','fst_woocommerce_order_complete_post_variable', 10, 2);


    function fst_check_noweight_products() {
        $args = [
            'post_type' => 'product'
        ];
        $products = query_posts($args);
        $_products = [];
        foreach($products as $product) {
            $productDetails = wc_get_product( $product->ID );
            if(!$productDetails->get_weight()) {
                $_products[] = $product->ID;
            }
        }
        return $_products;
    }
    
    function fst_display_noweights_error(){

        $products = new FST_Weightless_List_Table();
        $count = $products->get_count();

        if($count > 0) {
            fst_display_error_notice( "<h3>Funnel Shipping Notice!</h3><strong>Notice: You have <a href='".admin_url('?page=fst-weightcheck')."'>".$count." product(s)</a> with no weight set. A default weight would be used for your shipping calculation instead.</strong>");
        }
    }
    add_action( 'admin_notices', 'fst_display_noweights_error');     
    


    function fst_weight_errors_display_page(){
        ?>
        <div class="wrap">
            <h1><?php _e( 'Funnel for Woocommerce: Weight Checker', 'fst-shipping-api' ); ?></h1>
        <?php
            $_table_list = new Weightless_List_Table();
            $_table_list->prepare_items();
            echo '<input type="hidden" name="post" value="" />';
            echo '<input type="hidden" name="section" value="products" />';
            
            $_table_list->views();
            $_table_list->search_box( __( 'Search Key', 'fst-shipping-api' ), 'key' );
            $_table_list->display();
        ?>
        </div>
        <?php
    }

    function fst_add_errorspage_to_menu() {
        add_submenu_page(NULL,'Page Title','Page Title', 'manage_options', 'fst-weightcheck', 'fst_weight_errors_display_page');
    }        
    add_action( 'admin_menu', 'fst_add_errorspage_to_menu' );


    function fst_http_get($url, $token) {
        $args = array(
                     'headers' => array(
                         'Auth-Token' => $token
                     )
                 );
        $response = wp_remote_get($url, $args);

        return $response;
    }

    function fst_http_post($url, $body, $token) {
        $args = array(
            'body' => $body,
            'timeout' => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                            'Auth-Token' => $token,
                            'Content-Type' => 'application/x-www-form-urlencoded'
                            ),
            'cookies' => array()
        );                 
        $response = wp_remote_post($url, $args);
        
        return $response;
    }

}