<?php 

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Controller class
 */
final class Woo_Manual_Order_Ctrl {

    /** Plugin name */
    var $plugin_path = '';

	/** @var string the plugin url */
	var $plugin_url;

	/** @var \WC_Logger instance */
	var $logger;

	/**
	 * Store all notice
	 */
	var $_notices = array();

	var $customer;

	/**
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $_instance;

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function instance( $file_path ) {
        if ( null === self::$_instance ) {
            self::$_instance = new self( $file_path );
        }
        return self::$_instance;
	}

	/**
	 * Initializes the plugin
	 *
	 * @since 1.0
	 */
	public function __construct( $file_path ) {

        $this->plugin_path = $file_path;

		// include required files
		$this->includes();
		
		$this->init_hooks();

		// load translation
		add_action( 'init', array( $this, 'load_translation' ) );
		
		// admin
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {

			// dependency check
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );

			// add a 'Configure' link to the plugin action links
			add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin_path ), array( $this, 'add_plugin_setup_link' ) );
			
			// run every time
			$this->install();

		} else {

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );
		}
	}

	/**
	 * Include required files
	 *
	 * @since 1.0
	 */
	public function includes() {
		require_once( $this->plugins_dir( 'includes/class-woo-manual-shortcodes.php' ) );
		require_once( $this->plugins_dir( 'includes/shortcodes/class-woo-manual-order-checkout.php' ) );
		require_once( $this->plugins_dir( 'includes/class-woo-manual-order-ajax.php' ) );

		require_once( $this->plugins_dir( 'includes/woo-manual-order-functions.php' ) );

		// admin
		require_once( $this->plugins_dir( 'includes/class-woo-manual-order-admin.php' ) );

		// gateways
		require_once( $this->plugins_dir( 'includes/gateways/class-woo-gateway-invoicing.php' ) );
		require_once( $this->plugins_dir( 'includes/gateways/holder/class-woo-gateway-holder.php' ) );
		require_once( $this->plugins_dir( 'includes/gateways/paypal/class-woo-gateway-paypal-invoicing.php' ) );
		require_once( $this->plugins_dir( 'includes/gateways/paypal/class-woo-paypal-invoicing-remote.php' ) );
	}
	
	/**
	 * Handle localization, WPML compatible
	 *
	 * @since 1.0
	 */
	public function load_translation() {
		// localization in the init action for WPML support
		load_plugin_textdomain( WC_MANUAL_ORDER_EXTENSION_SLUG, false, dirname( plugin_basename( $this->plugin_path ) ) . '/languages' );
	}

	/** Admin methods ******************************************************/

	/**
	 * Checks if required PHP extensions are loaded and SSL is enabled.
	 * Adds an admin notice if either check fails
	 *
	 * @since 1.0
	 */
	public function admin_notices() {

		if(count($this->_notices) > 0){
			echo '<div class="error">';
			foreach( $this->_notices as $notice ){
				echo '<p>' . $notice . '</p>';
			}
			echo '</div>';
		}
	}
    /**
	 * Return the plugin action links.  This will only be called if the plugin
	 * is active.
	 *
	 * @since 1.0
	 * @param array $actions associative array of action names to anchor tags
	 * @return array associative array of plugin action links
	 */
	public function add_plugin_setup_link( $actions ) {

		$manage_url = admin_url( 'admin.php' );

		$manage_url = add_query_arg( array( 'page' => 'wc-settings', 'tab' => 'manual-order', 'section' => 'manual-order' ), $manage_url ); // WC 2.0+

		// add the link to the front of the actions list
		return ( array_merge( array( 
			'configure' => sprintf( '<a href="%s">%s</a>', $manage_url, __( 'Settings', 'woo-manual-order' ) ),
			'docs' => sprintf( '<a href="%s">%s</a>', 'https://docs.w4press.com/woo-manual-orders', __( 'Docs', 'woo-manual-order' ) ),
		), $actions ) );
	}

	//== Front-end=============

	/**
	 * Handle frontend scripts
	 */
	public function enqueue_styles_scripts(){
		// Load for all page
		wp_enqueue_style( 'woo_manual_order_styles', $this->plugins_url('/assets/css/styles.css'), array(), WC_MANUAL_ORDER_EXTENSION_VERSION );
	}

	/** Helper methods ******************************************************/
	
	/**
	 * This will ensure any links output to a page (when viewing via HTTPS) are also served over HTTPS.
	 *
	 * @since 1.0
	 * @return string url
	 */
	public function force_ssl( $url ) {

		return WC_HTTPS::force_https_url( $url );
	}

	/**
	 * Gets the absolute plugin path without a trailing slash, e.g.
	 * /path/to/wp-content/plugins/plugin-directory
	 *
	 * @since 1.0
	 * @return string plugin path
	 */
	public function plugins_dir( $path='' ) {
		// Besure without the first slash
		$path = ltrim($path, '/');

		$dir_path = plugin_dir_path( $this->plugin_path );
		
		return $dir_path . $path;
	}

	/**
	 * Gets the plugin url without a trailing slash
	 *
	 * @since 1.0
	 * 
	 * @param $path: Path to the plugin file of which URL you want to retrieve
	 * @return string the plugin url
	 */
	public function plugins_url( $path='' ) {
		// Besure without the first slash
		$path = ltrim($path, '/');
		
		if( ! empty( $this->plugin_url )) {
			return $this->plugin_url . $path;
		}

		$this->plugin_url = plugins_url( '/', $this->plugin_path );
		
		return $this->plugin_url . $path;
	}

	/**
     * Get other templates (e.g. product attributes) passing attributes and including the file.
     * Extend from woocommerce function
     */
    public function get_template( $template_name, $args = array()){
        wc_get_template( $template_name, $args, '', $this->plugins_dir( 'templates/' ) );
    }
	
	/**
	 * Log errors / messages to WooCommerce error log (/wp-content/woocommerce/logs/)
	 *
	 * @since 1.0
	 * @param string $message
	 */
	public function log( $message, $id='manual_order' ) {
		
        if ( ! is_object( $this->logger ) )
            $this->logger = new WC_Logger();
		
		$this->logger->add( $id, $message );
	}
	
	/**
	 * Add message
	 *
	 * @since 1.0
	 * @param string $message
	 */
	public function add_message( $message='', $type='error' ) {
		global $woocommerce;
		wc_add_notice( $message, $type );
	}

	/** Install ******************************************************/

	/**
	 * Run every time.  Used since the activation hook is not executed when updating a plugin
	 *
	 * @since 1.0
	 */
	private function install() {

		// get current version to check for upgrade
		$installed_version = get_option( 'woocommerce_manual_order_version' );

		// upgrade if installed version lower than plugin version
		if ( -1 === version_compare( $installed_version, WC_MANUAL_ORDER_EXTENSION_VERSION ) )
			$this->upgrade( $installed_version );
	}

	/**
	 * Perform any version-related changes.
	 *
	 * @since 1.0
	 * @param int $installed_version the currently installed version of the plugin
	 */
	private function upgrade( $installed_version ) {

		// update the installed version option
		update_option( 'woocommerce_manual_order_version', WC_MANUAL_ORDER_EXTENSION_VERSION );
	}
	
	//================ Action ================//
	/** after woocommerce loaded */
	public function init_hooks(){
		add_action( 'woocommerce_init', array( $this, 'init' ) );
		add_action( 'wp', array( $this, 'loaded' ) );
	}

	/** Init method */
	public function init(){
		
		require_once( $this->plugins_dir( 'includes/woo-manual-order-migrate.php' ) );

		if( ! is_admin() ){
			Woo_Manual_Order_Shortcodes::init();
		}

		// add to WC payment methods
		add_filter( 'woocommerce_payment_gateways', array( $this, 'load_gateway' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
	}

	/**
	 * Hook to action after init
	 */
	public function loaded(){
		if( ! is_admin() ){
			if( is_manual_order_page() ) {
				$this->set_customer(false);
			}
		}
	}

	/**
	 * Load integrated gateway
	 * @param array $gateways
	 */
	public function load_gateway($gateways){
		$gateways[] = 'Woo_Gateway_Holder';
		$gateways[] = 'Woo_Gateway_Paypal_Invoicing';
		return $gateways;
	}

	/**
	 * General script
	 */
	public function load_scripts(){
		global $woo_manual_order;
		wp_enqueue_script( 'woo-manual-order', $woo_manual_order->plugins_url( 'assets/js/manual-order.js' ) );
	}

	/**
	 * Store customer id in session
	 */
	public function is_assigned_customer(){
		$customer_id = WC()->session->get( 'manual_oder_customer_id' );

		if( is_wp_error($customer_id)  ) $customer_id = 0;

		if( empty( $customer_id ) ) return false;
		
		return (int)$customer_id;
	}

	/** Set Customer object */
	public function set_customer($customer_id=false){
		if( empty ($this->customer )) {
			
			if( empty( WC()->session ) ) return $customer_id;

			if( false === $customer_id){
				$customer_id = WC()->session->get( 'manual_oder_customer_id', 0 );
			} elseif ( 0 === $customer_id) {
				// unset session
				WC()->session->__unset( 'manual_oder_customer_id' );
			} else {
				WC()->session->set( 'manual_oder_customer_id', $customer_id );
			}

			if( is_wp_error($customer_id)  ) {
				$customer_id = 0;
			}

			$this->customer    = new WC_Customer( $customer_id, true );
		}
		WC()->customer = $this->customer;
	}

	/**
	 * Get allowed roles
	 */
	public function get_roles(){
		return get_option( 'woocommerce_manual_order_roles', array() );
	}

	// check permission
	public function is_user_in_role(){
		$roles = $this->get_roles();
		foreach( $roles as $role ){
			if ( current_user_can( $role ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get page id of woocommerce
	 * @param string $page the page name
	 * @return int page id
	 */
	public function get_page_id( $page='' ) {
		if( ! empty( $page ) ) {
			return wc_get_page_id( $page );
		}
	}
	
	/**
	 * Get page link url of woocommerce
	 * @param string $page the page name
	 * @return int page id
	 */
	public function get_page_url( $page='' ) {
		return get_permalink( $this->get_page_id( $page ) );
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'woo-manual-order' ), '1.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woo-manual-order' ), '1.0' );
	}

}