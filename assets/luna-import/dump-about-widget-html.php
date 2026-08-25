<?php
/**
 * Read-only diagnostic: dump the full raw HTML+CSS of the About Us page's
 * main HTML widget (post 59, container[id=ff5f046]/widget[id=ce307e5]) so
 * the mobile rendering bug (page is almost entirely blank below the hero
 * image on a 390px viewport - "Our Story" text and the philosophy/values/
 * promise sections never appear) can be diagnosed directly from the actual
 * markup and CSS, rather than guessed at from screenshots alone.
 */

global $wpdb;

function lunaci_dump_get_widget_html_by_id( $node, $target_id ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['id'], $node['widgetType'] ) && $node['id'] === $target_id && 'html' === $node['widgetType'] ) {
		return isset( $node['settings']['html'] ) ? $node['settings']['html'] : null;
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci_dump_get_widget_html_by_id( $value, $target_id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", 59 )
);
if ( null === $raw ) {
	echo "ERROR: no _elementor_data for post 59\n";
	exit( 1 );
}
$decoded = json_decode( $raw, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ERROR: json_decode failed: " . json_last_error_msg() . "\n";
	exit( 1 );
}

$html = lunaci_dump_get_widget_html_by_id( $decoded, 'ce307e5' );
if ( null === $html ) {
	echo "ERROR: widget ce307e5 not found\n";
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
