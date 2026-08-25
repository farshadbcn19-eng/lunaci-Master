<?php
/**
 * Guarded fix: bring the Home page's ES translation (post 772) up to date
 * with the EN page (post 57)'s 2026-08-23 changes, which were applied
 * directly to post 57 and never propagated to its WPML translation:
 *   1) 5 stale image src values (hero + 4 collection tiles) swapped for
 *      the current Luna photoshoot images (images are not language-specific,
 *      so the same URLs are used).
 *   2) The "Our Origin" section inserted (translated into Spanish),
 *      immediately before the Newsletter section (<section class="ln-news">),
 *      matching its exact position on the EN page. Its CSS rules are added
 *      to the widget's existing <style> block.
 *
 * Confirmed via read-only diagnostics (PRs #183-185):
 *   - post 772 has exactly one HTML widget, at the SAME path as post 57:
 *     container[id=bf4109a]/widget[id=9b0a463].
 *   - Its current image srcs and section order were dumped and matched
 *     1:1 by position/semantics to EN's pre-fix state.
 *
 * STEP A: staleness gate - re-read, decode, locate widget, verify each of
 *         the 5 old image URLs and the ln-news anchor appear EXACTLY once,
 *         and confirm the Our Origin section is NOT already present.
 * STEP B: race-condition guard + targeted string replacements + write.
 * STEP C: full read-back verification (new content present, old content
 *         gone, unrelated settings leaves unchanged, JSON still valid).
 */

global $wpdb;

$page_id             = 772;
$target_container_id = 'bf4109a';
$target_widget_id    = '9b0a463';
$expected_byte_len    = 26840; // confirmed live _elementor_data byte length (read-only diagnostic, PR #185)

function lunaci772_find_widget( $node, $target_container_id, $target_widget_id ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['id'], $node['elType'] ) && 'container' === $node['elType'] && $node['id'] === $target_container_id ) {
		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				if ( isset( $child['id'], $child['elType'] ) && 'widget' === $child['elType'] && $child['id'] === $target_widget_id ) {
					return $child;
				}
			}
		}
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci772_find_widget( $value, $target_container_id, $target_widget_id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

function lunaci772_set_widget_html( &$node, $target_container_id, $target_widget_id, $new_html ) {
	if ( ! is_array( $node ) ) {
		return false;
	}
	if ( isset( $node['id'], $node['elType'] ) && 'container' === $node['elType'] && $node['id'] === $target_container_id ) {
		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as &$child ) {
				if ( isset( $child['id'], $child['elType'] ) && 'widget' === $child['elType'] && $child['id'] === $target_widget_id ) {
					$child['settings']['html'] = $new_html;
					return true;
				}
			}
			unset( $child );
		}
	}
	foreach ( $node as &$value ) {
		if ( is_array( $value ) ) {
			if ( lunaci772_set_widget_html( $value, $target_container_id, $target_widget_id, $new_html ) ) {
				return true;
			}
		}
	}
	unset( $value );
	return false;
}

function lunaci772_count_elements( $node ) {
	if ( ! is_array( $node ) ) {
		return 0;
	}
	$count = 0;
	if ( isset( $node['id'], $node['elType'] ) ) {
		$count = 1;
	}
	foreach ( $node as $key => $value ) {
		if ( 'settings' === $key ) {
			continue;
		}
		if ( is_array( $value ) ) {
			$count += lunaci772_count_elements( $value );
		}
	}
	return $count;
}

function lunaci772_collect_other_leaves( $node, $path, $target_widget_id, &$results ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	$current_id     = isset( $node['id'] ) ? $node['id'] : null;
	$current_eltype = isset( $node['elType'] ) ? $node['elType'] : null;
	if ( null !== $current_id && null !== $current_eltype ) {
		$path = $path . '/' . $current_eltype . '[id=' . $current_id . ']';
	}
	$is_target_widget = ( 'widget' === $current_eltype && $current_id === $target_widget_id );
	if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
		foreach ( $node['settings'] as $key => $value ) {
			if ( $is_target_widget && 'html' === $key ) {
				continue;
			}
			if ( is_string( $value ) ) {
				$results[ $path . '/settings/' . $key ] = $value;
			}
		}
	}
	foreach ( $node as $key => $value ) {
		if ( ! is_array( $value ) ) {
			continue;
		}
		if ( 'elements' === $key ) {
			foreach ( $value as $child ) {
				lunaci772_collect_other_leaves( $child, $path, $target_widget_id, $results );
			}
		} elseif ( 'settings' !== $key ) {
			lunaci772_collect_other_leaves( $value, $path, $target_widget_id, $results );
		}
	}
}

