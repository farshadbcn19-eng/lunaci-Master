<?php
/**
 * Guarded fix: homepage H1 + two link fixes, scoped to the homepage only
 * (EN post 57, ES post 772). The whole site is built as raw HTML pasted
 * into per-page Elementor HTML widgets (no shared template), so this
 * intentionally does NOT sweep every other page - see the audit report
 * for that tradeoff. Each anchor is counted before AND after the
 * str_replace to guard against a partial/unexpected match; nothing is
 * written unless the counts match exactly what was confirmed live.
 *
 * 1. <div class="ln-hero__wordmark">...</div> -> <h1 class="ln-hero__wordmark">...</h1>
 *    (EN + ES). Pure tag swap, zero visual change - all styling is
 *    class-based. This is the site's largest, first, most prominent
 *    text (the "LUNACI / BARCELONA" wordmark), so it's a real headline,
 *    just not marked up as one.
 * 2. EN homepage nav+teaser+footer: href="https://lunacibarcelona.com/about"
 *    -> ".../about-us/" (was a 301 redirect), and .../contact -> .../contact/
 *    (was a 301 redirect for the missing trailing slash).
 * 3. ES homepage: the "Ver Más Vendidos" button linked to the English
 *    /products/ catalog instead of /es/productos/.
 *
 * Second attempt (2026-09-08). The first attempt used update_post_meta()
 * with wp_slash(), which turned out to run into an unwanted
 * stripslashes-equivalent on this specific meta key and corrupted the
 * homepage's _elementor_data (recovered via restore-post57-elementor-
 * data.php from a database backup). This version writes via
 * $wpdb->update() directly instead - proven safe by that recovery - and
 * adds an explicit check that the in-memory mutation actually took
 * effect before writing anything, on top of the existing occurrence-
 * count guard and the post-write readback comparison.
 */

$changed = array();
$skipped = array();

/**
 * Recursively rebuild an Elementor element tree, applying str_replace to
 * every 'html' widget's content and counting how many replacements were
 * made. Pure value semantics (no PHP references) - returns a new array
 * rather than mutating in place, which sidesteps the reference-chain bug
 * an earlier version of this script hit (occurrence counting worked, but
 * &$el['settings'] references taken inside a recursive foreach-by-
 * reference did not reliably propagate mutations back to the caller's
 * $decoded array, so the whole edit silently no-op'd).
 */
function lunaci_replace_in_html_widgets( array $elements, string $search, string $replace, int &$replacements_made ) {
	foreach ( $elements as $i => $el ) {
		if ( isset( $el['widgetType'], $el['settings']['html'] ) && $el['widgetType'] === 'html' ) {
			$count = substr_count( $el['settings']['html'], $search );
			if ( $count > 0 ) {
				$el['settings']['html'] = str_replace( $search, $replace, $el['settings']['html'] );
				$replacements_made += $count;
			}
		}
		if ( ! empty( $el['elements'] ) ) {
			$el['elements'] = lunaci_replace_in_html_widgets( $el['elements'], $search, $replace, $replacements_made );
		}
		$elements[ $i ] = $el;
	}
	return $elements;
}

/**
 * Apply a guarded str_replace across every html widget's content for one
 * post: only commits if the anchor's total occurrence count across all
 * html widgets exactly matches $expected_count before the edit.
 *
 * Writes via $wpdb->update() directly on wp_postmeta, NOT
 * update_post_meta(). The incident on 2026-09-08 (see
 * restore-post57-elementor-data.php) found that update_post_meta() on
 * this specific meta key runs the value through an unwanted
 * stripslashes-equivalent that mangles every \n and \" in the stored
 * JSON. $wpdb->update() uses parameterized SQL with no PHP-level
 * slashing layer, so the string handed to it is exactly what gets
 * stored - confirmed safe by that recovery (readback matched byte-for-
 * byte).
 */
