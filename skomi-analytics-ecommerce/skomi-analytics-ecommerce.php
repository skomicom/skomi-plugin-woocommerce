<?php
/**
 * Plugin Name:       Skomi for WooCommerce
 * Plugin URI:        https://skomi.com/docs/installing-on-wordpress
 * Description:       Reports WooCommerce purchases to Skomi, with the order's own total, currency and number — so revenue is attributed to the visit that earned it.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Requires Plugins:  skomi-analytics
 * WC requires at least: 7.0
 * Author:            Skomi
 * Author URI:        https://skomi.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       skomi-analytics-ecommerce
 *
 * An add-on to Skomi Analytics: that plugin loads the script, this one gives it
 * the two events a shop actually needs.
 *
 * ⚠ <b>Unlike Shopify, a WooCommerce checkout is yours.</b> Shopify has retired
 * every hook a script had into its checkout, so no tool can measure a purchase
 * there. Here the thank-you page is a page of your own site, which is why this
 * plugin can exist at all.
 */

defined( 'ABSPATH' ) || exit;

define( 'SKOMI_WC_VERSION', '1.0.0' );

/** The order meta key that records a purchase as already reported. */
define( 'SKOMI_WC_TRACKED_META', '_skomi_tracked' );

/**
 * High-Performance Order Storage. Declared, because a shop with HPOS on and a
 * plugin that has not declared compatibility gets a warning telling them to
 * deactivate it.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Both halves have to be present: this plugin reports events to a script the
 * other one loads, and on its own it would put calls on a page where nothing
 * answers them.
 */
function skomi_wc_ready() {
	return function_exists( 'skomi_analytics_should_track' )
		&& function_exists( 'WC' )
		&& skomi_analytics_should_track();
}

/** Tell an administrator why nothing is happening, rather than failing quietly. */
function skomi_wc_admin_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	if ( ! function_exists( 'skomi_analytics_should_track' ) ) {
		echo '<div class="notice notice-warning"><p>';
		esc_html_e(
			'Skomi for WooCommerce needs the Skomi Analytics plugin, which loads the script it reports to.',
			'skomi-analytics-ecommerce'
		);
		echo '</p></div>';

		return;
	}

	if ( ! function_exists( 'WC' ) ) {
		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'Skomi for WooCommerce needs WooCommerce.', 'skomi-analytics-ecommerce' );
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'skomi_wc_admin_notice' );

// ------------------------------------------------------------------ purchase

/**
 * Report one completed order, once.
 *
 * ⚠ <b>The whole difficulty here is firing exactly once.</b> A thank-you page is
 * reloaded: somebody refreshes it, presses back, or opens the confirmation email
 * a week later and lands on the same URL. Each of those is a page view and none
 * of them is a second sale.
 *
 * Two defences, and both are needed:
 *
 *  1. <b>Order meta.</b> Once reported, the order carries a flag and this returns
 *     early for ever after. That covers the same browser and any other.
 *  2. <b>The order number travels to Skomi.</b> The server drops a repeat of an
 *     order id it has already counted, so even if this plugin were reinstalled,
 *     or the meta lost in a migration, the sale is not counted twice.
 *
 * The first stops the request being made; the second stops it mattering. A
 * shop's revenue figure is the number nobody re-checks, so it is worth two.
 */
function skomi_wc_purchase( $order_id ) {
	if ( ! skomi_wc_ready() || ! $order_id ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		return;
	}

	if ( $order->get_meta( SKOMI_WC_TRACKED_META ) ) {
		return;
	}

	/**
	 * Filters the value reported for an order.
	 *
	 * Defaults to the order total, which includes tax and shipping — the amount
	 * the customer actually paid, and the one a shop recognises. Subtract them
	 * here if your reporting works the other way.
	 *
	 * @param float    $value The amount to report.
	 * @param WC_Order $order The order.
	 */
	$value = (float) apply_filters( 'skomi_wc_order_value', (float) $order->get_total(), $order );

	$props = array(
		'items'          => (string) $order->get_item_count(),
		'payment_method' => (string) $order->get_payment_method_title(),
	);

	if ( $order->get_coupon_codes() ) {
		$props['coupon'] = (string) implode( ',', $order->get_coupon_codes() );
	}

	/**
	 * Filters the properties reported with a purchase.
	 *
	 * ⚠ Skomi keeps at most 25 properties per event and 255 characters per
	 * value, and stores them as text. Line items do not fit and are not sent —
	 * which product sold is a question for WooCommerce's own reports.
	 *
	 * @param array    $props The properties.
	 * @param WC_Order $order The order.
	 */
	$props = (array) apply_filters( 'skomi_wc_order_props', $props, $order );

	skomi_wc_emit(
		'purchase',
		$props,
		$value,
		$order->get_currency(),
		(string) $order->get_order_number()
	);

	$order->update_meta_data( SKOMI_WC_TRACKED_META, current_time( 'mysql', true ) );
	$order->save();
}
add_action( 'woocommerce_thankyou', 'skomi_wc_purchase', 10, 1 );

// ------------------------------------------------------------ begin checkout

/**
 * Reported when the checkout form is shown with something in the cart.
 *
 * ⚠ Not a goal in itself — it is the denominator. The rate from a product page
 * to reaching checkout is the number most shops are actually trying to move, and
 * without this event the funnel ends at the cart.
 */
function skomi_wc_begin_checkout() {
	if ( ! skomi_wc_ready() || ! WC()->cart || WC()->cart->is_empty() ) {
		return;
	}

	$cart = WC()->cart;

	skomi_wc_emit(
		'begin_checkout',
		array( 'items' => (string) $cart->get_cart_contents_count() ),
		(float) $cart->get_total( 'edit' ),
		get_woocommerce_currency(),
		null
	);
}
add_action( 'woocommerce_before_checkout_form', 'skomi_wc_begin_checkout', 10, 0 );

// ---------------------------------------------------------------- the emitter

/**
 * Print one `skomi.track(…)` call.
 *
 * ⚠ <b>Guarded on `window.skomi` existing.</b> The bundle is deferred and this
 * runs inline, so on a slow connection the call can be reached before the script
 * has finished loading — and an unguarded call would throw a ReferenceError into
 * a customer's checkout. Queued on the load event when it is not there yet.
 *
 * Everything is JSON-encoded rather than concatenated: a product name with an
 * apostrophe in it is the most ordinary thing in a shop and would otherwise end
 * the string.
 */
function skomi_wc_emit( $event, array $props, $value, $currency, $order_id ) {
	$payload = wp_json_encode(
		array(
			'event'    => (string) $event,
			'props'    => array_map( 'strval', $props ),
			'value'    => round( (float) $value, 2 ),
			'currency' => (string) $currency,
			'orderId'  => null === $order_id ? null : (string) $order_id,
		)
	);

	if ( false === $payload ) {
		return;
	}

	$script = "( function () {
	var d = {$payload};
	function send() {
		if ( ! window.skomi || typeof window.skomi.track !== 'function' ) { return false; }
		window.skomi.track( d.event, d.props, d.value, d.currency, d.orderId );
		return true;
	}
	if ( ! send() ) { window.addEventListener( 'load', send ); }
}() );";

	wp_print_inline_script_tag( $script, array( 'id' => 'skomi-wc-' . $event ) );
}
