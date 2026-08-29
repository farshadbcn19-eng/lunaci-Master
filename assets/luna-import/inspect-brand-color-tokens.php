<?php
global $wpdb;
$table = $wpdb->prefix . 'snippets';

foreach ( array( 6, 7 ) as $id ) {
	$code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM `{$table}` WHERE id = %d", $id ) );
	if ( null === $code ) {
		echo "snippet {$id}: not found\n";
		continue;
	}
	echo "=== snippet {$id} ===\n";
	if ( preg_match( '/:root\s*\{[^}]*\}/s', $code, $m ) ) {
		echo "--- :root block ---\n";
		echo $m[0] . "\n\n";
	} else {
		echo "no :root block found\n";
	}
	// also grab any hex color literals used, deduped
	preg_match_all( '/#[0-9a-fA-F]{3,8}\b/', $code, $hexm );
	if ( $hexm[0] ) {
		echo "--- hex colors used (deduped) ---\n";
		echo implode( ', ', array_unique( $hexm[0] ) ) . "\n";
	}
	echo "\n";
}
