<?php
/**
 * Checkout Form
 * @package woo-manual-orders/Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $woo_manual_order;

if ( !is_user_logged_in() ) {
	$woo_manual_order->get_template( 'checkout/form-login.php', array(
		'checkout' => $checkout,
	) );
	return;
}

// check user permissions
if( ! $woo_manual_order->is_user_in_role() ){
	_e( 'You do not have permission to access this feature.', 'woo-manual-order' );
	return;
}

$customer = $woo_manual_order->customer;

$fullname = '';
if( $customer && $customer->get_id() > 0 ){
	$fullname = sprintf(
		esc_html__( '%1$s (#%2$s &ndash; %3$s)', 'woo-manual-order' ),
		$customer->get_first_name() . ' ' . $customer->get_last_name(),
		$customer->get_id(),
		$customer->get_email()
	);
}

?>
<div class="woo-manual-order-wrap">
	<div class="form-row">
		<div class="woo-manual-choose-customer">
			<label for="wc-customer-search"><?php _e( 'Choose Customer', 'woo-manual-order' ); ?></label>
			<select class="wc-customer-search" id="wc-customer-search" 
				data-placeholder="<?php esc_attr_e( 'Guest', 'woo-manual-order' ); ?>" data-allow_clear="true">
				<?php if( ! empty($fullname) ): ?>
				<option value="<?php echo $customer->get_id() ?>" selected="true"><?php echo $fullname ?></option>
				<?php endif; ?>
			</select>
		</div>
	</div>
	<div class="form-row">
		<div class="woo-manual-product-search">
			<label for="wc-product-search"><?php _e( 'Choose Products', 'woo-manual-order' ); ?></label>
			<select id="wc-product-search" class="wc-product-search" name="product_id" 
				data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woo-manual-order' ); ?>" 
				data-action="woocommerce_manual_order_search_products"
				data-allow_clear="true">
			</select>
		</div>
	</div>
</div>
<div class="woocommerce-cart woo-manual-order-cart-form">
	<?php
	$woo_manual_order->get_template( 'checkout/cart.php' );
	?>
</div>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
	<input type="hidden" name="manual_order_checkout" value="1" />
	<?php if ( $checkout->get_checkout_fields() ) : ?>

		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<div class="col2-set" id="customer_details" class="woo-manual-order-customer-details">
			<?php $woo_manual_order->get_template( 'checkout/customer-details.php', array( 'checkout' => $checkout ) ); ?>
		</div>

		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	<?php endif; ?>

	<h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woo-manual-order' ); ?></h3>

	<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

	<div id="order_review" class="woocommerce-checkout-review-order">
		<?php do_action( 'woocommerce_checkout_order_review' ); ?>
	</div>

	<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

<?php add_thickbox(); ?>
