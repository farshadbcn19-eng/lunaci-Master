<?php
/**
 * Guarded fix: the "ALL" tab in the LUNACI Shop Design filter bar
 * (Code Snippets plugin, snippet id=6, hooked on
 * woocommerce_before_shop_loop) points to wc_get_page_permalink('shop'),
 * i.e. https://lunacibarcelona.com/shop/. A prior session's SEO fix
 * (fix-shop-products-duplicate-catalog.php) added a 301
 * .htaccess redirect for /shop/ -> /products/ (to kill a duplicate
 * WooCommerce-archive-vs-marketing-page catalog) and noindexed post 56
 * in AIOSEO. Nobody re-checked this snippet at the time, so "All" has
 * dead-ended on the marketing page ever since.
 *
 * Per the user's explicit choice: build a real "all products" grid
 * rather than just repointing "All" at /products/, and avoid
 * recreating the duplicate-catalog SEO problem the prior fix solved.
 *
 * This creates a new, dedicated page (slug "all-products") with a
 * real WooCommerce [products] grid (all SKUs, no pagination needed
 * for a 14-item catalog), noindexes it in AIOSEO from day one (so it
 * never becomes a second indexable "all products" URL alongside
 * /products/), extends the LUNACI Shop Design snippet's page-type
 * guards to include it (so it gets the same dark-luxury styling and
 * filter bar as /shop/, category, and product pages), and repoints
 * the "All" tab's URL + active-state at it. woocommerce_shop_page_id
 * and the .htaccess redirect are deliberately left untouched.
 */

global $wpdb;

$snippets_table = $wpdb->prefix . 'snippets';
$aioseo_table   = $wpdb->prefix . 'aioseo_posts';
$slug           = 'all-products';

$old_guard = "    if (!is_shop() && !is_product_category() && !is_product()) return;";
$new_guard = "    if (!is_shop() && !is_product_category() && !is_product() && !is_page( 'all-products' )) return;";

$old_all_tab = "        'all'   => array( 'label' => 'All',   'url' => wc_get_page_permalink( 'shop' ), 'active' => is_shop() ),";
$new_all_tab = "        'all'   => array( 'label' => 'All',   'url' => home_url( '/all-products/' ), 'active' => ( is_shop() || is_page( 'all-products' ) ) ),";

echo "--- STEP A: PREPARE ---\n";

// Idempotent/resumable: a previous run may have already created the page
// and stopped before touching the snippet (e.g. exec() unavailable on this
// host). Reuse it rather than aborting, but only if it's still exactly the
// bare page this script would have created (no unexpected edits).
$existing_page = get_page_by_path( $slug, OBJECT, 'page' );
$reusing_page  = false;
if ( $existing_page ) {
	$existing_edata = get_post_meta( $existing_page->ID, '_elementor_data', true );
	if ( 'All Products' === $existing_page->post_title
		&& 'publish' === $existing_page->post_status
		&& $existing_edata
		&& false !== strpos( $existing_edata, 'shortcode' )
		&& false !== strpos( $existing_edata, 'products' )
	) {
		echo "RESUMING: page '{$slug}' already exists (ID={$existing_page->ID}) with the expected content - reusing it instead of creating a duplicate\n";
		$reusing_page = true;
	} else {
		echo "ABORT: a page with slug '{$slug}' already exists (ID={$existing_page->ID}) but doesn't match what this script would have created - refusing to touch it\n";
		exit( 1 );
	}
} else {
	echo "OK: no existing page at slug '{$slug}'\n";
}

$snippet_row = $wpdb->get_row( $wpdb->prepare( "SELECT id, code FROM {$snippets_table} WHERE id = %d", 6 ), ARRAY_A );
if ( ! $snippet_row ) {
	echo "ABORT: snippet id=6 not found\n";
	exit( 1 );
}
$original_code = $snippet_row['code'];
$original_hash = md5( $original_code );

