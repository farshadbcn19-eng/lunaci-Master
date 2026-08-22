<?php
global $wpdb;
$post_id = 60;

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
					echo str_repeat( '  ', $depth + 1 ) . "  >>> first 300 chars: " . substr( $v, 0, 300 ) . "\n";
				}
			}
		}
		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) && count( $el['elements'] ) > 0 ) {
			walk_elements( $el['elements'], $depth + 1 );
		}
	}
}

walk_elements( $data );

echo "\ndone\n";
