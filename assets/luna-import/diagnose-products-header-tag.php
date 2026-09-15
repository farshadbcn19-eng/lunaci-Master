<?php
/**
 * Read-only: get the exact opening <header ...> tag (with attributes) for
 * the local legacy header block on the Products page, EN (61) and ES (771),
 * widget id 296bd28 (confirmed via diagnose-products-local-header.php: the
 * literal '<header>' search failed but '</header>' was found, meaning the
 * opening tag carries attributes not matched by that exact string).
 */

function lunaci_find_widget_by_needle_3( $node, $needle, &$found ) {
	if ( $found ) return;
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $needle ) ) {
				$found = array( 'id' => $node['id'], 'html' => $node['settings']['html'] );
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_find_widget_by_needle_3( $child, $needle, $found );
			if ( $found ) return;
		}
	}
}

foreach ( array( 'EN Products' => 61, 'ES Productos' => 771 ) as $label => $post_id ) {
	echo "=====================================================================\n";
	echo "{$label} (post {$post_id})\n";
	echo "=====================================================================\n";
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	$decoded = json_decode( $raw, true );
	$found = null;
	lunaci_find_widget_by_needle_3( $decoded, 'nav-cta', $found );
	if ( ! $found ) {
		echo "no widget with 'nav-cta' found\n\n";
		continue;
	}
	$html = $found['html'];
	echo "widget id: {$found['id']}, length: " . strlen( $html ) . "\n";

	$header_close = strpos( $html, '</header>' );
	echo "position of </header>: {$header_close}\n";

	if ( false === $header_close ) {
		echo "no </header> found - skipping\n\n";
		continue;
	}

	// Search backward from </header> for the nearest '<header' (any attributes).
	$search_region = substr( $html, 0, $header_close );
	$header_open = strripos( $search_region, '<header' );
	echo "position of nearest preceding '<header': {$header_open}\n";

	if ( false === $header_open ) {
		echo "no opening <header tag found before </header> - dumping 400 bytes before </header> for manual inspection\n";
		echo substr( $html, max( 0, $header_close - 400 ), 400 ) . "\n\n";
		continue;
	}

	// find the end of the opening tag (the next '>' after $header_open)
	$tag_end = strpos( $html, '>', $header_open );
	$opening_tag = substr( $html, $header_open, $tag_end - $header_open + 1 );
	echo "exact opening tag: " . $opening_tag . "\n";

	$block_len = $header_close + strlen( '</header>' ) - $header_open;
	echo "full <header>...</header> block length: {$block_len}\n\n";
	echo "--- FULL local header block ---\n";
	echo substr( $html, $header_open, $block_len ) . "\n";
	echo "--- END local header block ---\n\n";

	echo "--- 150 bytes BEFORE opening tag ---\n";
	echo substr( $html, max( 0, $header_open - 150 ), 150 ) . "\n";
	echo "--- end ---\n\n";

	echo "--- 150 bytes AFTER </header> ---\n";
	echo substr( $html, $header_close, 150 ) . "\n";
	echo "--- end ---\n";
	echo "\n";
}

echo "OK: read-only diagnostic complete, no writes performed\n";
