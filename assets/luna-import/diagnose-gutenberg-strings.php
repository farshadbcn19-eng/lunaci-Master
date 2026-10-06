<?php
/**
 * Read-only. For each EN/ES Gutenberg page pair: the WPML package strings
 * (gutenberg-<EN id>) next to the flattened text blocks of the EN and ES
 * pages, to map each string to its Spanish counterpart.
 */
global $wpdb;
$st    = $wpdb->prefix . 'icl_strings';
$pairs = array( 759 => 765, 760 => 766, 676 => 768, 3 => 769 );
echo 'icl_strings columns: ' . implode( ',', $wpdb->get_col( "SHOW COLUMNS FROM $st" ) ) . "\n";
echo 'package table: ' . wp_json_encode( $wpdb->get_results( "SELECT ID, kind, name, title, post_id FROM {$wpdb->prefix}icl_string_packages WHERE kind_slug='gutenberg' OR name LIKE 'gutenberg%' OR kind LIKE '%utenberg%' LIMIT 20" ) ) . "\n";

function lunaci_g_flat( $blocks, &$out ) {
	foreach ( $blocks as $b ) {
		if ( ! empty( $b['innerBlocks'] ) ) {
			lunaci_g_flat( $b['innerBlocks'], $out );
		}
		$txt = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $b['innerHTML'] ) ) );
		if ( $b['blockName'] && '' !== $txt ) {
			$out[] = array( $b['blockName'], $txt );
		}
	}
}
foreach ( $pairs as $en => $es ) {
	echo "\n===== EN $en / ES $es\n";
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $st WHERE context=%s ORDER BY id", 'gutenberg-' . $en ) );
	foreach ( $rows as $r ) {
		$extra = array();
		foreach ( array( 'type', 'title', 'location', 'wrap', 'string_package_id', 'status', 'translation_priority' ) as $c ) {
			if ( isset( $r->$c ) && '' !== (string) $r->$c ) {
				$extra[] = "$c=" . substr( (string) $r->$c, 0, 30 );
			}
		}
		printf( "S %d name=%s %s | %s\n", $r->id, substr( $r->name, 0, 40 ), implode( ' ', $extra ), substr( str_replace( "\n", '\\n', $r->value ), 0, 110 ) );
	}
	foreach ( array( 'EN' => $en, 'ES' => $es ) as $l => $id ) {
		$flat = array();
		lunaci_g_flat( parse_blocks( get_post( $id )->post_content ), $flat );
		echo "-- $l blocks: " . count( $flat ) . "\n";
		foreach ( $flat as $i => $f ) {
			printf( "%s %2d %s | %s\n", $l, $i, $f[0], substr( $f[1], 0, 100 ) );
		}
	}
}
