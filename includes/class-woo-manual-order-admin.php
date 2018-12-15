<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manual Order Admin
 * 
 * @package		Woo Manual Order Admin
 * @subpackage	Woo_Manual_Order_Admin
 * @category	Class
 * @author		D.Bui
 * @since		1.0.0
 */
class Woo_Manual_Order_Admin {
    /**
	 * The WooCommerce settings tab name
	 *
	 * @since 1.0
	 */
    public static $tab_name = 'manual-order';
    
    /**
	 * The prefix for manual order settings
	 *
	 * @since 1.0
	 */
    public static $option_prefix = 'woocommerce_manual_order';
    
    /**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.0
	 */
	public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_' . self::$tab_name, __CLASS__ . '::settings_page' );
		add_action( 'woocommerce_update_options_' . self::$tab_name, __CLASS__ . '::update_settings' );
		
		// Add dropdown to admin orders screen to filter on order type
		add_action( 'restrict_manage_posts', __CLASS__ . '::restrict_manage_order', 50 );
		
		// Add filter to queries on admin orders screen to filter on order type. To avoid WC overriding our query args, we need to hook on after them on 10.
		add_filter( 'request', __CLASS__ . '::orders_by_type_query', 11 );

		add_action( 'admin_enqueue_scripts', __CLASS__ . '::enqueue_styles_scripts' );

		add_action( 'wp_ajax_manual_order_create_page', array( __CLASS__, 'create_manual_order_page' ) );

