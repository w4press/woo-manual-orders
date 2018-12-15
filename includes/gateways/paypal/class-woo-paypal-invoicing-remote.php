<?php
/**
 * Class Woo_Gateway_Paypal_Invoicing file.
 *
 * @package WooCommerce\Gateways
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Paypal Invoicing Gateway.
 *
 * Provides a Paypal Invoicing Payment Gateway.
 *
 * @class       Woo_Paypal_Invoicing_Remote
 * @version     1.0.0
 * @package     Woo_Manual_Orders/Classes/Payment
 */
class Woo_Paypal_Invoicing_Remote {
    var $config; // array of config

    var $apiContext;

    const REST_SANDBOX_ENDPOINT = "https://api.sandbox.paypal.com/";
    const REST_LIVE_ENDPOINT = "https://api.paypal.com/";

    /**
     * Main Woo_Paypal_Remote Instance.
     *
     * Ensures only one instance of Woo_Paypal_Remote is loaded or can be loaded.
     *
     * @since 1.0
     * @return Woo_Paypal_Remote Main instance
     */
    public static function instance( $config )
    {
        return new self( $config );
    }

    function __construct( $config )
    {
        $this->config = $config;

    }

    private function get_client_id(){
        if( 'sandbox' == $this->config['trans_mode'] ) {
            return $this->config['sandbox_client_id'];;
        } 
        
        return $this->config['client_id'];;
    }
    private function get_client_secret(){
        if( 'sandbox' == $this->config['trans_mode'] ) {
            return $this->config['sandbox_client_secret'];;
        } 
        
        return $this->config['client_secret'];;
    }
   
    var $messages = array();

    public function get_messages(){
        return $this->messages;
    }


    public function get_api_url(){
        if( 'sandbox' == $this->config['trans_mode'] ) {
            return self::REST_SANDBOX_ENDPOINT;
        } 
        
        return self::REST_LIVE_ENDPOINT;
    }

    /**
     * Token API url
     */
    public function get_oauth_url(){
        $baseEndpoint = rtrim(trim($this->get_api_url()), '/');
        return $baseEndpoint . "/v1/oauth2/token";
    }

    /**
     * Invoice API url
     */
    public function get_invoice_url(){
        $baseEndpoint = rtrim(trim($this->get_api_url()), '/');
        return $baseEndpoint . "/v1/invoicing/invoices";
    }

    public function get_send_url( $inv_id ){
        $baseEndpoint = rtrim(trim($this->get_api_url()), '/');
        return $baseEndpoint . "/v1/invoicing/invoices/" . $inv_id . '/send';
    }

    public function get_refund_url( $inv_id ){
        $baseEndpoint = rtrim(trim($this->get_api_url()), '/');
        return $baseEndpoint . "/v1/invoicing/invoices/" . $inv_id . '/record-refund';
    }

    /**
     * Remote to gateway
     */
    private function remote($url, $data=array(), $method='post'){
        
        $auth_str = sprintf('%s:%s', $this->get_client_id(), $this->get_client_secret() );

        $this->remote_log( 'Authorization string: ' . $auth_str );

        $base_auth = base64_encode( $auth_str );
    
        $headers = (isset( $data['headers'] ) ? (array)$data['headers'] : array()) + array(
            'Content-type'  => 'application/json',
            'Authorization' => sprintf('Basic %s', $base_auth ),
        );

        $args = array(
            'timeout'       => 70,
            'user-agent'    => 'WooCommerce/' . WC()->version,
            'httpversion'   => '1.1',
            'headers'       => $headers,
        );

        if(isset(  $data['body'] )) {
            $args['body'] = $data['body'];
        }

        $this->remote_log( ' Remote params: ' . print_r($args, true) );
        
        $request = null;

        if( 'post' == $method ){
            $request = wp_safe_remote_post( $url, $args );
        } else {
            $request = wp_safe_remote_get( $url, $args );
        }

        if ( is_wp_error( $request ) ) {
            return $request;
        } 
        
        if( ! empty( $request['body'] ) ) {
            return json_decode( $request['body'], true );
        }

		return $request['response'];
    }

    /** Get token */
    public function get_access_token(){
        global $woo_manual_order;

        $this->remote_log( 'Get access token' );

        $data = array(
            'headers' => array(
                'Content-type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
            ),
        );

        return $this->remote( $this->get_oauth_url(), $data );
    }