$image_replacements = array(
	'https://lunacibarcelona.com/wp-content/uploads/2026/05/lunaci_hero_retouched-1-scaled.jpg' => 'https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-hero-luna.jpg?v=2',
	'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-foundation.jpg'               => 'https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-face-luna.jpg?v=2',
	'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-eyeliner.jpg'                 => 'https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-eyes-luna.jpg?v=2',
	'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-lipstick.png'                 => 'https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-lips-luna.jpg?v=2',
	'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-nail-polish.jpg'               => 'https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-nails-luna.jpg?v=2',
);

$news_anchor = '<section class="ln-news">';

$origin_css = ".ln-origin{position:relative;display:grid;grid-template-columns:1fr 1fr;align-items:stretch;background:#0B0B0B;height:clamp(450px,42vw,550px);overflow:hidden;}\n.ln-origin__img{position:relative;overflow:hidden;height:100%;}\n.ln-origin__img img{width:100%;height:100%;object-fit:cover;object-position:center;}\n.ln-origin__txt{padding:60px 7%;display:flex;flex-direction:column;justify-content:center;background:rgba(11,11,11,.9);}\n.ln-origin__eyebrow{font-family:'Helvetica LUNACI',Helvetica,Arial,sans-serif;font-size:10px;font-weight:500;letter-spacing:.35em;text-transform:uppercase;color:#D4AF37;margin-bottom:18px;display:block;}\n.ln-origin__title{font-family:'Trade Gothic LT Std Extended',sans-serif;font-size:clamp(26px,3vw,40px);font-weight:400;line-height:1.25;color:#F7F4EE;margin-bottom:22px;}\n.ln-origin__p{font-family:'Helvetica LUNACI',Helvetica,Arial,sans-serif;font-size:13px;font-weight:300;letter-spacing:.04em;line-height:1.85;color:rgba(247,244,238,.65);max-width:420px;margin-bottom:32px;}\n.ln-origin__btn{font-family:'Helvetica LUNACI',Helvetica,Arial,sans-serif;font-size:10px;font-weight:500;letter-spacing:.3em;text-transform:uppercase;color:#D4AF37;background:transparent;padding:14px 32px;text-decoration:none;border:1px solid #D4AF37;transition:all .3s;display:inline-block;}\n.ln-origin__btn:hover{background:#D4AF37;color:#0B0B0B;}\n@media(max-width:768px){.ln-origin{grid-template-columns:1fr;height:auto;}.ln-origin__img{height:320px;}.ln-origin__txt{padding:44px 6%;}}\n";

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read, decode, locate widget, validate preconditions\n";
echo "=====================================================================\n";

$raw_before = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw_before ) {
	echo "ABORT: _elementor_data not found for page ID={$page_id}.\n";
	exit( 1 );
}
$actual_byte_len = strlen( $raw_before );
echo "Current _elementor_data byte length = {$actual_byte_len} (expected {$expected_byte_len}): " . ( $actual_byte_len === $expected_byte_len ? 'PASS' : 'FAIL' ) . "\n";
if ( $actual_byte_len !== $expected_byte_len ) {
	echo "ABORT: byte length mismatch - content has changed since the last diagnostic, needs re-diagnosis before a safe write.\n";
	exit( 1 );
}

$decoded_before = json_decode( $raw_before, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ABORT: json_decode failed: " . json_last_error_msg() . "\n";
	exit( 1 );
}
echo "OK: json_decode succeeded.\n";

$widget_before = lunaci772_find_widget( $decoded_before, $target_container_id, $target_widget_id );
if ( null === $widget_before || ! isset( $widget_before['settings']['html'] ) || ! is_string( $widget_before['settings']['html'] ) ) {
	echo "ABORT: target widget path not found or settings.html missing/not a string.\n";
	exit( 1 );
}
$current_html = $widget_before['settings']['html'];
echo "OK: target widget found, html length=" . strlen( $current_html ) . "\n\n";

foreach ( $image_replacements as $old => $new ) {
	$count = substr_count( $current_html, $old );
	echo "old image URL occurs {$count}x: {$old}\n";
	if ( 1 !== $count ) {
		echo "ABORT: expected exactly 1 occurrence, found {$count}. Refusing to write.\n";
		exit( 1 );
	}
}

$news_count = substr_count( $current_html, $news_anchor );
echo "\nInsertion anchor '{$news_anchor}' occurs {$news_count}x\n";
if ( 1 !== $news_count ) {
	echo "ABORT: expected exactly 1 occurrence of the Newsletter section anchor, found {$news_count}.\n";
	exit( 1 );
}

