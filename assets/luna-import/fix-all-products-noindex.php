<?php
/**
 * Guarded fix: the new /all-products/ page (post 836) has no
 * wp_aioseo_posts row at all - AIOSEO's own save_post hook apparently
 * doesn't create one when a post is inserted via wp_insert_post() in a
 * WP-CLI eval-file context, unlike a normal admin/REST save. Per the
 * user's explicit requirement (avoid recreating the duplicate-catalog
 * SEO problem a prior session fixed), this page must be noindexed from
 * the start, the same way post 56 already is.
 *
 * Clones post 56's entire aioseo_posts row (which already has
 * robots_default=0, robots_noindex=1 from that prior fix) into a new
 * row for post 836, letting the primary key auto-increment - this
 * guarantees every NOT NULL/required column AIOSEO expects is
 * populated with a known-valid value, rather than guessing the schema.
 */

global $wpdb;

$aioseo_table = $wpdb->prefix . 'aioseo_posts';
$new_post_id  = 836;
$template_id  = 56;

echo "--- STEP A: PREPARE ---\n";

$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$aioseo_table} WHERE post_id = %d", $new_post_id ), ARRAY_A );
if ( $existing ) {
	echo "already have an aioseo_posts row for post {$new_post_id} (id={$existing['id']}) - checking its robots state instead of inserting\n";
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT robots_default, robots_noindex FROM {$aioseo_table} WHERE post_id = %d", $new_post_id ), ARRAY_A );
	if ( '0' === (string) $row['robots_default'] && '1' === (string) $row['robots_noindex'] ) {
		echo "OK: already noindexed - nothing to do\n";
		echo "FINAL RESULT: SUCCESS (no-op)\n";
		exit( 0 );
	}
	$result = $wpdb->update(
		$aioseo_table,
		array( 'robots_default' => 0, 'robots_noindex' => 1 ),
		array( 'post_id' => $new_post_id )
	);
	echo 'update result: ' . var_export( $result, true ) . "\n";
	$verify = $wpdb->get_row( $wpdb->prepare( "SELECT robots_default, robots_noindex FROM {$aioseo_table} WHERE post_id = %d", $new_post_id ), ARRAY_A );
	$ok = ( '0' === (string) $verify['robots_default'] && '1' === (string) $verify['robots_noindex'] );
	echo 'FINAL RESULT: ' . ( $ok ? 'SUCCESS' : 'FAILURE' ) . "\n";
	exit( $ok ? 0 : 1 );
}

$template_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$aioseo_table} WHERE post_id = %d", $template_id ), ARRAY_A );
if ( ! $template_row ) {
	echo "ABORT: no template aioseo_posts row for post {$template_id} to clone\n";
	exit( 1 );
}
echo 'OK: found template row (id=' . $template_row['id'] . ") for post {$template_id}\n";
echo 'template robots_default=' . $template_row['robots_default'] . ' robots_noindex=' . $template_row['robots_noindex'] . "\n";

if ( '0' !== (string) $template_row['robots_default'] || '1' !== (string) $template_row['robots_noindex'] ) {
	echo "ABORT: template row is not in the expected noindex state - refusing to clone an unexpected state\n";
	exit( 1 );
}

echo "\n--- STEP B: COMMIT ---\n";

$new_row = $template_row;
unset( $new_row['id'] );
$new_row['post_id'] = $new_post_id;
// These are template/post-specific caches AIOSEO keeps; clear them so
// they get regenerated correctly for this post instead of carrying
// post 56's stale title/permalink/dates.
foreach ( array( 'title', 'description', 'permalink', 'created', 'updated' ) as $clearable ) {
	if ( array_key_exists( $clearable, $new_row ) ) {
		$new_row[ $clearable ] = in_array( $clearable, array( 'created', 'updated' ), true ) ? current_time( 'mysql' ) : '';
	}
}

$inserted = $wpdb->insert( $aioseo_table, $new_row );
echo '$wpdb->insert() rows affected: ' . var_export( $inserted, true ) . "\n";
if ( ! $inserted ) {
	echo 'ABORT: insert failed: ' . $wpdb->last_error . "\n";
	exit( 1 );
}

echo "\n--- STEP C: VERIFY ---\n";
$verify = $wpdb->get_row( $wpdb->prepare( "SELECT id, robots_default, robots_noindex FROM {$aioseo_table} WHERE post_id = %d", $new_post_id ), ARRAY_A );
if ( ! $verify ) {
	echo "MISMATCH: no row found after insert\n";
	exit( 1 );
}
echo "new row id={$verify['id']} robots_default={$verify['robots_default']} robots_noindex={$verify['robots_noindex']}\n";
$ok = ( '0' === (string) $verify['robots_default'] && '1' === (string) $verify['robots_noindex'] );

echo "\n=====================================================================\n";
if ( $ok ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE\n";
	exit( 1 );
}
