<?php
/**
 * Hide the redundant, legacy per-page <header><nav>...</nav></header> block
 * on Contact (EN 60 / ES 770, bare <header>) and Products (EN 61 / ES 771,
 * <header class="lp-header">) that overlaps the shared global nav
 * (#lunaciGlobalNav / .ln-nav) on mobile, per the client's screenshot.
 *
 * Confirmed this session:
 * - Contact and Products each embed a full standalone HTML document inside
 *   a single Elementor HTML widget; the only <header> tag in that document
 *   is this legacy block (predates the global nav).
 * - WPCode snippet 483 (a pure-CSS snippet, post_type=wpcode) already loads
 *   site-wide in <head> regardless of page - proven by the just-applied
 *   body.page-id-61 / body.page-id-771 background rules taking effect only
 *   on their respective pages via body-class scoping. This makes it the
 *   safe place to add more page-scoped rules without touching the large
 *   embedded HTML documents again (which caused a prior regression when
 *   edited directly).
 *
 * This is a pure CSS append, using the same read-verify-write-verify
 * pattern as fix-products-body-bg-es.php.
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

$anchor = 'body.page-id-61, body.page-id-771 { background-color: #0B0B0B !important; }';
$count_anchor = substr_count( $live, $anchor );
echo "count of anchor line (body.page-id-61/771 background rule): $count_anchor\n";

if ( 1 !== $count_anchor ) {
	echo "ERROR: expected exactly 1 occurrence of the anchor line (from the prior ES background fix), found $count_anchor - refusing to modify\n";
	echo "ABORT\n";
	exit( 1 );
}

$new_rule = 'body.page-id-60 header, body.page-id-770 header, body.page-id-61 .lp-header, body.page-id-771 .lp-header { display: none !important; }';

$count_new_rule = substr_count( $live, $new_rule );
echo "count of exact new rule (already applied?): $count_new_rule\n";

if ( 0 !== $count_new_rule ) {
	echo "ERROR: header-hiding rule appears to already exist verbatim - refusing to modify\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: preconditions match exactly (fix not yet applied)\n";

echo "\n==========================================================================\n";
echo "STEP B: COMMIT - append duplicate-header-hiding rule after the ES background rule\n";
echo "==========================================================================\n";

$needle      = $anchor;
$replacement = $anchor . "\r\n\r\n" . $new_rule;

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

	$has_new_rule = ( false !== strpos( $verify_row['post_content'], $new_rule ) );
	echo "new header-hiding rule present in stored post_content: " . ( $has_new_rule ? 'YES' : 'NO' ) . "\n";

	$exact_match = ( $verify_row['post_content'] === $updated_content );
	echo "stored post_content matches computed updated content byte-for-byte (rest unchanged): " . ( $exact_match ? 'YES' : 'NO' ) . "\n";

	if ( ! $status_ok || ! $has_new_rule || ! $exact_match ) {
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
echo "OK: duplicate local header hiding rule added to wp_posts ID=$post_id, post_status=publish, rest of content unchanged.\n";