if ( false !== strpos( $current_html, 'class="ln-origin"' ) ) {
	echo "ABORT: 'class=\"ln-origin\"' already present - the Our Origin section may already have been added. Refusing to write to avoid a duplicate.\n";
	exit( 1 );
}
echo "OK: Our Origin section confirmed not already present.\n";

$style_close_count = substr_count( $current_html, '</style>' );
echo "'</style>' occurs {$style_close_count}x\n";
if ( 1 !== $style_close_count ) {
	echo "ABORT: expected exactly 1 occurrence of '</style>', found {$style_close_count}.\n";
	exit( 1 );
}
echo "\n";

$about_es_url = get_permalink( 680 );
if ( ! is_string( $about_es_url ) || '' === $about_es_url ) {
	echo "ABORT: could not resolve the ES About page (post 680) permalink.\n";
	exit( 1 );
}
echo "Resolved ES About page URL for the 'Nuestra Historia' button: {$about_es_url}\n\n";

$origin_html = '<section class="ln-origin">' . "\n"
	. '  <div class="ln-origin__img">' . "\n"
	. '    <img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-origin-crafted-barcelona-luna.jpg" alt="Elaborado en Barcelona"/>' . "\n"
	. '  </div>' . "\n"
	. '  <div class="ln-origin__txt">' . "\n"
	. '    <span class="ln-origin__eyebrow ln-rv">Nuestro Origen</span>' . "\n"
	. '    <h2 class="ln-origin__title ln-rv d1">Elaborado en Barcelona.</h2>' . "\n"
	. '    <p class="ln-origin__p ln-rv d2">Arraigado en el Mediterráneo. Inspirado por mujeres que lideran con presencia, no con perfección.</p>' . "\n"
	. '    <a href="' . esc_url( $about_es_url ) . '" class="ln-origin__btn ln-rv d3">Nuestra Historia</a>' . "\n"
	. '  </div>' . "\n"
	. '</section>' . "\n\n";

$local_baseline_sha256 = hash( 'sha256', $current_html );
echo "Baseline sha256 of current widget html: {$local_baseline_sha256}\n\n";

echo "=====================================================================\n";
echo "STEP B: COMMIT - race-check, apply targeted replacements, write\n";
echo "=====================================================================\n";

$raw_guard = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw_guard ) {
	echo "ABORT: _elementor_data disappeared between STEP A and STEP B.\n";
	exit( 1 );
}
$decoded_guard = json_decode( $raw_guard, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ABORT: json_decode failed on guard re-read: " . json_last_error_msg() . "\n";
	exit( 1 );
}
$widget_guard = lunaci772_find_widget( $decoded_guard, $target_container_id, $target_widget_id );
if ( null === $widget_guard || ! isset( $widget_guard['settings']['html'] ) || ! is_string( $widget_guard['settings']['html'] ) ) {
	echo "ABORT: target widget not found on guard re-read.\n";
	exit( 1 );
}
$guard_sha256 = hash( 'sha256', $widget_guard['settings']['html'] );
if ( $guard_sha256 !== $local_baseline_sha256 ) {
	echo "ABORT: race condition detected - widget html changed between STEP A and STEP B. No write performed.\n";
	exit( 1 );
}
echo "PASS: race-condition guard confirms content unchanged immediately before write.\n\n";

$new_html = $current_html;

foreach ( $image_replacements as $old => $new ) {
	$new_html = str_replace( $old, $new, $new_html, $replace_count );
}

// Insert the .ln-origin CSS rules right before the single </style> occurrence.
$style_pos = strpos( $new_html, '</style>' );
$new_html  = substr( $new_html, 0, $style_pos ) . $origin_css . substr( $new_html, $style_pos );

// Insert the Our Origin section HTML right before the single ln-news anchor.
$anchor_pos = strpos( $new_html, $news_anchor );
$new_html   = substr( $new_html, 0, $anchor_pos ) . $origin_html . substr( $new_html, $anchor_pos );

echo "New widget html length: " . strlen( $new_html ) . " (was " . strlen( $current_html ) . ")\n\n";

$working = $decoded_guard;
$set_ok  = lunaci772_set_widget_html( $working, $target_container_id, $target_widget_id, $new_html );
if ( ! $set_ok ) {
	echo "ABORT: failed to set new settings.html on the in-memory structure.\n";
	exit( 1 );
}
echo "OK: in-memory structure updated.\n";

