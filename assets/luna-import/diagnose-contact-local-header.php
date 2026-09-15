<?php
/**
 * Read-only: client screenshot of the Contact page on mobile shows a
 * "SHOP NOW" button ghosting behind the globe icon in the header - this
 * looks like TWO overlapping fixed headers: the new shared global nav
 * (#lunaciGlobalNav, injected site-wide via snippet 8's wp_footer hook)
 * and the Contact page's OWN embedded <header><nav>...<div class="nav-cta">
 * SHOP NOW</div> markup baked directly into its HTML widget (predating the
 * global nav). The generic "old theme header" hiding rule in snippet 8
 * only targets .site-header/#masthead/etc. classes, not this page-local
 * <header> tag, so both render stacked at position:fixed;top:0.
 *
 * Dump the exact boundaries of this local <header>...</header> block on
 * both EN (60) and ES (770) Contact widgets, and check whether the same
 * pattern also exists on the About Us and Products page widgets. No
 * writes.
 */

function lunaci_find_widget_by_needle( $node, $needle, &$found ) {
	if ( $found ) return;
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $needle ) ) {
				$found = array( 'id' => $node['id'], 'html' => $node['settings']['html'] );
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_find_widget_by_needle( $child, $needle, $found );
			if ( $found ) return;
		}
	}
}

foreach ( array( 'EN Contact' => 60, 'ES Contacto' => 770 ) as $label => $post_id ) {
	echo "=====================================================================\n";
	echo "{$label} (post {$post_id})\n";
	echo "=====================================================================\n";
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	$decoded = json_decode( $raw, true );
	$found = null;
	lunaci_find_widget_by_needle( $decoded, 'nav-cta', $found );
	if ( ! $found ) {
		echo "no widget with 'nav-cta' found\n\n";
		continue;
	}
	$html = $found['html'];
	echo "widget id: {$found['id']}, length: " . strlen( $html ) . "\n";

	$header_open = strpos( $html, '<header>' );
	$header_close = strpos( $html, '</header>' );
	echo "positions: <header> at {$header_open}, </header> at {$header_close}\n";

	if ( false !== $header_open && false !== $header_close ) {
		$block_len = $header_close + strlen('</header>') - $header_open;
		echo "local <header>...</header> block length: {$block_len}\n\n";
		echo "--- FULL local header block ---\n";
		echo substr( $html, $header_open, $block_len ) . "\n";
		echo "--- END local header block ---\n\n";

		echo "--- 150 bytes immediately AFTER </header> ---\n";
		echo substr( $html, $header_close, 150 ) . "\n";
		echo "--- end ---\n";
	}
	echo "\n";
}

echo "=====================================================================\n";
echo "Cross-check: does the same local <header>/nav-cta pattern exist on About Us or Products widgets?\n";
echo "=====================================================================\n";
foreach ( array( 'About Us EN' => 59, 'About Us ES' => 680, 'Products EN' => 61, 'Products ES' => 771 ) as $label => $post_id ) {
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! $raw ) {
		echo "{$label}: no _elementor_data\n";
		continue;
	}
	$decoded = json_decode( $raw, true );
	$found = null;
	lunaci_find_widget_by_needle( $decoded, 'nav-cta', $found );
	echo "{$label}: contains 'nav-cta' local header: " . ( $found ? 'YES (widget id ' . $found['id'] . ')' : 'no' ) . "\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
