<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Weightless_List_Table extends WP_List_Table {

    public $count;
    /**
     * Initialize the table list.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Product', 'fst-shipping-api' ),
            'plural'   => __( 'Products', 'fst-shipping-api' ),
            'ajax'     => false
        ) );
    }

    /**
     * Get list columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'id'            => __( 'Product ID', 'fst-shipping-api' ),
            'product'         => __( 'Product', 'fst-shipping-api' ),
            'action'         => __( 'Action', 'fst-shipping-api' ),
        );
    }


    /**
     * Return ID column
     */
    public function column_id( $product ) {
        return $product['ID'];
    }

    /**
     * Return product column
     */
    public function column_product( $product ) {
        $url = get_edit_post_link($product['ID']);
        return sprintf( '<a href="%s">%s</a>', $url, $product['post_title'] );
    }

    public function column_action( $product ) {
        $edit_url = get_edit_post_link($product['ID']);
        $permalink = get_the_permalink($product['ID']);
        $links =  sprintf( '<a href="%s">%s</a>', $edit_url, 'Edit' );
        $links .=  sprintf( ' | <a href="%s" target="_blank">%s</a>', $permalink, 'View' );
        return $links;
    }

    public function get_count() {
        global $wpdb;
        $posts_table = $wpdb->prefix."posts";
        $postmeta_table = $wpdb->prefix."postmeta";

        $count = $wpdb->get_var( "SELECT count(ID) FROM {$posts_table} as A INNER JOIN {$postmeta_table} AS B on A.ID = B.post_id WHERE (B.meta_value = '' OR B.meta_value = 0) AND B.meta_key = '_weight';" );
        return $count;
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    protected function get_bulk_actions() {
        return array(

        );
    }

    /**
     * Prepare table list items.
     */
    public function prepare_items() {
        global $wpdb;

        $per_page = 10;
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $posts_table = $wpdb->prefix."posts";
        $postmeta_table = $wpdb->prefix."postmeta";

        // Column headers
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $current_page = $this->get_pagenum();
        if ( 1 < $current_page ) {
            $offset = $per_page * ( $current_page - 1 );
        } else {
            $offset = 0;
        }

        $search = '';

        if ( ! empty( $_REQUEST['s'] ) ) {
      //      $search = "AND description LIKE '%" . esc_sql( $wpdb->esc_like( $_REQUEST['s'] ) ) . "%' ";
        }

        $items = $wpdb->get_results(
            "SELECT A.ID, A.post_title, B.meta_value FROM {$posts_table} as A INNER JOIN {$postmeta_table} AS B on A.ID = B.post_id WHERE (B.meta_value = '' OR B.meta_value = 0) AND B.meta_key = '_weight'  {$search}" .
            $wpdb->prepare( "ORDER BY id DESC LIMIT %d OFFSET %d;", $per_page, $offset ), ARRAY_A
        );

//        print_r($items);

        $count = $wpdb->get_var( "SELECT count(ID) FROM {$posts_table} as A INNER JOIN {$postmeta_table} AS B on A.ID = B.post_id WHERE (B.meta_value = '' OR B.meta_value = 0) AND B.meta_key = '_weight' {$search};" );

        $this->items = $items;

        // Set the pagination
        $this->set_pagination_args( array(
            'total_items' => $count,
            'per_page'    => $per_page,
            'total_pages' => ceil( $count / $per_page )
        ) );
    }
}