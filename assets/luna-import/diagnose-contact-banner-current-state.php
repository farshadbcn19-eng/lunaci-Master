<?php
/**
 * Read-only: after fix-contact-hero-banner-image.php's first real write
 * attempt reported "old url gone: yes" but "new banner url present: no"
 * for both pages, dump the current .contact-hero background rule on both
 * pages to see the actual current state before writing any further fix.
 */

$pages = array(
	60  => 'EN Contact',
	770 => 'ES Contacto',
);

foreach ( $pages as $page_id => $label ) {
	echo "=== page {$page_id} ({$label}) ===\n";
	$raw = get_post_meta( $page_id, '_elementor_data', true );
	if ( ! $raw ) {
		echo "no _elementor_data\n\n";
		continue;
	}
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "could not decode JSON\n\n";
		continue;
	}

	$widget_html = null;
	$finder = function ( $node ) use ( &$finder, &$widget_html ) {
		if ( $widget_html ) {
			return;
		}
		if ( is_array( $node ) ) {
			if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
				if ( false !== strpos( $node['settings']['html'], '.contact-hero' ) ) {
					$widget_html = $node['settings']['html'];
					return;
				}
			}
			foreach ( $node as $child ) {
				$finder( $child );
				if ( $widget_html ) {
					return;
				}
			}
		}
	};
	$finder( $decoded );

	if ( ! $widget_html ) {
		echo "no widget found containing '.contact-hero'\n\n";
		continue;
	}

	if ( preg_match( '/\.contact-hero\s*\{[^}]*\}/s', $widget_html, $m ) ) {
		echo "--- .contact-hero rule (raw, exact bytes) ---\n";
		echo $m[0] . "\n";
		echo "--- hex-ish visible check for backslashes ---\n";
		echo "contains backslash: " . ( false !== strpos( $m[0], '\\' ) ? 'yes' : 'no' ) . "\n";
	} else {
		echo "could not extract .contact-hero rule via regex\n";
	}

	echo "occurrences of 'lunaimport-contact-hero-banner': " . substr_count( $widget_html, 'lunaimport-contact-hero-banner' ) . "\n";
	echo "occurrences of 'images.unsplash.com': " . substr_count( $widget_html, 'images.unsplash.com' ) . "\n";

	echo "\n";
}

echo "OK: read-only diagnostic complete\n";
