<?php
/**
 * Plugin Name: LUNACI Cache Vary Fix
 * Description: Lets LiteSpeed serve cached pages to first-time visitors and search engines.
 * Author: LUNACI
 *
 * LiteSpeed Cache's WooCommerce Multilingual integration adds the client
 * currency to the vary list of every visitor (LiteSpeed\Thirdparty\WCML::
 * apply_vary). For guests this makes the vary non-empty, so each response
 * sets the _lscache_vary cookie and a request without cookies (Googlebot, a
 * first visit) always misses the cache: TTFB 1-5 s instead of ~0.3 s.
 * Diagnosed 2026-10-03 (diagnose-lscache-guest-vary.yml).
 *
 * The store sells in a single currency, so the currency entry carries no
 * information. It is removed only while WCML has fewer than two active
 * currencies; with two or more, LiteSpeed's behaviour is left unchanged.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Active WCML currency codes (same sources LiteSpeed's integration reads).
 */
function lunaci_cache_wcml_currencies() {
	global $woocommerce_wpml;
	if ( is_object( $woocommerce_wpml )
		&& isset( $woocommerce_wpml->multi_currency )
		&& is_object( $woocommerce_wpml->multi_currency )
		&& method_exists( $woocommerce_wpml->multi_currency, 'get_currency_codes' )
	) {
		return (array) $woocommerce_wpml->multi_currency->get_currency_codes();
	}
	$settings = get_option( '_wcml_settings', array() );
	if ( empty( $settings['enable_multi_currency'] ) ) {
		return array(); // Multi-currency off: the store currency only.
	}
	if ( ! empty( $settings['currency_options'] ) && is_array( $settings['currency_options'] ) ) {
		return array_keys( $settings['currency_options'] );
	}
	return array();
}

// Runs after LiteSpeed's WCML integration (priority 10).
add_filter(
	'litespeed_vary',
	function ( $vary ) {
		if ( is_array( $vary ) && isset( $vary['wcml_currency'] ) && count( lunaci_cache_wcml_currencies() ) < 2 ) {
			unset( $vary['wcml_currency'] );
		}
		return $vary;
	},
	20
);
