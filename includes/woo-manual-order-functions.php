<?php
/**
 * WooCommerce Core Functions
 *
 * General core functions available on both the front-end and admin.
 *
 * @package WooCommerce\Functions
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'is_manual_order_page' ) ) {
    
    /**
     * Check if page is manual order checkout
     */
    function is_manual_order_page() {

        if( ! empty ($_REQUEST['manual_order_checkout']) || ! empty ($_REQUEST['manual_order_cart']) ) return true;

        $is_page = false;

        if ( defined('DOING_AJAX') && DOING_AJAX ) { 

            $url     = wp_get_referer();
            $post_id = url_to_postid( $url ); 
            $post = get_post( $post_id );

            if( ! empty( $post ) ) {
                $is_page = has_shortcode( $post->post_content, 'woocommerce_manual_order' );
            }

        } else {
            $is_page = wc_post_content_has_shortcode( 'woocommerce_manual_order' );
        }

        $page_id = wc_get_page_id( 'manual_order' );

        return ( $page_id && is_page( $page_id ) ) 
            || $is_page
            || apply_filters( 'woocommerce_is_manual_order_checkout', false );
    }

}

if ( ! function_exists( 'wc_manual_order_option' ) ) {

    /**
     * Get Manual Order option value
     * 
     * @param string $option: Option key
     * 
     */
    function wc_manual_order_option( $option='' ){
        
        if( empty($option) ) return null;

        return get_option( 'woocommerce_manual_order_' . $option, true );
    }
}
