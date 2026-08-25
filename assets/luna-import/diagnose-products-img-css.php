<?php
/**
 * Read-only diagnostic: user reports the new Blusher product image
 * doesn't fill its container/frame. Dump the CSS rules for
 * .prod-img-wrap and .prod-img (and any related classes) from post 61's
 * widget to determine the expected container dimensions/aspect-ratio and
 * how the image is sized within it (object-fit, width/height, padding,
 * etc), so we can tell whether this is a real image-dimension mismatch or
 * something else (e.g. object-fit:contain leaving letterbox space by
 * design, or a caching issue).
 */

global $wpdb;

function lunaci_css_find_html_widgets( $node, &$results ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['widgetType'], $node['settings']['html'] ) && 'html' === $node['widgetType'] ) {
		$results[] = array( 'id' => $node['id'], 'html' => $node['settings']['html'] );
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			lunaci_css_find_html_widgets( $value, $results );
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

$widgets = array();
lunaci_css_find_html_widgets( $decoded, $widgets );

foreach ( $widgets as $w ) {
	if ( false === stripos( $w['html'], 'prod-img' ) ) {
		continue;
	}
	echo "Widget id={$w['id']} html_len=" . strlen( $w['html'] ) . "\n";

	if ( preg_match( '/<style>(.*?)<\/style>/is', $w['html'], $style_match ) ) {
		$css = $style_match[1];
		// Dump every CSS rule whose selector mentions prod-img, prod-card, prod-badge, or Hero Product context
		if ( preg_match_all( '/[^\n{]*prod[a-zA-Z\-]*[^\n{]*\{[^}]*\}/i', $css, $css_matches ) ) {
			echo "\n--- CSS rules mentioning 'prod' ---\n";
			foreach ( $css_matches[0] as $rule ) {
				echo $rule . "\n";
			}
		}
	}

	// Also dump the immediate surrounding structural HTML around one prod-img occurrence, wider window
	$pos = stripos( $w['html'], 'blusher' );
	if ( false === $pos ) {
		$pos = stripos( $w['html'], 'prod-img' );
	}
	if ( false !== $pos ) {
		echo "\n--- Wider HTML context around 'blusher'/'prod-img' ---\n";
		echo substr( $w['html'], max( 0, $pos - 400 ), 700 ) . "\n";
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
