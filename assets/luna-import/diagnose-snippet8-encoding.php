<?php
global $wpdb;

$snippet_id = 8;
$snippets_table = $wpdb->prefix . 'snippets';

echo "=== STABILITY (3 reads via \$wpdb) ===\n";
for ( $i = 1; $i <= 3; $i++ ) {
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT code FROM $snippets_table WHERE id = %d", $snippet_id ),
		ARRAY_A
	);
	$code = $row['code'];
	echo "read #$i: strlen=" . strlen( $code ) . " mb_strlen(UTF-8)=" . mb_strlen( $code, 'UTF-8' ) . " md5=" . md5( $code ) . "\n";
	usleep( 300000 );
}

echo "\n=== ENCODING ANALYSIS (last read) ===\n";
$len = strlen( $code );
echo "byte length: $len\n";
echo "mb_strlen UTF-8: " . mb_strlen( $code, 'UTF-8' ) . "\n";
echo "mb_strlen ISO-8859-1 (should equal byte length): " . mb_strlen( $code, 'ISO-8859-1' ) . "\n";

$null_bytes = substr_count( $code, "\x00" );
echo "count of 0x00 (null) bytes in string: $null_bytes\n";

$high_bytes = 0;
for ( $i = 0; $i < $len; $i++ ) {
	if ( ord( $code[ $i ] ) > 127 ) {
		$high_bytes++;
	}
}
echo "count of bytes with value > 127 (non-ASCII): $high_bytes\n";

echo "\nfirst 60 bytes as hex:\n";
echo bin2hex( substr( $code, 0, 60 ) ) . "\n";

echo "\nlast 60 bytes as hex:\n";
echo bin2hex( substr( $code, -60 ) ) . "\n";

// Check for literal self-duplication: does the first half equal the second half?
$half = intdiv( $len, 2 );
$first_half = substr( $code, 0, $half );
$second_half = substr( $code, $half );
echo "\nfirst half length: " . strlen( $first_half ) . "\n";
echo "first half === (start of) second half: " . ( strpos( $second_half, substr( $first_half, 0, 100 ) ) === 0 ? 'YES (looks duplicated)' : 'NO' ) . "\n";

// Check DB column charset/collation for this table
echo "\n=== TABLE COLUMN INFO ===\n";
$col_info = $wpdb->get_row( "SHOW FULL COLUMNS FROM $snippets_table WHERE Field = 'code'", ARRAY_A );
echo "column 'code' definition: " . print_r( $col_info, true ) . "\n";

$table_status = $wpdb->get_row( "SHOW TABLE STATUS LIKE '$snippets_table'", ARRAY_A );
echo "table collation: " . ( $table_status['Collation'] ?? 'unknown' ) . "\n";

echo "\nwpdb charset: " . $wpdb->charset . "  wpdb collate: " . $wpdb->collate . "\n";

echo "\nOK: encoding diagnostic completed (no writes)\n";
