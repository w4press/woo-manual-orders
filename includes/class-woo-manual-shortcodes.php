<?php
/**
 * Shortcodes
 *
 * @package Woo-Manual-Order/Includes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Woo Manual Shortcodes class.
 */
class Woo_Manual_Order_Shortcodes {

    /**
	 * Init shortcodes.
	 */
	public static function init() {
		global $woo_manual_order;
		
		$shortcodes = array(
			'woocommerce_manual_order' => __CLASS__ . '::manual_order',
		);

		foreach ( $shortcodes as $shortcode => $function ) {
			add_shortcode( apply_filters( "{$shortcode}_shortcode_tag", $shortcode ), $function );
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'load_scripts' ) );
		add_filter( 'body_class', array( __CLASS__, 'add_body_class' ) );
		
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'maybe_hide_front_menu' ), 10, 2 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'remove_unsupported_gateways' ) );
		
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'after_checkout_validation' ), 11, 2 );

		add_filter( 'woocommerce_checkout_customer_id', array( __CLASS__, 'checkout_customer_id' ) );

		// @fixbug: can not update customer meta data with woo < 3.0.0.9
		add_filter( 'woocommerce_checkout_update_customer_data', array( __CLASS__, 'is_update_customer_data' ) ); // woo < 3.0.9
		add_action( 'woocommerce_checkout_update_user_meta', array( __CLASS__, 'update_user_meta' ), 10, 2 ); // woo < 3.0.9

		add_filter( 'woocommerce_get_customer_payment_tokens', array( __CLASS__, 'customer_payment_tokens' ), 11, 3 );
		add_filter( 'woocommerce_checkout_update_order_review_expired', array( __CLASS__, 'update_order_review_expired' ));
		
		add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'change_notify_session_has_expired' ) ); // for version <= 3.3
		add_action( 'woocommerce_before_checkout_process', array( __CLASS__, 'validate_cart_empty' ) ); // validate
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'remove_assigned_customer' ) );

		// For woo <= 3.2.6.0: change redirect url after update cart
		add_filter( 'woocommerce_get_cart_url', array( __CLASS__, 'get_cart_url' ) ); // validate
		add_filter( 'wc_add_to_cart_message_html', array( __CLASS__, 'remove_link_to_cart' ), 10, 2 );

		//Mark order as manual order type
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'mark_as_manual_order' ), 11, 2 ); // update order type

		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'after_add_to_cart_button' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'after_order_table' ) );

		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'add_to_cart_redirect' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'after_added_to_cart' ) );
	}

	/**
	 * Load scripts
	 */
	public static function load_scripts(){
		global $woo_manual_order;

		if( ! is_manual_order_page() ) {
			return;
		}
		// Load css
		wp_enqueue_style( 'select2' );

		wp_register_script( 'manual-order-select', $woo_manual_order->plugins_url( 'assets/js/enhanced-select.js' ), array('wc-checkout','select2')  );
		wp_register_script( 'woo-manual-order-checkout', $woo_manual_order->plugins_url( 'assets/js/manual-order-checkout.js' ), array('manual-order-select', 'wc-cart', 'wc-checkout') );
		
		$vars = array(
			'i18n_no_matches'           => _x( 'No matches found', 'enhanced select', 'woo-manual-order' ),
			'i18n_ajax_error'           => _x( 'Loading failed', 'enhanced select', 'woo-manual-order' ),
			'i18n_input_too_short_1'    => _x( 'Please enter 1 or more characters', 'enhanced select', 'woo-manual-order' ),
			'i18n_input_too_short_n'    => _x( 'Please enter %qty% or more characters', 'enhanced select', 'woo-manual-order' ),
			'i18n_input_too_long_1'     => _x( 'Please delete 1 character', 'enhanced select', 'woo-manual-order' ),
			'i18n_input_too_long_n'     => _x( 'Please delete %qty% characters', 'enhanced select', 'woo-manual-order' ),
			'i18n_selection_too_long_1' => _x( 'You can only select 1 item', 'enhanced select', 'woo-manual-order' ),
			'i18n_selection_too_long_n' => _x( 'You can only select %qty% items', 'enhanced select', 'woo-manual-order' ),
			'i18n_load_more'            => _x( 'Loading more results&hellip;', 'enhanced select', 'woo-manual-order' ),
			'i18n_searching'            => _x( 'Searching&hellip;', 'enhanced select', 'woo-manual-order' ),
			
			'ajax_url'                  => admin_url( 'admin-ajax.php' ),
			'search_products_nonce'     => wp_create_nonce( 'search-products' ),
			'search_customers_nonce'    => wp_create_nonce( 'search-customers' ),
			'search_categories_nonce'   => wp_create_nonce( 'search-categories' ),
			'assign_customer_nonce'   	=> wp_create_nonce( 'assign-customer' ),
		);
		wp_localize_script( 'woo-manual-order-checkout', 'woo_manual_order_params', $vars );

		wp_enqueue_script( 'woo-manual-order-checkout' );

	}

	/**
	 * Support all woocommerce css
	 * 
	 * @param array $classes
	 */
	public static function add_body_class($classes=array()){
		if ( is_manual_order_page() ) {
			$classes[] = 'woocommerce';
			$classes[] = 'woocommerce-page';
			$classes[] = 'woocommerce-checkout';
			$classes[] = 'woocommerce-cart';
		}
		return array_unique( $classes );
	}

	/**
     * Maybe filters the manual order manue objects before generating the menu's HTML.
     *
     * @since 1.0.3
     *
     * @param array    $items The menu items, sorted by each menu item's menu order.
     * @param stdClass $args              An object containing wp_nav_menu() arguments.
     */
	public static function maybe_hide_front_menu($items, $args=array()){
		global $woo_manual_order;

		if( ! $woo_manual_order->is_user_in_role() ){
			$filter_manue = array();
			$page_id = wc_get_page_id( 'manual_order' );

			foreach($items as $item) {
				if( $item instanceof WP_Post ){
					if( $page_id == $item->object_id ) {
						continue;
					}
				}
				$filter_manue[] = $item;
			}

			return $filter_manue;
		}
		return $items;
	}
    
    /**
	 * Shortcode Wrapper.
	 *
	 * @param string[] $function Callback function.
	 * @param array    $atts     Attributes. Default to empty array.
	 * @param array    $wrapper  Customer wrapper data.
	 *
	 * @return string
	 */
	public static function shortcode_wrapper(
		$function,
		$atts = array(),
		$wrapper = array(
			'class'  => 'woo-manual-order-container',
			'before' => null,
			'after'  => null,
		)) 
	{
		
		ob_start();

		// @codingStandardsIgnoreStart
		echo empty( $wrapper['before'] ) ? '<div class="' . esc_attr( $wrapper['class'] ) . '">' : $wrapper['before'];
		call_user_func( $function, $atts );
		echo empty( $wrapper['after'] ) ? '</div>' : $wrapper['after'];
		// @codingStandardsIgnoreEnd

		return ob_get_clean();
    }
    
    /**
	 * Order tracking page shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function manual_order( $atts ) {
		return self::shortcode_wrapper( array( 'Woo_Manual_Order_Shortcode_Checkout', 'output' ), $atts );
	}
	
	// Overwrite customer id
	public static function checkout_customer_id($customer_id){
		global $woo_manual_order;

		if( is_manual_order_page() ) {
			// if assinged
			if( $customer_id = $woo_manual_order->is_assigned_customer() ){
				return $customer_id;
			}
			return $customer_id;	
		}

		return $customer_id;
	}

	/**
	 * Because filter 'woocommerce_checkout_customer_id' support only version >= 3.0.9
	 * So we need update customer meta and ignore this action in core
	 */
	public static function is_update_customer_data($update){
		if( is_manual_order_page() ) {
			return false;
		}
		return $update;
	}

	/**
	 * Update user metadata
	 */
	public static function update_user_meta($customer_id='', $data=array()){
		if( is_manual_order_page() ) {
			
			$customer_id = self::checkout_customer_id(0);

			// Maybe checkout as guest
			if( empty($customer_id) ) return;

			$customer = new WC_Customer( $customer_id );

			if ( ! empty( $data['billing_first_name'] ) ) {
				$customer->set_first_name( $data['billing_first_name'] );
			}

			if ( ! empty( $data['billing_last_name'] ) ) {
				$customer->set_last_name( $data['billing_last_name'] );
			}

			foreach ( $data as $key => $value ) {
				// Use setters where available.
				if ( is_callable( array( $customer, "set_{$key}" ) ) ) {
					$customer->{"set_{$key}"}( $value );

				// Store custom fields prefixed with wither shipping_ or billing_.
				} elseif ( 0 === stripos( $key, 'billing_' ) || 0 === stripos( $key, 'shipping_' ) ) {
					$customer->update_meta_data( $key, $value );
				}
			}

			/**
			 * Action hook to adjust customer before save.
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_update_customer', $customer, $data );

			$customer->save();
		}
	}

	/**
	 * Maybe create customer
	 * We are shop manager, so we create account for my customer
	 * @param array $data
	 * @param array $errors
	 */
	public static function after_checkout_validation($data, $errors=''){
		global $woo_manual_order;

		if( ! is_manual_order_page() ) {
			return;
		}

		if ( ! $errors->get_error_messages() ) {
			if( ! empty( $data['createaccount'] )) { // create account
				
				$checkout = WC()->checkout;
				foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) {
					$type = sanitize_title( isset( $field['type'] ) ? $field['type'] : 'text' );

					switch ( $type ) {
						case 'password':
							$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
							break;
						default:
							$value = isset( $_POST[ $key ] ) ? wc_clean( wp_unslash( $_POST[ $key ] ) ) : ''; // WPCS: input var ok, CSRF ok.
							break;
					}

					$data[ $key ] = apply_filters( 'woocommerce_process_checkout_' . $type . '_field', apply_filters( 'woocommerce_process_checkout_field_' . $key, $value ) );
				}

				$username    = ! empty( $data['account_username'] ) ? $data['account_username'] : '';
				$password    = ! empty( $data['account_password'] ) ? $data['account_password'] : '';

				try {
					
					$customer_id = wc_create_new_customer( $data['billing_email'], $username, $password );

					if ( is_wp_error( $customer_id ) ) {
						throw new Exception( $customer_id->get_error_message() );
					}

					WC()->session->set( 'manual_oder_customer_id', $customer_id );

				} catch( Exception $ex ){
					$errors->add( 'createaccount', $ex->getMessage() );
				}
			}
		}
	}

	/** 
	 * Always display checkout page even cart is empty
	 * 
	 * @param bool $expired
	 */
	public static function update_order_review_expired($expired=true){
		if( is_manual_order_page() ) {
			return false;
		}
		return $expired;
	}

	/**
	 * 
	 * Hide notify message when cart was empty
	 * Fixbug: Show loading block on woo version 3.0
	 * @param array $fragments
	 */
	public static function change_notify_session_has_expired($fragments=''){
		if( is_manual_order_page() ) {
			if( WC()->cart->is_empty() ){
				return array( 
					'table.woocommerce-checkout-review-order-table'	=> '<table class="shop_table woocommerce-checkout-review-order-table"></table>',
					'div.woocommerce-checkout-payment'	=> '<div class="woocommerce-checkout-payment"></div>'
				);
			}
		}
		return $fragments;
	}

	/**
	 * Validate manual checkout, catch it for display correct message instead of woocommerce message
	 */
	public static function validate_cart_empty(){
		global $woo_manual_order;
		if( is_manual_order_page() ) {
			if ( WC()->cart->is_empty() ) {
				/* translators: %s: shop cart url */
				throw new Exception(__( 'Please add item to order before checkout.','woo-manual-order'));
			}
		}
	}

	/**
	 * Un-assiged customer id
	 * @param int $order_id
	 */
	public static function remove_assigned_customer($order_id){
		global $woo_manual_order;

		if( is_manual_order_page() ) {
			WC()->session->__unset( 'manual_oder_customer_id' );
		}
	}

	/**
	 * Conditionally remove any gateways that don't support 'invoicing' on the checkout page. 
	 * This is done because payment info is not required in this case so displaying gateways/payment fields is not needed.
	 *
	 * @since 1.0
	 * @param array $available_gateways
	 * @return array
	 */
	public static function remove_unsupported_gateways( $available_gateways=array() ) {
		global $woo_manual_order;

		if( is_manual_order_page() ) {
			
			$show_all = wc_manual_order_option( 'all_gateways' );

			// Remove any non-supported payment gateways
			foreach ( $available_gateways as $gateway_id => $gateway ) {

				if ( 'cod' == $gateway_id
					|| true == $gateway->supports( 'invoicing' ) 
					|| 'yes' == $show_all ) {
						continue;
				}

				unset( $available_gateways[ $gateway_id ] );
			}

			return apply_filters( 'woocommerce_manual_order_available_payment_gateways', $available_gateways );
		}

		return $available_gateways;
	}

	/**
	 * Update token info
	 */
	public static function customer_payment_tokens($tokens, $customer_id='', $gateway_id=''){
		global $woo_manual_order;

		if( is_manual_order_page() ){
			$customer_id = $woo_manual_order->is_assigned_customer();
			
			$tokens = WC_Payment_Tokens::get_tokens(
				array(
					'user_id'    => $customer_id,
					'gateway_id' => $gateway_id,
				)
			);
		}

		return $tokens;
	}

	/**
	 * Add more flag to know
	 */
	public static function after_add_to_cart_button(){
		if( !empty( $_REQUEST['manual_order_add_to_cart'] ) ) {
			echo '<input type="hidden" name="manual_order_add_to_cart" value="true" />';
		}
	}

	/**
	 * Comeback to manual order after add to cart from single page
	 */
	public static function add_to_cart_redirect( $url ){
		global $woo_manual_order;

		if( isset( $_REQUEST['manual_order_add_to_cart'] ) ) {

			$url = $woo_manual_order->get_page_url( 'manual_order' );
			return add_query_arg( array( 'tb-close' => 'true' ), $url );
		}

		return $url;
	}

	/**
	 * Close thickbox
	 */
	public static function after_added_to_cart(){
		if ( empty( $_REQUEST['tb-close'] ) ) {
			return;
		}
		echo '<script>if(self.parent) self.parent.location.reload();</script>';
		exit;
	}

	/**
	 * Hook to change cart url
	 */
	public static function get_cart_url($url=''){
		global $woo_manual_order;

		if( is_manual_order_page() ){
			return $woo_manual_order->get_page_url('manual_order');
		}

		return $url;
	}

	/**
	 * Remove link to cart
	 */
	public function remove_link_to_cart($message, $product=''){
		if( isset( $_REQUEST['manual_order_add_to_cart'] ) ) {
			return wp_strip_all_tags( $message );
		}
		return $message;
	}

	/**
	 * Mark order as manual order
	 */
	public static function mark_as_manual_order($order_id, $data=''){
		if( ! is_manual_order_page() ) return;

		update_post_meta( $order_id, '_manual_order', 'true' ) ;
	}

	/**
	 * Back to checkout
	 */
	public static function after_order_table($order){
		global $woo_manual_order;

		if( ! is_order_received_page() ) return;
		
		$order_id = $order->get_id();
		$is_manual_order = get_post_meta( $order_id, '_manual_order', true );
		if( ! empty( $is_manual_order ) ) {
			echo '<div class="woo-manual-order-wrap">'. apply_filters( 'woocommerce_manual_order_back_to'
			, sprintf('<a href="%s">%s</a> | <a href="%s">%s</a>'
				, $woo_manual_order->get_page_url('manual_order')
				, __('Add new manual order', 'woo-manual-order')
				, add_query_arg( array('post' => $order_id, 'action' => 'edit'), admin_url('post.php') )
				, __('View this order in the admin area', 'woo-manual-order')
				
			), $order ) . '</div>';
		}
	}

}