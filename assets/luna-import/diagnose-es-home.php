<?php
/**
 * Read-only. Spanish front page (post 772): its HTML widget 9b0a463 now, in
 * every revision of 772, and the WPML string translations of the English
 * front page widget (string context elementor-57). Prints md5, length, date,
 * language markers and the full HTML of the newest Spanish revision.
 */
global $wpdb;
function lunaci_d_widget( $raw, $wid ) {
	$found = null;
	$walk  = function ( $els ) use ( &$walk, &$found, $wid ) {
		foreach ( (array) $els as $el ) {
			if ( ( $el['id'] ?? '' ) === $wid ) {
				$found = (string) ( $el['settings']['html'] ?? '' );
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	$walk( json_decode( (string) $raw, true ) );
	return $found;
}
function lunaci_d_lang( $h ) {
	return ( false !== strpos( $h, 'Todas las mujeres' ) || false !== strpos( $h, 'Inspirada en el esp' ) ) ? 'SPANISH' : ( ( false !== strpos( $h, 'Inspired by the Mediterranean' ) ) ? 'ENGLISH' : '?' );
}
$cur = lunaci_d_widget( get_post_meta( 772, '_elementor_data', true ), '9b0a463' );
$p   = get_post( 772 );
echo "post 772 '{$p->post_title}' modified={$p->post_modified} widget 9b0a463 md5=" . md5( (string) $cur ) . ' len=' . strlen( (string) $cur ) . ' lang=' . lunaci_d_lang( (string) $cur ) . "\n";
echo 'post 772 edit_mode=' . get_post_meta( 772, '_elementor_edit_mode', true ) . ' element_cache=' . ( metadata_exists( 'post', 772, '_elementor_element_cache' ) ? 'present' : 'absent' ) . "\n";
$en = lunaci_d_widget( get_post_meta( 57, '_elementor_data', true ), '9b0a463' );
echo 'post 57 (EN front) widget 9b0a463 md5=' . md5( (string) $en ) . ' identical_to_772=' . ( $en === $cur ? 'YES' : 'no' ) . "\n";
$newest_es = null;
foreach ( $wpdb->get_results( "SELECT ID, post_modified, post_name FROM {$wpdb->posts} WHERE post_parent = 772 AND post_type = 'revision' ORDER BY post_modified DESC, ID DESC" ) as $r ) {
	$h = lunaci_d_widget( get_post_meta( $r->ID, '_elementor_data', true ), '9b0a463' );
	$l = null === $h ? 'no-widget' : lunaci_d_lang( $h );
	echo "revision {$r->ID} modified={$r->post_modified} {$r->post_name} widget md5=" . ( null === $h ? '-' : md5( $h ) ) . ' len=' . ( null === $h ? 0 : strlen( $h ) ) . " lang=$l\n";
	if ( 'SPANISH' === $l && null === $newest_es ) {
		$newest_es = array( $r->ID, $h );
	}
}
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_strings'" ) ) {
	foreach ( $wpdb->get_results( "SELECT s.id, s.name, t.language, t.status, LENGTH(t.value) AS len, MD5(t.value) AS md5, LOCATE('Inspirada', t.value) AS es_hit FROM {$wpdb->prefix}icl_strings s LEFT JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id WHERE s.context = 'elementor-57'" ) as $r ) {
		echo "WPML elementor-57 string {$r->id} {$r->name} lang={$r->language} status={$r->status} len={$r->len} md5={$r->md5} spanish_hit={$r->es_hit}\n";
	}
}
if ( $newest_es ) {
	echo "----- BEGIN newest Spanish revision {$newest_es[0]} widget 9b0a463 -----\n" . $newest_es[1] . "\n----- END newest Spanish revision -----\n";
}
