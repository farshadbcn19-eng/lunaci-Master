<?php
/**
 * GUARDED FIX. WCAG 2.2 finding from the round-6 A11y audit (1.3.1):
 * the homepage and product archive pages have no <main> landmark
 * (the Contact page's theme template already has one). Both are
 * Elementor Canvas pages - the entire header/nav/content/footer is
 * raw HTML inside _elementor_data, so the fix wraps the existing
 * content between the header/nav and the footer in <main id="content">
 * without altering anything inside that region.
 *
 * Same recursive-tree + str_replace + $wpdb->update() pattern used
 * throughout this project for _elementor_data (update_post_meta() is
 * known to corrupt this specific meta key). Idempotent: skips any
 * post where <main is already present. Guards on each anchor string
 * appearing exactly once before touching anything.
 */

global $wpdb;

// post_id => [ after_anchor (insert <main id="content"> right after this),
//              before_anchor (insert </main> right before this) ]
$targets = array(
	57  => array( // EN homepage
		'after'  => "</nav>\n\n<section class=\"ln-hero\">",
		'before' => '<footer class="ln-foot">',
		'label'  => 'EN homepage',
	),
	772 => array( // ES homepage
		'after'  => "</nav>\n\n<section class=\"ln-hero\">",
		'before' => '<footer class="ln-foot">',
		'label'  => 'ES homepage',
	),
	61  => array( // EN products archive
		'after'  => '</header>',
		'before' => '<footer class="lp-footer">',
		'label'  => 'EN products archive',
	),
	771 => array( // ES products archive
		'after'  => '</header>',
		'before' => '<footer class="lp-footer">',
		'label'  => 'ES products archive',
	),
);

function lunaci_wrap_main_in_html( string $html, string $after_anchor, string $before_anchor, string $label ): ?string {
	if ( strpos( $html, '<main' ) !== false ) {
		echo "  SKIP ($label): <main already present, not touching\n";
		return null;
	}

	$after_count = substr_count( $html, $after_anchor );
	if ( $after_count !== 1 ) {
		echo "  ABORT ($label): expected exactly 1 occurrence of the 'after' anchor, found $after_count\n";
		return null;
	}

	$before_count = substr_count( $html, $before_anchor );
	if ( $before_count !== 1 ) {
		echo "  ABORT ($label): expected exactly 1 occurrence of the 'before' anchor, found $before_count\n";
		return null;
	}

	$html = str_replace( $after_anchor, $after_anchor . "\n<main id=\"content\">", $html );
	$html = str_replace( $before_anchor, "</main>\n\n" . $before_anchor, $html );

	return $html;
}

function lunaci_walk_elements_for_main( array $elements, string $after_anchor, string $before_anchor, string $label, int &$changed ): array {
	foreach ( $elements as &$element ) {
		if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'html' && isset( $element['settings']['html'] ) ) {
			$new_html = lunaci_wrap_main_in_html( $element['settings']['html'], $after_anchor, $before_anchor, $label );
			if ( $new_html !== null ) {
				$element['settings']['html'] = $new_html;
				$changed++;
			}
		}
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = lunaci_walk_elements_for_main( $element['elements'], $after_anchor, $before_anchor, $label, $changed );
		}
	}
	return $elements;
}

foreach ( $targets as $post_id => $config ) {
	echo "=== Post $post_id ({$config['label']}) ===\n";

	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! $raw ) {
		echo "  SKIP: no _elementor_data\n";
		continue;
	}

	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) ) {
		echo "  ABORT: could not decode _elementor_data JSON\n";
		continue;
	}

	$changed = 0;
	$decoded = lunaci_walk_elements_for_main( $decoded, $config['after'], $config['before'], $config['label'], $changed );

	if ( $changed === 0 ) {
		continue;
	}

	$new_raw = wp_json_encode( $decoded );

	$updated = $wpdb->update(
		$wpdb->postmeta,
		array( 'meta_value' => $new_raw ),
		array( 'post_id' => $post_id, 'meta_key' => '_elementor_data' )
	);

	if ( $updated === false ) {
		echo "  ABORT: \$wpdb->update failed\n";
		continue;
	}

	$readback    = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'",
		$post_id
	) );
	$has_main    = strpos( $readback, '<main id="content">' ) !== false;
	$has_main_close = strpos( $readback, '</main>' ) !== false;

	clean_post_cache( $post_id );

	echo "  OK: wrapped in <main id=\"content\">, readback confirms open tag: " . ( $has_main ? 'yes' : 'NO' ) . ", close tag: " . ( $has_main_close ? 'yes' : 'NO' ) . "\n";
}

echo "\nOK: fix completed successfully\n";
