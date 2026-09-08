<?php
/**
 * Read-only, round 2. Round 1 found the homepage is built as raw HTML
 * inside a single Elementor HTML widget (no native heading/menu widgets),
 * and that post 772 (ES homepage) has zero matches for the best-sellers
 * strings - so the ES text is likely injected via WPML String Translation,
 * not stored in post 772's own _elementor_data. This confirms which
 * mechanism actually owns each piece of content before writing any fix.
 */

global $wpdb;

echo "--- post 772 (ES homepage) _elementor_data presence ---\n";
$es_data = get_post_meta( 772, '_elementor_data', true );
echo 'strlen: ' . strlen( (string) $es_data ) . "\n";
if ( $es_data ) {
	foreach ( array( 'Vendidos', 'Colecci', 'ln-hero', 'ln-nav' ) as $needle ) {
		$pos = mb_strpos( $es_data, $needle );
		echo "  contains '{$needle}': " . ( $pos !== false ? "yes (offset {$pos})" : 'no' ) . "\n";
	}
}

echo "\n--- WPML string translation tables ---\n";
foreach ( array( 'icl_strings', 'icl_string_translations' ) as $t ) {
	$full = $wpdb->prefix . $t;
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $full ) ) );
	echo "table {$full} exists: " . ( $exists ? 'yes' : 'no' ) . "\n";
}
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'icl_strings' ) ) ) ) {
	$rows = $wpdb->get_results(
		"SELECT s.id, s.name, s.value FROM {$wpdb->prefix}icl_strings s WHERE s.value LIKE '%Best Sellers%' OR s.value LIKE '%Vendidos%'",
		ARRAY_A
	);
	echo 'matching icl_strings rows: ' . count( $rows ) . "\n";
	foreach ( $rows as $r ) {
		echo "  id={$r['id']} name='{$r['name']}' value='" . substr( $r['value'], 0, 200 ) . "'\n";
		$translations = $wpdb->get_results(
			$wpdb->prepare( "SELECT language, value FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d", $r['id'] ),
			ARRAY_A
		);
		foreach ( $translations as $tr ) {
			echo "    -> [{$tr['language']}] " . substr( $tr['value'], 0, 300 ) . "\n";
		}
	}
}

echo "\n--- post 57 (EN homepage): nav bar + hero section raw HTML ---\n";
$en_data = get_post_meta( 57, '_elementor_data', true );
foreach ( array( 'ln-nav', 'ln-hero', '>About<', '>Contact<' ) as $needle ) {
	$pos = mb_strpos( $en_data, $needle );
	if ( $pos !== false ) {
		echo "  '{$needle}' at offset {$pos}:\n";
		echo '  ...' . mb_substr( $en_data, max( 0, $pos - 200 ), 500 ) . "...\n\n";
	} else {
		echo "  '{$needle}': not found\n";
	}
}

echo "\nOK: read-only diagnostic complete\n";
