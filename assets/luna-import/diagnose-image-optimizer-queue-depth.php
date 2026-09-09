<?php
/**
 * READ-ONLY. The 7 target attachments have been stuck at
 * "optimization-in-progress" for ~10 minutes while dozens of
 * "image-optimization/optimize/bulk" jobs completed for other
 * attachments in the media library. Estimate total queue depth and
 * position to give the user a realistic ETA instead of blind waiting.
 */

global $wpdb;

$total_attachments = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('image/jpeg','image/png')"
);
echo "Total image attachments in media library: $total_attachments\n";

$pending_or_running = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions
	 WHERE hook = 'image-optimization/optimize/bulk' AND status IN ('pending','in-progress')"
);
echo "image-optimization/optimize/bulk actions currently pending/in-progress: $pending_or_running\n";

$completed_last_hour = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions
	 WHERE hook = 'image-optimization/optimize/bulk' AND status = 'complete'
	 AND scheduled_date_gmt > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)"
);
echo "Completed in the last hour: $completed_last_hour\n";

$already_optimized = (int) $wpdb->get_var(
	"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
	 WHERE meta_key = 'image_optimizer_metadata' AND meta_value LIKE '%\"status\";s:9:\"optimized\"%'"
);
$still_in_progress = (int) $wpdb->get_var(
	"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
	 WHERE meta_key = 'image_optimizer_metadata' AND meta_value LIKE '%optimization-in-progress%'"
);
echo "Attachments with status=optimized: $already_optimized\n";
echo "Attachments with status=optimization-in-progress: $still_in_progress\n";

echo "\n--- our 7 target attachments: current status string ---\n";
$attachment_ids = array( 776, 778, 780, 782, 784, 786, 788 );
foreach ( $attachment_ids as $id ) {
	$meta = get_post_meta( $id, 'image_optimizer_metadata', true );
	echo "  $id => " . ( is_array( $meta ) ? ( $meta['status'] ?? '(no status key)' ) : '(no metadata)' ) . "\n";
}

echo "\nOK: read-only diagnostic complete\n";
