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
 * Mode (option lunaci_perf_mode): "off", "test" (active only on URLs with
 * ?lunaci_perf=1) or "on" (active for everyone).
 */

defined( 'ABSPATH' ) || exit;

const LUNACI_PERF_WEBP_QUALITY = 92;

function lunaci_perf_active() {
	static $active = null;
	if ( null !== $active ) {
		return $active;
	}
	$mode = get_option( 'lunaci_perf_mode', 'off' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$test   = isset( $_GET['lunaci_perf'] ) && '1' === $_GET['lunaci_perf'];
	$active = ( 'on' === $mode || ( 'test' === $mode && $test ) )
		&& ! is_admin()
		&& ! wp_doing_ajax()
		&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		&& ! isset( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return $active;
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
