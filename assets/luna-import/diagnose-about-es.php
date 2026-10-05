<?php
/**
 * Read-only. Spanish About page (post 680): slug, template, Elementor
 * structure and the full HTML of its HTML widget(s); the English About page
 * for comparison; and every place that still links /es/about-us-es/.
 */
global $wpdb;
foreach ( array( 680, 59 ) as $id ) {
	$p = get_post( $id );
	if ( ! $p ) {
		echo "post $id: NOT FOUND\n";
		continue;
	}
	$lang = apply_filters( 'wpml_post_language_details', null, $id );
	echo "=== post $id '{$p->post_title}' slug={$p->post_name} status={$p->post_status} lang=" . ( $lang['language_code'] ?? '?' ) . ' template=' . get_post_meta( $id, '_wp_page_template', true ) . ' edit_mode=' . get_post_meta( $id, '_elementor_edit_mode', true ) . "\n";
	echo 'old slugs: ' . wp_json_encode( get_post_meta( $id, '_wp_old_slug' ) ) . "\n";
	echo 'post_content length: ' . strlen( $p->post_content ) . "\n";
	$data = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
	$walk = function ( $els, $depth ) use ( &$walk, $id ) {
		foreach ( (array) $els as $el ) {
			$type = $el['widgetType'] ?? $el['elType'] ?? '?';
			echo str_repeat( '  ', $depth ) . "- {$type} id=" . ( $el['id'] ?? '?' ) . "\n";
			if ( 'html' === $type && 680 === $id ) {
				echo "----- BEGIN HTML widget {$el['id']} (" . strlen( $el['settings']['html'] ?? '' ) . " bytes) -----\n";
				echo $el['settings']['html'] ?? '';
				echo "\n----- END HTML widget {$el['id']} -----\n";
			} elseif ( 'html' === $type ) {
				echo str_repeat( '  ', $depth ) . '  html bytes=' . strlen( $el['settings']['html'] ?? '' ) . ' md5=' . md5( $el['settings']['html'] ?? '' ) . "\n";
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'], $depth + 1 );
			}
		}
	};
	$walk( $data, 1 );
	if ( 680 === $id && ! $data ) {
		echo "----- BEGIN post_content -----\n" . $p->post_content . "\n----- END post_content -----\n";
	}
}
echo "=== references to about-us-es\n";
foreach ( $wpdb->get_results( "SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_content LIKE '%about-us-es%'" ) as $r ) {
	echo "post_content: {$r->ID} {$r->post_type} {$r->post_status}\n";
}
foreach ( $wpdb->get_results( "SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_value LIKE '%about-us-es%' LIMIT 50" ) as $r ) {
	echo "postmeta: {$r->post_id} {$r->meta_key}\n";
}
foreach ( $wpdb->get_results( "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE '%about-us-es%' LIMIT 50" ) as $r ) {
	echo "option: {$r->option_name}\n";
}
$snip = $wpdb->prefix . 'snippets';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$snip'" ) ) {
	foreach ( $wpdb->get_results( "SELECT id, name, active FROM $snip WHERE code LIKE '%about-us-es%'" ) as $r ) {
		echo "snippet: {$r->id} '{$r->name}' active={$r->active}\n";
	}
}
foreach ( wp_get_nav_menus() as $menu ) {
	foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
		if ( (int) $item->object_id === 680 || false !== strpos( (string) $item->url, 'about-us-es' ) ) {
			echo "menu '{$menu->name}': item {$item->ID} '{$item->title}' type={$item->type} url={$item->url}\n";
		}
	}
}
$tr = apply_filters( 'wpml_element_trid', null, 680, 'post_page' );
echo 'WPML trid for 680: ' . wp_json_encode( $tr ) . ' translations: ' . wp_json_encode( apply_filters( 'wpml_get_element_translations', null, $tr, 'post_page' ) ? array_map( function ( $t ) { return $t->element_id; }, (array) apply_filters( 'wpml_get_element_translations', null, $tr, 'post_page' ) ) : null ) . "\n";
