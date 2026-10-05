<?php
/**
 * Spanish product categories (WPML).
 *
 * The four product categories had no Spanish translation: the 13 Spanish
 * products sat in the English terms, Spanish pages showed "Lips", there
 * were no /es/categoria-producto/ archives and no hreflang pair.
 *
 * LUNACI_MODE=dry-run  report only
 * LUNACI_MODE=apply    1. create Labios / Rostro / Ojos / Uñas as WPML
 *                         translations of Lips / Face / Eyes / Nails
 *                      2. move each Spanish product to the Spanish term
 *                      3. snippet 7 (category banners): same banner for
 *                         the Spanish slugs
 *                      4. snippet 6 (filter bar): labels and links follow
 *                         the page language (English output unchanged)
 * LUNACI_MODE=rollback restore from LUNACI_BACKUP_DIR/es-categories-backup.json
 *
 * Writes es-categories-backup.json to LUNACI_BACKUP_DIR before any change.
 */

global $wpdb;

$mode = getenv( 'LUNACI_MODE' );
$dir  = rtrim( (string) getenv( 'LUNACI_BACKUP_DIR' ), '/' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || '' === $dir || ! is_dir( $dir ) ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and an existing LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$backup_file = $dir . '/es-categories-backup.json';
$snippets    = $wpdb->prefix . 'snippets';

// English term id => [ English slug, Spanish name, Spanish slug ].
$map = array(
	29 => array( 'lips', 'Labios', 'labios' ),
	30 => array( 'face', 'Rostro', 'rostro' ),
	84 => array( 'eyes', 'Ojos', 'ojos' ),
	31 => array( 'nails', 'Uñas', 'unas' ),
);

$tabs_old_re = "#\\\$lunaci_tabs = array\\(\\s*'all'\\s*=>.*?'nails'\\s*=>\\s*array\\(.*?\\),\\s*\\);#s";
$tabs_new    = <<<'PHP'
$lunaci_es   = ( 'es' === apply_filters( 'wpml_current_language', null ) );
    $lunaci_tabs = array(
        'all' => array(
            'label'  => $lunaci_es ? 'Todos' : 'All',
            'url'    => $lunaci_es ? apply_filters( 'wpml_permalink', home_url( '/productos/' ), 'es' ) : home_url( '/all-products/' ),
            'active' => ( is_shop() || is_page( 'all-products' ) ),
        ),
    );
    // English term id, English label, Spanish label (lunaci-master es-categories.php).
    foreach ( array( 'face' => array( 30, 'Face', 'Rostro' ), 'lips' => array( 29, 'Lips', 'Labios' ), 'eyes' => array( 84, 'Eyes', 'Ojos' ), 'nails' => array( 31, 'Nails', 'Uñas' ) ) as $lunaci_key => $lunaci_cat ) {
        $lunaci_id = (int) apply_filters( 'wpml_object_id', $lunaci_cat[0], 'product_cat', true );
        $lunaci_tabs[ $lunaci_key ] = array(
            'label'  => $lunaci_es ? $lunaci_cat[2] : $lunaci_cat[1],
            'url'    => get_term_link( $lunaci_id, 'product_cat' ),
            'active' => is_product_category() && (int) get_queried_object_id() === $lunaci_id,
        );
    }
PHP;
$banner_re  = "#('nails'\\s*=>\\s*832\\s*,)#";
$banner_add = "\n        // Spanish category slugs (lunaci-master es-categories.php).\n        'rostro' => 824,\n        'labios' => 828,\n        'ojos'   => 826,\n        'unas'   => 832,";

function lunaci_es_term_id( $en_id ) {
	$es = apply_filters( 'wpml_object_id', $en_id, 'product_cat', false, 'es' );
	return ( $es && (int) $es !== (int) $en_id ) ? (int) $es : 0;
}

function lunaci_es_products() {
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = 'post_product' AND language_code = 'es'" );
	return array_values( array_filter( array_map( 'intval', $ids ), function ( $id ) {
		$p = get_post( $id );
		return $p && 'product' === $p->post_type && 'trash' !== $p->post_status;
	} ) );
}

function lunaci_cat_ids( $pid ) {
	return array_map( 'intval', wp_get_object_terms( $pid, 'product_cat', array( 'fields' => 'ids' ) ) );
}

// ---------------------------------------------------------------- rollback
if ( 'rollback' === $mode ) {
	if ( ! is_readable( $backup_file ) ) {
		echo "ABORT: no backup at $backup_file\n";
		exit( 1 );
	}
	$b = json_decode( file_get_contents( $backup_file ), true );
	foreach ( array( 6, 7 ) as $sid ) {
		if ( isset( $b['snippets'][ $sid ] ) ) {
			$wpdb->update( $snippets, array( 'code' => $b['snippets'][ $sid ] ), array( 'id' => $sid ) );
			echo "snippet $sid restored\n";
		}
	}
	foreach ( (array) $b['product_cats'] as $pid => $cats ) {
		wp_set_object_terms( (int) $pid, array_map( 'intval', $cats ), 'product_cat', false );
		echo "product $pid cats restored: " . implode( ',', $cats ) . "\n";
	}
	foreach ( $map as $en_id => $m ) {
		$es_id = lunaci_es_term_id( $en_id );
		if ( $es_id && in_array( $es_id, (array) $b['created_terms'], true ) ) {
			wp_delete_term( $es_id, 'product_cat' );
			echo "deleted ES term $es_id ({$m[1]})\n";
		}
	}
	wp_cache_flush();
	echo "ROLLBACK DONE\n";
	exit( 0 );
}

// ----------------------------------------------------------- preconditions
echo "--- preconditions ---\n";
$ok = true;
foreach ( $map as $en_id => $m ) {
	$t = get_term( $en_id, 'product_cat' );
	if ( ! $t || is_wp_error( $t ) || $t->slug !== $m[0] ) {
		echo "FAIL: EN term $en_id is not '{$m[0]}'\n";
		$ok = false;
		continue;
	}
	$es = lunaci_es_term_id( $en_id );
	echo "EN $en_id {$m[0]} -> ES " . ( $es ? "exists ($es)" : 'missing' ) . "\n";
	if ( $es ) {
		echo "FAIL: Spanish translation already exists for {$m[0]}\n";
		$ok = false;
	}
	$clash = get_term_by( 'slug', $m[2], 'product_cat' );
	if ( $clash ) {
		echo "FAIL: slug {$m[2]} already used by term {$clash->term_id}\n";
		$ok = false;
	}
}
$s6 = $wpdb->get_var( "SELECT code FROM $snippets WHERE id = 6" );
$s7 = $wpdb->get_var( "SELECT code FROM $snippets WHERE id = 7" );
$n6 = $s6 ? preg_match_all( $tabs_old_re, $s6 ) : 0;
$n7 = $s7 ? preg_match_all( $banner_re, $s7 ) : 0;
echo "snippet 6 filter-tabs block matches: $n6 (need 1)\n";
echo "snippet 7 'nails' => 832 matches: $n7 (need 1)\n";
if ( 1 !== $n6 || 1 !== $n7 ) {
	$ok = false;
}
$products = lunaci_es_products();
$plan     = array();
foreach ( $products as $pid ) {
	$cats = lunaci_cat_ids( $pid );
	$plan[ $pid ] = $cats;
	echo "ES product $pid \"" . get_the_title( $pid ) . '" cats: ' . implode( ',', $cats ) . "\n";
}
if ( ! $ok ) {
	echo "ABORT: preconditions not met, nothing written\n";
	exit( 1 );
}
echo "OK: preconditions met\n";

if ( 'dry-run' === $mode ) {
	echo "\nDRY-RUN: would create " . implode( ', ', array_map( function ( $m ) { return "{$m[1]} ({$m[2]})"; }, $map ) ) . ",\n";
	echo "move " . count( $products ) . " Spanish products to them, add Spanish slugs to snippet 7 and make snippet 6 language-aware.\n";
	exit( 0 );
}

// ------------------------------------------------------------------ backup
$backup = array(
	'created'       => gmdate( 'c' ),
	'snippets'      => array( 6 => $s6, 7 => $s7 ),
	'product_cats'  => $plan,
	'created_terms' => array(),
);
if ( false === file_put_contents( $backup_file, wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) ) {
	echo "ABORT: could not write $backup_file\n";
	exit( 1 );
}
echo "backup written: $backup_file\n";

// ------------------------------------------------------------------- apply
echo "\n--- 1. Spanish terms ---\n";
do_action( 'wpml_switch_language', 'es' );
$created = array();
foreach ( $map as $en_id => $m ) {
	$en_tt = (int) get_term( $en_id, 'product_cat' )->term_taxonomy_id;
	$det   = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $en_tt, 'element_type' => 'tax_product_cat' ) );
	$r     = wp_insert_term( $m[1], 'product_cat', array( 'slug' => $m[2] ) );
	if ( is_wp_error( $r ) ) {
		echo "FAIL: insert {$m[1]}: " . $r->get_error_message() . "\n";
		exit( 1 );
	}
	do_action(
		'wpml_set_element_language_details',
		array(
			'element_id'           => (int) $r['term_taxonomy_id'],
			'element_type'         => 'tax_product_cat',
			'trid'                 => (int) $det->trid,
			'language_code'        => 'es',
			'source_language_code' => 'en',
		)
	);
	foreach ( array( 'order', 'display_type', 'thumbnail_id' ) as $k ) {
		$v = get_term_meta( $en_id, $k, true );
		if ( '' !== $v ) {
			update_term_meta( (int) $r['term_id'], $k, $v );
		}
	}
	$created[ $en_id ] = (int) $r['term_id'];
	$backup['created_terms'][] = (int) $r['term_id'];
	echo "created {$m[1]} term {$r['term_id']} (tt {$r['term_taxonomy_id']}) linked to trid {$det->trid}\n";
}
file_put_contents( $backup_file, wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

echo "\n--- 2. Spanish products ---\n";
foreach ( $plan as $pid => $cats ) {
	$new = array_map( function ( $c ) use ( $created ) { return $created[ $c ] ?? $c; }, $cats );
	if ( $new === $cats ) {
		echo "product $pid unchanged\n";
		continue;
	}
	$r = wp_set_object_terms( $pid, $new, 'product_cat', false );
	if ( is_wp_error( $r ) ) {
		echo "FAIL: product $pid: " . $r->get_error_message() . "\n";
		exit( 1 );
	}
	echo "product $pid: " . implode( ',', $cats ) . ' -> ' . implode( ',', $new ) . "\n";
}
do_action( 'wpml_switch_language', null );
foreach ( array_merge( array_keys( $map ), array_values( $created ) ) as $tid ) {
	$tt = get_term( $tid, 'product_cat' );
	wp_update_term_count_now( array( (int) $tt->term_taxonomy_id ), 'product_cat' );
}

echo "\n--- 3/4. snippets ---\n";
$new7 = preg_replace( $banner_re, '$1' . $banner_add, $s7, 1 );
$new6 = preg_replace_callback( $tabs_old_re, function () use ( $tabs_new ) { return $tabs_new; }, $s6, 1 );
foreach ( array( 6 => array( $s6, $new6 ), 7 => array( $s7, $new7 ) ) as $sid => $pair ) {
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT code FROM $snippets WHERE id = %d", $sid ) ) !== $pair[0] ) {
		echo "FAIL: snippet $sid changed since the precondition check\n";
		exit( 1 );
	}
	if ( false === $wpdb->update( $snippets, array( 'code' => $pair[1] ), array( 'id' => $sid ) ) ) {
		echo "FAIL: snippet $sid update: {$wpdb->last_error}\n";
		exit( 1 );
	}
	echo "snippet $sid updated (" . strlen( $pair[0] ) . ' -> ' . strlen( $pair[1] ) . " bytes)\n";
}
wp_cache_flush();

echo "\n--- verify ---\n";
foreach ( $map as $en_id => $m ) {
	$es = lunaci_es_term_id( $en_id );
	do_action( 'wpml_switch_language', 'es' );
	$link = $es ? get_term_link( $es, 'product_cat' ) : '(none)';
	$cnt  = $es ? (int) get_term( $es, 'product_cat' )->count : 0;
	do_action( 'wpml_switch_language', null );
	echo "{$m[0]} -> ES term $es count=$cnt link=$link\n";
}
echo "APPLY DONE\n";
