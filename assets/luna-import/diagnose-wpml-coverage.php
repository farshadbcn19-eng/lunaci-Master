<?php
/**
 * Read-only. WPML translation coverage for Spanish (es):
 *  - posts by type: published originals (en) without a Spanish translation,
 *    Spanish translations not complete or flagged needs_update
 *  - taxonomy terms: originals without a Spanish term
 *  - menus (nav_menu terms) per language
 *  - String Translation: strings per domain/context and how many have a
 *    complete Spanish translation (status 10)
 *  - page-builder packages: strings without a Spanish translation
 */
global $wpdb;
$t  = $wpdb->prefix . 'icl_translations';
$ts = $wpdb->prefix . 'icl_translation_status';
echo "=== POSTS (published originals in en)\n";
$types = $wpdb->get_col( "SELECT DISTINCT p.post_type FROM {$wpdb->posts} p JOIN $t tr ON tr.element_id = p.ID AND tr.element_type = CONCAT('post_', p.post_type) WHERE p.post_status IN ('publish','private','inherit') AND p.post_type NOT IN ('revision','nav_menu_item','customize_changeset','oembed_cache','wp_global_styles')" );
foreach ( $types as $type ) {
	$orig = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_title, tr.trid FROM {$wpdb->posts} p JOIN $t tr ON tr.element_id = p.ID AND tr.element_type = %s WHERE tr.language_code = 'en' AND tr.source_language_code IS NULL AND p.post_status IN ('publish','private') ", 'post_' . $type ) );
	if ( ! $orig ) {
		$cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE element_type = %s", 'post_' . $type ) );
		echo "  $type: no published en originals ($cnt rows in icl_translations)\n";
		continue;
	}
	$missing = array();
	$incomplete = array();
	foreach ( $orig as $o ) {
		$es = $wpdb->get_row( $wpdb->prepare( "SELECT tr.element_id, s.status, s.needs_update, p.post_status FROM $t tr LEFT JOIN $ts s ON s.translation_id = tr.translation_id LEFT JOIN {$wpdb->posts} p ON p.ID = tr.element_id WHERE tr.trid = %d AND tr.language_code = 'es'", $o->trid ) );
		if ( ! $es || ! $es->element_id ) {
			$missing[] = "{$o->ID} '{$o->post_title}'";
		} elseif ( ( null !== $es->status && 10 !== (int) $es->status ) || 1 === (int) $es->needs_update || 'publish' !== $es->post_status && 'product_variation' !== $type ) {
			$incomplete[] = "{$o->ID}->{$es->element_id} status=" . var_export( $es->status, true ) . " needs_update={$es->needs_update} es_post_status={$es->post_status}";
		}
	}
	echo "  $type: " . count( $orig ) . ' en originals, ' . count( $missing ) . ' without es, ' . count( $incomplete ) . " es not complete\n";
	foreach ( array_slice( $missing, 0, 25 ) as $m ) {
		echo "     MISSING es: $m\n";
	}
	foreach ( array_slice( $incomplete, 0, 25 ) as $m ) {
		echo "     INCOMPLETE: $m\n";
	}
}
echo "=== TAXONOMIES\n";
foreach ( $wpdb->get_col( "SELECT DISTINCT element_type FROM $t WHERE element_type LIKE 'tax\\_%'" ) as $et ) {
	$tax  = substr( $et, 4 );
	$orig = $wpdb->get_results( $wpdb->prepare( "SELECT tr.trid, tt.term_id FROM $t tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.element_id WHERE tr.element_type = %s AND tr.language_code = 'en' AND tr.source_language_code IS NULL", $et ) );
	$miss = array();
	foreach ( $orig as $o ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM $t WHERE trid = %d AND language_code = 'es'", $o->trid ) ) ) {
			$term   = get_term( (int) $o->term_id );
			$miss[] = $term && ! is_wp_error( $term ) ? "{$term->term_id} '{$term->name}' ({$term->count} items)" : (string) $o->term_id;
		}
	}
	echo "  $tax: " . count( $orig ) . ' en terms, ' . count( $miss ) . " without es\n";
	foreach ( array_slice( $miss, 0, 20 ) as $m ) {
		echo "     MISSING es: $m\n";
	}
}
echo "=== MENUS\n";
foreach ( wp_get_nav_menus() as $m ) {
	$l = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $m->term_taxonomy_id, 'element_type' => 'nav_menu' ) );
	echo "  menu {$m->term_id} '{$m->name}' lang=" . var_export( $l, true ) . " items={$m->count}\n";
}
echo 'theme locations: ' . wp_json_encode( get_nav_menu_locations() ) . "\n";
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_strings'" ) ) {
	echo "=== STRING TRANSLATION (domain/context: strings, es complete, es other status)\n";
	$rows = $wpdb->get_results( "SELECT s.context, COUNT(*) AS n, SUM(CASE WHEN t.status = 10 THEN 1 ELSE 0 END) AS done, SUM(CASE WHEN t.id IS NOT NULL AND t.status <> 10 THEN 1 ELSE 0 END) AS other FROM {$wpdb->prefix}icl_strings s LEFT JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id AND t.language = 'es' WHERE s.language = 'en' GROUP BY s.context ORDER BY n DESC" );
	$tot = 0;
	$tdone = 0;
	foreach ( $rows as $r ) {
		$tot   += $r->n;
		$tdone += $r->done;
		echo "  {$r->context}: {$r->n} strings, {$r->done} es complete" . ( $r->other ? ", {$r->other} es other status" : '' ) . "\n";
	}
	echo "  TOTAL: $tot strings, $tdone with complete es translation\n";
	echo "--- strings in visible-text domains without es translation (sample)\n";
	foreach ( $wpdb->get_results( "SELECT s.id, s.context, s.name, LEFT(s.value, 80) AS v FROM {$wpdb->prefix}icl_strings s LEFT JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id AND t.language = 'es' AND t.status = 10 WHERE s.language = 'en' AND t.id IS NULL AND s.context NOT LIKE 'elementor-%' AND s.context NOT IN ('admin_texts_theme_mods', 'plugin All in One SEO') ORDER BY s.context LIMIT 60" ) as $r ) {
		echo "  [{$r->context}] {$r->name} = " . str_replace( "\n", ' ', $r->v ) . "\n";
	}
}
echo "=== SITE LANGUAGES\n";
echo wp_json_encode( array_map( function ( $l ) { return $l['code'] . ':' . $l['url']; }, (array) apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) ) ) . "\n";
