<?php
/**
 * READ-ONLY. Once /shop/ and /es/tienda/ 301-redirect into /products/ and
 * /es/productos/, they should also drop out of the AIOSEO XML sitemap
 * rather than sit there as redirected (non-200) URLs. Need the exact
 * wp_aioseo_posts column names/types for the robots override fields, and
 * their current values for posts 56 (EN shop) and 609 (ES tienda), so the
 * guarded fix sets exactly the right columns instead of guessing.
 */

global $wpdb;
$table = $wpdb->prefix . 'aioseo_posts';

echo "--- DESCRIBE {$table} (robots + canonical related columns only) ---\n";
$columns = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A );
foreach ( $columns as $col ) {
	if ( stripos( $col['Field'], 'robots' ) !== false || stripos( $col['Field'], 'canonical' ) !== false ) {
		echo "  {$col['Field']}  {$col['Type']}  default=" . var_export( $col['Default'], true ) . "\n";
	}
}

foreach ( array( 56 => 'EN /shop/', 609 => 'ES /es/tienda/' ) as $post_id => $label ) {
	echo "\n--- post_id={$post_id} ({$label}) current robots/canonical values ---\n";
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE post_id = %d", $post_id ), ARRAY_A );
	if ( ! $row ) {
		echo "  no row\n";
		continue;
	}
	foreach ( $row as $key => $value ) {
		if ( stripos( $key, 'robots' ) !== false || stripos( $key, 'canonical' ) !== false ) {
			echo "  {$key} = " . var_export( $value, true ) . "\n";
		}
	}
}

echo "\nOK: read-only diagnostic complete\n";
