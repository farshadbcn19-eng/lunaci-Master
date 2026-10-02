<?php
/**
 * Plugin Name: LUNACI Shipping Labels (ES)
 * Description: Shows the shipping method names in Spanish on the Spanish site
 *              (WPML language "es"). English stays as configured in WooCommerce.
 * Author: LUNACI
 *
 * The two methods were created in Phase 2 (2026-10-02): free_shipping
 * (España, Península y Baleares) and flat_rate EUR 14.90 (European Union).
 * WooCommerce stores one title per method; this relabels the calculated
 * rates per request language, so cart, checkout, order and emails show the
 * Spanish name for orders placed on /es/. The language is added to the
 * shipping package so the cached rates differ per language.
 */

defined( 'ABSPATH' ) || exit;

function lunaci_shipping_lang() {
	return (string) apply_filters( 'wpml_current_language', null );
}

add_filter(
	'woocommerce_cart_shipping_packages',
	function ( $packages ) {
		foreach ( $packages as $i => $package ) {
			$packages[ $i ]['lunaci_lang'] = lunaci_shipping_lang();
		}
		return $packages;
	}
);

add_filter(
	'woocommerce_package_rates',
	function ( $rates ) {
		if ( 'es' !== lunaci_shipping_lang() ) {
			return $rates;
		}
		$labels = array(
			'free_shipping' => 'Envío gratuito · 5–8 días laborables',
			'flat_rate'     => 'Envío estándar',
		);
		foreach ( $rates as $rate ) {
			$method = $rate->get_method_id();
			if ( isset( $labels[ $method ] ) ) {
				$rate->set_label( $labels[ $method ] );
			}
		}
		return $rates;
	},
	100
);
