<?php
/**
 * Read-only: About Us EN (post 59) widget ce307e5 has 'html' length 23207,
 * ES (post 680) same widget id has length 19420 - a ~3800 byte gap. Since
 * the page renders as ~17000px tall with long blank stretches (not just
 * shorter), the missing content likely left an empty-but-still-sized
 * section wrapper behind rather than removing the section outright. Break
 * both HTML strings into <section ...> chunks and compare structurally
 * (count, ids/classes, and each section's inner text length) to pinpoint
 * exactly which section(s) differ. No writes.
 */

foreach ( array( 'EN' => 59, 'ES' => 680 ) as $label => $post_id ) {
	echo "=====================================================================\n";
	echo "{$label} post {$post_id}\n";
	echo "=====================================================================\n";

	$raw = get_post_meta( $post_id, '_elementor_data', true );
	$decoded = json_decode( $raw, true );
	$html = null;
	$finder = function ( $node ) use ( &$finder, &$html ) {
		if ( $html ) return;
		if ( is_array( $node ) ) {
			if ( isset( $node['id'] ) && 'ce307e5' === $node['id'] && isset( $node['settings']['html'] ) ) {
				$html = $node['settings']['html'];
				return;
			}
			foreach ( $node as $child ) {
				$finder( $child );
				if ( $html ) return;
			}
		}
	};
	$finder( $decoded );

	if ( ! $html ) {
		echo "NOT FOUND\n\n";
		continue;
	}

	echo "total length: " . strlen( $html ) . "\n";

	// Split into <section ...> ... </section> chunks (non-greedy, DOTALL)
	preg_match_all( '/<section\b[^>]*>/i', $html, $opens, PREG_OFFSET_CAPTURE );
	echo "section tag count: " . count( $opens[0] ) . "\n\n";

	foreach ( $opens[0] as $i => $match ) {
		list( $tag, $pos ) = $match;
		$next_pos = isset( $opens[0][ $i + 1 ] ) ? $opens[0][ $i + 1 ][1] : strlen( $html );
		$chunk = substr( $html, $pos, $next_pos - $pos );
		$text_only = trim( preg_replace( '/\s+/', ' ', strip_tags( $chunk ) ) );
		echo "--- section #{$i} (tag: " . substr( $tag, 0, 120 ) . ") ---\n";
		echo "chunk length: " . strlen( $chunk ) . "   visible text length: " . strlen( $text_only ) . "\n";
		echo "visible text preview: " . substr( $text_only, 0, 150 ) . "\n";
		echo "count '<img': " . substr_count( $chunk, '<img' ) . "\n\n";
	}
	echo "\n";
}

echo "OK: read-only diagnostic complete, no writes performed\n";
