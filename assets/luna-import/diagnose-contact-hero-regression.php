<?php
/**
 * URGENT read-only diagnostic: after fix-contact-hero-height.php ran
 * (reported SUCCESS), the live Contact page now shows raw CSS text
 * printed on the page instead of it being applied as styling - meaning
 * the <style> tag structure broke during the JSON round-trip write.
 * Dump the exact bytes around the .contact-hero rule and the <style>
 * tag boundaries to find what went wrong. No writes.
 */

global $wpdb;

foreach ( array( 60 => 'EN Contact', 770 => 'ES Contacto' ) as $page_id => $label ) {
	echo "=====================================================================\n";
	echo "PAGE {$page_id} ({$label})\n";
	echo "=====================================================================\n";

	$raw = get_post_meta( $page_id, '_elementor_data', true );
	if ( ! $raw ) {
		echo "ERROR: no _elementor_data\n\n";
		continue;
	}
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ERROR: json_decode failed: " . json_last_error_msg() . "\n\n";
		continue;
	}

	$widget_html = null;
	$finder = function ( $node ) use ( &$finder, &$widget_html ) {
		if ( $widget_html ) return;
		if ( is_array( $node ) ) {
			if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
				if ( false !== strpos( $node['settings']['html'], '.contact-hero' ) ) {
					$widget_html = $node['settings']['html'];
					return;
				}
			}
			foreach ( $node as $child ) {
				$finder( $child );
				if ( $widget_html ) return;
			}
		}
	};
	$finder( $decoded );

	if ( ! $widget_html ) {
		echo "ERROR: widget not found\n\n";
		continue;
	}

	echo "widget html length: " . strlen( $widget_html ) . "\n";

	// count <style> and </style> tags
	echo "count of '<style': " . substr_count( $widget_html, '<style' ) . "\n";
	echo "count of '</style>': " . substr_count( $widget_html, '</style>' ) . "\n";

	$pos = strpos( $widget_html, 'min-height: 100vh;' );
	echo "'min-height: 100vh;' found at offset: " . ( false === $pos ? 'NOT FOUND' : $pos ) . "\n";
	if ( false !== $pos ) {
		$start = max( 0, $pos - 300 );
		echo "\n--- context around min-height: 100vh; (300 before, 300 after) ---\n";
		echo substr( $widget_html, $start, 700 ) . "\n";
		echo "--- end context ---\n";
	}

	// dump the raw bytes right after the FIRST <style tag opens, to check the style tag itself is well-formed
	$style_open_pos = strpos( $widget_html, '<style' );
	if ( false !== $style_open_pos ) {
		echo "\n--- first 200 bytes from <style tag ---\n";
		echo substr( $widget_html, $style_open_pos, 200 ) . "\n";
		echo "--- end ---\n";
	}

	echo "\n";
}

echo "OK: read-only regression diagnostic complete, no writes performed\n";