    /**
     * Creates a draft invoice. To move the invoice from a draft to payable state, you must send the invoice.
     * In the JSON request body, include invoice details including merchant information. 
     * The invoice object must include an items array.
     */
    public function create_invoice( $order ){
        global $woo_manual_order;
        
        $token = $this->get_access_token();

        if( ! is_wp_error( $token ) && ! empty( $token['access_token'] ) ) {
            $auth = sprintf('%s %s', $token['token_type'], $token['access_token'] );
            
            $this->remote_log( 'Create invoice with token: ' . $auth );

            $body = array(
                'merchant_info' => $this->get_merchant_info( $order ),
                'billing_info'  => array( $this->get_billing_info( $order ) ),
                'items'         => $this->get_items( $order ),
                'terms'         => $this->limit_length( $this->config['terms'], 4000),
                'note'          => $this->limit_length( $order->get_customer_note(), 4000),
            );

            if( wc_ship_to_billing_address_only() ){

                $body['shipping_info'] = $this->get_billing_info( $order );

            } else if ( WC()->cart->needs_shipping() ) {
                if( isset( $_REQUEST['ship_to_different_address'] ) ) {
                    $body['shipping_info'] = $this->get_shipping_info( $order );
                } else {
                    $body['shipping_info'] = $this->get_billing_info( $order );
                }
            }

            $data = array(
                'headers' => array(
                    'Authorization'  => $auth,
                ),
                'body'  => json_encode($body),
            );

            return $this->remote( $this->get_invoice_url(), $data );

        } elseif( is_wp_error( $token ) ){

            return $token;

        } else {
            return new WP_Error( 'paypal_invoicing_get_access_token_error', 'Can not get access token' );
        }
    }

    /**
     * Sends an invoice, by ID, to a customer. 
     * To suppress the merchant's email notification, set the notify_merchant query parameter to false.
     */
    public function send_invoice( $order ){
        global $woo_manual_order;

        $invoice = $this->create_invoice( $order );

        if( !is_wp_error( $invoice ) && ! empty( $invoice['id'] ) ){
            
            $this->remote_log( 'Send invoice: '. $invoice['id'] );

            $result = $this->remote( $this->get_send_url( $invoice['id'] ) );

            $invoice['result'] = $result;
            
        }
        return $invoice;
    }

    /**
     * Sends an invoice, by ID, to a customer. 
     * To suppress the merchant's email notification, set the notify_merchant query parameter to false.
     */
    public function refund( $order, $amount = null, $reason = '' ){
        global $woo_manual_order;

        $inv_id = $order->get_transaction_id();

        $this->remote_log( 'Process refund invoice: ' . $inv_id );

        if( ! empty( $inv_id ) ){
            $data = array(
                'body' => json_encode(array(
                    'note'      => $reason,
                    'amount'    => array(
                        "currency" => $order->get_currency(),
                        "value" => $this->number_format( $amount, $order ),
                    ),
                )),
            );

            return $this->remote( $this->get_refund_url( $inv_id ), $data );
        }

        return false;
    }

    //========= private method ====//
    private function get_merchant_info( $order ){
        $args = array(
            "email"    => $this->config['merchant_email']
        );

        return $args;
    }

    private function get_billing_info( $order ){
        $args = array(
            "email"         => $order->get_billing_email(),
            "first_name"    => $this->limit_length( $order->get_billing_first_name(), 32 ),
            "last_name"     => $this->limit_length( $order->get_billing_last_name(), 64 ),
            "address"       => array(
                "line1"         => $this->limit_length( $order->get_billing_address_1(), 100 ),
                "line2"         => $this->limit_length( $order->get_billing_address_2(), 100 ),
                "city"          => $this->limit_length( $order->get_billing_city(), 40 ),
                "state"         => $this->get_state( $order->get_billing_country(), $order->get_billing_state() ),
                "postal_code"   => $this->limit_length( wc_format_postcode( $order->get_billing_postcode(), $order->get_billing_country() ), 32 ),
                "country_code"  => $this->limit_length( $order->get_billing_country(), 2 ),
            ),
        );

        return $args;
    }

    /** 
     * Shipping to address 
     * @param \WC_Order $order
     **/
    private function get_shipping_info( $order ){
        $args = array(
            "first_name"    => $this->limit_length( $order->get_shipping_first_name(), 32 ),
            "last_name"     => $this->limit_length( $order->get_shipping_last_name(), 64 ),
            "address"       => array(
                "line1"         => $this->limit_length( $order->get_shipping_address_1(), 100 ),
                "line2"         => $this->limit_length( $order->get_shipping_address_2(), 100 ),
                "city"          => $this->limit_length( $order->get_shipping_city(), 40 ),
                "state"         => $this->get_state( $order->get_shipping_country(), $order->get_shipping_state() ),
                "postal_code"   => $this->limit_length( wc_format_postcode( $order->get_shipping_postcode(), $order->get_shipping_country() ), 32 ),
                "country_code"  => $this->limit_length( $order->get_shipping_country(), 2 ),
            ),
        );

        return $args;
    }

    /**
     * All item lines, shipping cost, discount, tax as an item
     */
    private function get_items( $order ){
        $items = array();

        $currency = $order->get_currency();
        // Products.
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			if ( 'fee' === $item['type'] ) {
				$item_line_total   = $this->number_format( $item['line_total'], $order );
				$items[] = $this->get_item( $item->get_name(), 1, $item_line_total );
			} else {
				$product           = $item->get_product();
				$item_line_total   = $this->number_format( $order->get_item_subtotal( $item, false ), $order );
				$items[] = $this->get_item( $this->get_order_item_name( $order, $item ), $item->get_quantity(), $item_line_total, $currency );
			}
        }