$guard_count = substr_count( $original_code, $old_guard );
$all_tab_count = substr_count( $original_code, $old_all_tab );
echo "guard line occurrences: {$guard_count} (expected 2)\n";
echo "'all' tab line occurrences: {$all_tab_count} (expected 1)\n";

if ( 2 !== $guard_count || 1 !== $all_tab_count ) {
	echo "ABORT: snippet code does not match the expected preconditions - refusing to edit\n";
	exit( 1 );
}
echo "OK: snippet id=6 preconditions satisfied\n";

$post56_meta = array(
	'_elementor_edit_mode'    => get_post_meta( 56, '_elementor_edit_mode', true ),
	'_elementor_template_type' => get_post_meta( 56, '_elementor_template_type', true ),
	'_elementor_version'      => get_post_meta( 56, '_elementor_version', true ),
	'_elementor_page_settings' => get_post_meta( 56, '_elementor_page_settings', true ),
);
foreach ( $post56_meta as $k => $v ) {
	echo "post56 {$k}: " . var_export( $v, true ) . "\n";
}

echo "\nOK: all STEP A preconditions satisfied\n";

echo "\n--- STEP B: COMMIT ---\n";

if ( $reusing_page ) {
	$new_post_id = $existing_page->ID;
	echo "skipping page creation, reusing page ID={$new_post_id}\n";
} else {
	// 1. Create the new page.
	$new_post_id = wp_insert_post(
		array(
			'post_title'   => 'All Products',
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		),
		true
	);

	if ( is_wp_error( $new_post_id ) ) {
		echo 'ABORT: wp_insert_post failed: ' . $new_post_id->get_error_message() . "\n";
		exit( 1 );
	}
	echo "created page ID={$new_post_id} slug={$slug}\n";

	// 2. Elementor content: one container > inner container > [products] shortcode widget.
	$elementor_data = array(
		array(
			'id'       => 'a11f001',
			'elType'   => 'container',
			'settings' => new stdClass(), // empty object, not [] - Elementor expects {}
			'elements' => array(
				array(
					'id'       => 'a11f002',
					'elType'   => 'container',
					'settings' => array(
						'flex_direction' => 'column',
						'content_width'  => 'full',
					),
					'elements' => array(
						array(
							'id'         => 'a11f003',
							'elType'     => 'widget',
							'settings'   => array(
								'shortcode' => '[products limit="-1" columns="4"]',
							),
							'elements'   => array(),
							'widgetType' => 'shortcode',
						),
					),
					'isInner'  => true,
				),
			),
			'isInner'  => false,
		),
	);

	// update_post_meta() runs wp_unslash() on the value before storing, which
	// strips the backslashes out of wp_json_encode()'s \" escapes (the
	// shortcode's own quoted attributes) and leaves invalid JSON in the DB.
	// $wpdb->insert()/->update() bypass that entirely, so use that instead
	// (same reasoning as fix-shop-collection-links.php's postmeta write).
	$wpdb->insert(
		$wpdb->postmeta,
		array(
			'post_id'    => $new_post_id,
			'meta_key'   => '_elementor_data',
			'meta_value' => wp_json_encode( $elementor_data ),
		)
	);
	update_post_meta( $new_post_id, '_elementor_edit_mode', $post56_meta['_elementor_edit_mode'] ?: 'builder' );
	update_post_meta( $new_post_id, '_elementor_template_type', $post56_meta['_elementor_template_type'] ?: 'wp-page' );
	update_post_meta( $new_post_id, '_elementor_version', $post56_meta['_elementor_version'] ?: '4.0.9' );
	if ( $post56_meta['_elementor_page_settings'] ) {
		update_post_meta( $new_post_id, '_elementor_page_settings', $post56_meta['_elementor_page_settings'] );
	}
	echo "set Elementor postmeta on page {$new_post_id}\n";
}

