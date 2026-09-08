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
 * Recursively find every 'html' widget's settings array (by reference) in
 * an Elementor element tree, so str_replace can be applied in place.
 */
function lunaci_find_html_widgets( array &$elements, array &$out ) {
	foreach ( $elements as &$el ) {
		if ( isset( $el['widgetType'], $el['settings']['html'] ) && $el['widgetType'] === 'html' ) {
			$out[] = &$el['settings'];
		}
		if ( ! empty( $el['elements'] ) ) {
			lunaci_find_html_widgets( $el['elements'], $out );
		}
	}
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

	$widgets = array();
	lunaci_find_html_widgets( $decoded, $widgets );

	$total_before = 0;
	foreach ( $widgets as &$settings ) {
		$total_before += substr_count( $settings['html'], $search );
	}
	unset( $settings );

	if ( $total_before !== $expected_count ) {
		$skipped[] = "{$label} (post {$post_id}: found {$total_before} occurrences of the anchor, expected {$expected_count} - left untouched)";
		return false;
	}

	foreach ( $widgets as &$settings ) {
		$settings['html'] = str_replace( $search, $replace, $settings['html'] );
	}
	unset( $settings );

	$new_raw = wp_json_encode( $decoded );

	// Confirm the in-memory mutation actually took effect (guards against a
	// reference-propagation bug silently producing an unchanged $decoded)
	// before writing anything.
	if ( $new_raw === $raw || substr_count( $new_raw, $replace ) < $expected_count || strpos( $new_raw, $search ) !== false ) {
		$skipped[] = "{$label} (post {$post_id}: in-memory mutation did not take effect as expected - refusing to write)";
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

// 1. Hero wordmark -> H1, EN (post 57) and ES (post 772). Single atomic
// replace of the whole element (open tag through close tag) so there's no
// intermediate mismatched-tag state if the anchor doesn't match exactly.
foreach ( array( 57 => 'EN', 772 => 'ES' ) as $post_id => $lang ) {
	lunaci_guarded_replace(
		$post_id,
		"hero wordmark div -> h1 ({$lang})",
		"<div class=\"ln-hero__wordmark\">\n      <span class=\"big\">Lunaci</span>\n      <span class=\"small\">Barcelona</span>\n    </div>",
		"<h1 class=\"ln-hero__wordmark\">\n      <span class=\"big\">Lunaci</span>\n      <span class=\"small\">Barcelona</span>\n    </h1>",
		1,
		$changed,
		$skipped
	);
}

// 2. EN homepage: /about -> /about-us/, /contact -> /contact/ (redirect-hop cleanup)
lunaci_guarded_replace( 57, 'nav+teaser+footer /about links -> /about-us/', 'href="https://lunacibarcelona.com/about"', 'href="https://lunacibarcelona.com/about-us/"', 3, $changed, $skipped );
lunaci_guarded_replace( 57, 'nav+teaser+footer /contact links -> /contact/', 'href="https://lunacibarcelona.com/contact"', 'href="https://lunacibarcelona.com/contact/"', 3, $changed, $skipped );

// 3. ES homepage: "Ver Más Vendidos" -> ES catalog
lunaci_guarded_replace(
	772,
	'"Ver Más Vendidos" button -> /es/productos/',
	'href="https://lunacibarcelona.com/products/" class="btn-out ln-rv">Ver Más Vendidos</a>',
	'href="https://lunacibarcelona.com/es/productos/" class="btn-out ln-rv">Ver Más Vendidos</a>',
	1,
	$changed,
	$skipped
);

echo "\n--- CHANGED (" . count( $changed ) . ") ---\n";
foreach ( $changed as $c ) {
	echo "  + {$c}\n";
}
echo "\n--- SKIPPED (" . count( $skipped ) . ") ---\n";
foreach ( $skipped as $s ) {
	echo "  - {$s}\n";
}

echo "\nOK: guarded fix complete\n";
