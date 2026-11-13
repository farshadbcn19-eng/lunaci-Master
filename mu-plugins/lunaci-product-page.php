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
		// Same side margins as /products/, measured at 390/768/1024/1440/1920 px: 40/70/90/124/162 = 8vw + 9px.
		// main.site-main outranks the theme's own width steps (500/600/800/1140 px) and its 10 px mobile gutter.
		. "$b main.site-main{width:100%;max-width:none;margin-left:0;margin-right:0;padding-inline:calc(8vw + 9px);box-sizing:border-box}"
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
		// T5: purchase controls. Square corners, Helvetica, /products/ CTA type
		// (11 px uppercase, 3 px tracking); the button's gold border and transparent
		// fill stay as lunaci-perf item 7 sets them.
		// Shade row: label above the dropdown, no grey WooCommerce boxes.
		// The whole table chain goes block: blocking only th/td left a table box 4-6 px wider than a 390 px screen.
		. "$b div.product table.variations,$b div.product table.variations tbody,$b div.product table.variations tr{display:block;width:100%}"
		. "$b div.product table.variations th,$b div.product table.variations td{display:block;padding:0;background:transparent;border:0}"
		. "$b div.product table.variations th.label{margin:0 0 12px;text-align:left}"
		. "$b div.product table.variations label{font-family:$sans;font-size:10px;font-weight:400;letter-spacing:4px;text-transform:uppercase;color:#D4AF37}"
		// Dropdown and quantity share one field style; 46 px matches the button (touch target >= 44 px).
		// Border at gold 60 %: ~3.9:1 on #0B0B0B (WCAG 1.4.11 needs 3:1); !important beats lunaci-perf item 7's 20 % border.
		. "$b div.product form.cart select,$b div.product form.cart input.qty{font-family:$sans;font-size:14px;letter-spacing:1px;color:#F7F4EE;background-color:transparent;border:1px solid rgba(212,175,55,.6)!important;border-radius:0;min-height:46px;box-sizing:border-box}"
		. "$b div.product form.cart select{width:100%;max-width:520px;padding:0 16px;margin:0}"
		// The store snippet paints the dropdown with background: var(--ld) !important.
		. "$b div.product table.variations select{background:transparent!important}"
		// Native option lists ignore the select's transparent background on some systems: set it so cream text stays readable.
		. "$b div.product form.cart select option{background-color:#0B0B0B;color:#F7F4EE}"
		. "$b div.product form.cart input.qty{width:64px;padding:0 8px;text-align:center}"
		// Quantity + button on one row (simple and variable products); floats would wrap them apart on mobile.
		. "$b div.product .woocommerce-variation-add-to-cart,$b div.product form.cart:not(.variations_form){display:flex;flex-wrap:nowrap;align-items:stretch;gap:12px}"
		. "$b div.product form.cart .quantity{float:none;margin:0}"
		// WooCommerce's clearfix pseudo-elements would become flex items and add two 12 px gaps.
		. "$b div.product form.cart:not(.variations_form)::before,$b div.product form.cart:not(.variations_form)::after{display:none}"
		// The store snippet's related-products grid (minmax(260px,1fr) !important) overflows a 320 px screen with these margins.
		. "$b .related ul.products,$b .upsells ul.products{grid-template-columns:repeat(auto-fill,minmax(min(260px,100%),1fr))!important}"
		// font-size and letter-spacing answer the store snippet's own !important on this button.
		. "$b div.product form.cart .single_add_to_cart_button{font-family:$sans;font-size:11px!important;letter-spacing:3px!important;text-transform:uppercase;padding:16px 36px;min-height:46px;border-radius:0}"
		// "Clear" link: was theme pink (#CC3366); now a quiet /products/ link.
		. "$b div.product .reset_variations{font-family:$sans;font-size:10px;letter-spacing:3px;text-transform:uppercase;color:rgba(247,244,238,.62);text-decoration:none;display:inline-block;margin-top:12px}"
		. "$b div.product .reset_variations:hover{color:#F7F4EE}"
		// Selected-variation price and stock: same as the main price and the meta line.
		. "$b div.product .woocommerce-variation-price .price{font-family:$sans;font-size:16px;letter-spacing:1px}"
		. "$b div.product .woocommerce-variation-availability p.stock,$b div.product .summary p.stock{font-family:$sans;font-size:12px;letter-spacing:1px;color:rgba(247,244,238,.62)}"
		// Visible keyboard focus on every purchase control.
		. "$b div.product form.cart select:focus-visible,$b div.product form.cart input.qty:focus-visible,$b div.product .reset_variations:focus-visible{outline:1px solid #F7F4EE;outline-offset:3px}"
		. "@media (max-width:768px){"
		. "$b div.product h1.product_title{font-size:32px}"
		// Existing bug: up to 768 px the fixed header covers the top 9 px of the breadcrumb.
		. "$b nav.woocommerce-breadcrumb{padding-top:16px}"
		// "AÑADIR AL CARRITO" needs the full row next to the quantity at 390 px.
		. '}'
		// Phones: the button takes the rest of the row; 2 px tracking fits "AÑADIR AL CARRITO" at 390 px, below that it wraps instead of overflowing.
		. '@media (max-width:575px){'
		. "$b div.product form.cart .single_add_to_cart_button{flex:1 1 0;min-width:0;padding-left:16px;padding-right:16px;letter-spacing:2px!important;white-space:normal;line-height:1.4}"
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
