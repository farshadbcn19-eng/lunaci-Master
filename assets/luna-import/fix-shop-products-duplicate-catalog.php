<?php
/**
 * Guarded fix: resolve the /shop/ vs /products/ duplicate catalog - the
 * one remaining Critical item from the original SEO audit, deliberately
 * left untouched until now given the WooCommerce risk.
 *
 * Confirmed by diagnose-products-page-and-shop-redirect.php and
 * diagnose-lunaci-products-muplugin-and-htaccess.php:
 *   - /shop/ (post 56) and /es/tienda/ (post 609) are WooCommerce's
 *     native shop archive, set via woocommerce_shop_page_id = 56.
 *   - /products/ (post 61) and /es/productos/ (post 771) are separate,
 *     standalone Elementor pages that site nav/CTAs already point to.
 *   - The existing mu-plugin (lunaci-products.php) only renders static
 *     HTML fragments via shortcode - it doesn't touch routing, so it
 *     can't conflict with a redirect.
 *   - .htaccess is clean (LiteSpeed + stock WordPress rewrite blocks
 *     only, no existing redirects) and writable.
 *
 * Approach: 301 redirect /shop/ and /es/tienda/ into /products/ and
 * /es/productos/ at the .htaccess level, and mark the two WooCommerce
 * pages noindex in AIOSEO so they drop out of the XML sitemap and
 * search results rather than sitting there as redirected non-200 URLs.
 * woocommerce_shop_page_id is deliberately NOT touched - WooCommerce's
 * internal cart/checkout/breadcrumb logic keeps working exactly as
 * before; the .htaccess rule only intercepts the public HTTP request,
 * same pattern as the site's existing /about -> /about-us/ redirect.
 *
 * Both steps are guarded and idempotent: the .htaccess write is skipped
 * if the marker block already exists, and each aioseo_posts write is
 * skipped unless the row is still in the exact un-noindexed starting
 * state confirmed by diagnose-aioseo-posts-robots-columns.php.
 */

global $wpdb;

$changed = array();
$skipped = array();

// ---- 1. .htaccess redirect ----
$htaccess_path = ABSPATH . '.htaccess';
$marker_start  = '# BEGIN LUNACI redirects';
$marker_end    = '# END LUNACI redirects';

$current_htaccess = file_exists( $htaccess_path ) ? file_get_contents( $htaccess_path ) : false;

if ( $current_htaccess === false ) {
	$skipped[] = '.htaccess redirect (file not found or not readable)';
} elseif ( strpos( $current_htaccess, $marker_start ) !== false ) {
	$skipped[] = '.htaccess redirect (marker block already present - left untouched)';
} else {
	$redirect_block = $marker_start . "\n"
		. "<IfModule mod_rewrite.c>\n"
		. "RewriteEngine On\n"
		. "RewriteRule ^shop/?$ /products/ [R=301,L]\n"
		. "RewriteRule ^es/tienda/?$ /es/productos/ [R=301,L]\n"
		. "</IfModule>\n"
		. $marker_end . "\n";

	$new_htaccess  = $redirect_block . $current_htaccess;
	$bytes_written = file_put_contents( $htaccess_path, $new_htaccess, LOCK_EX );

	if ( $bytes_written !== false ) {
		$readback = file_get_contents( $htaccess_path );
		if ( strpos( $readback, $marker_start ) !== false && strpos( $readback, $marker_end ) !== false ) {
			$changed[] = '.htaccess: added 301 redirects /shop/ -> /products/ and /es/tienda/ -> /es/productos/';
		} else {
			$skipped[] = '.htaccess redirect (write reported success but readback did not contain the marker block - needs manual check)';
		}
	} else {
		$skipped[] = '.htaccess redirect (file_put_contents failed)';
	}
}

// ---- 2. Noindex the redirected-away URLs in AIOSEO ----
$aioseo_table     = $wpdb->prefix . 'aioseo_posts';
$noindex_targets  = array(
	56  => 'EN /shop/',
	609 => 'ES /es/tienda/',
);

foreach ( $noindex_targets as $post_id => $label ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT robots_default, robots_noindex FROM `{$aioseo_table}` WHERE post_id = %d", $post_id ), ARRAY_A );
	if ( ! $row ) {
		$skipped[] = "aioseo_posts robots for post_id={$post_id} ({$label}): no row found";
		continue;
	}
	if ( (int) $row['robots_default'] === 0 && (int) $row['robots_noindex'] === 1 ) {
		$skipped[] = "aioseo_posts robots for post_id={$post_id} ({$label}): already noindexed";
		continue;
	}
	if ( (int) $row['robots_default'] !== 1 || (int) $row['robots_noindex'] !== 0 ) {
		$skipped[] = "aioseo_posts robots for post_id={$post_id} ({$label}): unexpected current state (robots_default={$row['robots_default']}, robots_noindex={$row['robots_noindex']}) - left untouched";
		continue;
	}

	$result = $wpdb->update(
		$aioseo_table,
		array( 'robots_default' => 0, 'robots_noindex' => 1 ),
		array( 'post_id' => $post_id )
	);
	if ( $result !== false ) {
		$readback = $wpdb->get_row( $wpdb->prepare( "SELECT robots_default, robots_noindex FROM `{$aioseo_table}` WHERE post_id = %d", $post_id ), ARRAY_A );
		if ( (int) $readback['robots_default'] === 0 && (int) $readback['robots_noindex'] === 1 ) {
			$changed[] = "aioseo_posts robots for post_id={$post_id} ({$label}): set noindex (robots_default=0, robots_noindex=1)";
			clean_post_cache( $post_id );
		} else {
			$skipped[] = "aioseo_posts robots for post_id={$post_id} ({$label}): write reported success but readback did not match";
		}
	} else {
		$skipped[] = "aioseo_posts robots for post_id={$post_id} ({$label}): \$wpdb->update failed: {$wpdb->last_error}";
	}
}

echo "--- CHANGED (" . count( $changed ) . ") ---\n";
foreach ( $changed as $c ) {
	echo "  + {$c}\n";
}
echo "\n--- SKIPPED (" . count( $skipped ) . ") ---\n";
foreach ( $skipped as $s ) {
	echo "  - {$s}\n";
}

echo "\nOK: guarded fix complete\n";