		add_filter( 'woocommerce_admin_reports', array( __CLASS__, 'admin_reports' ) );
		add_filter( 'woocommerce_reports_charts', array( __CLASS__, 'admin_reports' ) );
		add_filter( 'wc_admin_reports_path', array( __CLASS__, 'admin_reports_path' ), 10, 3 );
    }

    /**
	 * Get all the settings for the Subscriptions extension in the format required by the @see woocommerce_admin_fields() function.
	 *
	 * @return array Array of settings in the format required by the @see woocommerce_admin_fields() function.
	 * @since 1.0
	 */
	public static function get_settings() {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/user.php' );
		}

		$roles = get_editable_roles();

		foreach ( $roles as $role => $details ) {
			$roles_options[ $role ] = translate_user_role( $details['name'] );
		}

		$args = array(
			'business'		=> 'SHBNL4U35NFGW',
			'cmd'			=> '_donations',
			'currency_code'	=> 'USD',
			'item_name' 	=> 'Buy a coffee',
		);
		$endpoint = 'https://www.paypal.com/cgi-bin/webscr?';
		$link = add_query_arg($args, $endpoint);

		$donations_link = sprintf( '<a style="display:inline-block;" href="%s" target="_blank"><img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" alt="PayPal - The safer, easier way to donate."></a>', $link );

		return apply_filters( 'woocommerce_manual_order_settings', array(

			array(
				'title'     	=> __( 'Manual Order Page', 'woo-manual-order' ),
				'type'     	=> 'title',
				'desc'     	=> '',
				'id'       	=> self::$option_prefix . '_section_open',
			),
            
			array(
				'title'    	=> __( 'Manual Order page', 'woo-manual-order' ),
                /* Translators: %s Page contents. */
				'desc'     	=> '<p>' . sprintf( __( 'These page need to be set so that Woo-Manual-Order knows where the function to do. You can create a page with content: [%s], or <a id="%s" href="#">auto create page</a>', 'woo-manual-order' ), 'woocommerce_manual_order', 'woo-manual-order-create-page' ) . '</p>',
                'id'       	=> self::$option_prefix . '_page_id',
                'type'     	=> 'single_select_page',
                'default'  	=> '',
                'class'    	=> 'wc-enhanced-select-nostd',
                'css'      	=> 'min-width:300px;',
                'desc_tip' 	=> false,
			),

			array(
				'title'     	=> __( 'Allow Roles', 'woo-manual-order' ),
				'desc'     	=> __( 'Use Access Roles to control what users (or User Groups) can do within the Manual Order feature', 'woo-manual-order' ),
				'tip'      	=> '',
				'id'       	=> self::$option_prefix . '_roles',
				'type'     	=> 'multiselect',
				'class'		=> 'wc-enhanced-select',
				'options'  	=> $roles_options,
				'desc_tip' 	=> true,
			),

			array(
				'title'     	=> __( 'Show all available gateways', 'woo-manual-order' ),
				'desc'            => __( 'Allow checkout all available gateways, instead of gateways has only support "Invoicing" type', 'woo-manual-order' ),
				'id'              => self::$option_prefix . '_all_gateways',
				'default'         => 'no',
				'type'            => 'checkbox',
			),

			array(
				'title'     	=> __( 'Hide product variable', 'woo-manual-order' ),
				'desc'            => __( 'If you want to hide all product variable undefined price', 'woo-manual-order' ),
				'id'              => self::$option_prefix . '_hide_product_variable',
				'default'         => 'yes',
				'type'            => 'checkbox',
			),
			
			array( 'type' => 'sectionend', 'id' => self::$option_prefix . '_section_end' ),

			array(
				'title'     => '',
				'type'      => 'title',
				'desc' 		=> sprintf('If you think this plugin is useful, please buy me a coffee. Thank so much! :-) %s', $donations_link),
			),

			array( 'type' => 'sectionend', 'id' => self::$option_prefix . '_credentials_end' ),
		) );

    }
    
    /**
	 * Add the manual order settings tab to the WooCommerce settings tabs array.
	 *
	 * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
	 * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
	 * @since 1.0
	 */
	public static function add_settings_tab( $settings_tabs ) {

		$settings_tabs[ self::$tab_name ] = __( 'Manual Order', 'woo-manual-order' );

		return $settings_tabs;
    }
    
    /**
	 * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
	 *
	 * @uses woocommerce_admin_fields()
	 * @uses self::get_settings()
	 * @since 1.0
	 */
	public static function settings_page() {
		woocommerce_admin_fields( self::get_settings() );
		wp_nonce_field( 'woo_manual_order_settings', '_wmononce', false );
		
		?>
		<script>
			jQuery(document).ready(function($){
				$('#woo-manual-order-create-page').on('click', function(ev){
					ev.preventDefault();

					if (confirm('<?php esc_html_e( 'Are you sure you want to do this action?', 'woo-manual-order' ) ?>')) {
						jQuery.post( ajaxurl, {
								'action': 'manual_order_create_page',
								'_wmononce': $('#_wmononce').val(),
							}, 
							function(response) {
								window.location.reload();
							}
						);
					}
				});
				
			});
		</script>
		<?php
	}
	
	/**
	 * Ajax create new page
	 */
	public static function create_manual_order_page(){

		check_ajax_referer( 'woo_manual_order_settings', '_wmononce' );

		$page_id = wc_create_page( 'manual-order'
			, 'woocommerce_manual_order_page_id'
			, _x( 'Manual Order', 'Page title', 'woo-manual-order' )
			, '[woocommerce_manual_order]'
			, '' 
		);
		if( ! empty( $page_id ) ){
			wp_send_json(array(
				'result' => 'success',
				'page_id' => $page_id,
			));
		}

		exit;
	}
    
    /**
	 * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
	 *
	 * @uses woocommerce_update_options()
	 * @uses self::get_settings()
	 * @since 1.0
	 */
	public static function update_settings() {

		if ( empty( $_POST['_wmononce'] ) || ! wp_verify_nonce( $_POST['_wmononce'], 'woo_manual_order_settings' ) ) {
			return;
        }

		$settings = self::get_settings();
		
		foreach ( $settings as $setting ) {
			if ( ! isset( $setting['id'], $setting['default'], $_POST[ $setting['id'] ] ) ) {
				continue;
			}

			// Set the setting to its default if no value has been submitted.
			if ( '' === wc_clean( $_POST[ $setting['id'] ] ) ) {
				$_POST[ $setting['id'] ] = $setting['default'];
			}
		}

		woocommerce_update_options( $settings );
	}
	
	/**
	 * Adds all necessary admin styles.
	 *
	 * @since 1.0
	 */
	public static function enqueue_styles_scripts() {
		global $post, $woo_manual_order;
		// Get admin screen id
		$screen = get_current_screen();

		$is_woocommerce_screen = in_array( $screen->id, array( 'edit-shop_order', 'shop_order' ) );

		if ( $is_woocommerce_screen ) {
			wp_enqueue_style( 'woo_manual_order_admin_styles', $woo_manual_order->plugins_url('/assets/css/admin.css'), array(), WC_MANUAL_ORDER_EXTENSION_VERSION );
		}
	}

	/**
	 * Add admin dropdown for order types to Woocommerce -> Orders screen
	 *
	 * @since 1.0
	 */
	public static function restrict_manage_order(){
		global $typenow;

		if ( 'shop_order' != $typenow ) {
			return;
		}
		$subtype = isset($_GET['manual_order_subtype']) ? $_GET['manual_order_subtype'] : '';

		?><input name="manual_order_subtype" id="manual_order_subtype" type="checkbox" value="1"<?php (checked( 1, $subtype )) ?> class="manual-order-subtype" title="<?php esc_html_e('Manual Order', 'woo-manual-order') ?>" /><?php
	}

	/**
	 * Add request filter for order types to Woocommerce -> Orders screen
	 *
	 * Including or excluding posts with a '_manual_order' meta value
	 *
	 * @param array $vars
	 * @since 1.0
	 */
	public static function orders_by_type_query( $vars=array() ) {
		global $typenow, $wpdb;

		if ( 'shop_order' == $typenow && ! empty( $_GET['manual_order_subtype'] ) ) {

			$meta_key = apply_filters( 'woocommerce_manual_order_admin_order_type_filter_meta_key', '_manual_order' );

			if ( ! empty( $meta_key ) ) {
				$vars['meta_query'][] = array(
					'key'     => $meta_key,
					'compare' => 'EXISTS',
				);
			}
		}

		return $vars;
	}

	/**
	 * Add link report by Manual Order
	 * 
	 * @param array $reports
	 */
	public static function admin_reports($reports){
		$reports['orders']['reports']['sales_by_manual_order'] = array(
			'title'       => __( 'Sales by Manual Order', 'woo-manual-order' ),
			'description' => '',
			'hide_title'  => true,
			'callback'    => 'WC_Admin_Reports::get_report',
		);
		return $reports;
	}

	/**
	 * Get path of file contain WC_Report_Sales_By_Product
	 * 
	 * @param string $path
	 * @param string $name
	 * @param string $class
	 */
	public static function admin_reports_path($path='', $name='', $class=''){
		global $woo_manual_order;

		if( 'sales-by-manual-order' == $name ) {
			if ( ! function_exists( 'WC_Report_Sales_By_Date' ) ) {
				require_once WC()->plugin_path() . '/includes/admin/reports/class-wc-report-sales-by-date.php';
			}
			return $woo_manual_order->plugins_dir( 'includes/class-woo-manual-order-report.php' );
		}
		return $path;
	}
}

Woo_Manual_Order_Admin::init();