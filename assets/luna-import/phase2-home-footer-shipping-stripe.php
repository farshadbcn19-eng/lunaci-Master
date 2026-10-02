<?php
/**
 * LUNACI Phase 2 (2026-10-02): Home footer social links, shipping zones,
 * Stripe in test mode.
 *
 * Usage (from the workflow):
 *   wp eval-file phase2-home-footer-shipping-stripe.php dry-run <backup_dir>
 *   wp eval-file phase2-home-footer-shipping-stripe.php apply   <backup_dir>
 *
 * Decisions (Farshad, 2026-10-02):
 * - Home footer: approved to fix IG / remove TK, PT / add LinkedIn, exactly
 *   as already done on About. Nothing else on Home changes.
 * - Shipping: Spain (peninsula + Balearics) free; rest of the EU EUR 14.90
 *   flat; Canary Islands, Ceuta and Melilla excluded for now.
 *   Live state 2026-10-02: no shipping zones at all, rest-of-world has 0
 *   methods, so checkout could not complete for any address.
 * - Stripe: start in test mode. Farshad connects his Stripe account himself.
 *
 * Same guarded helpers as Phase 0/1: dry-run writes nothing; apply backs up
 * before every write and verifies after.
 */

global $wpdb;

$mode       = $args[0] ?? 'dry-run';
$backup_dir = $args[1] ?? '';
$apply      = ( 'apply' === $mode );

if ( ! in_array( $mode, array( 'dry-run', 'apply' ), true ) ) {
	echo "ABORT: mode must be 'dry-run' or 'apply' (got '{$mode}')\n";
	exit( 1 );
}
if ( $apply ) {
	if ( '' === $backup_dir || ! is_dir( $backup_dir ) || ! is_writable( $backup_dir ) ) {
		echo "ABORT: apply mode needs an existing writable backup dir (got '{$backup_dir}')\n";
		exit( 1 );
	}
}

echo "MODE: {$mode}\n";
echo $apply ? "BACKUP DIR: {$backup_dir}\n" : "(dry-run: nothing is written)\n";

// wp eval-file includes this file inside a method, so top-level variables
// are not real globals. Shared state lives in $GLOBALS explicitly.
$GLOBALS['p0_apply']      = $apply;
$GLOBALS['p0_backup_dir'] = $backup_dir;
$GLOBALS['p0_fail']       = 0;

function p0_h( $title ) {
	echo "\n==========================================================================\n{$title}\n==========================================================================\n";
}

