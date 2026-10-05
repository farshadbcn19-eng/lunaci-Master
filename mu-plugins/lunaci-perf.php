<?php
/**
 * Plugin Name: LUNACI Performance
 * Description: WebP images and removal of unused Google Fonts. No visual change.
 * Author: LUNACI
 *
 * From the Phase B performance audit (2026-10-05):
 *  1. Product photos are served as PNG/JPG (main product image ~640 KB, LCP
 *     12 s on mobile). A WebP copy is stored next to each upload
 *     (image.png -> image.png.webp, quality 92, mean colour error dE < 1) and
 *     front-end HTML points to it when it exists. Originals are never changed.
 *  2. Elementor loads Roboto, Roboto Slab and Open Sans from Google Fonts on
 *     every page, but no text uses them (checked on 7 pages, screenshots
 *     pixel-identical without them). Montserrat, Raleway and Cormorant
 *     Garamond are in use and stay.
 *
 *  3. Product pages load Stripe.js (WooPayments, 250 KB), WooPayments
 *     product-details/tracks and the PayPal SDK, but render no visible
 *     button or message there (containers are 0 px high). The main thread
 *     work delays the product image paint (LCP render delay 4.2 s). These
 *     scripts are removed on single product pages only; cart and checkout
 *     keep every gateway.
 *
 *  4. Brand typography (Trade Gothic LT Std Extended + Helvetica). Montserrat
 *     (menu, footer, About body), Raleway (product pages) and Cormorant
 *     Garamond (About headings) came from Google Fonts. Their stylesheets are
 *     removed and the same family names are aliased to the self-hosted brand
 *     files, so no page HTML or copy changes: Montserrat/Raleway -> Helvetica
 *     LUNACI, Cormorant Garamond -> Trade Gothic (size-adjust 72% keeps the
 *     line width; Trade Gothic Extended is ~39% wider). Product page section
 *     headings use Trade Gothic. The aliases declare weight 400 only, so the
 *     browser still synthesizes bold for 600-900 text.
 *
 * Modes: "off", "test" (active only on URLs with ?lunaci_perf=1) or "on".
 * Items 1-2 use option lunaci_perf_mode, item 3 lunaci_perf_payments_mode,
 * item 4 lunaci_brand_fonts_mode.
 */

defined( 'ABSPATH' ) || exit;

const LUNACI_PERF_WEBP_QUALITY = 92;

function lunaci_perf_mode_active( $option ) {
	static $active = array();
	if ( isset( $active[ $option ] ) ) {
		return $active[ $option ];
	}
	$mode = get_option( $option, 'off' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$test               = isset( $_GET['lunaci_perf'] ) && '1' === $_GET['lunaci_perf'];
	$active[ $option ] = ( 'on' === $mode || ( 'test' === $mode && $test ) )
		&& ! is_admin()
		&& ! wp_doing_ajax()
		&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		&& ! isset( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return $active[ $option ];
}

function lunaci_perf_active() {
	return lunaci_perf_mode_active( 'lunaci_perf_mode' );
}

/**
 * Write file.ext.webp next to an image file. Returns true when the WebP exists.
 */
function lunaci_perf_make_webp( $file ) {
	if ( ! preg_match( '/\.(png|jpe?g)$/i', $file ) || ! is_readable( $file ) ) {
		return false;
	}
	$webp = $file . '.webp';
	if ( file_exists( $webp ) && filemtime( $webp ) >= filemtime( $file ) ) {
		return true;
	}
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) || ! $editor->supports_mime_type( 'image/webp' ) ) {
		return false;
	}
	$editor->set_quality( LUNACI_PERF_WEBP_QUALITY );
	$saved = $editor->save( $webp, 'image/webp' );
	if ( is_wp_error( $saved ) ) {
		return false;
	}
	// Keep the WebP only when it is actually smaller.
	if ( filesize( $webp ) >= filesize( $file ) ) {
		wp_delete_file( $webp );
		return false;
	}
	return true;
}

// New uploads get their WebP copies (original and every generated size).
add_filter(
	'wp_generate_attachment_metadata',
	function ( $metadata, $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( $file ) {
			lunaci_perf_make_webp( $file );
			$dir = trailingslashit( dirname( $file ) );
			foreach ( (array) ( $metadata['sizes'] ?? array() ) as $size ) {
				if ( ! empty( $size['file'] ) ) {
					lunaci_perf_make_webp( $dir . $size['file'] );
				}
			}
		}
		return $metadata;
	},
	20,
	2
);

// 1. Point front-end image URLs to their WebP copies.
add_action(
	'template_redirect',
	function () {
		if ( ! lunaci_perf_active() ) {
			return;
		}
		ob_start( 'lunaci_perf_rewrite_html' );
	},
	1
);

