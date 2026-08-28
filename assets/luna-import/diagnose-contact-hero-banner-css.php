<?php
/**
 * Read-only: found both Contact pages (60 EN, 770 ES) use Elementor HTML
 * widgets with a `.contact-hero` section whose background is a CSS
 * `background: linear-gradient(...), url('https://images.unsplash.com/...')
 * center/cover no-repeat;` - a stock placeholder photo. Before replacing it
 * with the user's uploaded banner image, dump the EXACT current CSS rule
 * (and confirm whether EN/ES use the same Unsplash URL) so the find/replace
 * can match precisely.
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

	echo "widget html length: " . strlen( $widget_html ) . "\n";

	if ( preg_match( '/\.contact-hero\s*\{[^}]*\}/s', $widget_html, $m ) ) {
		echo "--- .contact-hero rule ---\n" . $m[0] . "\n";
	} else {
		echo "could not extract .contact-hero rule via regex\n";
	}

	// count all occurrences of images.unsplash.com in this widget
	$unsplash_count = substr_count( $widget_html, 'images.unsplash.com' );
	echo "occurrences of 'images.unsplash.com' in this widget: {$unsplash_count}\n";
	if ( preg_match_all( '/https:\/\/images\.unsplash\.com\/[^\'")\s]+/', $widget_html, $matches ) ) {
		foreach ( array_unique( $matches[0] ) as $url ) {
			echo "  found unsplash URL: {$url}\n";
		}
	}

	echo "\n";
}

echo "OK: read-only diagnostic complete\n";
