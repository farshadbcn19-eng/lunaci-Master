<?php
/**
 * 1. Code Snippet 6 (LUNACI Shop Design): the "All" / "Todos" filter button
 *    is removed from the category filter tabs.
 * 2. Page 836 "All Products" is moved to the trash (lunaci-seo.php item 16
 *    redirects /all-products/ to /products/).
 * 3. Code Snippet 7 (Lunaci Category Banners): the banner image gets
 *    height: 100% !important. WooCommerce's ".woocommerce img
 *    { height: auto }" won, so on mobile the image stayed 163 px inside a
 *    260 px box, leaving an empty band under it.
 * Direct writes to wp_snippets (exact, single-occurrence replacements;
 * the new snippet 6 code is syntax-checked with php -l first).
 *
 * LUNACI_MODE=dry-run | apply | rollback (backup: $LUNACI_BACKUP_DIR/all-products-banner-backup.json)
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$t  = $wpdb->prefix . 'snippets';
$bk = rtrim( $dir, '/' ) . '/all-products-banner-backup.json';

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $bk ), true );
	if ( ! is_array( $b ) || ! isset( $b['s6'], $b['s7'] ) ) {
		echo "ABORT: backup unreadable: $bk\n";
		exit( 1 );
	}
	$wpdb->update( $t, array( 'code' => $b['s6'] ), array( 'id' => 6 ) );
	$wpdb->update( $t, array( 'code' => $b['s7'] ), array( 'id' => 7 ) );
	if ( 'trash' === get_post_status( 836 ) ) {
		wp_untrash_post( 836 );
		wp_update_post( array( 'ID' => 836, 'post_status' => $b['page_status'] ) );
	}
	echo 'ROLLBACK: snippet 6 ' . ( $wpdb->get_var( "SELECT code FROM $t WHERE id=6" ) === $b['s6'] ? 'restored' : 'NOT restored' ) . ', snippet 7 ' . ( $wpdb->get_var( "SELECT code FROM $t WHERE id=7" ) === $b['s7'] ? 'restored' : 'NOT restored' ) . ', page 836 status ' . get_post_status( 836 ) . "\n";
	exit( 0 );
}

$fail = 0;
$s6   = (string) $wpdb->get_var( "SELECT code FROM $t WHERE id=6" );
$s7   = (string) $wpdb->get_var( "SELECT code FROM $t WHERE id=7" );
// 1. the 'all' tab entry: from "'all' => array(" to its closing "),".
$n6 = preg_match_all( "#\n[ \t]*'all'\s*=>\s*array\(.*?\n[ \t]*\),[ \t]*(?=\n)#s", $s6, $m6 );
echo "snippet 6: md5 " . md5( $s6 ) . ", 'all' tab blocks found: $n6\n";
if ( 1 !== $n6 || false === strpos( $m6[0][0], "'All'" ) || false === strpos( $m6[0][0], 'all-products' ) ) {
	echo "FAIL: expected exactly one 'all' tab block containing 'All' and all-products\n";
	$fail = 1;
} else {
	echo "  block to remove:\n" . $m6[0][0] . "\n";
}
$new6 = $n6 === 1 ? str_replace( $m6[0][0], '', $s6 ) : $s6;
// Syntax check of the new code (what php -l does), with PHP's own parser.
try {
	token_get_all( "<?php\n" . $new6, TOKEN_PARSE );
	echo "  new snippet 6 syntax: OK\n";
} catch ( \ParseError $e ) {
	echo '  new snippet 6 syntax: ERROR ' . $e->getMessage() . ' line ' . $e->getLine() . "\n";
	$fail = 1;
}
// 3. banner image height.
$old7 = "        height: 100%;\n        object-fit: cover;";
$new7 = "        height: 100% !important;\n        object-fit: cover;";
$n7   = substr_count( $s7, $old7 );
echo "snippet 7: md5 " . md5( $s7 ) . ", target rule found: $n7\n";
if ( 1 !== $n7 ) {
	$fail = 1;
}
// 2. page 836.
$p = get_post( 836 );
echo 'page 836: ' . ( $p ? "{$p->post_name} {$p->post_status}" : 'missing' ) . "\n";
if ( ! $p || 'all-products' !== $p->post_name ) {
	$fail = 1;
}
if ( $fail ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "PLAN: remove the All tab (snippet 6), trash page 836, banner image height !important (snippet 7)\nDRY-RUN OK\n";
	exit( 0 );
}

if ( false === file_put_contents( $bk, wp_json_encode( array( 's6' => $s6, 's7' => $s7, 'page_status' => $p->post_status ) ) ) ) {
	echo "ABORT: cannot write backup $bk\n";
	exit( 1 );
}
$bad = 0;
$wpdb->update( $t, array( 'code' => $new6 ), array( 'id' => 6 ) );
$ok = $wpdb->get_var( "SELECT code FROM $t WHERE id=6" ) === $new6;
echo 'snippet 6 updated: ' . ( $ok ? 'yes' : 'NO' ) . "\n";
$bad = $bad || ! $ok;
$n7c = str_replace( $old7, $new7, $s7 );
$wpdb->update( $t, array( 'code' => $n7c ), array( 'id' => 7 ) );
$ok = $wpdb->get_var( "SELECT code FROM $t WHERE id=7" ) === $n7c;
echo 'snippet 7 updated: ' . ( $ok ? 'yes' : 'NO' ) . "\n";
$bad = $bad || ! $ok;
$tr = wp_trash_post( 836 );
echo 'page 836 trashed: ' . ( $tr && 'trash' === get_post_status( 836 ) ? 'yes' : 'NO' ) . "\n";
$bad = $bad || 'trash' !== get_post_status( 836 );
wp_cache_flush();
echo $bad ? "APPLY FINISHED WITH ERRORS (rollback available)\n" : "APPLY OK\n";
exit( $bad ? 1 : 0 );
