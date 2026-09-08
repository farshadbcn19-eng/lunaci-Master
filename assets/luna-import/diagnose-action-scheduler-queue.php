<?php
/**
 * READ-ONLY. The image optimizer plugin hooks into
 * wp_generate_attachment_metadata (modules/optimization/components/
 * upload-optimization.php:116) and bundles Action Scheduler (WooCommerce's
 * async task-queue library). Calling wp_generate_attachment_metadata()
 * directly in fix-homepage-images-trigger-optimizer.php produced zero
 * .webp files and zero optimizer postmeta - the likely explanation is
 * the hook just enqueues an Action Scheduler job rather than optimizing
 * synchronously, and nothing has run that queue yet. Check whether jobs
 * for the 7 homepage attachments are actually sitting in the queue.
 */

global $wpdb;

$table = $wpdb->prefix . 'actionscheduler_actions';
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
echo "actionscheduler_actions table exists: " . ( $table_exists ? 'yes' : 'no' ) . "\n";

if ( $table_exists ) {
	echo "\n--- pending/recent actions with hook LIKE '%optim%' or '%image%' ---\n";
	$rows = $wpdb->get_results(
		"SELECT action_id, hook, status, scheduled_date_gmt, args
		 FROM {$table}
		 WHERE hook LIKE '%optim%' OR hook LIKE '%image%'
		 ORDER BY scheduled_date_gmt DESC
		 LIMIT 30",
		ARRAY_A
	);
	if ( $rows ) {
		foreach ( $rows as $row ) {
			echo "  id={$row['action_id']} hook={$row['hook']} status={$row['status']} scheduled={$row['scheduled_date_gmt']} args=" . substr( (string) $row['args'], 0, 150 ) . "\n";
		}
	} else {
		echo "  (none found)\n";
	}

	echo "\n--- total pending actions (any hook) ---\n";
	$pending_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
	echo "  pending: {$pending_count}\n";

	echo "\n--- all distinct hooks currently in the table ---\n";
	$hooks = $wpdb->get_col( "SELECT DISTINCT hook FROM {$table} LIMIT 50" );
	foreach ( $hooks as $h ) {
		echo "  {$h}\n";
	}
}

echo "\n--- is the 'action-scheduler' WP-CLI command available? ---\n";
$output = shell_exec( 'wp help action-scheduler 2>&1' );
echo $output ? $output : "(no output / command not found)\n";

echo "\nOK: read-only diagnostic complete\n";