function lunaci_perf_rewrite_html( $html ) {
	$pos = stripos( $html, '<body' );
	if ( false === $pos ) {
		return $html;
	}
	$uploads = wp_get_upload_dir();
	$base    = $uploads['baseurl'];
	$basedir = $uploads['basedir'];
	$body    = substr( $html, $pos );
	$cache   = array();

	// Plain and JSON-escaped (\/) upload URLs ending in png/jpg/jpeg.
	foreach ( array( $base, str_replace( '/', '\/', $base ) ) as $prefix ) {
		$pattern = '#' . preg_quote( $prefix, '#' ) . '((?:\\\\?/[^"\'\s<>()?\\\\]+)+?\.(?:png|jpe?g))(?=[?"\'\s<>),\\\\]|$)#i';
		$body    = preg_replace_callback(
			$pattern,
			function ( $m ) use ( $prefix, $basedir, &$cache ) {
				$rel = str_replace( '\/', '/', $m[1] );
				if ( false !== strpos( $rel, '..' ) ) {
					return $m[0];
				}
				if ( ! isset( $cache[ $rel ] ) ) {
					$cache[ $rel ] = file_exists( $basedir . $rel . '.webp' );
				}
				return $cache[ $rel ] ? $m[0] . '.webp' : $m[0];
			},
			$body
		);
	}
	return substr( $html, 0, $pos ) . $body;
}

// 2. Drop the unused Elementor Google Fonts.
add_filter(
	'style_loader_tag',
	function ( $tag, $handle ) {
		if ( lunaci_perf_active() && in_array( $handle, array( 'elementor-gf-roboto', 'elementor-gf-robotoslab', 'elementor-gf-opensans' ), true ) ) {
			return '';
		}
		return $tag;
	},
	10,
	2
);

// 3. Payment gateway scripts that render nothing on single product pages.
function lunaci_perf_drop_product_payment_scripts() {
	if ( ! function_exists( 'is_product' ) || ! is_product() || ! lunaci_perf_mode_active( 'lunaci_perf_payments_mode' ) ) {
		return;
	}
	foreach ( array( 'WCPAY_PRODUCT_DETAILS', 'wcpay-frontend-tracks', 'wc-ppcp-sdk-v6-boot', 'stripe' ) as $handle ) {
		wp_dequeue_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'lunaci_perf_drop_product_payment_scripts', 999 );
add_action( 'wp_print_footer_scripts', 'lunaci_perf_drop_product_payment_scripts', 1 );

// 4. Brand typography.
function lunaci_brand_fonts_active() {
	return lunaci_perf_mode_active( 'lunaci_brand_fonts_mode' );
}

add_filter(
	'style_loader_tag',
	function ( $tag, $handle ) {
		return ( 'elementor-gf-montserrat' === $handle && lunaci_brand_fonts_active() ) ? '' : $tag;
	},
	10,
	2
);

add_action(
	'wp_head',
	function () {
		if ( ! lunaci_brand_fonts_active() ) {
			return;
		}
		$base = get_stylesheet_directory_uri() . '/fonts/';
		$tg   = esc_url( $base . 'TradeGothicLTStd-Extended.woff2' );
		$hv   = esc_url( $base . 'Helvetica.woff2' );
		$css  = '';
		foreach ( array( 'Montserrat', 'Raleway' ) as $family ) {
			$css .= "@font-face{font-family:'$family';src:url('$hv') format('woff2');font-weight:400;font-style:normal;font-display:swap}";
		}
		$css .= "@font-face{font-family:'Cormorant Garamond';src:url('$tg') format('woff2');font-weight:400;font-style:normal;font-display:swap;size-adjust:72%}";
		$css .= "body.single-product div.product .woocommerce-tabs h2,body.single-product div.product .woocommerce-tabs .panel h2,body.single-product .woocommerce-Reviews-title,body.single-product .comment-reply-title,body.single-product .related>h2,body.single-product .upsells>h2{font-family:'Trade Gothic LT Std Extended',sans-serif!important;font-weight:400!important}";
		echo '<link rel="preload" href="' . $hv . '" as="font" type="font/woff2" crossorigin>' . "\n";
		echo '<style id="lunaci-brand-fonts">' . $css . '</style>' . "\n";
	},
	1
);

add_action(
	'template_redirect',
	function () {
		if ( lunaci_brand_fonts_active() ) {
			ob_start( 'lunaci_brand_fonts_strip_links' );
		}
	},
	2
);

function lunaci_brand_fonts_strip_links( $html ) {
	$html = preg_replace( '#<link\b[^>]*fonts\.googleapis\.com/css2?\?family=(?:Raleway|Cormorant)[^>]*>\s*#i', '', $html );
	if ( ! preg_match( '#<link\b[^>]*fonts\.googleapis\.com/css#i', $html ) ) {
		$html = preg_replace( '#<link\b[^>]*rel=["\']?(?:preconnect|dns-prefetch)["\']?[^>]*fonts\.(?:googleapis|gstatic)\.com[^>]*>\s*#i', '', $html );
	}
	return $html;
}
