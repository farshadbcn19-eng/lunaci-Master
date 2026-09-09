<?php
/**
 * READ-ONLY. No .webp/.avif files or postmeta appeared after the user
 * clicked "Bulk Optimize" in the Image Optimization plugin's admin UI.
 * Check wp_options for any job/queue/progress state the plugin may have
 * written when the click was registered (e.g. a bulk job id, a stats
 * counter, a "last run" timestamp) to determine whether the click
 * actually reached the server at all.
 */

global $wpdb;

echo "--- all image_optimizer* / image-optimization* options (name + short value) ---\n";
$rows = $wpdb->get_results(
	"SELECT option_name, option_value FROM {$wpdb->options}
	 WHERE option_name LIKE 'image_optimizer%' OR option_name LIKE '%image-optimization%' OR option_name LIKE '%img_optm%'
	 ORDER BY option_name"
);
foreach ( $rows as $row ) {
	$val = $row->option_value;
	if ( is_string( $val ) && strlen( $val ) > 300 ) {
		$val = substr( $val, 0, 300 ) . '... (truncated, ' . strlen( $row->option_value ) . ' bytes total)';
	}
	echo "  {$row->option_name} = $val\n";
}

echo "\n--- image_optimizer* transients ---\n";
$transients = $wpdb->get_results(
	"SELECT option_name, option_value FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_%image%optim%' OR option_name LIKE '_transient_%optim%image%'
	 ORDER BY option_name"
);
if ( $transients ) {
	foreach ( $transients as $row ) {
		$val = is_string( $row->option_value ) && strlen( $row->option_value ) > 300 ? substr( $row->option_value, 0, 300 ) . '...' : $row->option_value;
		echo "  {$row->option_name} = $val\n";
	}
} else {
	echo "  (none found)\n";
}

echo "\n--- last 5 entries in PHP error log (if readable), filtered for 'image' or 'optim' ---\n";
$log_candidates = array(
	WP_CONTENT_DIR . '/debug.log',
	ini_get( 'error_log' ),
);
foreach ( $log_candidates as $log ) {
	if ( $log && file_exists( $log ) && is_readable( $log ) ) {
		echo "  reading: $log\n";
		$lines = file( $log );
		$matches = array_filter( $lines, function ( $l ) {
			return stripos( $l, 'image' ) !== false || stripos( $l, 'optim' ) !== false;
		} );
		$matches = array_slice( $matches, -5 );
		foreach ( $matches as $m ) {
			echo '    ' . trim( $m ) . "\n";
		}
	}
}

echo "\nOK: read-only diagnostic complete\n";
