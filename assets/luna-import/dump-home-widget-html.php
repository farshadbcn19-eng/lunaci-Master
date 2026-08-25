<?php
/**
 * Read-only diagnostic: dump the full raw HTML+CSS of the Home page's main
 * HTML widget (post 57, container[id=bf4109a]/widget[id=9b0a463]) to
 * investigate two mobile issues reported via screenshot audit:
 *  1) badge-row text ("Premium Ingredients", "Designed as a Company" etc.)
 *     visibly clipped at the right edge on a 390px viewport, in the very
 *     first screen (no scrolling needed) - likely a real overflow bug
 *  2) large blank vertical gaps between sections in a full-page screenshot -
 *     possibly the same opacity:0 scroll-reveal artifact confirmed on the
 *     About Us page (PR #202/#203), rather than a real bug
 */

global $wpdb;

function lunaci_dump_home_get_widget_html_by_id( $node, $target_id ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['id'], $node['widgetType'] ) && $node['id'] === $target_id && 'html' === $node['widgetType'] ) {
		return isset( $node['settings']['html'] ) ? $node['settings']['html'] : null;
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci_dump_home_get_widget_html_by_id( $value, $target_id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", 57 )
);
if ( null === $raw ) {
	echo "ERROR: no _elementor_data for post 57\n";
	exit( 1 );
}
$decoded = json_decode( $raw, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ERROR: json_decode failed: " . json_last_error_msg() . "\n";
	exit( 1 );
}

$html = lunaci_dump_home_get_widget_html_by_id( $decoded, '9b0a463' );
if ( null === $html ) {
	echo "ERROR: widget 9b0a463 not found\n";
	exit( 1 );
}

echo "TOTAL LENGTH: " . strlen( $html ) . "\n";
echo "=====================================================================\n";
echo "FULL HTML+CSS DUMP FOLLOWS\n";
echo "=====================================================================\n";
echo $html;
echo "\n=====================================================================\n";
echo "END OF DUMP\n";
echo "=====================================================================\n";