function lunaci_guarded_replace( int $post_id, string $label, string $search, string $replace, int $expected_count, array &$changed, array &$skipped ) {
	global $wpdb;

	$raw = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$post_id, '_elementor_data'
	) );
	if ( ! $raw ) {
		$skipped[] = "{$label} (post {$post_id}: no _elementor_data)";
		return false;
	}
	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) ) {
		$skipped[] = "{$label} (post {$post_id}: _elementor_data did not decode as JSON)";
		return false;
	}

	$replacements_made = 0;
	$mutated = lunaci_replace_in_html_widgets( $decoded, $search, $replace, $replacements_made );

	if ( $replacements_made !== $expected_count ) {
		$skipped[] = "{$label} (post {$post_id}: found {$replacements_made} occurrences of the anchor, expected {$expected_count} - left untouched)";
		return false;
	}

	$new_raw = wp_json_encode( $mutated );

	// Confirm the mutation actually took effect before writing anything.
	if ( $new_raw === $raw || substr_count( $new_raw, $replace ) < $expected_count || strpos( $new_raw, $search ) !== false ) {
		$skipped[] = "{$label} (post {$post_id}: mutation did not take effect as expected in the re-encoded JSON - refusing to write)";
		return false;
	}

	$result = $wpdb->update(
		$wpdb->postmeta,
		array( 'meta_value' => $new_raw ),
		array( 'post_id' => $post_id, 'meta_key' => '_elementor_data' )
	);
	if ( $result === false ) {
		$skipped[] = "{$label} (post {$post_id}: \$wpdb->update failed: {$wpdb->last_error})";
		return false;
	}

	// Re-read straight from the DB and compare structurally (decoded, not
	// a raw string search) to confirm the write actually landed correctly.
	$readback = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$post_id, '_elementor_data'
	) );
	if ( $readback !== $new_raw ) {
		$skipped[] = "{$label} (post {$post_id}: readback does not exactly match what was written - left as-is, needs manual check)";
		return false;
	}
	clean_post_cache( $post_id );

	$changed[] = "{$label} (post {$post_id}: {$expected_count}x)";
	return true;
}

// 1. Hero wordmark -> H1, EN only (post 57). ES (post 772) no longer has
// a <div class="ln-hero__wordmark"> element at all - only the CSS rule
// remains, meaning the ES hero section's markup has diverged from EN
// since the original audit (confirmed via diagnose-es-wordmark-block.php:
// no exact-tag match found, only the .ln-hero__wordmark{...} CSS
// selector). That needs its own fresh look, not a copy of the EN fix -
// scoping this run to EN only. Single atomic replace of the whole
// element (open tag through close tag) so there's no intermediate
// mismatched-tag state if the anchor doesn't match exactly.
lunaci_guarded_replace(
	57,
	'hero wordmark div -> h1 (EN)',
	"<div class=\"ln-hero__wordmark\">\n      <span class=\"big\">Lunaci</span>\n      <span class=\"small\">Barcelona</span>\n    </div>",
	"<h1 class=\"ln-hero__wordmark\">\n      <span class=\"big\">Lunaci</span>\n      <span class=\"small\">Barcelona</span>\n    </h1>",
	1,
	$changed,
	$skipped
);

// 2. EN homepage: /about -> /about-us/, /contact -> /contact/ (redirect-hop
// cleanup). Counts re-verified directly against the live page after
// recovery via diagnose-exact-link-text.php: /about is 3x (nav, mid-page
// teaser, footer); /contact is 2x within this document's html widget
// (nav, footer) - the live-rendered page's earlier "3x" count for
// /contact included something outside this widget entirely (not
// present in the stored source), so 2 is the correct target here.
lunaci_guarded_replace( 57, 'nav+teaser+footer /about links -> /about-us/', 'href="https://lunacibarcelona.com/about"', 'href="https://lunacibarcelona.com/about-us/"', 3, $changed, $skipped );
lunaci_guarded_replace( 57, 'nav+footer /contact links -> /contact/', 'href="https://lunacibarcelona.com/contact"', 'href="https://lunacibarcelona.com/contact/"', 2, $changed, $skipped );

// 3. ES homepage "Ver Más Vendidos" button: already fixed (points to
// /es/productos/ already, confirmed via diagnose-exact-link-text.php) -
// likely edited by someone else since the original audit. Nothing to do.

echo "\n--- CHANGED (" . count( $changed ) . ") ---\n";
foreach ( $changed as $c ) {
	echo "  + {$c}\n";
}
echo "\n--- SKIPPED (" . count( $skipped ) . ") ---\n";
foreach ( $skipped as $s ) {
	echo "  - {$s}\n";
}

echo "\nOK: guarded fix complete\n";
