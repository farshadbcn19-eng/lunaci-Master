<?php
/**
 * READ-ONLY. While the user restores via hPanel, check whether Hostinger
 * also exposes backup files directly on the filesystem (common on shared
 * hosting - a JetBackup/R1Soft snapshot directory, or a cron-generated
 * .sql/.sql.gz dump) that could let us do a surgical single-row restore
 * of wp_postmeta (post_id=57, meta_key='_elementor_data') instead of a
 * full database rollback that would also revert live orders/content.
 * Lists candidate paths only - does not read or modify anything.
 */

$home = getenv( 'HOME' ) ?: '/home';
$candidates = array(
	$home . '/backups',
	$home . '/.backups',
	$home . '/backup',
	$home . '/db_backups',
	'/home/backups',
	$home . '/public_html/wp-content/backups',
	$home . '/public_html/wp-content/ai1wm-backups',
	$home . '/public_html/wp-content/updraft',
);

foreach ( $candidates as $dir ) {
	if ( is_dir( $dir ) ) {
		echo "FOUND directory: {$dir}\n";
		$files = @scandir( $dir );
		if ( $files ) {
			foreach ( array_slice( $files, 0, 20 ) as $f ) {
				if ( $f === '.' || $f === '..' ) {
					continue;
				}
				$full = $dir . '/' . $f;
				echo '  ' . $f . ' (' . ( is_dir( $full ) ? 'dir' : filesize( $full ) . ' bytes' ) . ', modified ' . date( 'Y-m-d H:i:s', filemtime( $full ) ) . ")\n";
			}
		}
	} else {
		echo "not found: {$dir}\n";
	}
}

echo "\n--- any *.sql / *.sql.gz anywhere under home (shallow scan, 3 levels) ---\n";
$found_sql = shell_exec( 'find ' . escapeshellarg( $home ) . ' -maxdepth 3 \\( -iname "*.sql" -o -iname "*.sql.gz" \\) 2>/dev/null | head -30' );
echo $found_sql ?: "(none found)\n";

echo "\nOK: read-only backup-location scan complete\n";
