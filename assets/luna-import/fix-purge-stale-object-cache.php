<?php
/**
 * Root cause of "fix applied but not visible on live site" (client report,
 * confirmed via direct curl fetch of /contact/ bypassing all browser/CDN
 * cache): our raw $wpdb->update() writes (used to avoid the KSES/sanitize
 * pipeline that corrupted <style> tags earlier this session) bypass
 * WordPress's normal clean_post_cache() call that wp_update_post() /
 * update_post_meta() would otherwise trigger. If a persistent object cache
 * (Redis/Memcached, common on LiteSpeed-managed hosting) is active, the
 * front-end keeps serving the OLD cached WP_Post/postmeta for the touched
 * posts indefinitely, even though the database row itself is correct
 * (proven by direct SQL re-reads in every fix script's STEP C).
 *
 * This is a read-only-safe operation: clean_post_cache() only deletes
 * cache keys, it does not touch post content or run any sanitization.
 */

echo "wp_using_ext_object_cache(): " . ( wp_using_ext_object_cache() ? 'YES (persistent object cache active)' : 'NO' ) . "\n";
echo "object-cache.php drop-in present: " . ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ? 'YES' : 'NO' ) . "\n\n";

$post_ids = array( 483, 60, 770, 61, 771 );

foreach ( $post_ids as $post_id ) {
	clean_post_cache( $post_id );
	echo "clean_post_cache($post_id) called\n";
}

wp_cache_flush();
echo "\nwp_cache_flush() called\n";

if ( function_exists( 'litespeed_purge_all' ) ) {
	litespeed_purge_all();
	echo "litespeed_purge_all() called\n";
}

if ( has_action( 'litespeed_purge_all' ) ) {
	do_action( 'litespeed_purge_all' );
	echo "do_action('litespeed_purge_all') fired\n";
}

foreach ( $post_ids as $post_id ) {
	if ( has_action( 'litespeed_purge_post' ) ) {
		do_action( 'litespeed_purge_post', $post_id );
	}
}
echo "do_action('litespeed_purge_post', ID) fired for each touched post\n";

echo "\n--- Fresh re-read of post 483 right after cache purge (should reflect DB truth) ---\n";
global $wpdb;
$row = $wpdb->get_row( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", 483 ), ARRAY_A );
echo "count 'page-id-771': " . substr_count( $row['post_content'], 'page-id-771' ) . "\n";
echo "count 'page-id-60 header': " . substr_count( $row['post_content'], 'page-id-60 header' ) . "\n";

// Also verify via the normal WP post-object API (this is what front-end rendering actually uses)
$post_via_api = get_post( 483 );
echo "\n--- Same check via get_post() (goes through object cache) ---\n";
echo "count 'page-id-771' via get_post(): " . substr_count( $post_via_api->post_content, 'page-id-771' ) . "\n";
echo "count 'page-id-60 header' via get_post(): " . substr_count( $post_via_api->post_content, 'page-id-60 header' ) . "\n";

echo "\nOK: cache purge complete\n";
