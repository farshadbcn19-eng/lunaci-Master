<?php
/**
 * Extend the existing dark-background override on WPCode Products CSS
 * (post 483) to also cover the Spanish Products page (page-id-771).
 *
 * Confirmed live via diagnose-products-body-bg-current.php (this session):
 * the rule 'body.page-id-61 { background-color: #0B0B0B !important; }'
 * exists exactly once, was never extended to page-id-771, and the ES
 * Products page renders with a plain white <body> background, causing the
 * visible white stripe reported by the client.
 */

global $wpdb;
$post_id = 483;

echo "==========================================================================\n";
echo "STEP A: PREPARE - fresh-read wp_posts row ID=$post_id and validate preconditions\n";
echo "==========================================================================\n";

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID, post_status, post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ),
	ARRAY_A
);

if ( ! $row ) {
	echo "ERROR: no wp_posts row found with ID=$post_id\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "found row: ID={$row['ID']}  post_status={$row['post_status']}\n";

if ( 'publish' !== $row['post_status'] ) {
	echo "ERROR: expected post_status=publish, found post_status={$row['post_status']}\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: post_status=publish confirmed\n";

$live = $row['post_content'];

$count_en = substr_count( $live, 'body.page-id-61' );
$count_es = substr_count( $live, 'body.page-id-771' );
echo "count 'body.page-id-61': $count_en\n";
echo "count 'body.page-id-771': $count_es\n";

if ( 1 !== $count_en ) {
	echo "ERROR: expected exactly 1 occurrence of 'body.page-id-61', found $count_en - refusing to modify\n";
	echo "ABORT\n";
	exit( 1 );
}
if ( 0 !== $count_es ) {
	echo "ERROR: expected 0 occurrences of 'body.page-id-771' (rule should not already exist), found $count_es - refusing to modify\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: preconditions match exactly (fix not yet applied)\n";

echo "\n==========================================================================\n";
echo "STEP B: COMMIT - extend the body.page-id-61 selector to include body.page-id-771\n";
echo "==========================================================================\n";

$needle      = 'body.page-id-61 { background-color: #0B0B0B !important; }';
$replacement = 'body.page-id-61, body.page-id-771 { background-color: #0B0B0B !important; }';

$updated_content = str_replace( $needle, $replacement, $live, $replace_count );
echo "replacements made: $replace_count\n";

if ( 1 !== $replace_count ) {
	echo "ERROR: expected exactly 1 replacement, made $replace_count - refusing to modify (ambiguous or missing insertion point)\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: computed updated content locally, new length=" . strlen( $updated_content ) . " (was " . strlen( $live ) . ")\n";

$update_ok = $wpdb->update(
	$wpdb->posts,
	array( 'post_content' => $updated_content ),
	array( 'ID' => $post_id ),
	array( '%s' ),
	array( '%d' )
);

if ( false === $update_ok ) {
	echo "ERROR: \$wpdb->update() failed: {$wpdb->last_error}\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: update() returned $update_ok (rows affected)\n";

echo "\n==========================================================================\n";
echo "STEP C: VERIFY - re-read, confirm new rule present, post_status still publish, rest unchanged\n";
echo "==========================================================================\n";

$any_error = false;

$verify_row = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID, post_status, post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ),
	ARRAY_A
);

if ( ! $verify_row ) {
	echo "ERROR: could not re-read row ID=$post_id after update\n";
	$any_error = true;
} else {
	echo "re-read ID={$verify_row['ID']}  post_status={$verify_row['post_status']}\n";

	$status_ok = ( 'publish' === $verify_row['post_status'] );
	echo "post_status is still 'publish': " . ( $status_ok ? 'YES' : 'NO' ) . "\n";

	$has_es_rule = ( false !== strpos( $verify_row['post_content'], 'body.page-id-771' ) );
	echo "new rule 'body.page-id-771' present in stored post_content: " . ( $has_es_rule ? 'YES' : 'NO' ) . "\n";

	$exact_match = ( $verify_row['post_content'] === $updated_content );
	echo "stored post_content matches computed updated content byte-for-byte (rest unchanged): " . ( $exact_match ? 'YES' : 'NO' ) . "\n";

	if ( ! $status_ok || ! $has_es_rule || ! $exact_match ) {
		echo "ERROR: verification FAILED\n";
		$any_error = true;
	} else {
		echo "OK: verification passed\n";
	}
}

echo "\n==========================================================================\n";
if ( $any_error ) {
	echo "ABORT: verification error - see ERROR notice(s) above. Recommend manual DB inspection immediately.\n";
	exit( 1 );
}
echo "OK: body.page-id-771 background rule added to wp_posts ID=$post_id, post_status=publish, rest of content unchanged.\n";
