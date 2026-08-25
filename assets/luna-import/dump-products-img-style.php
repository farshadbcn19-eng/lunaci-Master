<?php
/**
 * Read-only diagnostic: dump the exact raw CSS text surrounding
 * ".prod-img" and ".prod-img-wrap" from post 61's widget style block,
 * using plain substring search (not regex) to avoid the earlier
 * diagnostic's regex-matching pitfalls. Confirms the exact current
 * declaration for .prod-img (particularly whether it sets an explicit
 * height) before designing a fix.
 */

global $wpdb;

function lunaci_style_find_html_widgets( $node, &$results ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['widgetType'], $node['settings']['html'] ) && 'html' === $node['widgetType'] ) {
		$results[] = array( 'id' => $node['id'], 'html' => $node['settings']['html'] );
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			lunaci_style_find_html_widgets( $value, $results );
		}
	}
}

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", 61 )
);
$decoded = json_decode( $raw, true );

$widgets = array();
lunaci_style_find_html_widgets( $decoded, $widgets );

foreach ( $widgets as $w ) {
	if ( false === strpos( $w['html'], '.prod-img' ) ) {
		continue;
	}
	echo "Widget id={$w['id']} html_len=" . strlen( $w['html'] ) . "\n";

	if ( preg_match( '/<style>(.*?)<\/style>/is', $w['html'], $style_match ) ) {
		$css = $style_match[1];
		echo "CSS total length: " . strlen( $css ) . "\n\n";

		$offset = 0;
		while ( false !== ( $pos = strpos( $css, '.prod-img', $offset ) ) ) {
			echo "--- occurrence at offset {$pos} ---\n";
			echo substr( $css, $pos, 250 ) . "\n\n";
			$offset = $pos + 9;
		}
	} else {
		echo "No <style> block found in this widget\n";
	}
}

echo "OK: read-only diagnostic complete, no writes performed\n";
