<?php
/**
 * Checkout login form
 *
 * @package woo-manual-orders/Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) {
	return;
}

?>
<div class="woocommerce-form-login-toggle">
	<?php wc_print_notice( apply_filters( 'woocommerce_checkout_login_message', __( 'Please login to use this feature,', 'woo-manual-order' ) ) . ' <a href="#" class="showlogin">' . __( 'Click here to login', 'woo-manual-order' ) . '</a>', 'notice' ); ?>
</div>
<?php

woocommerce_login_form(
	array(
		'message'  => __( 'Username or Email Address.', 'woo-manual-order' ),
		'redirect' => wc_get_page_permalink( 'manual_order' ),
		'hidden'   => true,
	)
);
