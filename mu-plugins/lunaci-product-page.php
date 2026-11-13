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

// Values are taken from the live /products/ page (measured 2026-11-13), never
// invented: background #0B0B0B, cream #F7F4EE, white Trade Gothic 300 headings
// (42 px desktop, 32 px mobile), gold uppercase Helvetica labels (10 px,
// 4-5 px tracking), Helvetica 14 px / 1.9 body text, 40 px mobile gutter.
function lunaci_product_page_css() {
	$b    = 'body.single-product';
	$sans = "'Helvetica LUNACI',Helvetica,Arial,sans-serif";
	$head = "'Trade Gothic LT Std Extended',sans-serif";

	// T4: page frame, breadcrumb, title, price, short description, meta.
	// The store WPCode snippet (inline, earlier in <head>) paints with an older
	// palette through variables: --lk #0D0D0D, --lc #F5F0E8, --lg #C4A35A. Pointing
	// them at the brand values on product pages fixes every rule that uses them,
	// without adding !important.
	// The !important flags below answer that snippet's own !important rules on the
	// breadcrumb and title; the selectors are more specific than its, so they win
	// regardless of order.
	return "$b{--lk:#0B0B0B;--lc:#F7F4EE;--lg:#D4AF37}"
		// Same content width as /products/ (1192 px between 124 px margins at 1440).
		. "$b main{max-width:1272px;margin-left:auto;margin-right:auto;padding-left:40px;padding-right:40px;box-sizing:border-box}"
		// Breadcrumb as a /products/ section label: small uppercase, wide tracking, gold links.
		. "$b nav.woocommerce-breadcrumb{font-family:$sans!important;font-size:10px!important;line-height:1.7;letter-spacing:4px!important;text-transform:uppercase;color:rgba(247,244,238,.62)!important;margin:0 0 32px}"
		. "html $b nav.woocommerce-breadcrumb a{color:#D4AF37!important;text-decoration:none!important}"
		. "html $b nav.woocommerce-breadcrumb a:hover,html $b nav.woocommerce-breadcrumb a:focus-visible{color:#F7F4EE!important}"
		// Title as a /products/ heading (cat-name scale), white like every /products/ heading.
		. "$b div.product h1.product_title{font-family:$head!important;font-size:42px;font-weight:300!important;line-height:1.15;letter-spacing:normal!important;color:#FFFFFF!important;margin:0 0 20px}"
		// Price: gold stays (lunaci-perf item 7); /products/ body sans with hero-sub tracking.
		. "$b div.product .summary p.price{font-family:$sans;font-size:16px;letter-spacing:1px;margin:0 0 24px}"
		// Short description as /products/ hero-sub.
		. "$b .woocommerce-product-details__short-description,$b .woocommerce-product-details__short-description p{font-family:$sans;font-size:14px;line-height:1.9;letter-spacing:1px;color:#F7F4EE;max-width:520px}"
		. "$b .woocommerce-product-details__short-description{margin:0 0 32px}"
		// SKU / category as /products/ cat-count.
		. "$b div.product .product_meta{font-family:$sans;font-size:12px;line-height:1.7;letter-spacing:1px;color:rgba(247,244,238,.62)}"
		. "@media (max-width:768px){"
		. "$b main{padding-left:40px;padding-right:40px}"
		. "$b div.product h1.product_title{font-size:32px}"
		// Existing bug: up to 768 px the fixed header covers the top 9 px of the breadcrumb.
		. "$b nav.woocommerce-breadcrumb{padding-top:16px}"
		. '}';
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
