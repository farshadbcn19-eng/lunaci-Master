<?php
/**
 * Guarded fix: add alt text and explicit width/height to the 7 homepage
 * <img> tags (per language) confirmed missing them by
 * diagnose-homepage-images.php. Real pixel dimensions came from
 * getimagesize() on the actual files (diagnose-homepage-image-
 * dimensions.php), not guessed. The 8th image (the small nav logo)
 * already has alt/width/height and is left untouched.
 *
 * Same structure as fix-homepage-h1-and-links.php: the homepage is raw
 * HTML pasted into Elementor "html" widgets, so this is a guarded
 * str_replace across those widgets' content, not a native Elementor
 * Image-widget edit. Writes via $wpdb->update() directly on
 * wp_postmeta, NOT update_post_meta() - see fix-homepage-h1-and-links.php
 * for why (update_post_meta() corrupted _elementor_data once on this
 * install; $wpdb->update() is the proven-safe method since then).
 */

$changed = array();
$skipped = array();

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
		$skipped[] = "{$label} (post {$post_id}: found {$replacements_made} occurrences, expected {$expected_count} - left untouched)";
		return false;
	}

	$new_raw = wp_json_encode( $mutated );
	if ( $new_raw === $raw ) {
		$skipped[] = "{$label} (post {$post_id}: re-encoded JSON identical to original despite {$replacements_made} counted replacements - refusing to write)";
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

	$readback = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$post_id, '_elementor_data'
	) );
	if ( $readback !== $new_raw ) {
		$skipped[] = "{$label} (post {$post_id}: readback did not exactly match what was written)";
		return false;
	}
	clean_post_cache( $post_id );

	$changed[] = "{$label} (post {$post_id}: {$expected_count}x)";
	return true;
}

// EN homepage (post 57)
lunaci_guarded_replace( 57, 'hero image -> alt+width+height (EN)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-hero-luna.jpg?v=2" alt="LUNACI"/>',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-hero-luna.jpg?v=2" alt="LUNACI" width="1376" height="768"/>',
	1, $changed, $skipped
);
lunaci_guarded_replace( 57, 'collection-face image -> alt+width+height (EN)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-face-luna.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-face-luna.jpg?v=2" alt="LUNACI Barcelona face makeup collection" width="1024" height="1024">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 57, 'collection-eyes image -> alt+width+height (EN)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-eyes-luna.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-eyes-luna.jpg?v=2" alt="LUNACI Barcelona eye makeup collection" width="1172" height="896">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 57, 'collection-lips image -> alt+width+height (EN)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-lips-luna.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-lips-luna.jpg?v=2" alt="LUNACI Barcelona lip makeup collection" width="1024" height="1024">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 57, 'collection-nails image -> alt+width+height (EN)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-nails-luna.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-nails-luna.jpg?v=2" alt="LUNACI Barcelona nail collection" width="1024" height="1024">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 57, 'why2 image -> alt+width+height (EN)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-why2-luna-replacement.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-why2-luna-replacement.jpg?v=2" alt="LUNACI Barcelona - Mediterranean-made luxury makeup" width="1023" height="1537">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 57, 'origin-crafted image -> width+height (EN)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-origin-crafted-barcelona-luna.jpg" alt="Crafted in Barcelona"/>',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-origin-crafted-barcelona-luna.jpg" alt="Crafted in Barcelona" width="1672" height="941"/>',
	1, $changed, $skipped
);

// ES homepage (post 772)
lunaci_guarded_replace( 772, 'hero image -> alt+width+height (ES)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-hero-luna.jpg?v=2" alt="LUNACI"/>',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-hero-luna.jpg?v=2" alt="LUNACI" width="1376" height="768"/>',
	1, $changed, $skipped
);
lunaci_guarded_replace( 772, 'collection-face image -> alt+width+height (ES)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-face-luna.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-face-luna.jpg?v=2" alt="Colección de maquillaje facial LUNACI Barcelona" width="1024" height="1024">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 772, 'collection-eyes image -> alt+width+height (ES)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-eyes-luna.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-eyes-luna.jpg?v=2" alt="Colección de maquillaje de ojos LUNACI Barcelona" width="1172" height="896">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 772, 'collection-lips image -> alt+width+height (ES)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-lips-luna.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-lips-luna.jpg?v=2" alt="Colección de maquillaje de labios LUNACI Barcelona" width="1024" height="1024">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 772, 'collection-nails image -> alt+width+height (ES)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-nails-luna.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-collection-nails-luna.jpg?v=2" alt="Colección de uñas LUNACI Barcelona" width="1024" height="1024">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 772, 'why2 image -> alt+width+height (ES)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-why2-luna-replacement.jpg?v=2">',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-why2-luna-replacement.jpg?v=2" alt="LUNACI Barcelona - maquillaje de lujo mediterráneo" width="1023" height="1537">',
	1, $changed, $skipped
);
lunaci_guarded_replace( 772, 'origin-crafted image -> width+height (ES)',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-origin-crafted-barcelona-luna.jpg" alt="Elaborado en Barcelona"/>',
	'<img src="https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-origin-crafted-barcelona-luna.jpg" alt="Elaborado en Barcelona" width="1672" height="941"/>',
	1, $changed, $skipped
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