$new_raw = wp_json_encode( $working, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
if ( false === $new_raw ) {
	echo "ABORT: wp_json_encode failed.\n";
	exit( 1 );
}
echo "OK: re-encoded structure (new byte length: " . strlen( $new_raw ) . ").\n\n";

$update_result = update_post_meta( $page_id, '_elementor_data', wp_slash( $new_raw ) );
if ( false === $update_result ) {
	echo "ABORT: update_post_meta() returned false.\n";
	exit( 1 );
}
echo "OK: update_post_meta() succeeded.\n\n";

clean_post_cache( $page_id );
delete_post_meta( $page_id, '_elementor_css' );
if ( class_exists( '\\Elementor\\Plugin' ) ) {
	try {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
		echo "OK: Elementor files_manager cache cleared.\n";
	} catch ( \Throwable $e ) {
		echo "WARNING: Elementor cache clear threw: " . $e->getMessage() . "\n";
	}
}
wp_cache_flush();
echo "OK: post cache cleared, _elementor_css meta deleted, object cache flushed.\n\n";

echo "=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back, confirm exact expected state\n";
echo "=====================================================================\n";

$any_error = false;

$raw_after = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
$decoded_after = json_decode( $raw_after, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ERROR: json_decode failed on read-back: " . json_last_error_msg() . "\n";
	$any_error = true;
} else {
	echo "OK: read-back content decodes successfully.\n";
}

$widget_after = lunaci772_find_widget( $decoded_after, $target_container_id, $target_widget_id );
if ( null === $widget_after || ! isset( $widget_after['settings']['html'] ) || ! is_string( $widget_after['settings']['html'] ) ) {
	echo "ERROR: target widget not found after write.\n";
	$any_error = true;
} else {
	$html_after = $widget_after['settings']['html'];

	$content_matches = ( $html_after === $new_html );
	echo "widget html after write matches intended new content exactly: " . ( $content_matches ? 'PASS' : 'FAIL' ) . "\n";
	if ( ! $content_matches ) {
		$any_error = true;
	}

	foreach ( $image_replacements as $old => $new ) {
		$old_gone = ( 0 === substr_count( $html_after, $old ) );
		$new_present = ( 1 === substr_count( $html_after, $new ) );
		echo "  {$new}: old gone=" . ( $old_gone ? 'yes' : 'no' ) . " new present(x1)=" . ( $new_present ? 'yes' : 'no' ) . "\n";
		if ( ! $old_gone || ! $new_present ) {
			$any_error = true;
		}
	}

	foreach ( array( 'class="ln-origin"', 'Nuestro Origen', 'Elaborado en Barcelona.', 'Arraigado en el Mediterráneo', 'Nuestra Historia' ) as $marker ) {
		$present = ( false !== strpos( $html_after, $marker ) );
		echo "  marker present: \"{$marker}\": " . ( $present ? 'yes' : 'no' ) . "\n";
		if ( ! $present ) {
			$any_error = true;
		}
	}

	$news_still_present = ( 1 === substr_count( $html_after, $news_anchor ) );
	echo "  Newsletter section anchor still present (x1): " . ( $news_still_present ? 'yes' : 'no' ) . "\n";
	if ( ! $news_still_present ) {
		$any_error = true;
	}
}

$elements_count_before = lunaci772_count_elements( $decoded_before );
$elements_count_after  = lunaci772_count_elements( $decoded_after );
$counts_match          = ( $elements_count_before === $elements_count_after );
echo "\nTotal elements before: {$elements_count_before}, after: {$elements_count_after}: " . ( $counts_match ? 'PASS (unchanged)' : 'FAIL (structure changed)' ) . "\n";
if ( ! $counts_match ) {
	$any_error = true;
}

$other_leaves_before = array();
$other_leaves_after  = array();
lunaci772_collect_other_leaves( $decoded_before, '', $target_widget_id, $other_leaves_before );
lunaci772_collect_other_leaves( $decoded_after, '', $target_widget_id, $other_leaves_after );
$same_keyset = ( array_keys( $other_leaves_before ) === array_keys( $other_leaves_after ) );
echo "Other settings leaf keyset identical before/after: " . ( $same_keyset ? 'PASS' : 'FAIL' ) . "\n";
if ( ! $same_keyset ) {
	$any_error = true;
}
$other_values_unchanged = true;
foreach ( $other_leaves_before as $k => $v ) {
	if ( ! array_key_exists( $k, $other_leaves_after ) || $other_leaves_after[ $k ] !== $v ) {
		$other_values_unchanged = false;
		echo "  unexpected diff at: {$k}\n";
	}
}
echo "All other settings leaf values unchanged: " . ( $other_values_unchanged ? 'PASS' : 'FAIL' ) . "\n";
if ( ! $other_values_unchanged ) {
	$any_error = true;
}

if ( $any_error ) {
	echo "\nFINAL RESULT: FAILURE\n";
	exit( 1 );
}

echo "\nFINAL RESULT: SUCCESS\n";
