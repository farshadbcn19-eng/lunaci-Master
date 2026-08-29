<?php
global $wpdb;
$table = $wpdb->prefix . 'snippets';

echo "expected table name: {$table}\n";

$like = $wpdb->esc_like( $table );
$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $like ) );
echo 'exact table exists: ' . ( $exists ? "yes ({$exists})" : 'NO' ) . "\n";

echo "\n--- all tables matching '%snippet%' ---\n";
$snippet_tables = $wpdb->get_col( "SHOW TABLES LIKE '%snippet%'" );
if ( $snippet_tables ) {
	foreach ( $snippet_tables as $t ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" );
		echo "{$t} -> row count: {$count}\n";
	}
} else {
	echo "none found\n";
}

echo "\n--- direct query on expected table ---\n";
$rows = $wpdb->get_results( "SELECT id, name FROM `{$table}`", ARRAY_A );
echo 'last_error: ' . ( $wpdb->last_error ? $wpdb->last_error : '(none)' ) . "\n";
echo 'rows returned: ' . ( is_array( $rows ) ? count( $rows ) : 'null (query failed)' ) . "\n";
if ( is_array( $rows ) ) {
	foreach ( $rows as $r ) {
		echo "  id={$r['id']} name=\"{$r['name']}\"\n";
	}
}

echo "\n--- active plugins ---\n";
$active = get_option( 'active_plugins' );
foreach ( (array) $active as $p ) {
	if ( false !== stripos( $p, 'snippet' ) || false !== stripos( $p, 'wpcode' ) || false !== stripos( $p, 'code-snippet' ) ) {
		echo "  {$p}\n";
	}
}
