<?php
/**
 * READ-ONLY. wp_generate_attachment_metadata() did NOT trigger the
 * Hostinger Image Optimization plugin (confirmed: standard WP thumbnail
 * sizes regenerated, but zero .webp files and zero optimizer postmeta
 * appeared). Rather than guess again, grep the plugin's own source for
 * every add_action()/add_filter() call and every wp_schedule*() cron
 * registration, to find the real trigger mechanism.
 */

$plugin_dir = WP_PLUGIN_DIR . '/image-optimization';

if ( ! is_dir( $plugin_dir ) ) {
	echo "plugin directory not found at {$plugin_dir}\n";
	exit;
}

function lunaci_scan_dir_for_php( string $dir, int $max_depth, int $depth = 0 ): array {
	if ( $depth > $max_depth ) {
		return array();
	}
	$files = array();
	$entries = scandir( $dir );
	foreach ( $entries as $entry ) {
		if ( $entry === '.' || $entry === '..' ) {
			continue;
		}
		$path = $dir . '/' . $entry;
		if ( is_dir( $path ) ) {
			$files = array_merge( $files, lunaci_scan_dir_for_php( $path, $max_depth, $depth + 1 ) );
		} elseif ( substr( $entry, -4 ) === '.php' ) {
			$files[] = $path;
		}
	}
	return $files;
}

$php_files = lunaci_scan_dir_for_php( $plugin_dir, 4 );
echo 'total .php files found: ' . count( $php_files ) . "\n";

$hook_lines = array();
$cron_lines = array();
foreach ( $php_files as $file ) {
	$lines = file( $file );
	if ( ! $lines ) {
		continue;
	}
	foreach ( $lines as $line_no => $line ) {
		if ( preg_match( '/add_action\s*\(\s*[\'"]([a-zA-Z0-9_\/\-]+)[\'"]/', $line, $m ) ) {
			$hook = $m[1];
			if ( stripos( $hook, 'attach' ) !== false || stripos( $hook, 'upload' ) !== false || stripos( $hook, 'optim' ) !== false || stripos( $hook, 'media' ) !== false || stripos( $hook, 'cron' ) !== false ) {
				$hook_lines[] = str_replace( $plugin_dir . '/', '', $file ) . ':' . ( $line_no + 1 ) . ' -> ' . $hook;
			}
		}
		if ( preg_match( '/wp_schedule_(single_)?event\s*\(/', $line ) || preg_match( '/register_activation_hook/', $line ) ) {
			$cron_lines[] = str_replace( $plugin_dir . '/', '', $file ) . ':' . ( $line_no + 1 ) . ' -> ' . trim( $line );
		}
	}
}

echo "\n--- relevant add_action() hooks (attach/upload/optim/media/cron) ---\n";
foreach ( array_slice( $hook_lines, 0, 40 ) as $l ) {
	echo "  {$l}\n";
}
echo '  (total matches: ' . count( $hook_lines ) . ")\n";

echo "\n--- cron scheduling references ---\n";
foreach ( array_slice( $cron_lines, 0, 20 ) as $l ) {
	echo "  {$l}\n";
}
echo '  (total matches: ' . count( $cron_lines ) . ")\n";

echo "\n--- currently scheduled cron events containing 'optim' or 'image' ---\n";
$crons = _get_cron_array();
if ( is_array( $crons ) ) {
	foreach ( $crons as $timestamp => $hooks ) {
		foreach ( $hooks as $hook_name => $events ) {
			if ( stripos( $hook_name, 'optim' ) !== false || stripos( $hook_name, 'image' ) !== false ) {
				echo '  ' . $hook_name . ' @ ' . date( 'Y-m-d H:i:s', $timestamp ) . "\n";
			}
		}
	}
}

echo "\nOK: read-only diagnostic complete\n";
