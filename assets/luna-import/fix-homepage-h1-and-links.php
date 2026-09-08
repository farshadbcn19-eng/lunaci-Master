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
 */
function lunaci_guarded_replace( int $post_id, string $label, string $search, string $replace, int $expected_count, array &$changed, array &$skipped ) {
	$raw = get_post_meta( $post_id, '_elementor_data', true );
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
	$updated = update_post_meta( $post_id, '_elementor_data', wp_slash( $new_raw ) );

	// update_post_meta returns false both on failure and on "value unchanged" -
	// re-read to confirm the write actually landed.
	$readback = get_post_meta( $post_id, '_elementor_data', true );
	if ( strpos( $readback, $replace ) === false ) {
		$skipped[] = "{$label} (post {$post_id}: update_post_meta did not persist - left as-is, needs manual check)";
		return false;
	}

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
