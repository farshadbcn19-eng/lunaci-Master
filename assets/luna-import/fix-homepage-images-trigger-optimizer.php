<?php
/**
 * Guarded fix: retroactively let the Hostinger Image Optimization
 * plugin (v1.7.6, active, configured for optimize_on_upload=1,
 * convert_to_format=webp) process the 7 homepage images. Confirmed by
 * diagnose-image-optimizer-attachment-status.php: all 7 ARE registered
 * media-library attachments (IDs 776-788), but none carry any
 * optimizer-related postmeta - they were inserted directly by earlier
 * import work (wp_insert_attachment() calls in fix-seo-audit-social-
 * schema.php and similar scripts) which never fired the standard
 * 'wp_generate_attachment_metadata' filter WordPress image-processing
 * plugins hook into for new uploads.
 *
 * Rather than guessing at the plugin's internal/undocumented API, this
 * re-triggers that standard, documented WordPress core hook by calling
 * wp_generate_attachment_metadata() again for each attachment - the
 * same mechanism "regenerate thumbnails" style plugins use. If the
 * optimizer plugin is hooked in (expected, since it's active and
 * configured), this gives it the same chance to act it would have had
 * on a normal upload. If it isn't hooked into this filter for some
 * reason, this call is a harmless no-op beyond regenerating the
 * standard WP thumbnail sizes.
 *
 * Guarded: only processes attachments that currently have NO
 * optimizer-related postmeta (the exact state confirmed above) - a
 * second run would skip everything, since by then either the postmeta
 * exists or it doesn't and re-running is still safe/idempotent.
 */

require_once ABSPATH . 'wp-admin/includes/image.php';

$attachment_ids = array(
	776 => 'lunaimport-hero-luna.jpg',
	778 => 'lunaimport-collection-face-luna.jpg',
	780 => 'lunaimport-collection-eyes-luna.jpg',
	782 => 'lunaimport-collection-lips-luna.jpg',
	784 => 'lunaimport-collection-nails-luna.jpg',
	788 => 'lunaimport-why2-luna-replacement.jpg',
	786 => 'lunaimport-origin-crafted-barcelona-luna.jpg',
);

$changed = array();
$skipped = array();

foreach ( $attachment_ids as $attachment_id => $label ) {
	// Guard: only proceed if there's genuinely no optimizer postmeta yet
	// (the exact state the diagnostic confirmed for all 7).
	$existing_optimizer_meta = array();
	foreach ( get_post_meta( $attachment_id ) as $meta_key => $meta_val ) {
		if ( stripos( $meta_key, 'optim' ) !== false || stripos( $meta_key, 'webp' ) !== false || stripos( $meta_key, 'avif' ) !== false ) {
			$existing_optimizer_meta[ $meta_key ] = true;
		}
	}
	if ( ! empty( $existing_optimizer_meta ) ) {
		$skipped[] = "{$label} (id={$attachment_id}): already has optimizer postmeta (" . implode( ', ', array_keys( $existing_optimizer_meta ) ) . ') - left untouched';
		continue;
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) {
		$skipped[] = "{$label} (id={$attachment_id}): attached file not found on disk ({$file})";
		continue;
	}

	$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
	if ( is_wp_error( $metadata ) || empty( $metadata ) ) {
		$skipped[] = "{$label} (id={$attachment_id}): wp_generate_attachment_metadata() returned empty/error";
		continue;
	}
	wp_update_attachment_metadata( $attachment_id, $metadata );

	// Re-check whether any optimizer postmeta appeared as a result.
	$new_optimizer_meta = array();
	foreach ( get_post_meta( $attachment_id ) as $meta_key => $meta_val ) {
		if ( stripos( $meta_key, 'optim' ) !== false || stripos( $meta_key, 'webp' ) !== false || stripos( $meta_key, 'avif' ) !== false ) {
			$new_optimizer_meta[] = $meta_key;
		}
	}

	if ( $new_optimizer_meta ) {
		$changed[] = "{$label} (id={$attachment_id}): metadata regenerated, optimizer postmeta now present (" . implode( ', ', $new_optimizer_meta ) . ')';
	} else {
		$changed[] = "{$label} (id={$attachment_id}): metadata regenerated, but no optimizer postmeta appeared - plugin may not hook into wp_generate_attachment_metadata, or processes asynchronously";
	}
}

echo "\n--- CHANGED (" . count( $changed ) . ") ---\n";
foreach ( $changed as $c ) {
	echo "  + {$c}\n";
}
echo "\n--- SKIPPED (" . count( $skipped ) . ") ---\n";
foreach ( $skipped as $s ) {
	echo "  - {$s}\n";
}

echo "\nOK: guarded fix complete\n";
