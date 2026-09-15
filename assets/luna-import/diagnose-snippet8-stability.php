<?php
global $wpdb;

$snippet_id = 8;
$snippets_table = $wpdb->prefix . 'snippets';

for ( $i = 1; $i <= 3; $i++ ) {
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, active, LENGTH(code) AS code_len, MD5(code) AS code_md5 FROM $snippets_table WHERE id = %d", $snippet_id ),
		ARRAY_A
	);
	echo "read #$i: " . ( $row ? "len={$row['code_len']} md5={$row['code_md5']} active={$row['active']}" : 'NOT FOUND' ) . "\n";
	usleep( 300000 );
}

echo "\n--- direct file read of the same row via a fresh mysqli connection (bypass any object cache / persistent connection reuse) ---\n";
$creds_path = ABSPATH . 'wp-config.php';
echo "wp-config path: $creds_path (exists: " . ( file_exists( $creds_path ) ? 'yes' : 'no' ) . ")\n";
$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
if ( $mysqli->connect_error ) {
	echo "mysqli connect error: " . $mysqli->connect_error . "\n";
} else {
	$res = $mysqli->query( "SELECT id, active, LENGTH(code) AS code_len, MD5(code) AS code_md5 FROM $snippets_table WHERE id = $snippet_id" );
	$r = $res->fetch_assoc();
	echo "fresh-connection read: " . ( $r ? "len={$r['code_len']} md5={$r['code_md5']} active={$r['active']}" : 'NOT FOUND' ) . "\n";
	$mysqli->close();
}

echo "\nOK: stability diagnostic completed (no writes)\n";