function p0_backup_write( $name, $data ) {
	$path  = rtrim( $GLOBALS['p0_backup_dir'], '/' ) . '/' . $name;
	$bytes = file_put_contents( $path, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	if ( false === $bytes ) {
		echo "ERROR: could not write backup {$path}\n";
		return false;
	}
	echo "backup: {$path} ({$bytes} bytes)\n";
	return true;
}

/** Full backup of a post: row, every postmeta row, AIOSEO row. */
function p0_backup_post( $post_id ) {
	global $wpdb;
	// Keep the first (pre-change) snapshot: a post edited twice in one run
	// must not have its backup overwritten by the already-modified state.
	if ( file_exists( rtrim( $GLOBALS['p0_backup_dir'], '/' ) . "/post-{$post_id}.json" ) ) {
		echo "backup: post-{$post_id}.json already holds the pre-run snapshot - kept\n";
		return true;
	}
	$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A );
	$meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $post_id ), ARRAY_A );
	$aio  = null;
	$tbl  = $wpdb->prefix . 'aioseo_posts';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl ) {
		$aio = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tbl}` WHERE post_id = %d", $post_id ), ARRAY_A );
	}
	return p0_backup_write( "post-{$post_id}.json", array( 'post' => $post, 'postmeta' => $meta, 'aioseo_posts' => $aio ) );
}

/** Walk Elementor elements; apply $fn to the single HTML widget whose html contains $needle. */
function p0_walk( array $nodes, $needle, callable $fn, &$hits ) {
	foreach ( $nodes as $k => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( 'html' === ( $node['widgetType'] ?? '' )
			&& isset( $node['settings']['html'] ) && is_string( $node['settings']['html'] )
			&& false !== strpos( $node['settings']['html'], $needle ) ) {
			$hits++;
			$nodes[ $k ]['settings']['html'] = $fn( $node['settings']['html'] );
		}
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			$nodes[ $k ]['elements'] = p0_walk( $node['elements'], $needle, $fn, $hits );
		}
	}
	return $nodes;
}

/** Replace from $start marker up to (not including) $end marker. Both must occur exactly once, in order. */
function p0_between( $html, $start, $end, $replacement, $include_end, &$errors ) {
	$cs = substr_count( $html, $start );
	$ce = substr_count( $html, $end );
	if ( 1 !== $cs || 1 !== $ce ) {
		$errors[] = "marker count: '{$start}'={$cs}, '{$end}'={$ce} (expected 1 / 1)";
		return $html;
	}
	$a = strpos( $html, $start );
	$b = strpos( $html, $end );
	if ( $b <= $a ) {
		$errors[] = "marker order: '{$end}' is not after '{$start}'";
		return $html;
	}
	if ( $include_end ) {
		$b += strlen( $end );
	}
	return substr( $html, 0, $a ) . $replacement . substr( $html, $b );
}

function p0_preg_once( $pattern, $replacement, $html, $label, &$errors ) {
	$out = preg_replace( $pattern, $replacement, $html, -1, $count );
	if ( 1 !== $count ) {
		$errors[] = "{$label}: expected exactly 1 match, found {$count}";
		return $html;
	}
	return $out;
}

function p0_str_once( $search, $replace, $html, $label, &$errors ) {
	$count = substr_count( $html, $search );
	if ( 1 !== $count ) {
		$errors[] = "{$label}: expected exactly 1 occurrence, found {$count}";
		return $html;
	}
	return str_replace( $search, $replace, $html );
}

function p0_elementor_cache_key() {
	if ( class_exists( '\Elementor\Core\Base\Document' ) ) {
		$c = ( new ReflectionClass( '\Elementor\Core\Base\Document' ) )->getConstants();
		if ( ! empty( $c['CACHE_META_KEY'] ) ) {
			return $c['CACHE_META_KEY'];
		}
	}
	return '_elementor_element_cache';
}

/**
 * Guarded edit of one HTML widget inside a post's _elementor_data.
 * $transform( $html, &$errors ) returns the new html; any error aborts the post.
 * $forbidden: strings that must be absent from the new widget html.
 * $required:  strings that must be present in the new widget html.
 */
function p0_edit_elementor( $post_id, $label, $needle, callable $transform, array $forbidden, array $required ) {
	global $wpdb;
	$apply = $GLOBALS['p0_apply'];
	p0_h( "{$label} (post {$post_id})" );

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $post_id ), ARRAY_A );
	if ( ! $row ) {
		echo "ERROR: no _elementor_data row - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}
	$data = json_decode( $row['meta_value'], true );
	if ( ! is_array( $data ) ) {
		echo "ERROR: _elementor_data does not decode - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}

	$errors   = array();
	$old_html = null;
	$new_html = null;
	$hits     = 0;
	$new_data = p0_walk(
		$data,
		$needle,
		function ( $html ) use ( $transform, &$errors, &$old_html, &$new_html ) {
			$old_html = $html;
			$new_html = $transform( $html, $errors );
			return $new_html;
		},
		$hits
	);

	if ( 1 !== $hits ) {
		echo "ERROR: expected exactly 1 HTML widget containing '{$needle}', found {$hits} - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}

	echo "widget html length: " . strlen( $old_html ) . ' -> ' . strlen( $new_html ) . "\n";
	echo "BEFORE stored widget: <form=" . substr_count( $old_html, '<form' ) . ' <input=' . substr_count( $old_html, '<input' ) . ' <select=' . substr_count( $old_html, '<select' ) . ' <option=' . substr_count( $old_html, '<option' ) . ' <script=' . substr_count( $old_html, '<script' ) . "\n";

	foreach ( $forbidden as $f ) {
		$c = substr_count( $new_html, $f );
		if ( $c > 0 ) {
			$errors[] = "forbidden string still present after transform: '{$f}' x{$c}";
		}
	}
	foreach ( $required as $r ) {
		if ( false === strpos( $new_html, $r ) ) {
			$errors[] = "required string missing after transform: '{$r}'";
		}
	}

	if ( $errors ) {
		echo "GUARDS FAILED - nothing will be written for post {$post_id}:\n";
		foreach ( $errors as $e ) {
			echo "  ! {$e}\n";
		}
		$GLOBALS['p0_fail']++;
		return;
	}
	if ( $new_html === $old_html ) {
		echo "SKIP: already in the desired state\n";
		return;
	}
	echo "OK: all guards passed\n";

	$new_json = wp_json_encode( $new_data );
	if ( ! $new_json || json_decode( $new_json, true ) !== $new_data ) {
		echo "ERROR: re-encoded JSON does not round-trip - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}

	if ( ! $apply ) {
		echo "DRY-RUN: would write new _elementor_data (" . strlen( $row['meta_value'] ) . ' -> ' . strlen( $new_json ) . " bytes)\n";
		echo "----- NEW WIDGET HTML (markup only, CSS block omitted) -----\n";
		$markup_at = strpos( $new_html, '</style>' );
		echo ( false !== $markup_at ? substr( $new_html, $markup_at + 8 ) : $new_html ) . "\n";
		echo "----- END -----\n";
		return;
	}

	if ( ! p0_backup_post( $post_id ) ) {
		echo "ERROR: backup failed - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}
	file_put_contents( rtrim( $GLOBALS['p0_backup_dir'], '/' ) . "/post-{$post_id}-widget-before.html", $old_html );

	$ok = $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new_json ), array( 'meta_id' => $row['meta_id'] ), array( '%s' ), array( '%d' ) );
	if ( false === $ok ) {
		echo "ERROR: update failed: {$wpdb->last_error}\n";
		$GLOBALS['p0_fail']++;
		return;
	}
	$verify = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $row['meta_id'] ) );
	$match  = ( $verify === $new_json );
	echo 'VERIFY stored bytes match: ' . ( $match ? 'YES' : 'NO' ) . "\n";
	if ( ! $match ) {
		$GLOBALS['p0_fail']++;
	}

	$cache_key = p0_elementor_cache_key();
	delete_post_meta( $post_id, $cache_key );
	clean_post_cache( $post_id );
	echo "cleared Elementor cache meta '{$cache_key}' + post cache\n";
}

/* --------------------------------------------------------------------------
 * 1. Home footer social links (approved by Farshad 2026-10-02)
 *    Only the .ln-foot__s block changes; every other byte of the Home widget
 *    is verified identical before writing.
 * ----------------------------------------------------------------------- */

$home_social = array(
	57  => array( 'Instagram' => 'LUNACI Barcelona on Instagram', 'LinkedIn' => 'LUNACI Barcelona on LinkedIn' ),
	772 => array( 'Instagram' => 'LUNACI Barcelona en Instagram', 'LinkedIn' => 'LUNACI Barcelona en LinkedIn' ),
);

foreach ( $home_social as $post_id => $labels ) {
	$block = '<div class="ln-foot__s">' . "\n"
		. '      <a href="https://www.instagram.com/lunaci.barcelona/" target="_blank" rel="noopener" aria-label="' . $labels['Instagram'] . '">IG</a>' . "\n"
		. '      <a href="https://www.linkedin.com/company/lunaci-barcelona/" target="_blank" rel="noopener" aria-label="' . $labels['LinkedIn'] . '">IN</a>' . "\n"
		. '    </div>';
	p0_edit_elementor(
		$post_id,
		"HOME FOOTER SOCIAL (post {$post_id})",
		'ln-foot__s',
		function ( $html, &$errors ) use ( $block ) {
			if ( ! preg_match( '#<div class="ln-foot__s">.*?</div>#s', $html, $m ) ) {
				$errors[] = 'ln-foot__s block not found';
				return $html;
			}
			$new = p0_preg_once( '#<div class="ln-foot__s">.*?</div>#s', $block, $html, 'ln-foot__s block', $errors );
			// Prove nothing else changed: swapping the old block back must give the original.
			if ( str_replace( $block, $m[0], $new ) !== $html ) {
				$errors[] = 'change is not limited to the footer social block';
			}
			return $new;
		},
		array( '>TK<', '>PT<', '<a href="#">IG</a>' ),
		array( 'https://www.instagram.com/lunaci.barcelona/', 'https://www.linkedin.com/company/lunaci-barcelona/', 'aria-label="LUNACI Barcelona' )
	);
}

// Search/feed fallback text of Home EN still lists TK/PT; regenerate it after
// the footer change, same method as Phase 1.
foreach ( array( 57 => 'Home EN', 772 => 'Home ES' ) as $post_id => $label ) {
	p0_h( "POST_CONTENT CLEANUP {$label} (post {$post_id})" );
	$content = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
	if ( false === strpos( $content, '>TK<' ) && false === strpos( $content, '>PT<' ) ) {
		echo "SKIP: nothing stale\n";
		continue;
	}
	if ( ! $apply ) {
		echo "DRY-RUN: post_content has TK/PT; would regenerate it from the Elementor render after the footer change\n";
		continue;
	}
	$rendered = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id );
	$text     = preg_replace( '#<(style|script|svg|title|noscript|form)\b[^>]*>.*?</\1>#is', '', (string) $rendered );
	$text     = preg_replace( '#<!--.*?-->#s', '', $text );
	$text     = wp_kses( $text, array( 'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'p' => array(), 'a' => array( 'href' => true ), 'br' => array(), 'em' => array(), 'strong' => array(), 'ul' => array(), 'li' => array() ) );
	$text     = trim( preg_replace( "/\n\s*\n+/", "\n\n", preg_replace( '/[ \t]+/', ' ', $text ) ) );
	if ( strlen( $text ) < 200 || false !== strpos( $text, '>TK<' ) ) {
		echo 'GUARD FAILED: regenerated text too short or still has TK (' . strlen( $text ) . " bytes) - untouched\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	if ( ! p0_backup_post( $post_id ) ) {
		$GLOBALS['p0_fail']++;
		continue;
	}
	$ok = $wpdb->update( $wpdb->posts, array( 'post_content' => $text ), array( 'ID' => $post_id ) );
	clean_post_cache( $post_id );
	echo 'VERIFY post_content updated: ' . ( false !== $ok ? 'YES (' . strlen( $text ) . ' bytes)' : 'NO' ) . "\n";
}

/* --------------------------------------------------------------------------
 * 2. Shipping zones (decided by Farshad 2026-10-02)
 *    - Spain, peninsula + Balearics: free shipping
 *    - Rest of the EU (26 countries): flat rate EUR 14.90
 *    - Canary Islands, Ceuta, Melilla: excluded for now (no zone, no method)
 * ----------------------------------------------------------------------- */

p0_h( 'SHIPPING ZONES' );

$virtual = $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_virtual' AND m.meta_value = 'yes' WHERE p.post_type IN ('product','product_variation') AND p.post_status = 'publish'" );
echo 'published products/variations marked virtual (no shipping): ' . ( $virtual ? implode( ',', $virtual ) : 'none' ) . "\n";
echo 'woocommerce_ship_to_countries = ' . var_export( get_option( 'woocommerce_ship_to_countries' ), true ) . "\n";

$calc_taxes = get_option( 'woocommerce_calc_taxes' );
$incl_tax   = get_option( 'woocommerce_prices_include_tax' );
echo "woocommerce_calc_taxes = {$calc_taxes}, woocommerce_prices_include_tax = {$incl_tax}\n";

$eu26 = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'SE' );
$es_states   = array_keys( (array) WC()->countries->get_states( 'ES' ) );
$es_excluded = array( 'GC', 'TF', 'CE', 'ML' ); // Las Palmas, Santa Cruz de Tenerife, Ceuta, Melilla
$es_included = array_values( array_diff( $es_states, $es_excluded ) );
echo 'ES states known to WooCommerce: ' . count( $es_states ) . '; included: ' . count( $es_included ) . '; excluded: ' . implode( ',', array_intersect( $es_excluded, $es_states ) ) . "\n";

$existing = array();
foreach ( WC_Shipping_Zones::get_zones() as $z ) {
	$existing[ $z['zone_name'] ] = $z['id'];
	echo "existing zone #{$z['id']} '{$z['zone_name']}'\n";
}

$zones = array(
	'España (Península y Baleares)' => array(
		'locations' => array_map( function ( $s ) { return array( 'code' => 'ES:' . $s, 'type' => 'state' ); }, $es_included ),
		'method'    => 'free_shipping',
		'settings'  => array( 'title' => 'Free shipping · 5–8 business days', 'requires' => '' ),
	),
	'European Union' => array(
		'locations' => array_map( function ( $c ) { return array( 'code' => $c, 'type' => 'country' ); }, $eu26 ),
		'method'    => 'flat_rate',
		'settings'  => array( 'title' => 'Standard shipping', 'cost' => '14.90', 'tax_status' => 'none' ),
	),
);

$zone_guard_ok = true;
if ( count( $es_states ) < 40 || array_diff( $es_excluded, $es_states ) ) {
	echo "GUARD FAILED: unexpected WooCommerce ES state list - zones not created\n";
	$zone_guard_ok = false;
	$GLOBALS['p0_fail']++;
}
if ( 'yes' === $calc_taxes ) {
	// With taxes on, WooCommerce adds VAT on top of a taxable shipping cost.
	// tax_status=none keeps the customer price at exactly EUR 14.90 as decided;
	// the accountant should confirm how shipping VAT is booked.
	echo "NOTE: taxes are enabled - EU flat rate set as tax_status=none so the customer pays exactly EUR 14.90\n";
}

foreach ( $zones as $name => $z ) {
	$zone_id = $existing[ $name ] ?? null;
	echo ( $zone_id ? "zone '{$name}' exists (#{$zone_id}) - will verify/repair its method" : "will create zone '{$name}'" )
		. ': ' . count( $z['locations'] ) . " locations, method {$z['method']} " . wp_json_encode( $z['settings'], JSON_UNESCAPED_UNICODE ) . "\n";
	if ( ! $apply || ! $zone_guard_ok ) {
		continue;
	}
	$zone = $zone_id ? new WC_Shipping_Zone( $zone_id ) : new WC_Shipping_Zone();
	if ( ! $zone_id ) {
		$zone->set_zone_name( $name );
		$zone->set_locations( $z['locations'] );
		$zone->save();
	}
	// Re-runs repair a zone left incomplete by an earlier failed apply.
	$instance_id = 0;
	foreach ( $zone->get_shipping_methods() as $method ) {
		if ( $method->id === $z['method'] ) {
			$instance_id = $method->instance_id;
			break;
		}
	}
	if ( ! $instance_id ) {
		$instance_id = $zone->add_shipping_method( $z['method'] );
	}
	if ( ! $instance_id ) {
		echo "ERROR: could not add {$z['method']} to '{$name}'\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	$option_key = "woocommerce_{$z['method']}_{$instance_id}_settings";
	$current    = (array) get_option( $option_key, array() );
	$settings   = array_merge( $current, $z['settings'] );
	if ( $settings !== $current ) {
		update_option( $option_key, $settings );
	}
	$check = (array) get_option( $option_key, array() );
	$ok    = ! array_diff_assoc( $z['settings'], $check );
	echo "zone #{$zone->get_id()} '{$name}': {$z['method']} instance {$instance_id}, settings " . ( $ok ? 'OK' : 'MISMATCH' ) . "\n";
	if ( ! $ok ) {
		$GLOBALS['p0_fail']++;
	}
}

if ( $apply ) {
	WC_Cache_Helper::get_transient_version( 'shipping', true );
	echo "VERIFY zones now:\n";
	foreach ( WC_Shipping_Zones::get_zones() as $z ) {
		$m = array_map( function ( $x ) { return $x->id . ':' . $x->get_title() . ( isset( $x->cost ) && '' !== $x->cost ? ' ' . $x->cost : '' ); }, $z['shipping_methods'] );
		echo "  #{$z['id']} '{$z['zone_name']}' locations=" . count( $z['zone_locations'] ) . ' methods=' . implode( ', ', $m ) . "\n";
	}
}

/* --------------------------------------------------------------------------
 * 3. Stripe (WooCommerce Stripe Payment Gateway, free official plugin).
 *    The workflow installs/activates the plugin before this runs. Here only
 *    non-secret settings are set: test mode on, statement descriptor.
 *    The account itself is connected by Farshad with "Connect with Stripe"
 *    (WooCommerce > Settings > Payments > Stripe). No keys pass through here.
 * ----------------------------------------------------------------------- */

p0_h( 'STRIPE' );
$stripe_active = class_exists( 'WC_Stripe' ) || defined( 'WC_STRIPE_VERSION' );
echo 'plugin active: ' . ( $stripe_active ? 'YES' . ( defined( 'WC_STRIPE_VERSION' ) ? ' v' . WC_STRIPE_VERSION : '' ) : 'NO' ) . "\n";
$stripe = get_option( 'woocommerce_stripe_settings', array() );
$stripe = is_array( $stripe ) ? $stripe : array();
$has_keys = ! empty( $stripe['test_publishable_key'] ) || ! empty( $stripe['publishable_key'] );
echo 'current: enabled=' . ( $stripe['enabled'] ?? '(unset)' ) . ' testmode=' . ( $stripe['testmode'] ?? '(unset)' ) . ' account connected=' . ( $has_keys ? 'YES' : 'NO' ) . "\n";

$wanted = array(
	'testmode'             => 'yes',
	'statement_descriptor' => 'LUNACI BARCELONA',
	'short_statement_descriptor' => 'LUNACI',
	'capture'              => 'yes',
);
$diff = array();
foreach ( $wanted as $k => $v ) {
	if ( ( $stripe[ $k ] ?? null ) !== $v ) {
		$diff[ $k ] = $v;
	}
}
if ( ! $diff ) {
	echo "SKIP: Stripe settings already as wanted\n";
} elseif ( ! $apply ) {
	echo 'DRY-RUN: would set ' . wp_json_encode( $diff ) . "\n";
} elseif ( ! $stripe_active ) {
	echo "ERROR: Stripe plugin not active - settings not written\n";
	$GLOBALS['p0_fail']++;
} else {
	if ( ! p0_backup_write( 'option-woocommerce_stripe_settings.json', array( 'woocommerce_stripe_settings' => $stripe ) ) ) {
		echo "ERROR: backup failed - Stripe settings left untouched\n";
		$GLOBALS['p0_fail']++;
	} else {
		update_option( 'woocommerce_stripe_settings', array_merge( $stripe, $diff ) );
		$check = get_option( 'woocommerce_stripe_settings' );
		echo 'VERIFY testmode=' . ( $check['testmode'] ?? '?' ) . ' descriptor=' . ( $check['statement_descriptor'] ?? '?' ) . "\n";
	}
}

p0_h( 'SUMMARY' );
echo "mode: {$mode}\n";
echo "guard / write failures: {$GLOBALS['p0_fail']}\n";
if ( $GLOBALS['p0_fail'] > 0 ) {
	echo "RESULT: FAILURES PRESENT - review the log above before the next step\n";
	exit( 2 );
}
echo "RESULT: OK\n";
