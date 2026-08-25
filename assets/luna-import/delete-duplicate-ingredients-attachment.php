<?php
/**
 * Guarded cleanup: delete the unused duplicate Media Library attachment
 * left over from the first (failed) ingredients-image fix attempt. That
 * run successfully imported the file (attachment ID 794,
 * lunaimport-products-ingredients-luna.jpg) before the guarded write
 * step aborted on a since-fixed script bug; the retry re-imported the
 * same file, which WordPress renamed to -1.jpg (attachment ID 795),
 * which is the one actually referenced live. This removes attachment
 * 794 only after confirming it is not referenced anywhere in wp_posts
 * or wp_postmeta.
 */

global $wpdb;

$attachment_id = 794;

echo "=====================================================================\n";
echo "STEP A: VERIFY - confirm attachment identity and that it is unreferenced\n";
echo "=====================================================================\n";

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID, post_type, post_status, guid FROM {$wpdb->posts} WHERE ID = %d", $attachment_id ),
	ARRAY_A
);

if ( ! $row ) {
	echo "ERROR: no wp_posts row found with ID={$attachment_id} - nothing to delete, aborting safely\n";
	exit( 1 );
}

echo "found row: ID={$row['ID']}  post_type={$row['post_type']}  post_status={$row['post_status']}\n";
echo "guid: {$row['guid']}\n";

if ( 'attachment' !== $row['post_type'] ) {
	echo "ERROR: expected post_type=attachment, found post_type={$row['post_type']} - refusing to delete a non-attachment post\n";
	exit( 1 );
}

$expected_guid = 'https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-products-ingredients-luna.jpg';
if ( $row['guid'] !== $expected_guid ) {
	echo "ERROR: guid does not match the expected duplicate file.\n";
	echo "expected: {$expected_guid}\n";
	echo "found:    {$row['guid']}\n";
	echo "ABORT: refusing to delete an unexpected attachment\n";
	exit( 1 );
}
echo "OK: guid matches the expected duplicate file exactly\n";

$filename_only = basename( parse_url( $row['guid'], PHP_URL_PATH ) );

$referencing_posts = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT ID, post_type FROM {$wpdb->posts}
		 WHERE ID != %d AND (post_content LIKE %s OR post_content LIKE %s)",
		$attachment_id,
		'%' . $wpdb->esc_like( $filename_only ) . '%',
		'%' . $wpdb->esc_like( $row['guid'] ) . '%'
	),
	ARRAY_A
);

if ( $referencing_posts ) {
	echo "ERROR: found " . count( $referencing_posts ) . " other post(s) referencing this filename/guid in post_content - refusing to delete:\n";
	foreach ( $referencing_posts as $p ) {
		echo "  - post ID={$p['ID']} type={$p['post_type']}\n";
	}
	exit( 1 );
}
echo "OK: no other wp_posts row references this filename/guid in post_content\n";

$referencing_meta = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT post_id, meta_key FROM {$wpdb->postmeta}
		 WHERE meta_value LIKE %s OR meta_value LIKE %s",
		'%' . $wpdb->esc_like( $filename_only ) . '%',
		'%' . $wpdb->esc_like( $row['guid'] ) . '%'
	),
	ARRAY_A
);

if ( $referencing_meta ) {
	echo "ERROR: found " . count( $referencing_meta ) . " postmeta row(s) referencing this filename/guid - refusing to delete:\n";
	foreach ( $referencing_meta as $m ) {
		echo "  - post_id={$m['post_id']} meta_key={$m['meta_key']}\n";
	}
	exit( 1 );
}
echo "OK: no wp_postmeta row references this filename/guid\n";

echo "\n=====================================================================\n";
echo "STEP B: DELETE - permanently remove the unused duplicate attachment\n";
echo "=====================================================================\n";

$result = wp_delete_attachment( $attachment_id, true );

if ( false === $result ) {
	echo "ERROR: wp_delete_attachment() returned false - deletion failed\n";
	exit( 1 );
}
echo "OK: wp_delete_attachment({$attachment_id}, true) succeeded\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - confirm the attachment record and file are gone\n";
echo "=====================================================================\n";

$verify_row = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $attachment_id )
);

if ( $verify_row ) {
	echo "ERROR: attachment row still exists after deletion attempt\n";
	echo "\nFINAL RESULT: FAILURE\n";
	exit( 1 );
}
echo "OK: attachment row confirmed gone from wp_posts\n";

echo "\nFINAL RESULT: SUCCESS\n";
