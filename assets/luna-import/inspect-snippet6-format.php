<?php
global $wpdb;
$table = $wpdb->prefix . 'snippets';
$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, description, type, scope, code, LENGTH(code) AS code_len FROM {$table} WHERE id = %d", 6 ), ARRAY_A );
if ( ! $row ) {
	echo "ABORT: snippet id=6 not found\n";
	exit( 1 );
}
echo "id: {$row['id']}\n";
echo "name: {$row['name']}\n";
echo "type: {$row['type']}\n";
echo "scope: {$row['scope']}\n";
echo "code_len: {$row['code_len']}\n";
echo "--- first 200 chars ---\n";
echo substr( $row['code'], 0, 200 ) . "\n";
echo "--- last 400 chars ---\n";
echo substr( $row['code'], -400 ) . "\n";
echo "--- contains 'ul.products' clearfix hints ---\n";
foreach ( array( ':before', ':after', 'clearfix', 'display:table', 'display: table' ) as $needle ) {
	echo $needle . ': ' . ( false !== strpos( $row['code'], $needle ) ? 'FOUND' : 'not found' ) . "\n";
}
