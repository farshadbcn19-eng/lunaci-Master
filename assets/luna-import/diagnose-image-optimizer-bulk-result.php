<?php
/**
 * READ-ONLY. User clicked "Bulk Optimize" in the Image Optimization plugin's
 * admin UI (admin.php?page=image-optimization-settings). Verify whether the
 * 7 pre-existing homepage image attachments were actually converted:
 * - optimizer-related postmeta now present
 * - .webp / .avif sibling files now exist on disk next to the originals
 * - any related Action Scheduler jobs and their status
 */

$attachment_ids = array( 776, 778, 780, 782, 784, 786, 788 );

echo "--- per-attachment check ---\n";
foreach ( $attachment_ids as $id ) {
	$file = get_attached_file( $id );
	echo "Attachment $id\n";
	echo "  file: " . ( $file ?: '(none)' ) . "\n";

	if ( $file && file_exists( $file ) ) {
		$webp = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file );
		$avif = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $file );
		echo '  .webp exists: ' . ( $webp !== $file && file_exists( $webp ) ? 'YES (' . $webp . ')' : 'no' ) . "\n";
		echo '  .avif exists: ' . ( $avif !== $file && file_exists( $avif ) ? 'YES (' . $avif . ')' : 'no' ) . "\n";
	}

	global $wpdb;
	$meta_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND (meta_key LIKE %s OR meta_key LIKE %s)",
		$id, '%optim%', '%webp%'
	) );
	if ( $meta_rows ) {
		foreach ( $meta_rows as $row ) {
			$val = is_string( $row->meta_value ) && strlen( $row->meta_value ) > 200 ? substr( $row->meta_value, 0, 200 ) . '...' : $row->meta_value;
			echo "  postmeta [{$row->meta_key}] = $val\n";
		}
	} else {
		echo "  postmeta: (no optim/webp related keys found)\n";
	}
	echo "\n";
}

global $wpdb;
echo "--- recent action scheduler entries mentioning optim/image (last 15) ---\n";
$actions = $wpdb->get_results(
	"SELECT action_id, hook, status, scheduled_date_gmt FROM {$wpdb->prefix}actionscheduler_actions
	 WHERE hook LIKE '%optim%' OR hook LIKE '%image%'
	 ORDER BY action_id DESC LIMIT 15"
);
if ( $actions ) {
	foreach ( $actions as $a ) {
		echo "  #{$a->action_id} hook={$a->hook} status={$a->status} scheduled={$a->scheduled_date_gmt}\n";
	}
} else {
	echo "  (none found)\n";
}

echo "\nOK: read-only diagnostic complete\n";
