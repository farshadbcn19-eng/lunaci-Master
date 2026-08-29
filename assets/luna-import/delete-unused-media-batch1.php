<?php
/**
 * Guarded bulk deletion of media library attachments confirmed unused by
 * the pre-launch site audit (audit-unused-media.php + verify-ambiguous-
 * media.php). Grouped by why each is safe to delete:
 *
 * - WooCommerce sample-data images whose parent demo products were already
 *   deleted (orphaned "External Image For post: X" attachments).
 * - Two stray .html file uploads (LUNACI_Nav_Elementor) - not images, not
 *   referenced by any enqueue/snippet.
 * - Duplicate DB rows created alongside an already-in-use upload (each
 *   product/banner below is confirmed to have a DIFFERENT, actively-used
 *   attachment ID already set - these are the leftover twin rows).
 * - Old category banner iterations superseded by the current live ones
 *   (824 face / 826 eyes / 828 lips / 832 nails).
 * - A "woocommerce-placeholder.webp" upload that the live
 *   woocommerce_placeholder_image option does NOT point to.
 * - An original hero photo superseded by a "-1" re-upload already in use
 *   on the live homepage.
 *
 * NOTE: a separate set of ~39 shade-swatch prep photos (Lip Gloss Velvet /
 * Lip Fix / Lip Velvet / Foundation) was also flagged unused by the audit,
 * but the user asked to KEEP those - they're prep material for a future
 * Shade Selector feature that hasn't been built yet, not garbage. They are
 * deliberately excluded from this deletion list.
 *
 * Every ID is re-verified live (still exists, still not a featured image,
 * still not in a product gallery) immediately before deletion - anything
 * that fails re-verification is SKIPPED, not force-deleted.
 */

global $wpdb;

$candidate_ids = array(
	// orphaned WooCommerce sample-data images (parent demo products already deleted)
	13, 15, 17, 19, 21, 49, 51, 53, 55,
	522, 523, 524, 526, 528, 529, 530, 531, 532,
	// stray .html file uploads
	81, 533,
	// duplicate rows of already-in-use uploads
	333, 538, 553, 756, 762, 763, 819, 821, 823,
	// superseded category banner iterations
	614, 616, 618, 620, 622, 624, 626, 628, 630, 634, 636,
	825, 827, 829, 830, 831, 833,
	// misc unused
	521, 534,
);
$candidate_ids = array_values( array_unique( array_map( 'intval', $candidate_ids ) ) );

echo '--- STEP A: PREPARE (' . count( $candidate_ids ) . " candidates) ---\n";

$to_delete = array();
$skipped   = array();

foreach ( $candidate_ids as $id ) {
	$post = get_post( $id );
	if ( ! $post || 'attachment' !== $post->post_type ) {
		$skipped[] = "{$id}: no longer an attachment (already gone?)";
		continue;
	}

	$is_thumbnail = (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s",
		(string) $id
	) );
	if ( $is_thumbnail ) {
		$skipped[] = "{$id}: is currently someone's featured image - SKIPPING";
		continue;
	}

	$in_gallery = (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND (meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)",
		(string) $id,
		$wpdb->esc_like( $id . ',' ) . '%',
		'%' . $wpdb->esc_like( ',' . $id . ',' ) . '%',
		'%' . $wpdb->esc_like( ',' . $id )
	) );
	if ( $in_gallery ) {
		$skipped[] = "{$id}: is currently in a product image gallery - SKIPPING";
		continue;
	}

	$to_delete[] = $id;
}

echo 'confirmed safe to delete: ' . count( $to_delete ) . "\n";
echo 'skipped (re-verification failed): ' . count( $skipped ) . "\n";
foreach ( $skipped as $s ) {
	echo "  SKIP: {$s}\n";
}

if ( empty( $to_delete ) ) {
	echo "\nNothing to delete after re-verification.\n";
	exit( 0 );
}

echo "\n--- STEP B: COMMIT ---\n";
$deleted_count = 0;
$fail_count    = 0;
foreach ( $to_delete as $id ) {
	$result = wp_delete_attachment( $id, true );
	if ( $result ) {
		$deleted_count++;
	} else {
		$fail_count++;
		echo "  FAILED to delete {$id}\n";
	}
}
echo "deleted: {$deleted_count}, failed: {$fail_count}\n";

echo "\n--- STEP C: VERIFY ---\n";
$still_present = array();
foreach ( $to_delete as $id ) {
	if ( get_post( $id ) ) {
		$still_present[] = $id;
	}
}
echo 'still present after delete attempt: ' . count( $still_present ) . "\n";
if ( ! empty( $still_present ) ) {
	echo 'IDs still present: ' . implode( ', ', $still_present ) . "\n";
}

if ( $fail_count > 0 || ! empty( $still_present ) ) {
	echo "\nFINAL RESULT: PARTIAL (some deletions failed or were not verified - see above)\n";
	exit( 1 );
}

echo "\n=====================================================================\n";
echo "FINAL RESULT: SUCCESS ({$deleted_count} attachments deleted)\n";
