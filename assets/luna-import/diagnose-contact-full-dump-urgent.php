<?php
/**
 * URGENT read-only: full dump of the Contact page widget HTML (both EN 60
 * and ES 770) to find the exact boundaries of the CSS block that is now
 * missing its <style>/</style> wrapper tags, so a precise re-wrap fix can
 * be written. No writes.
 */

foreach ( array( 60 => 'EN', 770 => 'ES' ) as $page_id => $label ) {
	echo "===== PAGE {$page_id} ({$label}) =====\n";
	$raw = get_post_meta( $page_id, '_elementor_data', true );
	$decoded = json_decode( $raw, true );
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
		echo "NOT FOUND\n\n";
		continue;
	}

	echo "length: " . strlen( $widget_html ) . "\n";
	echo "count '<meta': " . substr_count( $widget_html, '<meta' ) . "\n";
	echo "count '<title': " . substr_count( $widget_html, '<title' ) . "\n";
	echo "count '<nav': " . substr_count( $widget_html, '<nav' ) . "\n";
	echo "count '<header': " . substr_count( $widget_html, '<header' ) . "\n";
	echo "count '<body': " . substr_count( $widget_html, '<body' ) . "\n";
	echo "count '<html': " . substr_count( $widget_html, '<html' ) . "\n";
	echo "count '<head': " . substr_count( $widget_html, '<head' ) . "\n";

	// find end of </head> or first tag after description meta, to locate where CSS block starts
	$desc_pos = strpos( $widget_html, '</title>' );
	if ( false !== $desc_pos ) {
		echo "\n--- 400 bytes after </title> ---\n";
		echo substr( $widget_html, $desc_pos, 400 ) . "\n";
		echo "--- end ---\n";
	}

	// find where the CSS block ends by locating the first real HTML tag after :root or .contact-hero block, e.g. <nav or <header or <body
	$nav_pos = strpos( $widget_html, '<nav' );
	$header_pos = strpos( $widget_html, '<header' );
	$body_pos = strpos( $widget_html, '<body' );
	echo "\npositions: <nav>={$nav_pos} <header>=" . var_export($header_pos, true) . " <body>=" . var_export($body_pos, true) . "\n";

	$candidates = array_filter( array( $nav_pos, $header_pos, $body_pos ), function( $v ) { return false !== $v; } );
	if ( ! empty( $candidates ) ) {
		$first_markup_pos = min( $candidates );
		echo "\n--- 300 bytes BEFORE first real markup tag (offset {$first_markup_pos}) ---\n";
		echo substr( $widget_html, max(0, $first_markup_pos - 300), 300 ) . "\n";
		echo "--- 100 bytes FROM first real markup tag ---\n";
		echo substr( $widget_html, $first_markup_pos, 100 ) . "\n";
		echo "--- end ---\n";
	}

	echo "\n\n";
}

echo "OK: read-only, no writes\n";
