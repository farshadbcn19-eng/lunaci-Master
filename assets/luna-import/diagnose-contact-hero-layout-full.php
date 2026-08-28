<?php
/**
 * Read-only: user reports the Contact hero banner looks "too faded" (like a
 * dim background wash) and the container box looks "shrunk again" with black
 * bars on either side (same full-bleed issue previously fixed on About Us).
 * Dump the full widget HTML surrounding .contact-hero (structural wrapper
 * classes, any width/max-width rules, and the full CSS block) so the fix can
 * target the right selectors.
 */

$page_id = 60; // EN Contact - structure is the same on ES (770), just inspect one

$raw = get_post_meta( $page_id, '_elementor_data', true );
if ( ! $raw ) {
	echo "no _elementor_data for page {$page_id}\n";
	exit;
}
$decoded = json_decode( $raw, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "could not decode JSON\n";
	exit;
}

$widget_html = null;
$finder = function ( $node ) use ( &$finder, &$widget_html ) {
	if ( $widget_html ) {
		return;
	}
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], '.contact-hero' ) ) {
				$widget_html = $node['settings']['html'];
				return;
			}
		}
		foreach ( $node as $child ) {
			$finder( $child );
			if ( $widget_html ) {
				return;
			}
		}
	}
};
$finder( $decoded );

if ( ! $widget_html ) {
	echo "no widget found containing '.contact-hero'\n";
	exit;
}

echo "widget html total length: " . strlen( $widget_html ) . "\n\n";

// dump the <style> block in full
if ( preg_match( '/<style[^>]*>(.*?)<\/style>/s', $widget_html, $m ) ) {
	echo "===================== FULL <style> BLOCK =====================\n";
	echo $m[1] . "\n";
	echo "===================== END STYLE BLOCK =====================\n\n";
} else {
	echo "no <style> block found\n\n";
}

// find where .contact-hero div opens in the markup, and dump surrounding structure (500 chars before, 800 after)
$pos = strpos( $widget_html, 'contact-hero' );
if ( false !== $pos ) {
	$start = max( 0, $pos - 600 );
	echo "===================== MARKUP AROUND FIRST 'contact-hero' occurrence =====================\n";
	echo substr( $widget_html, $start, 1400 ) . "\n";
	echo "===================== END MARKUP EXCERPT =====================\n";
} else {
	echo "'contact-hero' not found in markup at all (odd, since it was found in style)\n";
}

echo "\nOK: read-only diagnostic complete\n";
