<?php
/**
 * Protection of the Spanish Gutenberg pages (shipping, returns, terms,
 * privacy) against a WPML rebuild from the English original: turns on
 * lunaci-seo.php item 12 (option lunaci_es_post_guard).
 *
 * dry-run  - preconditions: WPML pairs, the Spanish pages are Spanish today,
 *            the guard's English detection on each English original and
 *            Spanish page. Nothing written.
 * apply    - writes the backup JSON, turns the guard on, then runs the
 *            wp_insert_post_data filter with the English original for each
 *            Spanish page (must come back as the current Spanish content and
 *            title) and with a Spanish edit (must pass through). The test
 *            writes no post; its log entries are removed afterwards.
 * rollback - restores the option from $LUNACI_BACKUP_DIR/es-legal-guard-backup.json
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
if ( ! function_exists( 'lunaci_seo_es_post_guard_blocks' ) ) {
	echo "ABORT: lunaci-seo.php item 12 not loaded\n";
	exit( 1 );
}
$bk    = rtrim( $dir, '/' ) . '/es-legal-guard-backup.json';
$tr    = $wpdb->prefix . 'icl_translations';
$pairs = array( 759 => 765, 760 => 766, 676 => 768, 3 => 769 ); // EN => ES

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $bk ), true );
	if ( ! is_array( $b ) || ! array_key_exists( 'option', $b ) ) {
		echo "ABORT: backup unreadable: $bk\n";
		exit( 1 );
	}
	if ( null === $b['option'] ) {
		delete_option( 'lunaci_es_post_guard' );
	} else {
		update_option( 'lunaci_es_post_guard', $b['option'], false );
	}
	echo 'ROLLBACK OK: lunaci_es_post_guard = ' . wp_json_encode( get_option( 'lunaci_es_post_guard', null ) ) . "\n";
	exit( 0 );
}

$fail = 0;
$md5  = array();
foreach ( $pairs as $en_id => $es_id ) {
	$en_p = get_post( $en_id );
	$es_p = get_post( $es_id );
	$trid = $wpdb->get_row( $wpdb->prepare( "SELECT a.trid a_trid, a.language_code a_lang, b.trid b_trid, b.language_code b_lang FROM $tr a, $tr b WHERE a.element_id=%d AND a.element_type='post_page' AND b.element_id=%d AND b.element_type='post_page'", $en_id, $es_id ) );
	$ok_pair = $en_p && $es_p && 'publish' === $es_p->post_status && $trid && $trid->a_trid === $trid->b_trid && 'en' === $trid->a_lang && 'es' === $trid->b_lang;
	list( $een, $ees ) = lunaci_seo_es_text_counts( $en_p ? $en_p->post_content : '' );
	list( $sen, $ses ) = lunaci_seo_es_text_counts( $es_p ? $es_p->post_content : '' );
	$ok_en = $en_p && lunaci_seo_es_post_guard_blocks( $en_p->post_content );
	$ok_es = $es_p && ! lunaci_seo_es_post_guard_blocks( $es_p->post_content ) && $ses > 10;
	$md5[ $es_id ] = $es_p ? md5( $es_p->post_content . "\0" . $es_p->post_title ) : '';
	printf(
		"%s ES %d /%s/ <- EN %d /%s/ | pair=%s | EN text en=%d es=%d detected-as-English=%s | ES text en=%d es=%d Spanish=%s | md5 %s\n",
		( $ok_pair && $ok_en && $ok_es ) ? 'OK  ' : 'FAIL',
		$es_id, $es_p ? $es_p->post_name : '-', $en_id, $en_p ? $en_p->post_name : '-',
		$ok_pair ? 'yes' : 'NO', $een, $ees, $ok_en ? 'yes' : 'NO', $sen, $ses, $ok_es ? 'yes' : 'NO', $md5[ $es_id ]
	);
	$fail |= ! ( $ok_pair && $ok_en && $ok_es );
}
$prev = get_option( 'lunaci_es_post_guard', null );
echo 'lunaci_es_post_guard now: ' . wp_json_encode( $prev ) . "\n";
if ( $fail ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "PLAN: backup JSON, lunaci_es_post_guard = on, filter test on the 4 pages (no post writes)\n";
	echo "DRY-RUN OK\n";
	exit( 0 );
}

// apply
if ( false === file_put_contents( $bk, wp_json_encode( array( 'option' => $prev, 'md5' => $md5 ) ) ) ) {
	echo "ABORT: cannot write backup $bk\n";
	exit( 1 );
}
echo "backup: $bk\n";
update_option( 'lunaci_es_post_guard', 'on', false );
$log_before = (array) get_option( 'lunaci_es_guard_log', array() );

foreach ( $pairs as $en_id => $es_id ) {
	$en_p = get_post( $en_id );
	$es_p = get_post( $es_id );
	$base = array( 'post_type' => 'page', 'post_status' => 'publish' );
	// English rebuild: must come back as the current Spanish content and title.
	$data = $base + array( 'post_content' => wp_slash( $en_p->post_content ), 'post_title' => wp_slash( $en_p->post_title ) );
	$out  = apply_filters( 'wp_insert_post_data', $data, array( 'ID' => $es_id ), array( 'ID' => $es_id ), true );
	$ok1  = wp_unslash( $out['post_content'] ) === $es_p->post_content && wp_unslash( $out['post_title'] ) === $es_p->post_title;
	// Spanish edit: must pass through unchanged.
	$edit = $es_p->post_content . "\n<!-- lunaci guard test -->";
	$data = $base + array( 'post_content' => wp_slash( $edit ), 'post_title' => wp_slash( $es_p->post_title ) );
	$out  = apply_filters( 'wp_insert_post_data', $data, array( 'ID' => $es_id ), array( 'ID' => $es_id ), true );
	$ok2  = wp_unslash( $out['post_content'] ) === $edit;
	printf( "%s guard test ES %d: English rebuild kept Spanish=%s, Spanish edit passes=%s\n", ( $ok1 && $ok2 ) ? 'PASS' : 'FAIL', $es_id, $ok1 ? 'yes' : 'NO', $ok2 ? 'yes' : 'NO' );
	$fail |= ! ( $ok1 && $ok2 );
}
update_option( 'lunaci_es_guard_log', $log_before, false ); // drop the test's log entries

// No post may have changed.
foreach ( $pairs as $es_id ) {
	clean_post_cache( $es_id );
	$p = get_post( $es_id );
	if ( md5( $p->post_content . "\0" . $p->post_title ) !== $md5[ $es_id ] ) {
		echo "FAIL: ES $es_id changed during the test\n";
		$fail = 1;
	}
}
if ( $fail ) {
	if ( null === $prev ) {
		delete_option( 'lunaci_es_post_guard' );
	} else {
		update_option( 'lunaci_es_post_guard', $prev, false );
	}
	echo "APPLY FAILED: guard option restored to " . wp_json_encode( $prev ) . "\n";
	exit( 1 );
}
echo "APPLY OK: lunaci_es_post_guard = on, 4 pages unchanged\n";
