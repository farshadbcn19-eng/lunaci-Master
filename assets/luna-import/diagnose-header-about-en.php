<?php
/**
 * Read-only. (1) Code Snippet 8 (LUNACI Global Header): full code and md5.
 * (2) English About page (post 59): HTML widget ce307e5, full HTML and md5.
 * (3) Where the theme footer's "All rights reserved" comes from.
 */
global $wpdb;
$row = $wpdb->get_row( "SELECT id, name, active, scope, priority, LENGTH(code) AS len, code FROM {$wpdb->prefix}snippets WHERE id = 8", ARRAY_A );
if ( $row ) {
	echo "=== snippet 8 '{$row['name']}' active={$row['active']} scope={$row['scope']} priority={$row['priority']} len={$row['len']} md5=" . md5( $row['code'] ) . "\n";
	echo "----- BEGIN snippet 8 -----\n" . $row['code'] . "\n----- END snippet 8 -----\n";
} else {
	echo "snippet 8 not found\n";
}
foreach ( $wpdb->get_results( "SELECT id, name, active, scope FROM {$wpdb->prefix}snippets WHERE code LIKE '%lunaciGlobalNav%' OR code LIKE '%ln-nav%'", ARRAY_A ) as $r ) {
	echo "snippet with header markup: {$r['id']} '{$r['name']}' active={$r['active']} scope={$r['scope']}\n";
}

$data = json_decode( (string) get_post_meta( 59, '_elementor_data', true ), true );
$walk = function ( $els ) use ( &$walk ) {
	foreach ( (array) $els as $el ) {
		if ( ( $el['id'] ?? '' ) === 'ce307e5' ) {
			$h = (string) ( $el['settings']['html'] ?? '' );
			echo "=== post 59 widget ce307e5 bytes=" . strlen( $h ) . ' md5=' . md5( $h ) . "\n";
			echo "----- BEGIN about-en widget -----\n" . $h . "\n----- END about-en widget -----\n";
		}
		if ( ! empty( $el['elements'] ) ) {
			$walk( $el['elements'] );
		}
	}
};
$walk( $data );

echo "=== footer copyright sources\n";
echo 'theme: ' . get_stylesheet() . ' / ' . get_template() . "\n";
foreach ( (array) get_theme_mods() as $k => $v ) {
	if ( is_scalar( $v ) && ( false !== stripos( $k, 'footer' ) || false !== stripos( (string) $v, 'rights' ) ) ) {
		echo "theme_mod $k = " . wp_json_encode( $v ) . "\n";
	}
}
$kit = (int) get_option( 'elementor_active_kit' );
$ks  = (array) get_post_meta( $kit, '_elementor_page_settings', true );
foreach ( $ks as $k => $v ) {
	if ( is_scalar( $v ) && ( false !== stripos( $k, 'footer' ) || false !== stripos( (string) $v, 'rights' ) ) ) {
		echo "kit $kit setting $k = " . wp_json_encode( $v ) . "\n";
	}
}
foreach ( $wpdb->get_results( "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE '%All rights reserved%' LIMIT 20" ) as $r ) {
	echo "option containing 'All rights reserved': {$r->option_name}\n";
}
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_strings'" ) ) {
	foreach ( $wpdb->get_results( "SELECT s.id, s.context, s.name, s.value, (SELECT t.value FROM {$wpdb->prefix}icl_string_translations t WHERE t.string_id = s.id AND t.language = 'es' LIMIT 1) AS es FROM {$wpdb->prefix}icl_strings s WHERE s.value LIKE '%All rights reserved%'", ARRAY_A ) as $r ) {
		echo "WPML string {$r['id']} [{$r['context']}] {$r['name']} = " . wp_json_encode( $r['value'] ) . ' es=' . wp_json_encode( $r['es'] ) . "\n";
	}
}
$footer = get_template_directory() . '/template-parts/footer.php';
if ( is_readable( $footer ) ) {
	foreach ( file( $footer ) as $n => $line ) {
		if ( false !== stripos( $line, 'copyright' ) || false !== stripos( $line, 'rights' ) ) {
			echo 'footer.php:' . ( $n + 1 ) . ': ' . trim( $line ) . "\n";
		}
	}
}
