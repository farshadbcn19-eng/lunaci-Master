<?php
global $wpdb;
$table = $wpdb->prefix . 'snippets';
$rows = $wpdb->get_results( "SELECT id, name, type, scope, active, LENGTH(code) AS code_len FROM {$table} ORDER BY id", ARRAY_A );
if ( ! $rows ) {
	echo "ABORT: no rows found in {$table} (or table missing)\n";
	exit( 1 );
}
foreach ( $rows as $r ) {
	echo "id={$r['id']} | name=\"{$r['name']}\" | type={$r['type']} | scope={$r['scope']} | active={$r['active']} | code_len={$r['code_len']}\n";
}
