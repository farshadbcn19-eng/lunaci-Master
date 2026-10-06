<?php
/**
 * Read-only. For every English page built with Elementor that has a Spanish
 * WPML translation: the page-builder string package (icl_strings context
 * elementor-<EN id>), each string's status, whether its original value still
 * equals the English widget, whether a Spanish translation exists, and the
 * language of the Spanish page's own widget (by word markers).
 */
global $wpdb;
function lunaci_w_widgets( $raw ) {
	$out  = array();
	$walk = function ( $els ) use ( &$walk, &$out ) {
		foreach ( (array) $els as $el ) {
			foreach ( (array) ( $el['settings'] ?? array() ) as $k => $v ) {
				if ( is_string( $v ) && strlen( $v ) > 20 && preg_match( '/[a-z]/i', $v ) && ! preg_match( '#^https?://#', $v ) ) {
					$out[ ( $el['id'] ?? '?' ) . ':' . $k ] = $v;
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	$walk( json_decode( (string) $raw, true ) );
	return $out;
}
function lunaci_w_lang( $s ) {
	$t  = ' ' . strtolower( wp_strip_all_tags( $s ) ) . ' ';
	$en = 0;
	$es = 0;
	foreach ( array( 'the', 'and', 'your', 'with', 'our', 'for', 'of', 'is', 'every', 'that' ) as $w ) {
		$en += substr_count( $t, " $w " );
	}
	foreach ( array( 'el', 'la', 'de', 'tu', 'con', 'para', 'los', 'las', 'que', 'y', 'una' ) as $w ) {
		$es += substr_count( $t, " $w " );
	}
	return "en=$en es=$es";
}
$has_st = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_string_translations'" );
$pages  = $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_edit_mode' AND m.meta_value = 'builder' WHERE p.post_type IN ('page','post') AND p.post_status = 'publish'" );
foreach ( $pages as $id ) {
	$lang = apply_filters( 'wpml_post_language_details', null, (int) $id );
	if ( 'en' !== ( $lang['language_code'] ?? '' ) ) {
		continue;
	}
	$es_id = (int) apply_filters( 'wpml_object_id', (int) $id, get_post_type( $id ), false, 'es' );
	echo "=== EN $id '" . get_the_title( $id ) . "' -> ES " . ( $es_id ?: 'none' ) . ( $es_id ? " '" . get_the_title( $es_id ) . "' builder=" . get_post_meta( $es_id, '_elementor_edit_mode', true ) : '' ) . "\n";
	$en_w = lunaci_w_widgets( get_post_meta( $id, '_elementor_data', true ) );
	$es_w = $es_id ? lunaci_w_widgets( get_post_meta( $es_id, '_elementor_data', true ) ) : array();
	foreach ( $en_w as $k => $v ) {
		$e = $es_w[ $k ] ?? null;
		echo "  widget $k EN len=" . strlen( $v ) . ' md5=' . md5( $v ) . ' | ES ' . ( null === $e ? 'missing' : 'len=' . strlen( $e ) . ' md5=' . md5( $e ) . ' ' . lunaci_w_lang( $e ) ) . "\n";
	}
	if ( $has_st ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.id, s.name, s.status, s.string_package_id, MD5(s.value) AS vmd5, LENGTH(s.value) AS vlen, t.id AS tid, t.status AS tstatus, LENGTH(t.value) AS tlen FROM {$wpdb->prefix}icl_strings s LEFT JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id AND t.language = 'es' WHERE s.context = %s", 'elementor-' . $id ) );
		foreach ( $rows as $r ) {
			$match = '';
			foreach ( $en_w as $k => $v ) {
				if ( md5( $v ) === $r->vmd5 ) {
					$match = $k;
				}
			}
			echo "  string {$r->id} '{$r->name}' pkg={$r->string_package_id} status={$r->status} len={$r->vlen} equals_current_EN=" . ( $match ? $match : 'NO' ) . ' es_translation=' . ( $r->tid ? "id={$r->tid} status={$r->tstatus} len={$r->tlen}" : 'NONE' ) . "\n";
		}
		$pkg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_string_packages WHERE kind_slug = 'elementor' AND name = %s", (string) $id ), ARRAY_A );
		echo '  package: ' . ( $pkg ? wp_json_encode( $pkg ) : 'none' ) . "\n";
	}
	if ( $es_id ) {
		$st = $wpdb->get_row( $wpdb->prepare( "SELECT t.trid, t.source_language_code, s.status, s.needs_update, s.translation_service FROM {$wpdb->prefix}icl_translations t LEFT JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id WHERE t.element_id = %d AND t.element_type = %s", $es_id, 'post_' . get_post_type( $es_id ) ), ARRAY_A );
		echo '  ES translation status: ' . wp_json_encode( $st ) . "\n";
	}
}
echo "=== WPML settings\n";
$s = get_option( 'icl_sitepress_settings' );
foreach ( array( 'translation-management', 'custom_posts_sync_option' ) as $k ) {
	if ( isset( $s[ $k ] ) ) {
		echo "$k: " . substr( wp_json_encode( $s[ $k ] ), 0, 600 ) . "\n";
	}
}
echo 'WPML_ST_VERSION=' . ( defined( 'WPML_ST_VERSION' ) ? WPML_ST_VERSION : '-' ) . ' ICL_SITEPRESS_VERSION=' . ( defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : '-' ) . ' WPML_TM_VERSION=' . ( defined( 'WPML_TM_VERSION' ) ? WPML_TM_VERSION : '-' ) . "\n";
