<?php
/**
 * Nail Polish Long Lasting: publish shades 01-08 (market-led selection).
 *
 * Today the product has 12 placeholder variations "Shade 01 (placeholder)" ..
 * "Shade 12 (placeholder)" in both languages (EN parent 502, ES parent 742).
 * This script, for BOTH parents:
 *  - renames slots 01-08 to "Shade 01" .. "Shade 08" (variation meta, variation
 *    post_title, and the parent's _product_attributes value list);
 *  - sets each of those 8 variations' image (_thumbnail_id) to the real bottle
 *    photo supplied in LUNACI_MEDIA_MAP ("1:123,2:124,..."; same attachment EN+ES);
 *  - hides slots 09-12 (post_status "private", dropped from the attribute list)
 *    so no "placeholder" shade is shown next to real ones. Nothing is deleted.
 * Price, SKU, stock, descriptions and product copy are not touched.
 * Direct writes only (no save hooks: WPML/WCML hooks rebuilt a Spanish page
 * from English on 2026-10-05).
 *
 * LUNACI_MODE=dry-run | apply | rollback  (rollback reads $LUNACI_BACKUP_DIR/nailpolish-shades-backup.json)
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$bk      = rtrim( $dir, '/' ) . '/nailpolish-shades-backup.json';
$parents = array( 502 => 'EN', 742 => 'ES' );
$keep    = 8;
$total   = 12;
$old     = function ( $i ) { return sprintf( 'Shade %02d (placeholder)', $i ); };
$new     = function ( $i ) { return sprintf( 'Shade %02d', $i ); };

function lunaci_np_flush( $ids ) {
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
	if ( ! is_array( $b ) || empty( $b['posts'] ) ) {
		echo "ABORT: no backup at $bk\n";
		exit( 1 );
	}
	foreach ( $b['posts'] as $p ) {
		$wpdb->update( $wpdb->posts, array( 'post_title' => $p['post_title'], 'post_status' => $p['post_status'] ), array( 'ID' => $p['ID'] ) );
	}
	foreach ( $b['meta'] as $m ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $m['meta_value'] ), array( 'meta_id' => $m['meta_id'] ) );
	}
	foreach ( $b['thumb'] as $vid => $val ) { // '' = there was no _thumbnail_id row
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => (int) $vid, 'meta_key' => '_thumbnail_id' ) );
		if ( '' !== $val ) {
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => (int) $vid, 'meta_key' => '_thumbnail_id', 'meta_value' => $val ) );
		}
	}
	lunaci_np_flush( array_merge( array_keys( $parents ), $b['ids'] ) );
	echo "ROLLBACK DONE\n";
	exit( 0 );
}

// ---------- media map ----------
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

// ---------- plan ----------
$plan = array(); // parent => array( slot => variation id ) ; plus attribute meta row
foreach ( $parents as $pid => $lang ) {
	$prod = get_post( $pid );
	if ( ! $prod || 'product' !== $prod->post_type ) {
		echo "FAIL $lang: $pid is not a product\n";
		$fail = 1;
		continue;
	}
	if ( false === stripos( get_the_title( $pid ), 'nail polish' ) && 'EN' === $lang ) {
		echo "FAIL EN: parent $pid title is '" . get_the_title( $pid ) . "'\n";
		$fail = 1;
		continue;
	}
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_status, m.meta_id, m.meta_value AS shade FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'attribute_shade' WHERE p.post_parent = %d AND p.post_type = 'product_variation'", $pid ) );
	$slots = array();
	foreach ( $rows as $r ) {
		for ( $i = 1; $i <= $total; $i++ ) {
			if ( $r->shade === $old( $i ) ) {
				$slots[ $i ] = $r;
			}
		}
	}
	if ( count( $slots ) !== $total || count( $rows ) !== $total ) {
		echo "FAIL $lang $pid: expected exactly $total placeholder variations, found " . count( $rows ) . ' rows / ' . count( $slots ) . " matched\n";
		$fail = 1;
		continue;
	}
	$am = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_product_attributes'", $pid ) );
	$at = $am ? maybe_unserialize( $am->meta_value ) : null;
	$ak = is_array( $at ) ? ( isset( $at['shade'] ) ? 'shade' : null ) : null;
	if ( ! $ak || false === strpos( (string) $at[ $ak ]['value'], $old( 1 ) ) || false === strpos( (string) $at[ $ak ]['value'], ' | ' ) ) {
		echo "FAIL $lang $pid: _product_attributes has no 'shade' list in the expected ' | ' format\n";
		$fail = 1;
		continue;
	}
	$plan[ $pid ] = array( 'lang' => $lang, 'slots' => $slots, 'am' => $am, 'at' => $at, 'ak' => $ak );
	echo "OK $lang $pid: 12 placeholder variations found; slots 01-08 -> real, 09-12 -> hidden\n";
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
$b = array( 'posts' => array(), 'meta' => array(), 'thumb' => array(), 'ids' => array() );
foreach ( $plan as $pid => $pl ) {
	foreach ( $pl['slots'] as $i => $r ) {
		$b['posts'][]         = array( 'ID' => (int) $r->ID, 'post_title' => $r->post_title, 'post_status' => $r->post_status );
		$b['meta'][]          = array( 'meta_id' => (int) $r->meta_id, 'meta_value' => $r->shade );
		$b['thumb'][ $r->ID ] = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_thumbnail_id' LIMIT 1", $r->ID ) );
		$b['ids'][]           = (int) $r->ID;
	}
	$b['meta'][] = array( 'meta_id' => (int) $pl['am']->meta_id, 'meta_value' => $pl['am']->meta_value );
}
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0700, true );
}
file_put_contents( $bk, wp_json_encode( $b ) );
echo "backup written: $bk\n";

foreach ( $plan as $pid => $pl ) {
	foreach ( $pl['slots'] as $i => $r ) {
		if ( $i <= $keep ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new( $i ) ), array( 'meta_id' => $r->meta_id ) );
			$wpdb->update( $wpdb->posts, array( 'post_title' => str_replace( $old( $i ), $new( $i ), $r->post_title ) ), array( 'ID' => $r->ID ) );
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $r->ID, 'meta_key' => '_thumbnail_id' ) );
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $r->ID, 'meta_key' => '_thumbnail_id', 'meta_value' => $media[ $i ] ) );
		} else {
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $r->ID ) );
		}
	}
	$list                          = array_map( $new, range( 1, $keep ) );
	$pl['at'][ $pl['ak'] ]['value'] = implode( ' | ', $list );
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $pl['at'] ) ), array( 'meta_id' => $pl['am']->meta_id ) );
}
lunaci_np_flush( array_merge( array_keys( $parents ), $b['ids'] ) );

// ---------- verify through WooCommerce ----------
foreach ( $plan as $pid => $pl ) {
	$prod = wc_get_product( $pid );
	$av   = $prod ? $prod->get_available_variations() : array();
	$got  = array();
	foreach ( $av as $v ) {
		$got[ $v['attributes']['attribute_shade'] ] = (int) $v['image_id'];
	}
	ksort( $got );
	$want = array();
	for ( $i = 1; $i <= $keep; $i++ ) {
		$want[ $new( $i ) ] = $media[ $i ];
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
