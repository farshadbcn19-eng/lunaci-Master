<?php
/**
 * Read-only diagnostic: the user reported the "Lunaci Blusher" product
 * image on the Products page (post 61) displays broken/cropped oddly on
 * mobile, with a screenshot showing what looks like a tiny, badly-framed
 * fragment of the compact instead of the full product shot. Find every
 * occurrence of "blusher" in post 61's _elementor_data, identify the
 * containing HTML widget, and dump the surrounding markup + relevant CSS
 * rules so the actual cause (wrong/broken image file vs. a CSS cropping
 * bug like the earlier hero-image issues) can be diagnosed precisely.
 */

global $wpdb;

function lunaci_blusher_find_widgets_with_fragment( $node, $fragment, $path, &$results ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	$current_id     = isset( $node['id'] ) ? $node['id'] : null;
	$current_eltype = isset( $node['elType'] ) ? $node['elType'] : null;
	if ( null !== $current_id && null !== $current_eltype ) {
		$path = $path . '/' . $current_eltype . '[id=' . $current_id . ']';
	}
	if ( isset( $node['widgetType'], $node['settings']['html'] ) && 'html' === $node['widgetType'] && false !== stripos( $node['settings']['html'], $fragment ) ) {
		$results[] = array( 'path' => $path, 'id' => $current_id, 'html' => $node['settings']['html'] );
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			lunaci_blusher_find_widgets_with_fragment( $value, $fragment, $path, $results );
		}
	}
}

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", 61 )
);
if ( null === $raw ) {
	echo "ERROR: no _elementor_data for post 61\n";
	exit( 1 );
}
$decoded = json_decode( $raw, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ERROR: json_decode failed: " . json_last_error_msg() . "\n";
	exit( 1 );
}

$results = array();
lunaci_blusher_find_widgets_with_fragment( $decoded, 'blusher', '', $results );

echo "Found " . count( $results ) . " HTML widget(s) containing 'blusher' (case-insensitive)\n";

foreach ( $results as $r ) {
	echo "\n=====================================================================\n";
	echo "Widget path={$r['path']} id={$r['id']} html_len=" . strlen( $r['html'] ) . "\n";
	echo "=====================================================================\n";

	// Dump every <img> tag and its surrounding ~150 chars for context
	if ( preg_match_all( '/.{80}<img[^>]*>.{80}/is', $r['html'], $matches ) ) {
		foreach ( $matches[0] as $i => $m ) {
			echo "\n--- img context #{$i} ---\n" . $m . "\n";
		}
	}

	// Dump any CSS rule mentioning "blusher"
	if ( preg_match( '/<style>(.*?)<\/style>/is', $r['html'], $style_match ) ) {
		$css = $style_match[1];
		if ( preg_match_all( '/[^\n{]*blusher[^\n{]*\{[^}]*\}/i', $css, $css_matches ) ) {
			echo "\n--- CSS rules mentioning 'blusher' ---\n";
			foreach ( $css_matches[0] as $rule ) {
				echo $rule . "\n";
			}
		}
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