// 3. Update the snippet, with a concurrency guard.
$fresh_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$snippets_table} WHERE id = %d", 6 ) );
if ( md5( (string) $fresh_code ) !== $original_hash ) {
	echo "ABORT: snippet id=6 code changed since STEP A (concurrent edit) - refusing to write. New page {$new_post_id} was already created; re-run cleanup if needed.\n";
	exit( 1 );
}

$new_code = str_replace( $old_guard, $new_guard, $fresh_code );
$new_code = str_replace( $old_all_tab, $new_all_tab, $new_code );

// This snippet is active + global scope (runs on every front-end request),
// so a syntax error here would break the whole site. exec()/shell_exec()
// are disabled on this host (confirmed by a prior run), so `php -l` isn't
// available from inside PHP. Two mitigations instead:
//  1. Static check: braces/parens/quotes must balance exactly the same
//     before and after the edit, since the only change is swapping two
//     known-good, already-hand-verified expressions in place - if counts
//     drift, something matched wrong and we abort before writing.
//  2. A live HTTP smoke test right after the write (below, STEP C) with
//     automatic rollback to $original_code if it fails.
$balance_check = function ( $code ) {
	return array(
		substr_count( $code, '(' ) - substr_count( $code, ')' ),
		substr_count( $code, '{' ) - substr_count( $code, '}' ),
		substr_count( $code, "'" ) % 2,
		substr_count( $code, '"' ) % 2,
	);
};
if ( $balance_check( $fresh_code ) !== $balance_check( $new_code ) ) {
	echo "ABORT: brace/paren/quote balance changed by the edit - refusing to write (site-wide, active, global-scope snippet)\n";
	exit( 1 );
}
echo "OK: brace/paren/quote balance unchanged by the edit\n";

$updated = $wpdb->update(
	$snippets_table,
	array( 'code' => $new_code ),
	array( 'id' => 6 ),
	array( '%s' ),
	array( '%d' )
);
echo '$wpdb->update() rows affected on snippet id=6: ' . var_export( $updated, true ) . "\n";

// 4. Noindex the new page in AIOSEO, mirroring how post 56 was noindexed.
// wp_insert_post() fires save_post, which AIOSEO listens to and should have
// already created its own row with defaults - just flip it to noindex.
$aioseo_row = $wpdb->get_row( $wpdb->prepare( "SELECT id, robots_default, robots_noindex FROM {$aioseo_table} WHERE post_id = %d", $new_post_id ), ARRAY_A );
if ( $aioseo_row ) {
	$result = $wpdb->update(
		$aioseo_table,
		array( 'robots_default' => 0, 'robots_noindex' => 1 ),
		array( 'post_id' => $new_post_id )
	);
	echo 'aioseo_posts robots update for post_id=' . $new_post_id . ': ' . var_export( $result, true ) . "\n";
} else {
	echo "NOTE: no aioseo_posts row found yet for post_id={$new_post_id} - noindex not set via AIOSEO table (page was still created; may need a manual AIOSEO check)\n";
}

wp_cache_flush();
clean_post_cache( $new_post_id );
clean_post_cache( 56 );

echo "\n--- STEP C: VERIFY ---\n";
$overall_success = true;

$verify_page = get_post( $new_post_id );
if ( ! $verify_page || 'publish' !== $verify_page->post_status ) {
	echo "MISMATCH: page {$new_post_id} not found or not published\n";
	$overall_success = false;
} else {
	echo "OK: page {$new_post_id} exists, status={$verify_page->post_status}, permalink=" . get_permalink( $new_post_id ) . "\n";
}

$verify_edata = get_post_meta( $new_post_id, '_elementor_data', true );
if ( $verify_edata && false !== strpos( $verify_edata, 'products' ) && false !== strpos( $verify_edata, 'shortcode' ) ) {
	echo "OK: _elementor_data contains the products shortcode widget\n";
} else {
	echo "MISMATCH: _elementor_data missing expected shortcode content\n";
	$overall_success = false;
}

