<?php
/**
 * ROOT CAUSE FIX for "database says fixed but live site shows old content":
 *
 * WPCode does NOT render its CSS/JS output from wp_posts.post_content on
 * every page load. It renders from the 'wpcode_snippets' option (a single
 * wp_options row caching {id, title, code, ...} for every active snippet),
 * which is only regenerated when a snippet is saved through the normal
 * WPCode admin UI (wp_insert_post / save_post_wpcode hooks). Every raw
 * $wpdb->update() write to wp_posts this session (post 483: ES background
 * rule + header-hiding rule) was correctly saved to the database - proven
 * by every fix script's own STEP C - but never touched this option, so the
 * live front-end kept serving its stale cached copy (confirmed: cached
 * 'code' for id=483 still ends at the pre-session state, timestamped
 * "2026-07-05 18:07:26").
 *
 * Fix: read current wp_posts.post_content for post 483 (ground truth),
 * find the matching entry (id===483) inside wpcode_snippets['site_wide_header'],
 * replace only its 'code' field with the current DB content, write back via
 * the normal update_option() (safe here - this option is a plain internal
 * cache, not user content passing through Elementor/KSES sanitization),
 * and verify.
 */

global $wpdb;
$post_id = 483;

echo "==========================================================================\n";
echo "STEP A: PREPARE - fresh-read ground truth (wp_posts) and current cached option\n";
echo "==========================================================================\n";

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ),
	ARRAY_A
);
if ( ! $row ) {
	echo "ERROR: no wp_posts row found with ID=$post_id\n";
	exit( 1 );
}
$db_content = $row['post_content'];
echo "wp_posts.post_content length: " . strlen( $db_content ) . "\n";
echo "db content contains 'page-id-771': " . ( false !== strpos( $db_content, 'page-id-771' ) ? 'YES' : 'NO' ) . "\n";
echo "db content contains 'page-id-60 header': " . ( false !== strpos( $db_content, 'page-id-60 header' ) ? 'YES' : 'NO' ) . "\n";

$option = get_option( 'wpcode_snippets' );
if ( ! is_array( $option ) || ! isset( $option['site_wide_header'] ) || ! is_array( $option['site_wide_header'] ) ) {
	echo "ERROR: 'wpcode_snippets' option missing or has unexpected structure - refusing to modify\n";
	exit( 1 );
}

$found_index = null;
foreach ( $option['site_wide_header'] as $index => $item ) {
	if ( is_array( $item ) && isset( $item['id'] ) && 483 == $item['id'] ) {
		$found_index = $index;
		break;
	}
}

if ( null === $found_index ) {
	echo "ERROR: could not find an entry with id=483 inside wpcode_snippets['site_wide_header'] - refusing to modify\n";
	exit( 1 );
}
echo "OK: found entry at site_wide_header[$found_index] with id=483\n";

$cached_code = $option['site_wide_header'][ $found_index ]['code'];
echo "cached 'code' length: " . strlen( $cached_code ) . "\n";
echo "cached code already matches DB content: " . ( $cached_code === $db_content ? 'YES (nothing to do)' : 'NO (stale - will sync)' ) . "\n";

if ( $cached_code === $db_content ) {
	echo "\nOK: cache already in sync with database, no update needed\n";
	exit( 0 );
}

echo "\n==========================================================================\n";
echo "STEP B: COMMIT - update only the 'code' field of the id=483 entry, write back full option\n";
echo "==========================================================================\n";

$updated_option = $option;
$updated_option['site_wide_header'][ $found_index ]['code']     = $db_content;
$updated_option['site_wide_header'][ $found_index ]['modified'] = current_time( 'mysql' );

$update_ok = update_option( 'wpcode_snippets', $updated_option );
echo "update_option('wpcode_snippets', ...) returned: " . ( $update_ok ? 'true' : 'false (may mean value was identical, or a genuine failure)' ) . "\n";

echo "\n==========================================================================\n";
echo "STEP C: VERIFY - re-read option, confirm id=483 code now matches DB content exactly\n";
echo "==========================================================================\n";

$any_error = false;

$verify_option = get_option( 'wpcode_snippets' );
$verify_found  = null;
foreach ( (array) ( $verify_option['site_wide_header'] ?? array() ) as $index => $item ) {
	if ( is_array( $item ) && isset( $item['id'] ) && 483 == $item['id'] ) {
		$verify_found = $item;
		break;
	}
}

if ( null === $verify_found ) {
	echo "ERROR: could not re-find id=483 entry after update\n";
	$any_error = true;
} else {
	$match = ( $verify_found['code'] === $db_content );
	echo "re-read cached code matches current wp_posts.post_content byte-for-byte: " . ( $match ? 'YES' : 'NO' ) . "\n";
	echo "re-read cached code contains 'page-id-771': " . ( false !== strpos( $verify_found['code'], 'page-id-771' ) ? 'YES' : 'NO' ) . "\n";
	echo "re-read cached code contains 'page-id-60 header': " . ( false !== strpos( $verify_found['code'], 'page-id-60 header' ) ? 'YES' : 'NO' ) . "\n";
	if ( ! $match ) {
		echo "ERROR: verification FAILED\n";
		$any_error = true;
	} else {
		echo "OK: verification passed\n";
	}
}

// Sanity check: confirm the OTHER entry (id=319) in the same array was left untouched.
foreach ( (array) ( $verify_option['site_wide_header'] ?? array() ) as $item ) {
	if ( is_array( $item ) && isset( $item['id'] ) && 319 == $item['id'] ) {
		$unchanged = ( $item['code'] === $option['site_wide_header'][1]['code'] );
		echo "sibling entry id=319 left unchanged: " . ( $unchanged ? 'YES' : 'NO (UNEXPECTED)' ) . "\n";
		if ( ! $unchanged ) {
			$any_error = true;
		}
	}
}

echo "\n==========================================================================\n";
if ( $any_error ) {
	echo "ABORT: verification error - see ERROR notice(s) above. Recommend manual inspection immediately.\n";
	exit( 1 );
}
echo "OK: wpcode_snippets cache for id=483 synced to match wp_posts, sibling entries unchanged.\n";
