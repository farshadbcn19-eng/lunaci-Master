<?php
/**
 * Read-only: fresh live snapshot of WPCode Products CSS snippet (post 483)
 * to confirm whether the body.page-id-61 dark-background rule (added by
 * fix-products-body-bg.yml in a prior session) is present, and to check
 * whether an equivalent body.page-id-771 (ES) rule already exists.
 */

global $wpdb;
$post_id = 483;

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID, post_status, post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ),
	ARRAY_A
);

if ( ! $row ) {
	echo "ERROR: no wp_posts row found with ID=$post_id\n";
	exit( 1 );
}

$content = $row['post_content'];
echo "post_status: {$row['post_status']}\n";
echo "content length: " . strlen( $content ) . "\n";
echo "contains CRLF line endings: " . ( false !== strpos( $content, "\r\n" ) ? 'YES' : 'NO' ) . "\n\n";

echo "count 'body.page-id-61': " . substr_count( $content, 'body.page-id-61' ) . "\n";
echo "count 'body.page-id-771': " . substr_count( $content, 'body.page-id-771' ) . "\n\n";

$needle = 'body.page-id-61';
$pos = strpos( $content, $needle );
if ( false !== $pos ) {
	echo "--- 40 bytes BEFORE 'body.page-id-61' ---\n";
	echo substr( $content, max( 0, $pos - 40 ), 40 ) . "\n";
	echo "--- end ---\n\n";

	echo "--- 120 bytes FROM 'body.page-id-61' ---\n";
	echo substr( $content, $pos, 120 ) . "\n";
	echo "--- end ---\n\n";
}

echo "--- LAST 300 bytes of post_content (verbatim) ---\n";
echo substr( $content, -300 ) . "\n";
echo "--- END ---\n\n";

echo "md5 of full content: " . md5( $content ) . "\n";

echo "OK: read-only diagnostic complete, no writes performed\n";
