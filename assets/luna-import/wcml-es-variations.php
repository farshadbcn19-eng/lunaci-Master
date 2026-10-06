<?php
/**
 * Spanish shade selection.
 *
 * The Spanish products already have one variation per English variation
 * (same shade, same price, SKU + "-ES"), but:
 *  - six Spanish parents are product_type "simple", so WooCommerce hides the
 *    variations (Fijador 711, Brillo Velvet 723, Delineador de Labios 731,
 *    Polvo Compacto 718, Lápiz de Cejas 737, Esmalte 742);
 *  - all 57 Spanish variations are registered in WPML as separate English
 *    originals instead of Spanish translations of the English variations.
 * This links each Spanish variation to its English variation (matched by the
 * shade attribute), sets the six parents to "variable", resyncs their price
 * meta, and renames the shade "Perfect 04-V" (forbidden word) to
 * "Signature 04-V" in both languages plus the pa_color term "Perfect 04".
 *
 * All writes are direct (no save hooks: WPML/WCML hooks rebuilt a Spanish page
 * from English on 2026-10-05) and verified.
 *
 * LUNACI_MODE=dry-run | apply | rollback (rollback: $LUNACI_BACKUP_DIR/wcml-es-variations-backup.json)
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$it   = $wpdb->prefix . 'icl_translations';
$bk   = rtrim( $dir, '/' ) . '/wcml-es-variations-backup.json';
$old_shade = 'Perfect 04-V';
$new_shade = 'Signature 04-V';
// EN parent => ES parent
$pairs  = array( 324 => 686, 325 => 718, 326 => 687, 327 => 723, 328 => 711, 329 => 685, 491 => 731, 497 => 737, 502 => 742 );
$simple = array( 718, 723, 711, 731, 737, 742 );

function lunaci_v_flush( $ids ) {
	foreach ( $ids as $id ) {
		wp_cache_delete( $id, 'post_meta' );
		clean_post_cache( $id );
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}
	}
	wp_cache_flush();
}
function lunaci_v_type_tt( $type ) {
	$t = get_term_by( 'slug', $type, 'product_type' );
	return $t ? (int) $t->term_taxonomy_id : 0;
}

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $bk ), true );
	if ( ! is_array( $b ) || empty( $b['icl'] ) ) {
		echo "ABORT: no backup at $bk\n";
		exit( 1 );
	}
	foreach ( $b['icl'] as $row ) {
		$wpdb->update( $it, array( 'trid' => $row['trid'], 'language_code' => $row['language_code'], 'source_language_code' => $row['source_language_code'] ), array( 'translation_id' => $row['translation_id'] ) );
	}
	foreach ( $b['types'] as $pid => $tt ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id IN (%d, %d)", $pid, lunaci_v_type_tt( 'simple' ), lunaci_v_type_tt( 'variable' ) ) );
		$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => $pid, 'term_taxonomy_id' => $tt ) );
	}
	foreach ( $b['meta'] as $m ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $m['meta_value'] ), array( 'meta_id' => $m['meta_id'] ) );
	}
	foreach ( $b['posts'] as $p ) {
		$wpdb->update( $wpdb->posts, array( 'post_title' => $p['post_title'], 'post_excerpt' => $p['post_excerpt'] ), array( 'ID' => $p['ID'] ) );
	}
	foreach ( $b['prices'] as $pid => $vals ) {
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $pid, 'meta_key' => '_price' ) );
		foreach ( $vals as $v ) {
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $pid, 'meta_key' => '_price', 'meta_value' => $v ) );
		}
	}
	foreach ( (array) ( $b['marker'] ?? array() ) as $vid => $val ) {
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => (int) $vid, 'meta_key' => '_wcml_duplicate_of_variation' ) );
		if ( '' !== $val ) {
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => (int) $vid, 'meta_key' => '_wcml_duplicate_of_variation', 'meta_value' => $val ) );
		}
	}
	if ( ! empty( $b['term'] ) ) {
		$wpdb->update( $wpdb->terms, array( 'name' => $b['term']['name'] ), array( 'term_id' => $b['term']['term_id'] ) );
	}
	wp_update_term_count_now( array( lunaci_v_type_tt( 'simple' ), lunaci_v_type_tt( 'variable' ) ), 'product_type' );
	lunaci_v_flush( array_merge( array_keys( $pairs ), array_values( $pairs ) ) );
	echo "ROLLBACK DONE\n";
	exit( 0 );
}

// ---------- plan ----------
$fail  = 0;
$links = array();   // es variation id => array( en var id, en trid, es icl row )
$meta  = array();   // meta rows to rename
$posts = array();   // variation posts to retitle
$types = array();   // es parent => current product_type tt id
$tt_simple   = lunaci_v_type_tt( 'simple' );
$tt_variable = lunaci_v_type_tt( 'variable' );
if ( ! $tt_simple || ! $tt_variable ) {
	echo "ABORT: product_type terms not found\n";
	exit( 1 );
}
foreach ( $pairs as $en => $es ) {
	if ( (int) apply_filters( 'wpml_object_id', $en, 'product', false, 'es' ) !== $es ) {
		echo "FAIL $en: WPML Spanish product is not $es\n";
		$fail = 1;
		continue;
	}
	$en_vars = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, m.meta_value AS shade FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'attribute_shade' WHERE p.post_parent = %d AND p.post_type = 'product_variation' AND p.post_status = 'publish'", $en ), OBJECT_K );
	$es_vars = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, m.meta_value AS shade FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'attribute_shade' WHERE p.post_parent = %d AND p.post_type = 'product_variation' AND p.post_status = 'publish'", $es ), OBJECT_K );
	$by_shade = array();
	foreach ( $en_vars as $v ) {
		$by_shade[ $v->shade ] = (int) $v->ID;
	}
	if ( count( $en_vars ) !== count( $es_vars ) || count( $by_shade ) !== count( $en_vars ) ) {
		echo "FAIL $en/$es: " . count( $en_vars ) . ' EN vs ' . count( $es_vars ) . " ES variations\n";
		$fail = 1;
		continue;
	}
	foreach ( $es_vars as $v ) {
		$ev = $by_shade[ $v->shade ] ?? 0;
		$ep = get_post_meta( $ev, '_price', true );
		$sp = get_post_meta( (int) $v->ID, '_price', true );
		$er = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $it WHERE element_id = %d AND element_type = 'post_product_variation'", $ev ), ARRAY_A );
		$sr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $it WHERE element_id = %d AND element_type = 'post_product_variation'", (int) $v->ID ), ARRAY_A );
		$taken = $er ? $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM $it WHERE trid = %d AND language_code = 'es'", $er['trid'] ) ) : null;
		if ( ! $ev || $ep !== $sp || ! $er || ! $sr || 'en' !== $er['language_code'] || ( $taken && (int) $taken !== (int) $v->ID ) ) {
			echo "FAIL ES variation {$v->ID} '{$v->shade}': EN match=$ev price EN=$ep ES=$sp " . ( $taken ? "trid already has es $taken" : '' ) . "\n";
			$fail = 1;
			continue;
		}
		$links[ (int) $v->ID ] = array( 'en' => $ev, 'trid' => (int) $er['trid'], 'row' => $sr );
	}
	$cur = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id IN (%d, %d)", $es, $tt_simple, $tt_variable ) );
	$types[ $es ] = $cur ? (int) $cur[0] : 0;
	$expected     = in_array( $es, $simple, true ) ? $tt_simple : $tt_variable;
	if ( 1 !== count( $cur ) || (int) $cur[0] !== $expected ) {
		echo "FAIL ES $es product_type is not the expected one\n";
		$fail = 1;
	}
	echo "PLAN EN $en -> ES $es: " . count( $es_vars ) . ' variations linked' . ( in_array( $es, $simple, true ) ? ', simple -> variable' : '' ) . "\n";
}
// rename Perfect 04-V
foreach ( array( 327, 723 ) as $pid ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_product_attributes'", $pid ), ARRAY_A );
	$pa  = $row ? maybe_unserialize( $row['meta_value'] ) : null;
	if ( ! is_array( $pa ) || false === strpos( (string) ( $pa['shade']['value'] ?? '' ), $old_shade ) ) {
		echo "FAIL product $pid: shade '$old_shade' not in _product_attributes\n";
		$fail = 1;
		continue;
	}
	$pa['shade']['value'] = str_replace( $old_shade, $new_shade, $pa['shade']['value'] );
	$meta[]               = array( 'meta_id' => (int) $row['meta_id'], 'old' => $row['meta_value'], 'new' => serialize( $pa ) );
}
foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_id, m.meta_value, m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = 'attribute_shade' AND m.meta_value = %s AND p.post_parent IN (327, 723)", $old_shade ), ARRAY_A ) as $row ) {
	$meta[] = array( 'meta_id' => (int) $row['meta_id'], 'old' => $row['meta_value'], 'new' => $new_shade );
	$p      = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_title, post_excerpt FROM {$wpdb->posts} WHERE ID = %d", $row['post_id'] ), ARRAY_A );
	$posts[] = $p;
}
$term = $wpdb->get_row( "SELECT t.term_id, t.name FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = 'pa_color' WHERE t.name = 'Perfect 04'", ARRAY_A );
echo 'PLAN rename: ' . count( $meta ) . ' meta rows, ' . count( $posts ) . ' variation titles, pa_color term ' . ( $term ? "{$term['term_id']} 'Perfect 04' -> 'Signature 04'" : 'not found' ) . "\n";
echo 'links planned: ' . count( $links ) . "\n";
if ( $fail || 57 !== count( $links ) || 4 !== count( $meta ) || 2 !== count( $posts ) ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}

// ---------- backup ----------
$prices = array();
foreach ( $simple as $pid ) {
	$prices[ $pid ] = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_price'", $pid ) );
}
$b = array(
	'icl'    => array_map( function ( $l ) { return $l['row']; }, array_values( $links ) ),
	'types'  => $types,
	'meta'   => array_map( function ( $m ) { return array( 'meta_id' => $m['meta_id'], 'meta_value' => $m['old'] ); }, $meta ),
	'posts'  => $posts,
	'prices' => $prices,
	'term'   => $term,
	'marker' => array_map( function ( $vid ) { return get_post_meta( $vid, '_wcml_duplicate_of_variation', true ); }, array_combine( array_keys( $links ), array_keys( $links ) ) ),
);
if ( false === file_put_contents( $bk, wp_json_encode( $b ) ) ) {
	echo "ABORT: cannot write $bk\n";
	exit( 1 );
}
echo "backup: $bk\n";

// ---------- write ----------
foreach ( $links as $vid => $l ) {
	$wpdb->update( $it, array( 'trid' => $l['trid'], 'language_code' => 'es', 'source_language_code' => 'en' ), array( 'translation_id' => $l['row']['translation_id'] ) );
	// WCML's own marker for a translated variation (used by its variation sync).
	$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $vid, 'meta_key' => '_wcml_duplicate_of_variation' ) );
	$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $vid, 'meta_key' => '_wcml_duplicate_of_variation', 'meta_value' => (string) $l['en'] ) );
}
foreach ( $simple as $pid ) {
	$wpdb->update( $wpdb->term_relationships, array( 'term_taxonomy_id' => $tt_variable ), array( 'object_id' => $pid, 'term_taxonomy_id' => $tt_simple ) );
}
wp_update_term_count_now( array( $tt_simple, $tt_variable ), 'product_type' );
foreach ( $meta as $m ) {
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $m['new'] ), array( 'meta_id' => $m['meta_id'] ) );
}
foreach ( $posts as $p ) {
	$wpdb->update( $wpdb->posts, array( 'post_title' => str_replace( $old_shade, $new_shade, $p['post_title'] ), 'post_excerpt' => str_replace( $old_shade, $new_shade, $p['post_excerpt'] ) ), array( 'ID' => $p['ID'] ) );
}
if ( $term ) {
	$wpdb->update( $wpdb->terms, array( 'name' => 'Signature 04' ), array( 'term_id' => $term['term_id'] ) );
}
// Variable parents keep one _price row per distinct variation price.
foreach ( $simple as $pid ) {
	$vp = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_parent = %d AND p.post_type = 'product_variation' AND p.post_status = 'publish' AND m.meta_key = '_price' AND m.meta_value <> ''", $pid ) );
	$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $pid, 'meta_key' => '_price' ) );
	foreach ( $vp as $v ) {
		$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $pid, 'meta_key' => '_price', 'meta_value' => $v ) );
	}
}
lunaci_v_flush( array_merge( array_keys( $pairs ), array_values( $pairs ), array_keys( $links ) ) );

// ---------- verify ----------
$bad = 0;
foreach ( $links as $vid => $l ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT trid, language_code, source_language_code FROM $it WHERE element_id = %d AND element_type = 'post_product_variation'", $vid ), ARRAY_A );
	if ( (int) $r['trid'] !== $l['trid'] || 'es' !== $r['language_code'] || (int) apply_filters( 'wpml_object_id', $l['en'], 'product_variation', false, 'es' ) !== $vid ) {
		echo "VERIFY FAIL link $vid\n";
		$bad = 1;
	}
}
foreach ( $pairs as $en => $es ) {
	$p = wc_get_product( $es );
	$n = $p && $p->is_type( 'variable' ) ? count( $p->get_children() ) : 0;
	$e = count( wc_get_product( $en )->get_children() );
	echo "VERIFY ES $es '" . ( $p ? $p->get_name() : '?' ) . "': type=" . ( $p ? $p->get_type() : '?' ) . " variations=$n (EN $e) price=" . ( $p ? $p->get_price() : '?' ) . "\n";
	if ( ! $p || ! $p->is_type( 'variable' ) || $n !== $e ) {
		$bad = 1;
	}
}
$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'attribute_shade' AND meta_value = %s", $old_shade ) );
echo "variations still named '$old_shade': $left\n";
$bad = $bad || $left;
echo $bad ? "APPLY FINISHED WITH ERRORS\n" : "APPLY DONE\n";
exit( $bad ? 1 : 0 );
