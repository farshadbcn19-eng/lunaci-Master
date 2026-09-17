<?php
/**
 * GUARDED FIX. The Contact page's AIOSEO meta description is
 * auto-generated navigation-scrape text ("Contact — LUNACI Barcelona
 * Products Shop Contact Shop Now Reach LUNACI We arehere for you...").
 * Same failure pattern fixed for /products/ in round 2, just never
 * checked on /contact/ until the round-6 spot check.
 *
 * Only touches wp_aioseo_posts.description for this one post - does
 * NOT touch _elementor_data or any rendered page content, so there is
 * zero layout risk. The current (broken) value is printed below
 * BEFORE the write, as an explicit backup record in this log.
 */

global $wpdb;

$post = get_page_by_path( 'contact' );
if ( ! $post ) {
	echo "ABORT: could not find a page with slug 'contact'\n";
	return;
}
$post_id = $post->ID;
echo "Contact page post_id: $post_id\n";

$current = $wpdb->get_var( $wpdb->prepare(
	"SELECT description FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d",
	$post_id
) );

echo "--- BACKUP: current description value before any change ---\n";
echo var_export( $current, true ) . "\n";
echo "--- end backup ---\n";

$expected_broken_markers = array( 'arehere', 'Products Shop Contact Shop Now' );
$looks_broken = ( $current === null || $current === '' );
foreach ( $expected_broken_markers as $marker ) {
	if ( $current && stripos( $current, $marker ) !== false ) {
		$looks_broken = true;
		break;
	}
}

if ( ! $looks_broken ) {
	echo "SKIP: current description is non-empty and does not match the known broken pattern - already fixed or changed by someone else. No write performed.\n";
	echo "OK: fix completed successfully (nothing to do)\n";
	return;
}

if ( $current === null || $current === '' ) {
	echo "Note: column is empty/NULL, not a stored broken string - the garbled text seen live is AIOSEO generating a description on the fly from page content because no override exists. Writing a real one below overrides that dynamic fallback.\n";
}

$new_description = 'Get in touch with LUNACI Barcelona. Email, call, or write to us — our team responds to every beauty enquiry within 24 hours.';

$updated = $wpdb->update(
	$wpdb->prefix . 'aioseo_posts',
	array( 'description' => $new_description ),
	array( 'post_id' => $post_id )
);

if ( $updated === false ) {
	echo "ABORT: \$wpdb->update failed\n";
	return;
}

$readback = $wpdb->get_var( $wpdb->prepare(
	"SELECT description FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d",
	$post_id
) );

echo "Readback after write: " . var_export( $readback, true ) . "\n";

if ( $readback !== $new_description ) {
	echo "ABORT: readback does not match what was written\n";
	return;
}

clean_post_cache( $post_id );

echo "OK: fix completed successfully\n";
