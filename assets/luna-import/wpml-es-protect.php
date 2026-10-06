<?php
/**
 * Permanent protection of the Spanish Elementor pages against WPML rebuilding
 * them from English (incident 2026-10-05).
 *
 * 1. WPML page-builder strings: for each EN/ES pair the string's original
 *    value is set to the live English widget and the live Spanish widget is
 *    registered as its complete Spanish translation, so any WPML rebuild of
 *    the translation produces the Spanish page.
 * 2. Option lunaci_es_guard = on (lunaci-seo.php item 9): writes of mostly
 *    English _elementor_data to the Spanish pages are refused and logged.
 * 3. Controlled test: an update_post_meta() of English data to post 680 must
 *    be refused and leave the page byte-identical (restored at once if not).
 *
 * LUNACI_MODE=dry-run | apply | rollback (rollback: $LUNACI_BACKUP_DIR/wpml-es-protect-backup.json)
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
if ( ! function_exists( 'lunaci_seo_es_guard_blocks' ) ) {
	echo "ABORT: lunaci-seo.php item 9 not loaded\n";
	exit( 1 );
}
$st   = $wpdb->prefix . 'icl_strings';
$stt  = $wpdb->prefix . 'icl_string_translations';
$bk   = rtrim( $dir, '/' ) . '/wpml-es-protect-backup.json';
// string id => array( EN post, ES post, widget id )
$pairs = array(
	125 => array( 57, 772, '9b0a463' ),
	126 => array( 59, 680, 'ce307e5' ),
	136 => array( 60, 770, 'eca166b' ),
	137 => array( 61, 771, '296bd28' ),
);

function lunaci_p_html( $post, $wid ) {
	global $wpdb;
	$raw  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_elementor_data'", $post ) );
	$html = null;
	$walk = function ( $els ) use ( &$walk, &$html, $wid ) {
		foreach ( (array) $els as $el ) {
			if ( ( $el['id'] ?? '' ) === $wid ) {
				$html = (string) ( $el['settings']['html'] ?? '' );
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	$walk( json_decode( $raw, true ) );
	return $html;
}
function lunaci_p_wrap( $html ) {
	return wp_json_encode( array( array( 'id' => 'x', 'elType' => 'widget', 'widgetType' => 'html', 'settings' => array( 'html' => $html ) ) ) );
}

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $bk ), true );
	if ( ! is_array( $b ) || ! isset( $b['strings'] ) ) {
		echo "ABORT: no backup at $bk\n";
		exit( 1 );
	}
	foreach ( $b['strings'] as $sid => $row ) {
		$wpdb->update( $st, array( 'value' => $row['value'], 'status' => $row['status'] ), array( 'id' => (int) $sid ) );
		$wpdb->delete( $stt, array( 'string_id' => (int) $sid, 'language' => 'es' ) );
		if ( ! empty( $row['es'] ) ) {
			$wpdb->insert( $stt, $row['es'] );
		}
		echo "string $sid restored\n";
	}
	if ( null === $b['guard'] ) {
		delete_option( 'lunaci_es_guard' );
	} else {
		update_option( 'lunaci_es_guard', $b['guard'], true );
	}
	wp_cache_flush();
	echo "ROLLBACK DONE\n";
	exit( 0 );
}

if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$stt'" ) ) {
	echo "ABORT: WPML String Translation tables not found\n";
	exit( 1 );
}
$cols = $wpdb->get_col( "SHOW COLUMNS FROM $stt" );
echo 'icl_string_translations columns: ' . implode( ',', $cols ) . "\n";
$fail = 0;
$plan = array();
foreach ( $pairs as $sid => $p ) {
	list( $en_id, $es_id, $wid ) = $p;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, context, name, value, status FROM $st WHERE id=%d", $sid ), ARRAY_A );
	$en  = lunaci_p_html( $en_id, $wid );
	$es  = lunaci_p_html( $es_id, $wid );
	if ( ! $row || 'elementor-' . $en_id !== $row['context'] || 'html-html-' . $wid !== $row['name'] || null === $en || null === $es ) {
		echo "FAIL string $sid: row/context/widget mismatch\n";
		$fail = 1;
		continue;
	}
	list( $een, $ees ) = lunaci_seo_es_guard_counts( lunaci_p_wrap( $en ) );
	list( $sen, $ses ) = lunaci_seo_es_guard_counts( lunaci_p_wrap( $es ) );
	$ok_lang = lunaci_seo_es_guard_blocks( lunaci_p_wrap( $en ) ) && ! lunaci_seo_es_guard_blocks( lunaci_p_wrap( $es ) ) && $ses >= 20;
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $stt WHERE string_id=%d AND language='es'", $sid ), ARRAY_A );
	echo "string $sid {$row['name']} (EN $en_id en=$een es=$ees | ES $es_id en=$sen es=$ses) status={$row['status']} es_translation=" . ( $existing ? 'exists' : 'none' ) . ' -> ' . ( $ok_lang ? 'OK' : 'LANGUAGE CHECK FAILED' ) . "\n";
	if ( ! $ok_lang ) {
		$fail = 1;
		continue;
	}
	$plan[ $sid ] = array( 'row' => $row, 'en' => $en, 'es' => $es, 'existing' => $existing, 'es_id' => $es_id );
}
echo 'guard option now: ' . get_option( 'lunaci_es_guard', '(unset)' ) . "\n";
// WPML's own page-builder package API: which Spanish translation would a
// rebuild of each Spanish page use?
foreach ( $pairs as $sid => $p ) {
	$pkg = array( 'kind' => 'Elementor', 'kind_slug' => 'elementor', 'name' => (string) $p[0], 'title' => 'Page Builder Page ' . $p[0] );
	$tr  = apply_filters( 'wpml_get_translated_strings', array(), $pkg );
	$es  = $tr[ 'html-html-' . $p[2] ]['es'] ?? null;
	$cur = lunaci_p_html( $p[1], $p[2] );
	echo "WPML package elementor-{$p[0]} string html-html-{$p[2]}: es translation " . ( null === $es ? 'NOT returned' : 'status=' . ( $es['status'] ?? '?' ) . ' ' . ( ( $es['value'] ?? null ) === $cur ? 'equals the live Spanish page' : 'differs from the live Spanish page' ) ) . "\n";
}
echo 'guard filter priority: ' . ( has_filter( 'update_post_metadata' ) ? 'registered' : 'missing' ) . "\n";
if ( $fail || count( $plan ) !== 4 ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
echo "PLAN: 4 strings get their live English original and the live Spanish widget as complete Spanish translation; guard on; controlled guard test on post 680\n";
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}

$b = array( 'strings' => array(), 'guard' => get_option( 'lunaci_es_guard', null ) );
foreach ( $plan as $sid => $p ) {
	$b['strings'][ $sid ] = array( 'value' => $p['row']['value'], 'status' => $p['row']['status'], 'es' => $p['existing'] );
}
if ( false === file_put_contents( $bk, wp_json_encode( $b ) ) ) {
	echo "ABORT: cannot write $bk\n";
	exit( 1 );
}
echo "backup: $bk\n";
$now = current_time( 'mysql', true );
foreach ( $plan as $sid => $p ) {
	$wpdb->update( $st, array( 'value' => $p['en'], 'status' => 10 ), array( 'id' => $sid ) );
	$data = array( 'string_id' => $sid, 'language' => 'es', 'status' => 10, 'value' => $p['es'] );
	if ( in_array( 'translation_date', $cols, true ) ) {
		$data['translation_date'] = $now;
	}
	if ( $p['existing'] ) {
		$wpdb->update( $stt, $data, array( 'id' => $p['existing']['id'] ) );
	} else {
		$wpdb->insert( $stt, $data );
	}
}
update_option( 'lunaci_es_guard', 'on', true );
wp_cache_flush();

$bad = 0;
foreach ( $plan as $sid => $p ) {
	$v = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM $stt WHERE string_id=%d AND language='es' AND status=10", $sid ) );
	$o = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM $st WHERE id=%d AND status=10", $sid ) );
	$ok = $v === $p['es'] && $o === $p['en'];
	$bad = $bad || ! $ok;
	echo "VERIFY string $sid: " . ( $ok ? 'English original and Spanish translation stored, status complete' : 'FAIL' ) . "\n";
}

// Controlled guard test: try to overwrite post 680 with English data through
// the normal WordPress API (what WPML does). It must be refused.
$before = (string) $wpdb->get_var( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=680 AND meta_key='_elementor_data'" );
$english = (string) $wpdb->get_var( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=59 AND meta_key='_elementor_data'" );
$log_n  = count( (array) get_option( 'lunaci_es_guard_log', array() ) );
$res    = update_post_meta( 680, '_elementor_data', wp_slash( $english ) );
wp_cache_delete( 680, 'post_meta' );
$after  = (string) $wpdb->get_var( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=680 AND meta_key='_elementor_data'" );
if ( $after !== $before ) {
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $before ), array( 'post_id' => 680, 'meta_key' => '_elementor_data' ) );
	wp_cache_delete( 680, 'post_meta' );
	echo "GUARD TEST FAIL: English write went through (post 680 restored from the value read just before)\n";
	$bad = 1;
} else {
	$log = (array) get_option( 'lunaci_es_guard_log', array() );
	echo 'GUARD TEST OK: update_post_meta() with English data returned ' . var_export( $res, true ) . ', post 680 unchanged, log entries ' . $log_n . ' -> ' . count( $log ) . "\n";
	// The test entry is not a real incident.
	array_pop( $log );
	update_option( 'lunaci_es_guard_log', $log, false );
}
// A Spanish value must still be accepted by the guard.
echo 'guard decision for the live Spanish page data: ' . ( lunaci_seo_es_guard_blocks( $before ) ? 'BLOCK (wrong)' : 'allow' ) . "\n";
if ( lunaci_seo_es_guard_blocks( $before ) ) {
	$bad = 1;
}
// WPML's own translation lookup for the front page string, if available.
$pkg = array( 'kind' => 'Elementor', 'name' => '57', 'title' => 'Page Builder Page 57', 'edit_link' => '', 'view_link' => '' );
$tr  = apply_filters( 'wpml_translate_string', $plan[125]['en'], 'html-html-9b0a463', $pkg, 'es' );
echo 'WPML lookup (wpml_translate_string) for the front page widget in es: ' . ( $tr === $plan[125]['es'] ? 'returns the Spanish widget' : ( $tr === $plan[125]['en'] ? 'returns English (lookup not resolved by this filter)' : 'other (' . strlen( (string) $tr ) . ' bytes)' ) ) . "\n";
echo $bad ? "APPLY FINISHED WITH ERRORS\n" : "APPLY DONE\n";
exit( $bad ? 1 : 0 );
