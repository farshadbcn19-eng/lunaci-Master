<?php
/**
 * Nail Polish Long Lasting: swap the Shade 01-08 variation images to the dark/gold studio set.
 *
 * Prerequisite: shades 01-08 are already live (nailpolish-shades-1-8.php applied) on EN 502 and ES 742.
 * For BOTH parents this script only changes each variation's _thumbnail_id (media ids come from
 * LUNACI_MEDIA_MAP "1:123,2:124,..."; same attachment for EN+ES). Titles, price, SKU, stock,
 * status, attributes and all copy are NOT touched. Direct writes only (no WPML/WCML save hooks).
 *
 * LUNACI_MODE=dry-run | apply | rollback  (rollback reads $LUNACI_BACKUP_DIR/nailpolish-dark-backup.json)
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$bk      = rtrim( $dir, '/' ) . '/nailpolish-dark-backup.json';
$parents = array( 502 => 'EN', 742 => 'ES' );
$keep    = 8;

function lunaci_npd_flush( $ids ) {
	foreach ( $ids as $id ) {
		wp_cache_delete( $id, 'post_meta' );
		clean_post_cache( $id );
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}
	}
	wp_cache_flush();
}

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $bk ), true );
	if ( ! is_array( $b ) || empty( $b['thumb'] ) ) {
		echo "ABORT: no backup at $bk\n";
		exit( 1 );
	}
	foreach ( $b['thumb'] as $vid => $val ) { // '' = there was no _thumbnail_id row
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => (int) $vid, 'meta_key' => '_thumbnail_id' ) );
		if ( '' !== $val ) {
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => (int) $vid, 'meta_key' => '_thumbnail_id', 'meta_value' => $val ) );
		}
	}
	lunaci_npd_flush( array_merge( array_keys( $parents ), array_map( 'intval', array_keys( $b['thumb'] ) ) ) );
	echo "ROLLBACK DONE\n";
	exit( 0 );
}

$media = array();
foreach ( explode( ',', (string) getenv( 'LUNACI_MEDIA_MAP' ) ) as $pair ) {
	$kv = explode( ':', $pair );
	if ( 2 === count( $kv ) && (int) $kv[0] > 0 && (int) $kv[1] > 0 ) {
		$media[ (int) $kv[0] ] = (int) $kv[1];
	}
}
$fail = 0;
if ( 'apply' === $mode ) {
	for ( $i = 1; $i <= $keep; $i++ ) {
		if ( empty( $media[ $i ] ) || 'attachment' !== get_post_type( $media[ $i ] ) || ! get_attached_file( $media[ $i ] ) || ! file_exists( get_attached_file( $media[ $i ] ) ) ) {
			echo "FAIL: no valid attachment for shade $i\n";
			$fail = 1;
		}
	}
}

// ---------- plan: exactly the 8 published "Shade 0N" variations per parent ----------
$plan = array();
foreach ( $parents as $pid => $lang ) {
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, m.meta_value AS shade FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'attribute_shade' WHERE p.post_parent = %d AND p.post_type = 'product_variation' AND p.post_status = 'publish'", $pid ) );
	$slots = array();
	foreach ( $rows as $r ) {
		for ( $i = 1; $i <= $keep; $i++ ) {
			if ( sprintf( 'Shade %02d', $i ) === $r->shade ) {
				$slots[ $i ] = (int) $r->ID;
			}
		}
	}
	if ( count( $slots ) !== $keep || count( $rows ) !== $keep ) {
		echo "FAIL $lang $pid: expected exactly $keep published 'Shade 0N' variations, found " . count( $rows ) . ' rows / ' . count( $slots ) . " matched\n";
		$fail = 1;
		continue;
	}
	$plan[ $pid ] = array( 'lang' => $lang, 'slots' => $slots );
	echo "OK $lang $pid: 8 published Shade 01-08 variations found\n";
}
if ( $fail ) {
	echo "ABORT: plan failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}

// ---------- backup, then apply ----------
$b = array( 'thumb' => array() );
foreach ( $plan as $pl ) {
	foreach ( $pl['slots'] as $vid ) {
		$b['thumb'][ $vid ] = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_thumbnail_id' LIMIT 1", $vid ) );
	}
}
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0700, true );
}
file_put_contents( $bk, wp_json_encode( $b ) );
echo "backup written: $bk\n";

foreach ( $plan as $pl ) {
	foreach ( $pl['slots'] as $i => $vid ) {
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $vid, 'meta_key' => '_thumbnail_id' ) );
		$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $vid, 'meta_key' => '_thumbnail_id', 'meta_value' => $media[ $i ] ) );
	}
}
lunaci_npd_flush( array_merge( array_keys( $parents ), array_map( 'intval', array_keys( $b['thumb'] ) ) ) );

// ---------- verify through WooCommerce ----------
foreach ( $plan as $pid => $pl ) {
	$prod = wc_get_product( $pid );
	$got  = array();
	foreach ( $prod ? $prod->get_available_variations() : array() as $v ) {
		$got[ $v['attributes']['attribute_shade'] ] = (int) $v['image_id'];
	}
	ksort( $got );
	$want = array();
	for ( $i = 1; $i <= $keep; $i++ ) {
		$want[ sprintf( 'Shade %02d', $i ) ] = $media[ $i ];
	}
	if ( $got !== $want ) {
		echo "FAIL VERIFY {$pl['lang']} $pid: " . wp_json_encode( $got ) . "\n";
		$fail = 1;
		continue;
	}
	echo "VERIFY OK {$pl['lang']} $pid: 8 shades with images " . wp_json_encode( $got ) . "\n";
}
if ( $fail ) {
	echo "FAILED: run rollback\n";
	exit( 1 );
}
echo "APPLY DONE\n";
