<?php
/**
 * Read-only: locate (1) the ES "Ver Más Vendidos" link that wrongly points
 * to the English /products/ catalog, (2) nav menu items using short-form
 * URLs (/about, /contact) that 301-redirect, and (3) the homepage hero
 * heading's widget structure, to plan promoting it to a real H1. No writes.
 */

global $wpdb;

echo "--- ES homepage post ID ---\n";
$icl_table = $wpdb->prefix . 'icl_translations';
$es_home_id = $wpdb->get_var( $wpdb->prepare(
	"SELECT t2.element_id FROM {$icl_table} t1 JOIN {$icl_table} t2 ON t1.trid = t2.trid
	 WHERE t1.element_id = %d AND t1.element_type = 'post_page' AND t2.language_code = 'es'",
	57
) );
echo 'es_home_id: ' . var_export( $es_home_id, true ) . "\n";

echo "\n--- nav menus: items with short-form /about or /contact URLs ---\n";
$menus = wp_get_nav_menus();
foreach ( $menus as $menu ) {
	$items = wp_get_nav_menu_items( $menu->term_id );
	if ( ! $items ) {
		continue;
	}
	foreach ( $items as $item ) {
		if ( preg_match( '#/(about|contact)/?$#', rtrim( $item->url, '/' ) . '/' ) && ! preg_match( '#/(about-us|about-us-es|contacto)/#', $item->url ) ) {
			echo "menu='{$menu->name}' item_id={$item->ID} title='{$item->title}' url='{$item->url}'\n";
		}
	}
}

echo "\n--- homepage (post 57) content source ---\n";
$post57 = get_post( 57 );
echo 'post_type: ' . $post57->post_type . "\n";
$uses_elementor = get_post_meta( 57, '_elementor_edit_mode', true );
echo 'elementor edit mode: ' . var_export( $uses_elementor, true ) . "\n";

foreach ( array( 57 => 'EN', (int) $es_home_id => 'ES' ) as $pid => $lang ) {
	if ( ! $pid ) {
		continue;
	}
	echo "\n--- {$lang} homepage (post {$pid}): searching _elementor_data for target strings ---\n";
	$data = get_post_meta( $pid, '_elementor_data', true );
	if ( ! $data ) {
		echo "  no _elementor_data found\n";
		continue;
	}
	// Find "Ver Más Vendidos" / "View Best Sellers" and their href, and any heading widgets.
	foreach ( array( 'Ver Más Vendidos', 'View Best Sellers', 'Best Sellers' ) as $needle ) {
		$pos = mb_strpos( $data, $needle );
		if ( $pos !== false ) {
			echo "  found '{$needle}' at offset {$pos}:\n";
			echo '  context: ...' . mb_substr( $data, max( 0, $pos - 250 ), 500 ) . "...\n\n";
		}
	}

	// Dump every heading-type widget (title/heading widgets) with their tag + text, to find the hero headline.
	$decoded = json_decode( $data, true );
	if ( is_array( $decoded ) ) {
		$headings = array();
		$walk = function ( $elements ) use ( &$walk, &$headings ) {
			foreach ( $elements as $el ) {
				if ( isset( $el['widgetType'] ) && in_array( $el['widgetType'], array( 'heading', 'theme-post-title' ), true ) ) {
					$headings[] = array(
						'widgetType' => $el['widgetType'],
						'id'         => $el['id'] ?? null,
						'header_size' => $el['settings']['header_size'] ?? null,
						'title'      => $el['settings']['title'] ?? null,
					);
				}
				if ( ! empty( $el['elements'] ) ) {
					$walk( $el['elements'] );
				}
			}
		};
		$walk( $decoded );
		echo '  heading widgets found: ' . count( $headings ) . "\n";
		foreach ( $headings as $h ) {
			echo '  ' . json_encode( $h, JSON_UNESCAPED_UNICODE ) . "\n";
		}
	}
}

echo "\nOK: read-only diagnostic complete\n";
