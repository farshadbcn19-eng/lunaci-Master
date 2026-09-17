<?php
/**
 * Guarded fix: the /products/ page (post ID 61, confirmed live via
 * url_to_postid('/products/') in diagnose-shop-collection-links.php) has
 * one HTML widget whose markup contains 4 "Discover Collection" cards
 * (Face / Eye / Lip / Nail) that all link to the exact same generic
 * https://lunacibarcelona.com/shop/ URL instead of their own
 * /product-category/{slug}/ archive - so browsing by category is a
 * dead end that lands the visitor back on the page they're already on.
 * The footer's "Collections" column (Face/Eye/Lip/Nail) has the same
 * bug, in the same widget.
 *
 * _elementor_data is a JSON document; the HTML widget's markup is one
 * of its string values (JSON-escaped: \" \/ \uXXXX). Rather than hand-
 * write escaped str_replace patterns against that raw text, this
 * decodes the JSON, finds the one widget settings array whose 'html'
 * key contains "Discover Collection", edits the DECODED (plain) HTML
 * string, then re-encodes and writes back - so every replacement below
 * is a normal, readable string operation.
 *
 * Follows the same guarded STEP A/B/C pattern as
 * fix-shop-grid-reorder.php: read + verify preconditions, re-check
 * nothing changed underneath us, write, verify, clear caches.
 */

global $wpdb;

const LUNACI_PRODUCTS_POST_ID = 61;

// [byte-order-in-page => correct category slug], matching the confirmed
// card order (Collection I=Face, II=Eye, III=Lip, IV=Nail).
$card_order = array( 'face', 'eyes', 'lips', 'nails' );

// Footer "Collections" column: link text => correct category slug.
$footer_map = array(
	'Face' => 'face',
	'Eye'  => 'eyes',
	'Lip'  => 'lips',
	'Nail' => 'nails',
);

$wrong_url = 'https://lunacibarcelona.com/shop/';

function lunaci_find_html_widgets( &$node, &$out ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['settings'] ) && is_array( $node['settings'] ) && isset( $node['settings']['html'] ) && is_string( $node['settings']['html'] ) ) {
		$out[] =& $node['settings']['html'];
	}
	foreach ( $node as $key => &$child ) {
		if ( is_array( $child ) ) {
			lunaci_find_html_widgets( $child, $out );
		}
	}
}

echo "--- STEP A: PREPARE ---\n";

$row = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'",
		LUNACI_PRODUCTS_POST_ID
	),
	ARRAY_A
);

if ( ! $row ) {
	echo "ABORT: no _elementor_data found for post " . LUNACI_PRODUCTS_POST_ID . "\n";
	exit( 1 );
}

$meta_id = (int) $row['meta_id'];
$original_raw = $row['meta_value'];
$original_hash = md5( $original_raw );
echo "post_id=" . LUNACI_PRODUCTS_POST_ID . " meta_id={$meta_id} len=" . strlen( $original_raw ) . " md5={$original_hash}\n";

$data = json_decode( $original_raw, true );
if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
	echo "ABORT: json_decode failed: " . json_last_error_msg() . "\n";
	exit( 1 );
}

$html_refs = array();
lunaci_find_html_widgets( $data, $html_refs );

$matches = array();
foreach ( $html_refs as $i => &$html ) {
	if ( false !== strpos( $html, 'Discover Collection' ) ) {
		$matches[] =& $html;
	}
}
unset( $html );

echo 'HTML widgets found: ' . count( $html_refs ) . ", containing 'Discover Collection': " . count( $matches ) . "\n";

if ( 1 !== count( $matches ) ) {
	echo "ABORT: expected exactly 1 HTML widget containing 'Discover Collection', found " . count( $matches ) . " - refusing to guess which one to edit\n";
	exit( 1 );
}

$html =& $matches[0];

$card_link = '<a href="' . $wrong_url . '" class="cat-link">Discover Collection</a>';
$card_count = substr_count( $html, $card_link );
echo "cat-link occurrences of the generic /shop/ URL: {$card_count} (expected 4)\n";
if ( 4 !== $card_count ) {
	echo "ABORT: expected exactly 4 occurrences, found {$card_count} - refusing to write\n";
	exit( 1 );
}

$footer_checks = array();
foreach ( $footer_map as $label => $slug ) {
	$needle = '<a href="' . $wrong_url . '">' . $label . '</a>';
	$footer_checks[ $label ] = substr_count( $html, $needle );
	echo "footer link check '{$label}': {$footer_checks[$label]} occurrence(s) of the generic /shop/ URL\n";
}

echo "OK: preconditions satisfied\n";

echo "\n--- STEP B: COMMIT (in-memory edits) ---\n";

