<?php
/**
 * Spanish product category base: /es/categoria-producto/ instead of
 * /es/product-category/.
 *
 * WooCommerce Multilingual translates the category base from the string
 * "URL product_cat tax slug" (domain WordPress) and only uses a translation
 * whose status is complete (ICL_TM_COMPLETE). The Spanish translation
 * "categoria-producto" exists but is not used.
 *
 * LUNACI_MODE=dry-run   report string status, current links, rewrite rules
 * LUNACI_MODE=apply     mark the existing Spanish translation complete,
 *                       flush rewrite rules, verify
 * LUNACI_MODE=rollback  restore the status saved in LUNACI_BACKUP_DIR
 */

global $wpdb;

$mode = getenv( 'LUNACI_MODE' );
$dir  = rtrim( (string) getenv( 'LUNACI_BACKUP_DIR' ), '/' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || '' === $dir || ! is_dir( $dir ) ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and an existing LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$backup_file = $dir . '/es-category-base-backup.json';
$strings     = $wpdb->prefix . 'icl_strings';
$trans       = $wpdb->prefix . 'icl_string_translations';
$want        = 'categoria-producto';

$string = $wpdb->get_row( $wpdb->prepare( "SELECT id, value, language, context, name, status FROM $strings WHERE context = %s AND name = %s", 'WordPress', 'URL product_cat tax slug' ), ARRAY_A );
if ( ! $string ) {
	echo "ABORT: string 'URL product_cat tax slug' not found\n";
	exit( 1 );
}
$tr = $wpdb->get_row( $wpdb->prepare( "SELECT id, language, value, status, mo_string FROM $trans WHERE string_id = %d AND language = 'es'", $string['id'] ), ARRAY_A );

function lunaci_es_links() {
	$out = array();
	do_action( 'wpml_switch_language', 'es' );
	foreach ( array( 'labios', 'rostro', 'ojos', 'unas' ) as $slug ) {
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		$out[ $slug ] = $t ? get_term_link( $t, 'product_cat' ) : '(term not found)';
	}
	do_action( 'wpml_switch_language', null );
	return $out;
}

echo "--- state ---\n";
echo "string {$string['id']}: value={$string['value']} language={$string['language']} status={$string['status']}\n";
echo 'es translation: ' . ( $tr ? "id={$tr['id']} value={$tr['value']} status={$tr['status']} mo_string=" . var_export( $tr['mo_string'], true ) : 'none' ) . "\n";
echo 'ICL_TM_COMPLETE=' . ( defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : '?' ) . ' ICL_TM_NEEDS_UPDATE=' . ( defined( 'ICL_TM_NEEDS_UPDATE' ) ? ICL_TM_NEEDS_UPDATE : '?' ) . "\n";
echo 'translate_single_string(es): ' . apply_filters( 'wpml_translate_single_string', 'product-category', 'WordPress', 'URL product_cat tax slug', 'es' ) . "\n";
foreach ( lunaci_es_links() as $s => $l ) {
	echo "ES link $s: $l\n";
}
$rules = (array) get_option( 'rewrite_rules' );
$hits  = array_filter( array_keys( $rules ), function ( $k ) { return false !== strpos( $k, 'categoria-producto' ) || 0 === strpos( $k, 'product-category' ); } );
echo 'rewrite rules with categoria-producto / product-category: ' . implode( ' | ', array_slice( $hits, 0, 6 ) ) . "\n";

// ---------------------------------------------------------------- rollback
if ( 'rollback' === $mode ) {
	if ( ! is_readable( $backup_file ) ) {
		echo "ABORT: no backup at $backup_file\n";
		exit( 1 );
	}
	$b = json_decode( file_get_contents( $backup_file ), true );
	$wpdb->update( $trans, array( 'status' => (int) $b['status'], 'value' => $b['value'] ), array( 'id' => (int) $b['id'] ) );
	if ( class_exists( 'WPML\ST\TranslationFile\Manager' ) || function_exists( 'icl_update_string_status' ) ) {
		icl_update_string_status( (int) $string['id'] );
	}
	flush_rewrite_rules( false );
	wp_cache_flush();
	echo "restored translation {$b['id']} to status {$b['status']} value {$b['value']}\n";
	foreach ( lunaci_es_links() as $s => $l ) {
		echo "ES link $s: $l\n";
	}
	echo "ROLLBACK DONE\n";
	exit( 0 );
}

// ----------------------------------------------------------- preconditions
if ( 'product-category' !== $string['value'] || ! $tr || $want !== $tr['value'] ) {
	echo "ABORT: expected original 'product-category' with Spanish translation '$want'\n";
	exit( 1 );
}
if ( (int) $tr['status'] === (int) ICL_TM_COMPLETE ) {
	echo "NOTE: Spanish translation is already complete; the base is not applied for another reason - nothing written\n";
	exit( 'dry-run' === $mode ? 0 : 1 );
}
echo "OK: Spanish translation exists with status {$tr['status']} (needs " . ICL_TM_COMPLETE . ")\n";
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN: would set translation {$tr['id']} status to " . ICL_TM_COMPLETE . " and flush rewrite rules\n";
	exit( 0 );
}

// ------------------------------------------------------------------ apply
if ( false === file_put_contents( $backup_file, wp_json_encode( $tr ) ) ) {
	echo "ABORT: could not write $backup_file\n";
	exit( 1 );
}
echo "backup written: $backup_file\n";
$ok = $wpdb->update( $trans, array( 'status' => ICL_TM_COMPLETE ), array( 'id' => (int) $tr['id'] ) );
if ( false === $ok ) {
	echo "FAIL: update: {$wpdb->last_error}\n";
	exit( 1 );
}
if ( function_exists( 'icl_update_string_status' ) ) {
	icl_update_string_status( (int) $string['id'] );
}
wp_cache_flush();
flush_rewrite_rules( false );

echo "\n--- verify ---\n";
$tr2 = $wpdb->get_row( $wpdb->prepare( "SELECT status, value FROM $trans WHERE id = %d", $tr['id'] ), ARRAY_A );
echo "translation status now {$tr2['status']} value {$tr2['value']}\n";
echo 'translate_single_string(es): ' . apply_filters( 'wpml_translate_single_string', 'product-category', 'WordPress', 'URL product_cat tax slug', 'es' ) . "\n";
foreach ( lunaci_es_links() as $s => $l ) {
	echo "ES link $s: $l\n";
}
echo "APPLY DONE\n";
