<?php
/**
 * Read-only: fresh live snapshot of WPCode snippet 8 ("LUNACI Global Header
 * (unified nav)"), a custom post whose post_content holds JS/CSS injected via
 * wp_footer on every page. It already hides the OLD theme header via
 * '.site-header, header.site-header, .elementor-location-header, #masthead
 * { display: none !important; }' but that rule does not match the page-local
 * <header> (Contact, bare) or <header class="lp-header"> (Products) blocks
 * confirmed this session. This diagnostic locates that exact rule and its
 * surrounding context so a safe, scoped CSS-only extension can be written
 * (hiding the redundant local headers by page ID, instead of editing the
 * large embedded HTML documents directly).
 */

global $wpdb;
$post_id = 8;

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID, post_status, post_type, post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ),
	ARRAY_A
);

if ( ! $row ) {
	echo "ERROR: no wp_posts row found with ID=$post_id\n";
	exit( 1 );
}

$content = $row['post_content'];
echo "post_type: {$row['post_type']}\n";
echo "post_status: {$row['post_status']}\n";
echo "content length: " . strlen( $content ) . "\n";
echo "contains CRLF line endings: " . ( false !== strpos( $content, "\r\n" ) ? 'YES' : 'NO' ) . "\n\n";

$needle = 'display: none !important';
echo "count 'display: none !important': " . substr_count( $content, $needle ) . "\n\n";

$marker = '.site-header';
$pos = strpos( $content, $marker );
if ( false !== $pos ) {
	echo "--- 60 bytes BEFORE '.site-header' ---\n";
	echo substr( $content, max( 0, $pos - 60 ), 60 ) . "\n";
	echo "--- end ---\n\n";

	echo "--- 400 bytes FROM '.site-header' ---\n";
	echo substr( $content, $pos, 400 ) . "\n";
	echo "--- end ---\n\n";
} else {
	echo "'.site-header' not found in this snippet's content\n\n";
}

echo "count 'page-id-': " . substr_count( $content, 'page-id-' ) . "\n";
echo "count '.lp-header': " . substr_count( $content, '.lp-header' ) . "\n\n";

echo "md5 of full content: " . md5( $content ) . "\n";

echo "OK: read-only diagnostic complete, no writes performed\n";
