<?php
/**
 * Read-only. Every stored copy of the Spanish quote "Todas las mujeres son
 * vistas" (and "Toda mujer es vista"): Elementor data of every post, WPML
 * string translations, Code Snippets, options. For post 772 (Spanish front
 * page) and 680 it prints the widget id, md5 and the text around each hit.
 */
global $wpdb;
$needles = array( 'Todas las mujeres son vistas', 'Toda mujer es vista' );
foreach ( $needles as $n ) {
	$like = '%' . $wpdb->esc_like( $n ) . '%';
	echo "=== '$n'\n";
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_type, p.post_status, p.post_title, m.meta_key FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_value LIKE %s", $like ) ) as $r ) {
		echo "postmeta: post {$r->ID} {$r->post_type}/{$r->post_status} '{$r->post_title}' key={$r->meta_key}\n";
	}
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE post_content LIKE %s", $like ) ) as $r ) {
		echo "post_content: {$r->ID} {$r->post_type}/{$r->post_status}\n";
	}
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_string_translations'" ) ) {
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT t.id, t.string_id, t.language, t.status, s.context, s.name, LENGTH(t.value) AS len, MD5(t.value) AS md5 FROM {$wpdb->prefix}icl_string_translations t JOIN {$wpdb->prefix}icl_strings s ON s.id = t.string_id WHERE t.value LIKE %s", $like ) ) as $r ) {
			echo "WPML string translation: id={$r->id} string={$r->string_id} lang={$r->language} status={$r->status} [{$r->context}] {$r->name} len={$r->len} md5={$r->md5}\n";
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, context, name, language FROM {$wpdb->prefix}icl_strings WHERE value LIKE %s", $like ) ) as $r ) {
			echo "WPML string: id={$r->id} [{$r->context}] {$r->name} lang={$r->language}\n";
		}
	}
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM {$wpdb->prefix}snippets WHERE code LIKE %s", $like ) ) as $r ) {
		echo "snippet: {$r->id} '{$r->name}'\n";
	}
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s", $like ) ) as $r ) {
		echo "option: {$r->option_name}\n";
	}
}
foreach ( array( 772, 680 ) as $id ) {
	$raw  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_elementor_data'", $id ) );
	echo "=== post $id _elementor_data md5=" . md5( $raw ) . ' bytes=' . strlen( $raw ) . "\n";
	$walk = function ( $els ) use ( &$walk ) {
		foreach ( (array) $els as $el ) {
			foreach ( (array) ( $el['settings'] ?? array() ) as $k => $v ) {
				if ( is_string( $v ) && false !== strpos( $v, 'Todas las mujeres' ) ) {
					$c = substr_count( $v, 'Todas las mujeres son vistas' );
					echo '  widget ' . ( $el['id'] ?? '?' ) . ' (' . ( $el['widgetType'] ?? $el['elType'] ?? '?' ) . ") setting '$k' md5=" . md5( $v ) . " hits=$c\n";
					$off = 0;
					while ( false !== ( $p = strpos( $v, 'Todas las mujeres', $off ) ) ) {
						echo '    ...' . str_replace( "\n", '\n', substr( $v, max( 0, $p - 160 ), 300 ) ) . "...\n";
						$off = $p + 10;
					}
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	$walk( json_decode( $raw, true ) );
}