// Cards: 4 byte-identical occurrences in document order = Face, Eye, Lip, Nail.
$card_index = 0;
$html = preg_replace_callback(
	'/' . preg_quote( $card_link, '/' ) . '/',
	function ( $m ) use ( &$card_index, $card_order, $wrong_url ) {
		$slug = $card_order[ $card_index ] ?? null;
		$card_index++;
		if ( null === $slug ) {
			return $m[0]; // safety net, should never trigger given the count check above
		}
		return str_replace(
			$wrong_url,
			'https://lunacibarcelona.com/product-category/' . $slug . '/',
			$m[0]
		);
	},
	$html
);
echo "cat-link replacements made: {$card_index} (expected 4)\n";

// Footer: each label's href is only touched if it was found exactly once
// (the diagnostic step above), so a footer layout surprise never causes
// a partial/ambiguous write - it's just skipped and reported.
$footer_fixed = array();
foreach ( $footer_map as $label => $slug ) {
	if ( 1 !== ( $footer_checks[ $label ] ?? 0 ) ) {
		echo "skipping footer '{$label}' link (expected exactly 1 occurrence, saw " . ( $footer_checks[ $label ] ?? 0 ) . ")\n";
		continue;
	}
	$old = '<a href="' . $wrong_url . '">' . $label . '</a>';
	$new = '<a href="https://lunacibarcelona.com/product-category/' . $slug . '/">' . $label . '</a>';
	$html = str_replace( $old, $new, $html );
	$footer_fixed[] = $label;
}
echo 'footer links fixed: ' . ( $footer_fixed ? implode( ', ', $footer_fixed ) : '(none)' ) . "\n";

$new_raw = wp_json_encode( $data );
if ( ! is_string( $new_raw ) ) {
	echo "ABORT: re-encoding _elementor_data failed\n";
	exit( 1 );
}
echo 'new _elementor_data length: ' . strlen( $new_raw ) . " (was " . strlen( $original_raw ) . ")\n";

echo "\n--- STEP B: COMMIT (write, with concurrency guard) ---\n";
$fresh_raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $meta_id )
);
if ( md5( (string) $fresh_raw ) !== $original_hash ) {
	echo "ABORT: _elementor_data changed since STEP A (concurrent edit) - refusing to write\n";
	exit( 1 );
}

$updated = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => $new_raw ),
	array( 'meta_id' => $meta_id ),
	array( '%s' ),
	array( '%d' )
);
echo '$wpdb->update() rows affected: ' . var_export( $updated, true ) . "\n";

// Known gotcha in this project (see git log "ROOT CAUSE FOUND: clear
// Elementor's Document::CACHE_META_KEY postmeta"): Elementor caches its
// rendered HTML separately from _elementor_data, so a raw postmeta edit
// alone can keep serving the old markup until that cache is cleared.
$deleted_cache = delete_post_meta( LUNACI_PRODUCTS_POST_ID, '_elementor_element_cache' );
echo 'cleared _elementor_element_cache: ' . var_export( $deleted_cache, true ) . "\n";
clean_post_cache( LUNACI_PRODUCTS_POST_ID );
wp_cache_flush();

echo "\n--- STEP C: VERIFY ---\n";
$verify_raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $meta_id )
);
$overall_success = true;

if ( $verify_raw !== $new_raw ) {
	echo "MISMATCH: stored value does not match what we wrote\n";
	$overall_success = false;
} else {
	echo "OK: stored _elementor_data matches expected new value\n";
}

$verify_data = json_decode( $verify_raw, true );
$verify_refs = array();
lunaci_find_html_widgets( $verify_data, $verify_refs );
$verify_html = null;
foreach ( $verify_refs as &$h ) {
	if ( false !== strpos( $h, 'Discover Collection' ) ) {
		$verify_html =& $h;
		break;
	}
}
unset( $h );

if ( null === $verify_html ) {
	echo "MISMATCH: could not re-locate the HTML widget after write\n";
	$overall_success = false;
} else {
	$remaining_wrong = substr_count( $verify_html, $card_link );
	echo "remaining generic-/shop/ cat-link occurrences: {$remaining_wrong} (expected 0)\n";
	if ( 0 !== $remaining_wrong ) {
		$overall_success = false;
	}
	foreach ( array( 'face', 'eyes', 'lips', 'nails' ) as $slug ) {
		$count = substr_count( $verify_html, 'product-category/' . $slug . '/" class="cat-link"' );
		echo "cat-link -> product-category/{$slug}/ present: {$count} (expected 1)\n";
		if ( 1 !== $count ) {
			$overall_success = false;
		}
	}
	foreach ( $footer_fixed as $label ) {
		$slug = $footer_map[ $label ];
		$count = substr_count( $verify_html, 'product-category/' . $slug . '/">' . $label . '</a>' );
		echo "footer '{$label}' -> product-category/{$slug}/ present: {$count} (expected 1)\n";
		if ( 1 !== $count ) {
			$overall_success = false;
		}
	}
}

$cache_after = get_post_meta( LUNACI_PRODUCTS_POST_ID, '_elementor_element_cache', true );
echo '_elementor_element_cache after fix: ' . var_export( $cache_after, true ) . " (expected empty)\n";

echo "\n=====================================================================\n";
if ( $overall_success ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE - see per-check results above\n";
	exit( 1 );
}
