<?php
/**
 * Read-only. Where the "All" filter button and every /all-products/ link
 * come from, the state of page 836 ("All Products"), and the category
 * banner snippet (Code Snippets id 7) rule for the banner image.
 */
global $wpdb;
$p = get_post( 836 );
echo "page 836: " . ( $p ? "{$p->post_title} /{$p->post_name}/ status={$p->post_status} template=" . get_post_meta( 836, '_wp_page_template', true ) . ' elementor=' . ( get_post_meta( 836, '_elementor_edit_mode', true ) ?: 'no' ) : 'missing' ) . "\n";
echo 'WPML language of 836: ' . wp_json_encode( apply_filters( 'wpml_element_language_details', null, array( 'element_id' => 836, 'element_type' => 'page' ) ) ) . "\n";
echo "\n== Code Snippets (wp_snippets) mentioning the filter button / all-products\n";
foreach ( $wpdb->get_results( "SELECT id, name, active, scope, CHAR_LENGTH(code) n FROM {$wpdb->prefix}snippets WHERE code LIKE '%lunaci-filter-btn%' OR code LIKE '%all-products%' OR code LIKE '%lunaci-category-banner%'" ) as $r ) {
	echo "snippet {$r->id} '{$r->name}' active={$r->active} scope={$r->scope} len={$r->n}\n";
	$code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$wpdb->prefix}snippets WHERE id=%d", $r->id ) );
	foreach ( array( 'all-products', 'All</a>', "'All'", 'Todos', 'lunaci-category-banner__img {', 'height: 100%' ) as $needle ) {
		$pos = 0;
		while ( false !== ( $pos = strpos( $code, $needle, $pos ) ) ) {
			echo "   [$needle] @$pos: " . str_replace( array( "\n", "\t" ), array( '\\n', ' ' ), substr( $code, max( 0, $pos - 160 ), 320 ) ) . "\n";
			$pos += strlen( $needle );
		}
	}
}
echo "\n== posts / postmeta / options linking to all-products\n";
foreach ( $wpdb->get_results( "SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts} WHERE post_content LIKE '%all-products%' AND post_type NOT IN ('revision')" ) as $r ) {
	echo "post_content: {$r->ID} {$r->post_type} {$r->post_status} '{$r->post_title}'\n";
}
foreach ( $wpdb->get_results( "SELECT pm.post_id, pm.meta_key, p.post_type, p.post_status, p.post_title FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_value LIKE '%all-products%' AND p.post_type<>'revision'" ) as $r ) {
	echo "postmeta: {$r->post_id} {$r->meta_key} {$r->post_type} {$r->post_status} '{$r->post_title}'\n";
}
foreach ( $wpdb->get_results( "SELECT option_name, CHAR_LENGTH(option_value) n FROM {$wpdb->options} WHERE option_value LIKE '%all-products%'" ) as $r ) {
	echo "option: {$r->option_name} ({$r->n})\n";
}
foreach ( $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type='nav_menu_item' AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE (meta_key='_menu_item_object_id' AND meta_value='836') OR (meta_key='_menu_item_url' AND meta_value LIKE '%all-products%'))" ) as $r ) {
	echo "menu item: {$r->ID} '{$r->post_title}'\n";
}
echo "\nAIOSEO redirects table: " . ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}aioseo_redirects'" ) ? 'present' : 'none' ) . "\n";
echo 'sitemap includes 836? robots noindex meta: ' . wp_json_encode( $wpdb->get_row( "SELECT robots_noindex, robots_default FROM {$wpdb->prefix}aioseo_posts WHERE post_id=836" ) ) . "\n";