        // shipping total
        if($order->get_shipping_total() > 0){
            $item_line_total = $this->number_format( $order->get_shipping_total(), $order );
            $items[] = $this->get_item( __('Total shipping cost', 'woo-manual-order'), 1, $item_line_total, $currency );
        }

        // total discount
        if($order->get_total_discount() > 0){
            $item_line_total = 0-$this->number_format( $order->get_total_discount(), $order );
            $items[] = $this->get_item( __('Total discount', 'woo-manual-order'), 1, $item_line_total, $currency );
        }

        // total tax
        if($order->get_total_tax() > 0){
            $item_line_total = $this->number_format( $order->get_total_tax(), $order );
            $items[] = $this->get_item( __('Total tax', 'woo-manual-order'), 1, $item_line_total, $currency );
        }

        return $items;
    }

    /**
	 * Add PayPal Line Item.
	 *
	 * @param  string $item_name Item name.
	 * @param  int    $quantity Item quantity.
	 * @param  float  $amount Amount.
	 * @param  string $item_number Item number.
	 */
	protected function get_item( $item_name, $quantity = 1, $amount = 0.0, $currency = 'USD' ) {

		$item = apply_filters(
			'woocommerce_paypal_invoicing_line_item', array(
				'item_name'   => html_entity_decode( wc_trim_string( $item_name ? $item_name : __( 'Item', 'woo-manual-order' ), 127 ), ENT_NOQUOTES, 'UTF-8' ),
				'quantity'    => (int) $quantity,
				'amount'      => wc_float_to_string( (float) $amount ),
			), $item_name, $quantity, $amount
		);
        return array(
            "name" => $this->limit_length( $item['item_name'], 200 ),
            "quantity" => $item['quantity'],
            "unit_price" => array (
                "currency" => $currency,
                "value" => $item['amount']
            ),
        );
	}

    //==========

    /**
	 * Get order item names as a string.
	 *
	 * @param  WC_Order      $order Order object.
	 * @param  WC_Order_Item $item Order item object.
	 * @return string
	 */
	protected function get_order_item_name( $order, $item ) {
		$item_name = $item->get_name();
		$item_meta = strip_tags(
			wc_display_item_meta(
				$item, array(
					'before'    => '',
					'separator' => ', ',
					'after'     => '',
					'echo'      => false,
					'autop'     => false,
				)
			)
		);

		if ( $item_meta ) {
			$item_name .= ' (' . $item_meta . ')';
		}

		return apply_filters( 'woocommerce_paypal_invoicing_get_order_item_name', $item_name, $order, $item );
	}
    
    /**
	 * Get the state to send to paypal.
	 *
	 * @param  string $cc Country two letter code.
	 * @param  string $state State code.
	 * @return string
	 */
	private function get_state( $cc, $state ) {
		if ( 'US' === $cc ) {
			return $state;
		}

		$states = WC()->countries->get_states( $cc );

		if ( isset( $states[ $state ] ) ) {
			return $states[ $state ];
		}

		return $state;
    }
    
    /**
	 * Limit length of an arg.
	 *
	 * @param  string  $string Argument to limit.
	 * @param  integer $limit Limit size in characters.
	 * @return string
	 */
	private function limit_length( $string, $limit = 127 ) {
		// As the output is to be used in http_build_query which applies URL encoding, the string needs to be
		// cut as if it was URL-encoded, but returned non-encoded (it will be encoded by http_build_query later).
		$url_encoded_str = rawurlencode( $string );

		if ( strlen( $url_encoded_str ) > $limit ) {
			$string = rawurldecode( substr( $url_encoded_str, 0, $limit - 3 ) . '...' );
		}
		return $string;
    }
    
    /**
	 * Check if currency has decimals.
	 *
	 * @param  string $currency Currency to check.
	 * @return bool
	 */
	protected function currency_has_decimals( $currency ) {
		if ( in_array( $currency, array( 'HUF', 'JPY', 'TWD' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Round prices.
	 *
	 * @param  double   $price Price to round.
	 * @param  WC_Order $order Order object.
	 * @return double
	 */
	protected function round( $price, $order ) {
		$precision = 2;

		if ( ! $this->currency_has_decimals( $order->get_currency() ) ) {
			$precision = 0;
		}

		return round( $price, $precision );
    }
    
    /**
	 * Format prices.
	 *
	 * @param  float|int $price Price to format.
	 * @param  WC_Order  $order Order object.
	 * @return string
	 */
	protected function number_format( $price, $order ) {
		$decimals = 2;

		if ( ! $this->currency_has_decimals( $order->get_currency() ) ) {
			$decimals = 0;
		}

		return number_format( $price, $decimals, '.', '' );
    }
    
    /**
     * Remote add log
     */
    private function remote_log( $message ){
        global $woo_manual_order;

        $woo_manual_order->log( $message, 'paypal_invoicing' );
    }
}
