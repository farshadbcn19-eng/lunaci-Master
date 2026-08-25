<?php
/**
 * Read-only diagnostic: determine the exact storage format of the
 * `wpcode_snippets` wp_options value (PHP-serialized array, JSON, or
 * something else), so a guarded fix can safely decode -> patch -> re-encode
 * it (the same safe pattern already used for _elementor_data), rather than
 * doing a raw string replace that could corrupt PHP serialization length
 * prefixes.
 */

global $wpdb;

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'wpcode_snippets' )
);
if ( null === $raw ) {
	echo "ERROR: wpcode_snippets option not found\n";
	exit( 1 );
}

echo "raw length: " . strlen( $raw ) . "\n";
echo "first 200 chars: " . substr( $raw, 0, 200 ) . "\n\n";

echo "is_serialized(): " . ( is_serialized( $raw ) ? 'YES' : 'no' ) . "\n";

$unserialized = @unserialize( $raw );
echo "unserialize() success: " . ( false !== $unserialized || $raw === serialize( false ) ? 'YES' : 'no' ) . "\n";
if ( false !== $unserialized ) {
	echo "unserialized type: " . gettype( $unserialized ) . "\n";
	if ( is_array( $unserialized ) ) {
		echo "top-level array count: " . count( $unserialized ) . "\n";
		echo "top-level keys (first 10): " . implode( ', ', array_slice( array_keys( $unserialized ), 0, 10 ) ) . "\n";

		// find which entry contains .prod-img
		foreach ( $unserialized as $key => $entry ) {
			$entry_str = is_string( $entry ) ? $entry : wp_json_encode( $entry );
			if ( false !== strpos( $entry_str, '.prod-img' ) ) {
				echo "\nFOUND '.prod-img' inside top-level key: " . var_export( $key, true ) . "\n";
				echo "entry type: " . gettype( $entry ) . "\n";
				if ( is_array( $entry ) ) {
					echo "entry sub-keys: " . implode( ', ', array_keys( $entry ) ) . "\n";
					foreach ( $entry as $subkey => $subval ) {
						if ( is_string( $subval ) && false !== strpos( $subval, '.prod-img' ) ) {
							echo "  -> found inside sub-key '{$subkey}' (string, length " . strlen( $subval ) . ")\n";
							$pos = strpos( $subval, '.prod-img {' );
							echo "  fragment: " . substr( $subval, $pos, 200 ) . "\n";
						}
					}
				}
			}
		}
	}
}

$json_decoded = json_decode( $raw, true );
echo "\njson_decode() success: " . ( JSON_ERROR_NONE === json_last_error() ? 'YES' : 'no (' . json_last_error_msg() . ')' ) . "\n";

echo "\nOK: read-only diagnostic complete, no writes performed\n";
