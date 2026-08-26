<?php
/**
 * Read-only diagnostic: dump the full inline <style> block from the About
 * Us page's hero HTML widget (post 59, EN), so we can see every existing
 * .lna-hero* rule (including text/content padding) before crafting a
 * precise fix for:
 *  (a) desktop full-bleed width (currently capped at 1140px by Elementor's
 *      boxed container, per live diagnostic - a hard black seam shows at
 *      wide viewports)
 *  (b) moving the "Our Story" text block higher (currently bottom-anchored
 *      via align-items:flex-end with some existing bottom padding)
 */

global $wpdb;

$page_id = 59; // EN About Us

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw ) {
	echo "ERROR: _elementor_data not found for post {$page_id}\n";
	exit( 1 );
}

$decoded = json_decode( $raw, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ERROR: could not decode _elementor_data JSON\n";
	exit( 1 );
}

function lunaci_find_html_with_fragment( $node, $fragment, &$found ) {
	if ( $found ) return;
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $fragment ) ) {
				$found = $node['settings']['html'];
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_find_html_with_fragment( $child, $fragment, $found );
			if ( $found ) return;
		}
	}
}

$found = null;
lunaci_find_html_with_fragment( $decoded, '.lna-hero', $found );

if ( null === $found ) {
	echo "ERROR: no HTML widget found containing '.lna-hero'\n";
	exit( 1 );
}

echo "widget HTML length: " . strlen( $found ) . "\n\n";

// extract the <style>...</style> block
if ( preg_match( '/<style[^>]*>(.*?)<\/style>/s', $found, $m ) ) {
	echo "=== <style> block content ===\n";
	echo $m[1] . "\n";
} else {
	echo "no <style> block found in this widget HTML\n";
}

echo "\n=== first 200 chars of the HTML body (to see markup structure/ids) ===\n";
$body_start = strpos( $found, '</style>' );
if ( false !== $body_start ) {
	echo substr( $found, $body_start + 8, 800 ) . "\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
