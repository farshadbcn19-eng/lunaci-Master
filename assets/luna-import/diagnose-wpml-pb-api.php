<?php
/**
 * Read-only. Asks WPML's own package objects (the ones the page-builder
 * integration uses when it rebuilds a translation) which Spanish value each
 * front page / about / contact / products widget string would get, and
 * compares it with the live Spanish page.
 */
global $wpdb;
$pairs = array( 57 => array( 772, '9b0a463' ), 59 => array( 680, 'ce307e5' ), 60 => array( 770, 'eca166b' ), 61 => array( 771, '296bd28' ) );
function lunaci_a_html( $post, $wid ) {
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
$shown = false;
foreach ( $pairs as $en => $p ) {
	$pkgs = apply_filters( 'wpml_st_get_post_string_packages', array(), $en );
	foreach ( (array) $pkgs as $pid => $pkg ) {
		if ( ! $shown ) {
			echo 'package class ' . get_class( $pkg ) . ' methods: ' . implode( ',', array_filter( get_class_methods( $pkg ), function ( $m ) { return false !== stripos( $m, 'string' ) || false !== stripos( $m, 'transl' ); } ) ) . "\n";
			$shown = true;
		}
		$live = lunaci_a_html( $p[0], $p[1] );
		if ( method_exists( $pkg, 'get_translated_strings' ) ) {
			$tr = $pkg->get_translated_strings( array() );
			$es = $tr[ 'html-html-' . $p[1] ]['es'] ?? null;
			echo "EN $en package $pid get_translated_strings: " . count( (array) $tr ) . ' strings; es for html-html-' . $p[1] . ': ' . ( null === $es ? 'NONE' : 'status=' . ( $es['status'] ?? '?' ) . ' ' . ( ( $es['value'] ?? null ) === $live ? 'EQUALS the live Spanish page' : 'differs from the live Spanish page (len ' . strlen( (string) ( $es['value'] ?? '' ) ) . ')' ) ) . "\n";
		}
		if ( method_exists( $pkg, 'get_package_strings' ) ) {
			foreach ( (array) $pkg->get_package_strings() as $s ) {
				$s = (object) $s;
				echo "    package string {$s->id} {$s->name} status=" . ( $s->status ?? '?' ) . "\n";
			}
		}
	}
}
