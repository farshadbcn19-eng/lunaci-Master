<?php
/**
 * Read-only: find WPCode's own internal caching mechanism. Confirmed this
 * session: no WP object cache, LiteSpeed purge succeeds, yet the live
 * front-end still serves stale content for WPCode snippet 483 after direct
 * $wpdb->update() writes (verified via curl bypassing all browser cache).
 * WPCode is known to cache its compiled/active-snippet output (to avoid a
 * DB query on every page load); that cache likely only invalidates on the
 * normal save_post/admin-save hook path, which our raw SQL write bypassed.
 */

global $wpdb;

echo "--- wp_options rows matching '%wpcode%' ---\n";
$rows = $wpdb->get_results(
	"SELECT option_id, option_name, LENGTH(option_value) AS len, autoload FROM {$wpdb->options} WHERE option_name LIKE '%wpcode%' ORDER BY option_name",
	ARRAY_A
);
foreach ( $rows as $r ) {
	echo "option_id={$r['option_id']}  autoload={$r['autoload']}  len={$r['len']}  name=\"{$r['option_name']}\"\n";
}

echo "\n--- wp_options rows matching '%_transient%wpcode%' or '%_transient%snippet%' ---\n";
$rows2 = $wpdb->get_results(
	"SELECT option_id, option_name, LENGTH(option_value) AS len, autoload FROM {$wpdb->options}
	 WHERE option_name LIKE '%_transient%wpcode%' OR option_name LIKE '%_transient%snippet%'
	 ORDER BY option_name",
	ARRAY_A
);
if ( ! $rows2 ) {
	echo "none found\n";
} else {
	foreach ( $rows2 as $r ) {
		echo "option_id={$r['option_id']}  autoload={$r['autoload']}  len={$r['len']}  name=\"{$r['option_name']}\"\n";
	}
}

echo "\n--- Search active plugin path for WPCode ---\n";
$active_plugins = get_option( 'active_plugins' );
foreach ( (array) $active_plugins as $p ) {
	if ( false !== stripos( $p, 'wpcode' ) || false !== stripos( $p, 'insert-headers' ) ) {
		echo "active plugin: $p\n";
	}
}

echo "\n--- Registered hooks on save_post / wp_insert_post / updated_postmeta containing 'wpcode' or 'Wpcode' in callback ---\n";
global $wp_filter;
foreach ( array( 'save_post', 'save_post_wpcode', 'wp_insert_post', 'updated_postmeta', 'update_option', 'transition_post_status' ) as $hook ) {
	if ( ! isset( $wp_filter[ $hook ] ) ) {
		continue;
	}
	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$fn = $cb['function'];
			$label = '';
			if ( is_array( $fn ) ) {
				$label = ( is_object( $fn[0] ) ? get_class( $fn[0] ) : $fn[0] ) . '::' . $fn[1];
			} elseif ( is_string( $fn ) ) {
				$label = $fn;
			} else {
				$label = 'Closure';
			}
			if ( false !== stripos( $label, 'wpcode' ) || false !== stripos( $label, 'Wpcode' ) ) {
				echo "hook=$hook priority=$priority callback=$label\n";
			}
		}
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
