<?php
/**
 * READ-ONLY. The homepage (post 57, EN) is built as raw HTML pasted into
 * Elementor "html" widgets (confirmed by the earlier H1/links fixes), not
 * native Elementor Image widgets - so any <img> tags missing alt/width/
 * height live as literal markup inside _elementor_data, not as widget
 * settings. Dump every <img> tag on the homepage exactly as stored, so
 * the guarded fix can target exact byte-for-byte matches instead of
 * guessing at attribute order/whitespace.
 */

global $wpdb;

function lunaci_collect_html_widgets( array $elements, array &$out ) {
	foreach ( $elements as $el ) {
		if ( isset( $el['widgetType'], $el['settings']['html'] ) && $el['widgetType'] === 'html' ) {
			$out[] = $el['settings']['html'];
		}
		if ( ! empty( $el['elements'] ) ) {
			lunaci_collect_html_widgets( $el['elements'], $out );
		}
	}
}

foreach ( array( 57 => 'EN homepage', 772 => 'ES homepage' ) as $post_id => $label ) {
	echo "\n=== post {$post_id} ({$label}) ===\n";
	$raw = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, '_elementor_data'
	) );
	$decoded = json_decode( (string) $raw, true );
	if ( ! is_array( $decoded ) ) {
		echo "  _elementor_data did not decode as JSON\n";
		continue;
	}

	$html_blocks = array();
	lunaci_collect_html_widgets( $decoded, $html_blocks );
	$full_html = implode( "\n", $html_blocks );

	preg_match_all( '/<img\b[^>]*>/i', $full_html, $matches );
	echo '  total <img> tags found: ' . count( $matches[0] ) . "\n";
	foreach ( $matches[0] as $i => $img_tag ) {
		$has_alt    = preg_match( '/\balt\s*=/i', $img_tag ) === 1;
		$has_width  = preg_match( '/\bwidth\s*=/i', $img_tag ) === 1;
		$has_height = preg_match( '/\bheight\s*=/i', $img_tag ) === 1;
		echo "\n  --- img #{$i} (alt=" . ( $has_alt ? 'yes' : 'NO' ) . ' width=' . ( $has_width ? 'yes' : 'NO' ) . ' height=' . ( $has_height ? 'yes' : 'NO' ) . ") ---\n";
		echo '  ' . json_encode( $img_tag, JSON_UNESCAPED_SLASHES ) . "\n";
	}
}

echo "\n=== LiteSpeed Cache image optimization settings ===\n";
if ( class_exists( '\LiteSpeed\Conf' ) ) {
	// LiteSpeed Cache stores its config in a single serialized wp_options
	// row (litespeed.conf.* keys were the old format; 5.x+ uses a single
	// 'litespeed-cache-conf' option holding all settings as an array).
	$conf = get_option( 'litespeed-cache-conf' );
	if ( is_array( $conf ) ) {
		foreach ( $conf as $key => $value ) {
			if ( stripos( $key, 'webp' ) !== false || stripos( $key, 'avif' ) !== false || stripos( $key, 'img_optm' ) !== false ) {
				echo '  ' . $key . ' = ' . var_export( $value, true ) . "\n";
			}
		}
	} else {
		echo "  litespeed-cache-conf option not found or not an array\n";
	}
} else {
	echo "  LiteSpeed Cache plugin class not found - not active?\n";
}

echo "\nOK: read-only diagnostic complete\n";
