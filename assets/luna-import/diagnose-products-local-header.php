<?php
/**
 * Read-only: get the exact boundaries and content of the local
 * <header>...</header> block (widget id 296bd28, confirmed via
 * diagnose-contact-local-header.php cross-check) on the Products page,
 * EN (61) and ES (771), so it can be safely removed - same redundant
 * pre-global-nav header pattern already confirmed on Contact.
 */

function lunaci_find_widget_by_needle_2( $node, $needle, &$found ) {
	if ( $found ) return;
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $needle ) ) {
				$found = array( 'id' => $node['id'], 'html' => $node['settings']['html'] );
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_find_widget_by_needle_2( $child, $needle, $found );
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
	lunaci_find_widget_by_needle_2( $decoded, 'nav-cta', $found );
	if ( ! $found ) {
		echo "no widget with 'nav-cta' found\n\n";
		continue;
	}
	$html = $found['html'];
	echo "widget id: {$found['id']}, length: " . strlen( $html ) . "\n";

	$header_open = strpos( $html, '<header>' );
	$header_close = strpos( $html, '</header>' );
	echo "positions: <header> at {$header_open}, </header> at {$header_close}\n";
	echo "count '<header>': " . substr_count( $html, '<header>' ) . "   count '</header>': " . substr_count( $html, '</header>' ) . "\n";

	if ( false !== $header_open && false !== $header_close ) {
		$block_len = $header_close + strlen('</header>') - $header_open;
		echo "local <header>...</header> block length: {$block_len}\n\n";
		echo "--- FULL local header block ---\n";
		echo substr( $html, $header_open, $block_len ) . "\n";
		echo "--- END local header block ---\n\n";

		echo "--- 200 bytes BEFORE <header> ---\n";
		echo substr( $html, max(0, $header_open - 200), 200 ) . "\n";
		echo "--- end ---\n\n";

		echo "--- 200 bytes AFTER </header> ---\n";
		echo substr( $html, $header_close, 200 ) . "\n";
		echo "--- end ---\n";
	}
	echo "\n";
}

echo "OK: read-only diagnostic complete, no writes performed\n";
