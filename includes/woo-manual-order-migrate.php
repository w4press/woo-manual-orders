<?php
/**
 * Back capability function
 *
 * @version 1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//============== support deprecated old function ==============//
if ( ! function_exists( 'wc_get_formatted_cart_item_data' ) ) {
    function wc_get_formatted_cart_item_data( $cart_item ){
        return WC()->cart->get_item_data( $cart_item ); // woo < 3.3
    }
}

if ( ! function_exists( 'wc_get_cart_remove_url' ) ) {
    function wc_get_cart_remove_url( $cart_item_key ){
        return WC()->cart->get_remove_url( $cart_item_key ); // woo < 3.3
    }
}