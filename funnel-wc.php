<?php

defined('ABSPATH') or die('do not die please');
/*
* @wordpress-plugin
* Plugin Name:       Funnel for Woocommerce
* Plugin URI:        funnel.ng
* Description:       Bring the power of the funnel API to your WooCommerce merchant website and enjoy unlimited logistics possibilities
* Version:           1.0.2
* Author:            Funnel Shipping Technologies
* Author URI:        funnel.ng
* License:           GPL-2.0+
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:       fst-api-plugin
* Domain Path:       /languages
*/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    define('API_URL', 'http://shippingapps.test');


    add_action('wp_loaded', 'enqueue_swat_fst');
    add_action( 'wp_ajax_set_update_api_key', 'prefix_ajax_set_update_api_key');
    add_action('woocommerce_review_order_before_payment', 'fst_dynamic_data_section');
    
    //WP Ajax
    add_action( 'wp_ajax_fst_shipping_classes', 'process_shipping_classes' );
    add_action( 'wp_ajax_nopriv_fst_shipping_classes', 'process_shipping_classes' );
    
    function enqueue_swat_fst()
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

    function get_api_key() {
        $shipping_methods = WC()->shipping->load_shipping_methods();
        return $shipping_methods['fst-shipping-method']->settings['api_key'];
    }

    function process_shipping_classes() {
        $shipToData = [];        


        $shipToData['state'] =  $_POST['state'];
        $shipToData['city'] =  $_POST['city'];

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
        
        WC()->session->set('cartItems', serialize($cartInfo));
        
        // Create array of required values to calculate shipping costs
        
        $fstOrder = [];
        $fstOrder['weight'] = $itemWeight;
        $fstOrder['from'] = get_option('fst_default_shop_state');
        $fstOrder['to'] = $shipTo;
        $fstOrder['areaFrom'] = get_option('fst_default_shop_area');
        $fstOrder['areaTo'] = $shipToArea;
        $fstOrder['items'] = serialize($all_weights);


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
            
            foreach($items as $item => $values) { 
            $_product =  wc_get_product( $values['data']->get_id()); 
            $price = get_post_meta($values['product_id'] , '_price', true);
            $all_weights = [];
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
        
        WC()->session->set('cartItems', serialize($cartInfo));
        
        // Create array of required values to calculate shipping costs
        
        $fstOrder = [];
        $fstOrder['weight'] = $itemWeight;
        $fstOrder['from'] = get_option('fst_default_shop_state');
        $fstOrder['to'] = $shipTo;
        $fstOrder['areaFrom'] = get_option('fst_default_shop_area');
        $fstOrder['areaTo'] = $shipToArea;
        $fstOrder['items'] = serialize($all_weights);
        
        fst_checkout($fstOrder);

    }

    function fst_checkout($_fst_orders)
    {
       // return true;
       $curl = curl_init();       
       
       
//       print_r($_fst_orders);
        
        $shipping_methods = WC()->shipping->load_shipping_methods();
        if($shipping_methods['fst-shipping-method']->enabled == "yes")
        {
                echo "<div class='fst-title'><h4>Select a Shipping Method <abbr class='required' title='required'>*</abbr></h4></div>";
                
                if(!$_fst_orders['to']){
                    echo 'Please select your <strong>shipping state</strong> to view shipping options';
                }
                if(!$_fst_orders['areaTo']){
                    echo '<p>Please select your <strong>shipping city</strong> to view shipping options</p>';
                }
                
                

                curl_setopt_array($curl, array(
                    CURLOPT_URL => API_URL . "/api/v1/shipping/calculate?weight=".$_fst_orders['weight']."&shipFrom=".$_fst_orders['from']."&shipTo=".ucfirst($_fst_orders['to'])."&areaFrom=".$_fst_orders['areaFrom']."&areaTo=".$_fst_orders['areaTo']."&items=".$_fst_orders['items'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => array(
                        "Auth-Token: ".get_api_key().""
                    ),
                ));
            
                $response = curl_exec($curl);
                $err = curl_error($curl);
            
                curl_close($curl);
            
                    if ($err) {
                
                        echo "cURL Error #: " . $err;
                
                    } else {
                     //   print_r($_fst_orders);
						print_r($response);
                        $data = json_decode($response,true);
                        
                        WC()->session->set('requestSessionId', $data['requestSessionId']);
                
                        if($data["data"]) {
                
                        echo"
                        <div id='refreshing-box'></div>
                
                        <div class='shipping-loader'></div>";
                
                        echo "<div id='shippingElement' class='packagesBox'>";

                        WC()->session->set("data", $data["data"]);
                        $count = 0;
                
                        foreach($data["data"] as $information) {
                        echo "<div class='form-group'>";
                        echo "<label>".$information["Shipper"]["name"]."</label><br/>";
                
                        echo"<select name='fst_package' data-counter=".$count++." data-id =".$information["Shipper"]["id"]." class='fst_package form-control'>";
                
                                echo"<option selected value='' disabled class='packageSelect'>Select a Shipping Class</option>";
                        
                                foreach($information["ShippingClasses"] as $ShippingClass ) {
                        
                                    echo"<option value =".$ShippingClass["Id"]." data-price=".$ShippingClass["customerPrice"]." class='packageSelect'>";
                        
                                    echo $ShippingClass["Name"]." ";
                        
                                    echo "NGN" . number_format($ShippingClass["customerPrice"], 2);
                        
                                    echo"</option>";
                                    }
                            echo"</select>";
                            echo "</div>";

                        }
                } else {
                    echo"<h5><b>We do not offer delivery services your destination yet</b></h5> <div id='refreshing-box'></div>";
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
            WC()->session->set('Shipper_shipperID', $_POST['id']);
        }

        if(isset($_POST['cost'])) {
            WC()->session->set('fst-shipping-cost', $_POST['cost']);
        } else {
            WC()->session->set('fst-shipping-cost', 0);
        }
        return true;
    }
    add_action( 'wp_ajax_fst_calculate_costs', 'fst_calculate_costs' );
    add_action( 'wp_ajax_nopriv_fst_calculate_costs', 'fst_calculate_costs' );



    function m_prevent_submission($posted) {
        //Check if selected shipping method is `fst-shipping-method` and if a package is selected
        if ((isset($posted['shipping_method']) && ($posted['shipping_method'][0] == 'fst-shipping-method')) && !isset($_POST['fst_package']) && wc_notice_count( 'error' ) == 0 ) {
            wc_add_notice( __("Please select a shipping method", 'fst-shipping-api' ), 'error');
        }         
        if (empty($_POST['billing_phone'])) {
            wc_add_notice( __("Phone number field is mandatory for shipping", 'fst-shipping-api' ), 'error');
        }         
   }
   add_action('woocommerce_after_checkout_validation', 'm_prevent_submission');

   function fst_require_phone( $address_fields ) {
        if(!is_user_logged_in()) {
            unset($address_fields['billing_phone']['required']);
            $address_fields['billing_phone']['required'] = true;
        }
        return $address_fields;    
    }
    add_filter( 'woocommerce_billing_fields', 'fst_require_phone', 10, 1 );

    function clear_wc_shipping_rates_cache(){
        $packages = WC()->cart->get_shipping_packages();
        foreach ($packages as $key => $value) {
            $shipping_session = "shipping_for_package_$key";
            unset(WC()->session->$shipping_session);
        }
    }
    add_filter('woocommerce_checkout_update_order_review', 'clear_wc_shipping_rates_cache');    


    function fst_shipping_method() {
        require_once('inc/FstShippingMethodSetup.class.inc.php');
    }
    add_action( 'woocommerce_shipping_init', 'fst_shipping_method' );
 
    function add_fst_shipping_method( $methods ) {
        $methods[] = 'FST_Shipping_Method';
        return $methods;
    } 
    add_filter( 'woocommerce_shipping_methods', 'add_fst_shipping_method' );


    // Clear default shipping option.
    add_filter( 'woocommerce_shipping_chosen_method', '__return_false', 99);

    // Clear default payment option.
    add_filter( 'pre_option_woocommerce_default_gateway' . '__return_false', 99 );


    /**
     * Disable shipping calculator on the cart page
     */

    function disable_shipping_calc_on_cart( $show_shipping ) {

        //Yomi, set condition to check if `fst-shipping-method` is empty before it hides here.
        if( is_cart() ) {
            return false;
        }
        return $show_shipping;
    }
    add_filter( 'woocommerce_cart_ready_to_calc_shipping', 'disable_shipping_calc_on_cart', 99 );


    function verify_api_key() {
        $api_key = $_REQUEST['woocommerce_fst-shipping-method_api_key'];
        $curl = curl_init();        
                
        curl_setopt_array($curl, array(
            CURLOPT_URL => API_URL . "/api/v1/shipping/verify",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Auth-Token: ".$api_key.""
            ),
        ));

        $response = curl_exec($curl);

        $err = curl_error($curl);
    
        curl_close($curl);
    
        if ($err) {
            $message = "cURL Error #: " . $err;    
        } else {
            $response = json_decode($response);
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
    add_filter('woocommerce_update_options_shipping_fst-shipping-method', 'verify_api_key');

    /**
     * Hide shipping rates when free shipping is available.
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


    function before_shipping( $checkout ) {
        WC()->session->set('chosen_shipping_methods', ['fst-shipping-method'] );    
    }
    add_action( 'woocommerce_before_checkout_shipping_form', 'before_shipping');

    /**
     * Display custom notices
     */
    function fst_custom_notice(){
        $notice  = get_option( 'fst_submit_form_response', false );
        if($notice) {
            delete_option( 'fst_submit_form_response' );
            display_notice( $notice );
        }
    }
    add_action( 'admin_notices', 'fst_custom_notice');         

    function display_notice($notice) {
        echo '<div class="notice notice-error is-dismissible">
            <p>'.$notice.'</p>
        </div>';
    }

    function woocommerce_order_complete_post_variable(){
        global $woocommerce;
      
        $cart = WC()->cart;
      
        $customer = $cart->get_customer();

      //  print_r(WC()->session->get('requestSessionId'));

        $first_name = ($customer->get_billing_first_name() !== '')? $customer->get_billing_first_name(): $_POST['billing_first_name'];
        $last_name = ($customer->get_billing_last_name() !== '')? $customer->get_billing_last_name(): $_POST['billing_last_name'];

        $data=array();
          $data["sessionId"]= WC()->session->get('requestSessionId');
          $data["shippingClass"]= $_POST['fst_package'];
          $data["shippingParther"]= WC()->session->get('Shipper_shipperID');
          $data["customerEmail"]= ($customer->get_billing_email() !== '')? $customer->get_billing_email() : $_POST['billing_email'];
          $data["customerName"]= $first_name." ".$last_name;
          $data["description"]= "transaction";
          $data["customerAddress"]= $customer->get_billing_address();
          $data["customerPhone"]= ($customer->get_billing_phone() !== '')? $customer->get_billing_phone(): $_POST['billing_phone'];
          $curl = curl_init();

//          print_r($data);
//          die();
      
          curl_setopt_array($curl, array(
          CURLOPT_URL => API_URL . "/api/v1/shipping/submit",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "sessionId=".$data["sessionId"]."&shippingClass=".$data["shippingClass"]."&shippingParther=".WC()->session->get("Shipper_shipperID")."&customerEmail=".$data["customerEmail"]."&customerName=".$data["customerName"]."&description=".$data["description"]."&customerAddress=".$data["customerAddress"]."&customerPhone=".$data["customerPhone"]."&itemList=".WC()->session->get('cartItems')."",
          CURLOPT_HTTPHEADER => array(
              "Auth-Token: ".get_api_key()."",
              "Cache-Control: no-cache",
              "Content-Type: application/x-www-form-urlencoded",
              "Postman-Token: 168af565-cb4e-4351-958e-51e71a2b9919"
          ),
          ));
          
          $response = curl_exec($curl);
          $err = curl_error($curl);
        
          curl_close($curl);
      
          if ($err) {
  //          echo "cURL Error #:" . $err;
          } else {
          
            print_r($response);
            die();
          }
          
        
      }
      
    add_action('woocommerce_order_status_on-hold','woocommerce_order_complete_post_variable');

    

}