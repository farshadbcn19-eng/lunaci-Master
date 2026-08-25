<?php
/**
 * Read-only diagnostic: dump the exact raw CSS text around ".prod-img"
 * from WPCode snippet post 483 (a global/everywhere CSS snippet, already
 * identified in an earlier session investigation), which is where the
 * Products page's product-card image sizing CSS actually lives - not in
 * the page's own Elementor widget as initially assumed.
 */

global $wpdb;

$content = $wpdb->get_var(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", 483 )
);
if ( null === $content ) {
	echo "ERROR: post 483 not found\n";
	exit( 1 );
}

echo "post 483 content length: " . strlen( $content ) . "\n\n";

$offset = 0;
$count  = 0;
while ( false !== ( $pos = strpos( $content, '.prod-img', $offset ) ) ) {
	$count++;
	echo "--- occurrence #{$count} at offset {$pos} ---\n";
	echo substr( $content, $pos, 300 ) . "\n\n";
	$offset = $pos + 9;
}

echo "Total occurrences of '.prod-img': {$count}\n";

echo "\nOK: read-only diagnostic complete, no writes performed\n";
