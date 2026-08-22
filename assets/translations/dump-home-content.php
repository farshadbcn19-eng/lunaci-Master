<?php
global $wpdb;
$post_id = 57;

$raw = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = '_elementor_data'",
	$post_id
) );

if ( $raw === null ) {
	echo "ABORT: _elementor_data not found for post_id=$post_id\n";
	exit(1);
}

echo "raw length=" . strlen( $raw ) . "\n";

$data = json_decode( $raw, true );
if ( $data === null ) {
	echo "ABORT: json_decode failed: " . json_last_error_msg() . "\n";
	exit(1);
}

echo "top-level element count=" . count( $data ) . "\n";

function walk_elements( $elements, $depth = 0 ) {
	foreach ( $elements as $i => $el ) {
		$id = isset( $el['id'] ) ? $el['id'] : '?';
		$elType = isset( $el['elType'] ) ? $el['elType'] : '?';
		$widgetType = isset( $el['widgetType'] ) ? $el['widgetType'] : '';
		$settingsKeys = isset( $el['settings'] ) ? implode( ',', array_keys( $el['settings'] ) ) : '';
		echo str_repeat( '  ', $depth ) . "[$i] id=$id elType=$elType widgetType=$widgetType settingsKeys=$settingsKeys\n";
		if ( $widgetType === 'html' && isset( $el['settings'] ) ) {
			foreach ( $el['settings'] as $k => $v ) {
				if ( is_string( $v ) && strlen( $v ) > 200 ) {
					echo str_repeat( '  ', $depth + 1 ) . "  >>> LONG STRING SETTING '$k' length=" . strlen( $v ) . "\n";
				}
			}
		}
		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) && count( $el['elements'] ) > 0 ) {
			walk_elements( $el['elements'], $depth + 1 );
		}
	}
}
walk_elements( $data );

// dump the html widget content as base64, chunked into lines for safe log transport
if ( isset( $data[0]['elements'][0]['settings']['html'] ) ) {
	$html = $data[0]['elements'][0]['settings']['html'];
	echo "\n===BASE64_START===\n";
	echo chunk_split( base64_encode( $html ), 120, "\n" );
	echo "===BASE64_END===\n";
} else {
	echo "\nNo html widget found at expected path\n";
}

echo "\ndone\n";
