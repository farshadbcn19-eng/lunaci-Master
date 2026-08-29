<?php
global $wpdb;
$table = $wpdb->prefix . 'snippets';
$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, code, scope, active FROM `{$table}` WHERE id = %d", 9 ), ARRAY_A );
if ( ! $row ) {
	echo 'ABORT: snippet id=9 not found. last_error: ' . $wpdb->last_error . "\n";
	exit( 1 );
}
echo "name: {$row['name']}\n";
echo "scope: {$row['scope']}\n";
echo "active: {$row['active']}\n";
echo 'code length: ' . strlen( $row['code'] ) . "\n";
echo "\n--- full code ---\n";
echo $row['code'] . "\n";
