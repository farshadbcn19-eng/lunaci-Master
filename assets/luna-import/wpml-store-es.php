<?php
/**
 * WPML Spanish translations for the store and site strings that still have
 * none:
 *  - WooCommerce admin texts: checkout and registration privacy notices,
 *    email footer, sender address, price separators; Jetpack-style sharing
 *    label; the cart/checkout/account Elementor shortcode strings
 *  - the 15 account/checkout endpoint slugs ("WP Endpoints")
 *  - All in One SEO localized templates (breadcrumbs, 404, paging, titles)
 *  - Spanish terms for the 36 pa_color shades and the default product
 *    category "Uncategorized"
 * Empty strings (blank email subjects/headings, empty AIOSEO fields) are left
 * alone: WooCommerce and AIOSEO use their own translated defaults for them.
 * String translations are direct database writes (status 10, complete).
 * Terms are created through WordPress/WPML in Spanish and linked to their
 * English term; no product is changed.
 *
 * LUNACI_MODE=dry-run | apply | rollback (backup: $LUNACI_BACKUP_DIR/wpml-store-es-backup.json)
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$st  = $wpdb->prefix . 'icl_strings';
$stt = $wpdb->prefix . 'icl_string_translations';
$tr  = $wpdb->prefix . 'icl_translations';
$bk  = rtrim( $dir, '/' ) . '/wpml-store-es-backup.json';

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $bk ), true );
	if ( ! is_array( $b ) || ! isset( $b['translations'] ) ) {
		echo "ABORT: backup unreadable: $bk\n";
		exit( 1 );
	}
	foreach ( $b['translations'] as $sid => $old ) {
		$wpdb->delete( $stt, array( 'string_id' => (int) $sid, 'language' => 'es' ) );
		if ( $old ) {
			$wpdb->insert( $stt, $old );
		}
		$wpdb->update( $st, array( 'status' => (int) $b['status'][ $sid ] ), array( 'id' => (int) $sid ) );
	}
	foreach ( $b['terms'] as $t ) {
		wp_delete_term( (int) $t['term_id'], $t['taxonomy'] );
		$wpdb->delete( $tr, array( 'element_id' => (int) $t['tt_id'], 'element_type' => 'tax_' . $t['taxonomy'] ) );
	}
	flush_rewrite_rules( false );
	wp_cache_flush();
	echo 'ROLLBACK OK: ' . count( $b['translations'] ) . ' string translations and ' . count( $b['terms'] ) . " terms reverted\n";
	exit( 0 );
}

// context => name => Spanish (null = same as the English value)
$plan_strings = array(
	'admin_texts_woocommerce_checkout_privacy_policy_text'     => array( 'woocommerce_checkout_privacy_policy_text' => 'Utilizaremos tus datos personales para procesar tu pedido, facilitar tu experiencia en esta web y para los demás fines descritos en nuestra [privacy_policy].' ),
	'admin_texts_woocommerce_registration_privacy_policy_text' => array( 'woocommerce_registration_privacy_policy_text' => 'Utilizaremos tus datos personales para facilitar tu experiencia en esta web, gestionar el acceso a tu cuenta y para los demás fines descritos en nuestra [privacy_policy].' ),
	'admin_texts_woocommerce_email_footer_text'                => array( 'woocommerce_email_footer_text' => null ),
	'admin_texts_woocommerce_email_from_address'               => array( 'woocommerce_email_from_address' => null ),
	'admin_texts_woocommerce_price_decimal_sep'                => array( 'woocommerce_price_decimal_sep' => null ),
	'admin_texts_woocommerce_price_thousand_sep'               => array( 'woocommerce_price_thousand_sep' => null ),
	'admin_texts_sharing-options'                              => array( '[sharing-options][global]sharing_label' => 'Compartir:' ),
	'elementor-62'                                             => array( 'shortcode-shortcode-d088cbe' => null ),
	'elementor-63'                                             => array( 'shortcode-shortcode-83b0fa0' => null ),
	'elementor-64'                                             => array( 'shortcode-shortcode-18c27c9' => null ),
);
// AIOSEO: by English value; values made only of tags stay the same.
$aioseo_es = array(
	'Archives for #breadcrumb_archive_post_type_name'          => 'Archivo de #breadcrumb_archive_post_type_name',
	"Search Results for '#breadcrumb_search_string'"           => "Resultados de búsqueda de '#breadcrumb_search_string'",
	'404 - Page Not Found'                                      => '404 - Página no encontrada',
	'#separator_sa Page #page_number'                          => '#separator_sa Página #page_number',
);
// Endpoint slugs: by English value.
$endpoint_es = array(
	'orders'                     => 'pedidos',
	'view-order'                 => 'ver-pedido',
	'downloads'                  => 'descargas',
	'edit-account'               => 'editar-cuenta',
	'edit-address'               => 'editar-direccion',
	'payment-methods'            => 'metodos-de-pago',
	'lost-password'              => 'recuperar-contrasena',
	'customer-logout'            => 'cerrar-sesion',
	'add-payment-method'         => 'anadir-metodo-de-pago',
	'delete-payment-method'      => 'eliminar-metodo-de-pago',
	'set-default-payment-method' => 'metodo-de-pago-predeterminado',
	'order-pay'                  => 'pagar-pedido',
	'order-received'             => 'pedido-recibido',
	'my-account'                 => 'mi-cuenta',
	'pay'                        => 'pagar',
);

$fail = 0;
$todo = array(); // sid => array( en, es, label )
foreach ( $plan_strings as $ctx => $names ) {
	foreach ( $names as $name => $es ) {
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT id, value FROM $st WHERE context=%s AND name=%s AND language='en'", $ctx, $name ) );
		if ( ! $r ) {
			echo "FAIL: string not found [$ctx] $name\n";
			$fail = 1;
			continue;
		}
		$todo[ (int) $r->id ] = array( $r->value, null === $es ? $r->value : $es, "[$ctx] $name" );
	}
}
foreach ( $wpdb->get_results( "SELECT id, name, value FROM $st WHERE context='admin_texts_aioseo_options_localized' AND language='en'" ) as $r ) {
	$v = (string) $r->value;
	if ( '' === trim( $v ) ) {
		continue;
	}
	if ( isset( $aioseo_es[ $v ] ) ) {
		$todo[ (int) $r->id ] = array( $v, $aioseo_es[ $v ], "[aioseo] {$r->name}" );
	} elseif ( '' === trim( preg_replace( '/#[a-z_]+/', '', $v ) ) ) {
		$todo[ (int) $r->id ] = array( $v, $v, "[aioseo] {$r->name}" );
	} else {
		echo "FAIL: AIOSEO text without a planned translation: {$r->name} = $v\n";
		$fail = 1;
	}
}
foreach ( $wpdb->get_results( "SELECT id, name, value FROM $st WHERE context='WP Endpoints' AND language='en'" ) as $r ) {
	if ( isset( $endpoint_es[ $r->value ] ) ) {
		$todo[ (int) $r->id ] = array( $r->value, $endpoint_es[ $r->value ], "[endpoint] {$r->name}" );
	} else {
		echo "FAIL: endpoint without a planned translation: {$r->name} = {$r->value}\n";
		$fail = 1;
	}
}
foreach ( $wpdb->get_results( "SELECT id, context, name, value FROM $st WHERE context IN ('WP','gutenberg-1') AND language='en'" ) as $r ) {
	echo "INFO (not planned): [{$r->context}] {$r->name} = " . substr( $r->value, 0, 80 ) . "\n";
}
foreach ( $todo as $sid => $t ) {
	$ex = $wpdb->get_row( $wpdb->prepare( "SELECT status, value FROM $stt WHERE string_id=%d AND language='es'", $sid ) );
	printf( "STR %d %s: \"%s\" -> \"%s\"%s\n", $sid, $t[2], substr( $t[0], 0, 70 ), substr( $t[1], 0, 70 ), $ex ? " (existing es status {$ex->status})" : '' );
}

// Terms
$plan_terms = array();
foreach ( array( 'pa_color', 'product_cat' ) as $tax ) {
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.trid, tt.term_id, tt.term_taxonomy_id FROM $tr t JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=t.element_id WHERE t.element_type=%s AND t.language_code='en' AND t.source_language_code IS NULL", 'tax_' . $tax ) );
	foreach ( $rows as $o ) {
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM $tr WHERE trid=%d AND language_code='es'", $o->trid ) ) ) {
			continue;
		}
		$term = get_term( (int) $o->term_id, $tax );
		if ( 'product_cat' === $tax && 'uncategorized' !== $term->slug ) {
			echo "SKIP product_cat '{$term->name}' ({$term->count} products, legacy and empty: candidate for deletion)\n";
			continue;
		}
		$es_name = 'product_cat' === $tax ? 'Sin categoría' : $term->name;
		$es_slug = 'product_cat' === $tax ? 'sin-categoria' : $term->slug . '-es';
		if ( term_exists( $es_slug, $tax ) ) {
			echo "FAIL: slug $es_slug already exists in $tax\n";
			$fail = 1;
		}
		$plan_terms[] = array( 'tax' => $tax, 'trid' => (int) $o->trid, 'en' => $term, 'name' => $es_name, 'slug' => $es_slug );
		echo "TERM $tax '{$term->name}' -> es '$es_name' ($es_slug)\n";
	}
}
echo 'planned: ' . count( $todo ) . ' string translations, ' . count( $plan_terms ) . " terms\n";
if ( $fail ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}

// apply
$b = array( 'translations' => array(), 'status' => array(), 'terms' => array() );
foreach ( $todo as $sid => $t ) {
	$b['translations'][ $sid ] = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $stt WHERE string_id=%d AND language='es'", $sid ), ARRAY_A );
	$b['status'][ $sid ]       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $st WHERE id=%d", $sid ) );
}
if ( false === file_put_contents( $bk, wp_json_encode( $b ) ) ) {
	echo "ABORT: cannot write backup $bk\n";
	exit( 1 );
}
$now = current_time( 'mysql', true );
$bad = 0;
foreach ( $todo as $sid => $t ) {
	$wpdb->delete( $stt, array( 'string_id' => $sid, 'language' => 'es' ) );
	$wpdb->insert( $stt, array( 'string_id' => $sid, 'language' => 'es', 'status' => 10, 'value' => $t[1], 'translation_date' => $now ) );
	$wpdb->update( $st, array( 'status' => 10 ), array( 'id' => $sid ) );
	$v = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM $stt WHERE string_id=%d AND language='es' AND status=10", $sid ) );
	if ( $v !== $t[1] ) {
		echo "FAIL verify string $sid\n";
		$bad = 1;
	}
}
echo 'strings: ' . count( $todo ) . " Spanish translations stored\n";

do_action( 'wpml_switch_language', 'es' );
foreach ( $plan_terms as $p ) {
	$res = wp_insert_term( $p['name'], $p['tax'], array( 'slug' => $p['slug'], 'description' => $p['en']->description ) );
	if ( is_wp_error( $res ) ) {
		echo "FAIL term {$p['name']}: " . $res->get_error_message() . "\n";
		$bad = 1;
		continue;
	}
	do_action(
		'wpml_set_element_language_details',
		array(
			'element_id'           => (int) $res['term_taxonomy_id'],
			'element_type'         => 'tax_' . $p['tax'],
			'trid'                 => $p['trid'],
			'language_code'        => 'es',
			'source_language_code' => 'en',
		)
	);
	$b['terms'][] = array( 'term_id' => (int) $res['term_id'], 'tt_id' => (int) $res['term_taxonomy_id'], 'taxonomy' => $p['tax'] );
	file_put_contents( $bk, wp_json_encode( $b ) );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT trid, language_code, source_language_code FROM $tr WHERE element_id=%d AND element_type=%s", $res['term_taxonomy_id'], 'tax_' . $p['tax'] ) );
	if ( ! $row || (int) $row->trid !== $p['trid'] || 'es' !== $row->language_code ) {
		echo "FAIL link term {$p['name']}\n";
		$bad = 1;
	}
}
do_action( 'wpml_switch_language', 'en' );
echo 'terms: ' . count( $b['terms'] ) . " Spanish terms created and linked\n";
flush_rewrite_rules( false );
wp_cache_flush();
echo $bad ? "APPLY FINISHED WITH ERRORS (rollback available)\n" : "APPLY OK\n";
exit( $bad ? 1 : 0 );
