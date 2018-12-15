<?php
/**
 * Woo_Manual_Order_AJAX. AJAX Event Handlers.
 *
 * @class    Woo_Manual_Order_AJAX
 * @package  Woo_Manual_Orders/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Ajax class.
 */
class Woo_Manual_Order_AJAX extends WC_AJAX {
	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function init() {

		// woocommerce_EVENT => nopriv.
		$ajax_events = array(
			'manual_order_search_products' => true,
			'manual_order_assign_customer' => true,
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );

				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
		
    }
    
    /**
	 * AJAX update order review on checkout.
	 * 
	 * @param string $term: search keyword
	 */
	public static function manual_order_search_products($term = '') {

        check_ajax_referer( 'search-products', 'security' );

		$term = wc_clean( empty( $term ) ? wp_unslash( $_GET['term'] ) : $term );

		if ( empty( $term ) ) {
			wp_die();
		}

		if ( ! empty( $_GET['limit'] ) ) {
			$limit = absint( $_GET['limit'] );
		} else {
			$limit = absint( apply_filters( 'woocommerce_json_search_limit', 30 ) );
		}

		$data_store = WC_Data_Store::load( 'product' );
		$ids        = $data_store->search_products( $term, '', true, false, $limit );

		if ( ! empty( $_GET['exclude'] ) ) {
			$ids = array_diff( $ids, (array) $_GET['exclude'] );
		}

		if ( ! empty( $_GET['include'] ) ) {
			$ids = array_intersect( $ids, (array) $_GET['include'] );
		}

		// get list product object
		$product_objects = array_map( 'wc_get_product', $ids );

		$products = array();

		$hide_product_variable = (wc_manual_order_option( 'hide_product_variable' ) == 'yes' );

		foreach ( $product_objects as $product ) {
			
			if( empty( $product ) ) continue;

			$link = get_the_permalink($product->get_id());
			$text = self::formatted_name( $product );
			$class = $product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart' : '';

			if( empty($class) && $hide_product_variable ) {
				continue;
			}

			$products[ $product->get_id() ] = array(
				'text' 	=> $text,
				'url'	=> add_query_arg( array( 'manual_order_add_to_cart' => 'true', 'TB_iframe' => 'true'), $link),
				'class'	=> $class,
				'parent' => $product->get_parent_id(),
			);
		}

        wp_send_json( apply_filters( 'woocommerce_json_search_found_products', $products ) );
	}
	
	/** 
	 * Assign custome to order
	 * 
	 * @param int $customer_id
	 * 
	 */
	public static function manual_order_assign_customer($customer_id=''){
		global $woo_manual_order;

		check_ajax_referer( 'assign-customer', 'security' );

		$customer_id = (int)wc_clean( empty( $customer_id ) ? wp_unslash( $_POST['customer_id'] ) : $customer_id );

		$woo_manual_order->set_customer( $customer_id );

		$is_empty_cart = false;
		if ( WC()->cart->is_empty() ) {
			$is_empty_cart = true;
		} else {
			// Calc totals.
			WC()->cart->calculate_totals();
		}

		ob_start();
		
		$woo_manual_order->get_template( 'checkout/customer-details.php', array( 'checkout' => WC()->checkout() ) );

		$details = ob_get_clean();

		wp_send_json( array(
			'customer_id' => $customer_id,
			'customer_details' => $details,
			'is_empty_cart' => $is_empty_cart,
			'message'		=> __('Cutomer was assigned', 'woo-manual-order'),
		) );
	}
	//=========== protected method ======//

	/**
	 * Disaplay friendly product name
	 * 
	 * @param \WC_Product $product
	 * @param string $delimiter
	 */
	protected static function formatted_name( $product, $delimiter = '|' ) {
		$formatted_output = array();
		if ( $product->get_stock_quantity() ) {
			$formatted_output[] = $product->get_stock_status() . ' - ' . $product->get_stock_quantity();
		} else {
			$formatted_output[] = $product->get_stock_status();
		}
		$formatted_output[] = $product->get_price_html();
		$formatted_output[] = $product->get_sku();
		$formatted_output[] = rawurldecode( $product->get_name() );

		return join( ' ' . $delimiter . ' ', array_filter( $formatted_output ) );
	}

}

Woo_Manual_Order_AJAX::init();