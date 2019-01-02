<?php

if ( ! class_exists( 'FST_Shipping_Method' ) ) {
            class FST_Shipping_Method extends WC_Shipping_Method {
                public function __construct() {
                    $this->id                 = 'fst-shipping-method'; 
                    $this->method_title = __('Nipost Shipping API Settings', 'fst-shipping-method');
                    $this->method_description = __('Shipping method settings', 'fst-shipping-method');
                    $this->title = __('Delivery Cost', 'fst-shipping-method');

                    $this->availablility = 'including';
                    $this->countries = ['NG'];

                    $this->init();

                    $this->enabled = isset($this->settings['enabled'])? $this->settings['enabled'] : 'yes';
                }
 
                /**
                 * Initialize settings
                 */
                function init() {
                    $this->init_form_fields(); 
                    $this->init_settings(); 
 
                    // Save settings
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                }
 
                /**
                 * Define settings field for this shipping method
                 */
                function init_form_fields() { 

                    $this->form_fields = [
                        'enabled' => [
                            'title' => __('Enable', 'fst-shipping-method'),
                            'type' => 'checkbox',
                            'description' => __('Enable this shipping method?', 'fst-shipping-method'),
                            'default' => 'yes'
                        ],
                        'override' => [
                            'title' => __('Override other shipping methods?', 'fst-shipping-method'),
                            'type' => 'checkbox',
                            'description' => __('Once this field is checked, other shipping methods will not display on the cart and checkout pages.', 'fst-shipping-method'),
                            'default' => 'yes'
                        ],
                        'api_key' => [
                            'title' => __('API key', 'fst-shipping-method'),
                            'type' => 'text',
                            'description' => __('Enter API key', 'fst-shipping-method'),
                        ],
                        'excluded_categories' => [
                            'title' => __('Exclude Categories', 'fst-shipping-method'),
                            'type' => 'text',
                            'description' => __('Select IDs of categories to exclude (separated by comma). <a target="_blank" href="">Click here to know how to get your products\' category IDs</a>', 'fst-shipping-method'),
                        ]
                    ];
 
                }
 
                /**
                 * Calculate shipping cost
                 */
                public function calculate_shipping( $package = []) {
                    
                    $weight = 0;
                    $cost = 0;
                    $shipping_cost = 0;
                    $country = $package["destination"]["country"];
 
                    foreach ( $package['contents'] as $item_id => $values ) 
                    { 
                        $_product = $values['data']; 
                        $weight += (int)$_product->get_weight() * (int)$values['quantity']; 
                    }
 
                    $weight = wc_get_weight( $weight, 'kg' );
 
                    //Add Shipping cost set into the `fst-shipping-cost` session key
                    if(WC()->session->get('fst-shipping-cost')) {
                        $shipping_cost = WC()->session->get('fst-shipping-cost'); 
                    }
                    
                    $cost += $shipping_cost;

                    $rate = array(
                        'id' => $this->id,
                        'label' => $this->title,
                        'cost' => $cost
                    );
                    $this->add_rate( $rate );

                }
            }
 }
