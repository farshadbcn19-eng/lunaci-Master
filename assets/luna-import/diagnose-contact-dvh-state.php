<?php
/**
 * Read-only: check the exact current state of the contact-hero CSS in
 * _elementor_data for posts 60 and 770, and confirm what the RENDERED
 * (decoded) HTML actually looks like via Elementor's own frontend
 * rendering path, not just a raw string check.
 */

foreach ( array( 'EN Contact' => 60, 'ES Contacto' => 770 ) as $label => $post_id ) {
	echo "=====================================================================\n";
	echo "{$label} (post {$post_id})\n";
	echo "=====================================================================\n";

	$raw = get_post_meta( $post_id, '_elementor_data', true );
	echo "raw meta_value length: " . strlen( $raw ) . "\n";

	$pos = strpos( $raw, 'min-height: 100vh' );
	if ( false !== $pos ) {
		echo "--- 80 raw bytes from 'min-height: 100vh' (verbatim, escapes visible) ---\n";
		echo addcslashes( substr( $raw, $pos, 80 ), "\0..\37" ) . "\n";
	}

	$decoded = json_decode( $raw, true );
	$json_ok = ( null !== $decoded && JSON_ERROR_NONE === json_last_error() );
	echo "\njson_decode success: " . ( $json_ok ? 'YES' : 'NO (' . json_last_error_msg() . ')' ) . "\n";

	if ( $json_ok ) {
		// Find the widget html containing contact-hero and print the exact decoded text around min-height.
		$found = null;
		$stack = array( $decoded );
		while ( $stack ) {
			$node = array_pop( $stack );
			if ( is_array( $node ) ) {
				if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
					if ( false !== strpos( $node['settings']['html'], 'contact-hero' ) ) {
						$found = $node['settings']['html'];
						break;
					}
				}
				foreach ( $node as $child ) {
					if ( is_array( $child ) ) {
						$stack[] = $child;
					}
				}
			}
		}
		if ( $found ) {
			$p = strpos( $found, 'min-height: 100vh' );
			echo "\n--- 80 DECODED bytes from 'min-height: 100vh' (this is what Elementor actually has in memory) ---\n";
			echo addcslashes( substr( $found, $p, 80 ), "\0..\37" ) . "\n";
		} else {
			echo "\ncould not find widget containing 'contact-hero'\n";
		}
	}
	echo "\n";
}

echo "OK: read-only diagnostic complete\n";
