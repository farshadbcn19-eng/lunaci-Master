<?php
/**
 * Plugin Name: LUNACI Product Page
 * Description: Brings WooCommerce single product pages in line with the live
 *              /products/ page design. CSS only; no HTML or copy changes.
 * Author: LUNACI
 *
 * Spec: SPEC.md (consistent single-product pages). Plan: tasks/plan.md.
 *
 * Prints one <style id="lunaci-product-page"> block on product pages only,
 * after the store styling of lunaci-perf.php (items 6 and 7, priorities 999
 * and 1000), so it can refine them without fighting them.
 *
 * Mode: option lunaci_product_page_mode = "off" (default), "test" (only on
 * URLs with ?lunaci_perf=1, visitors see no change) or "on".
 * Rollback: set the mode to "off" (workflow deploy-product-page.yml).
 *
 * The off/test/on logic is lunaci_perf_mode_active() from lunaci-perf.php
 * (mu-plugins load alphabetically, so it is defined first). If it is ever
 * missing, this layer stays off rather than turning on unconditionally.
 */

defined( 'ABSPATH' ) || exit;

function lunaci_product_page_active() {
	return function_exists( 'lunaci_perf_mode_active' )
		&& function_exists( 'is_product' )
		&& is_product()
		&& lunaci_perf_mode_active( 'lunaci_product_page_mode' );
}

function lunaci_product_page_css() {
	// T2 placeholder: an empty rule set, so the deploy pipeline can be proven
	// before anything visible changes. T4-T6 replace it.
	return 'body.single-product div.product{}';
}

add_action(
	'wp_head',
	function () {
		if ( ! lunaci_product_page_active() ) {
			return;
		}
		echo '<style id="lunaci-product-page">' . lunaci_product_page_css() . '</style>' . "\n";
	},
	1001
);