$verify_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$snippets_table} WHERE id = %d", 6 ) );
$remaining_old_guard = substr_count( $verify_code, $old_guard );
$new_guard_count = substr_count( $verify_code, $new_guard );
$remaining_old_tab = substr_count( $verify_code, $old_all_tab );
$new_tab_count = substr_count( $verify_code, $new_all_tab );

echo "remaining old guard occurrences: {$remaining_old_guard} (expected 0)\n";
echo "new guard occurrences: {$new_guard_count} (expected 2)\n";
echo "remaining old 'all' tab occurrences: {$remaining_old_tab} (expected 0)\n";
echo "new 'all' tab occurrences: {$new_tab_count} (expected 1)\n";

if ( 0 !== $remaining_old_guard || 2 !== $new_guard_count || 0 !== $remaining_old_tab || 1 !== $new_tab_count ) {
	$overall_success = false;
}

// Live smoke test: no exec() means no php -l, so this is the real safety
// net for the active, global-scope snippet edit. If the shop/category
// pages don't come back clean, roll the snippet back to $original_code
// immediately rather than leaving a broken site-wide snippet live.
$smoke_urls = array( home_url( '/product-category/face/' ), home_url( '/all-products/' ), home_url( '/' ) );
$smoke_ok = true;
foreach ( $smoke_urls as $smoke_url ) {
	$resp = wp_remote_get( $smoke_url, array( 'timeout' => 20 ) );
	if ( is_wp_error( $resp ) ) {
		echo "SMOKE TEST FAIL: {$smoke_url} -> " . $resp->get_error_message() . "\n";
		$smoke_ok = false;
		continue;
	}
	$code = wp_remote_retrieve_response_code( $resp );
	$body = wp_remote_retrieve_body( $resp );
	$has_fatal = ( false !== stripos( $body, 'Fatal error' ) || false !== stripos( $body, 'Parse error' ) || false !== stripos( $body, 'syntax error' ) );
	echo "SMOKE TEST {$smoke_url}: HTTP {$code}, fatal/parse error in body: " . ( $has_fatal ? 'YES' : 'no' ) . "\n";
	if ( 200 !== (int) $code || $has_fatal ) {
		$smoke_ok = false;
	}
}

if ( ! $smoke_ok ) {
	echo "SMOKE TEST FAILED - rolling snippet id=6 back to its original code immediately\n";
	$rollback = $wpdb->update(
		$snippets_table,
		array( 'code' => $original_code ),
		array( 'id' => 6 ),
		array( '%s' ),
		array( '%d' )
	);
	echo 'rollback $wpdb->update() rows affected: ' . var_export( $rollback, true ) . "\n";
	wp_cache_flush();
	echo "ABORT: rolled back - see smoke test results above\n";
	exit( 1 );
}
echo "OK: smoke test passed on all checked URLs\n";

$verify_aioseo = $wpdb->get_row( $wpdb->prepare( "SELECT robots_default, robots_noindex FROM {$aioseo_table} WHERE post_id = %d", $new_post_id ), ARRAY_A );
if ( $verify_aioseo ) {
	echo 'aioseo robots_default=' . $verify_aioseo['robots_default'] . ' robots_noindex=' . $verify_aioseo['robots_noindex'] . " (expected 0 / 1)\n";
	if ( '0' !== (string) $verify_aioseo['robots_default'] || '1' !== (string) $verify_aioseo['robots_noindex'] ) {
		echo "NOTE: aioseo robots not in expected noindex state (non-fatal, may need manual check in AIOSEO)\n";
	}
} else {
	echo "NOTE: no aioseo_posts row for the new page yet (non-fatal, may need manual check)\n";
}

echo "\nnew page ID: {$new_post_id}\n";
echo "new page URL: " . get_permalink( $new_post_id ) . "\n";

echo "\n=====================================================================\n";
if ( $overall_success ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE - see per-check results above\n";
	exit( 1 );
}
