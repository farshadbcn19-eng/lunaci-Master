<?php
global $wpdb;
$table = $wpdb->prefix . 'snippets';
$cols = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A );
if ( ! $cols ) {
	echo 'ABORT: DESCRIBE failed: ' . $wpdb->last_error . "\n";
	exit( 1 );
}
echo "--- columns ---\n";
foreach ( $cols as $c ) {
	echo "{$c['Field']} ({$c['Type']})\n";
}

echo "\n--- row id=6 (all columns) ---\n";
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", 6 ), ARRAY_A );
if ( ! $row ) {
	echo 'ABORT: row not found. last_error: ' . $wpdb->last_error . "\n";
	exit( 1 );
}
foreach ( $row as $k => $v ) {
	if ( 'code' === $k ) {
		echo "{$k}: (length=" . strlen( $v ) . ")\n";
	} else {
		echo "{$k}: {$v}\n";
	}
}

echo "\n--- code: first 200 chars ---\n";
echo substr( $row['code'], 0, 200 ) . "\n";
echo "\n--- code: last 500 chars ---\n";
echo substr( $row['code'], -500 ) . "\n";
echo "\n--- clearfix hints ---\n";
foreach ( array( ':before', ':after', 'clearfix', 'display:table', 'display: table' ) as $needle ) {
	echo $needle . ': ' . ( false !== strpos( $row['code'], $needle ) ? 'FOUND' : 'not found' ) . "\n";
}
