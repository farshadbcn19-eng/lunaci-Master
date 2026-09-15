<?php
/**
 * Read-only: inspect the structure of the 'wpcode_snippets' option (found
 * via diagnose-wpcode-caching.php), which is almost certainly WPCode's own
 * front-end performance cache/mirror of all active snippets, separate from
 * wp_posts. This is the missing piece explaining why our raw
 * $wpdb->update() writes to wp_posts (483 etc.) are correct in the database
 * but never appear on the live front-end.
 *
 * Purely read-only. Does NOT modify the option.
 */

$data = get_option( 'wpcode_snippets' );

echo "type: " . gettype( $data ) . "\n";
if ( is_array( $data ) ) {
	echo "top-level array count: " . count( $data ) . "\n";
	echo "top-level keys: " . implode( ', ', array_slice( array_keys( $data ), 0, 20 ) ) . "\n\n";

	// Try to find the entry for post 483 by looking at structure.
	foreach ( $data as $key => $value ) {
		if ( (string) $key === '483' || ( is_array( $value ) && isset( $value['id'] ) && 483 == $value['id'] ) ) {
			echo "--- Found entry keyed/id-matching 483 ---\n";
			echo "key: $key\n";
			echo "value type: " . gettype( $value ) . "\n";
			if ( is_array( $value ) ) {
				foreach ( $value as $k => $v ) {
					$preview = is_string( $v ) ? substr( $v, 0, 200 ) : json_encode( $v );
					echo "  [$k] (" . gettype( $v ) . ", len=" . ( is_string( $v ) ? strlen( $v ) : '-' ) . "): " . $preview . "\n";
				}
			}
			echo "\n";
		}
	}

	// Also dump first entry structure generically, to understand shape if 483 lookup above found nothing.
	echo "--- First array entry (structure sample) ---\n";
	$first_key = array_key_first( $data );
	echo "first_key: $first_key\n";
	$first_val = $data[ $first_key ];
	echo "first_val type: " . gettype( $first_val ) . "\n";
	if ( is_array( $first_val ) ) {
		foreach ( $first_val as $k => $v ) {
			$preview = is_string( $v ) ? substr( $v, 0, 150 ) : json_encode( $v );
			echo "  [$k]: $preview\n";
		}
	}
} else {
	echo "raw value (first 2000 chars): " . substr( (string) $data, 0, 2000 ) . "\n";
}

echo "\n--- Does the option contain our new rule strings at all? ---\n";
$serialized = maybe_serialize( $data );
echo "count 'page-id-771': " . substr_count( $serialized, 'page-id-771' ) . "\n";
echo "count 'page-id-60 header': " . substr_count( $serialized, 'page-id-60 header' ) . "\n";
echo "count 'page-id-61': " . substr_count( $serialized, 'page-id-61' ) . "\n";

echo "\nOK: read-only diagnostic complete, no writes performed\n";
