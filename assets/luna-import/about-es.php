<?php
/**
 * Spanish About page (post 680): Spanish copy in its HTML widget, slug
 * about-us-es -> sobre-nosotros (mu-plugins/lunaci-seo.php item 7 sends the
 * old URL there with a 301), and the hard-coded /es/about-us-es/ links in
 * other Spanish pages updated. The front page is never edited (hard rule);
 * its link reaches the page through the 301.
 *
 * Guards: the widget must still hold exactly the HTML read by the read-only
 * diagnostic (md5), the new HTML file must be the reviewed one (md5), and
 * the target slug must be free.
 *
 * LUNACI_MODE=dry-run | apply | rollback (rollback uses $LUNACI_BACKUP_DIR/about-es-backup.json)
 */

$mode = getenv( 'LUNACI_MODE' );
$dir  = getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
const LUNACI_ABOUT_ES   = 680;
const LUNACI_WIDGET     = 'ce307e5';
const LUNACI_OLD_MD5    = '10a5afcc01ec87d7071c1676455db19f';
const LUNACI_NEW_MD5    = '949c002dbebd85f75b3bb631aa7e287d';
const LUNACI_NEW_SLUG   = 'sobre-nosotros';
$old_url = 'https://lunacibarcelona.com/es/about-us-es/';
$new_url = 'https://lunacibarcelona.com/es/' . LUNACI_NEW_SLUG . '/';
$backup  = rtrim( $dir, '/' ) . '/about-es-backup.json';

function lunaci_clear_elementor_cache( $id ) {
	delete_post_meta( $id, '_elementor_element_cache' );
	delete_post_meta( $id, '_elementor_css' );
	clean_post_cache( $id );
}

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $backup ), true );
	if ( ! is_array( $b ) || empty( $b['data'] ) ) {
		echo "ABORT: no backup at $backup\n";
		exit( 1 );
	}
	foreach ( $b['data'] as $id => $raw ) {
		update_post_meta( (int) $id, '_elementor_data', wp_slash( $raw ) );
		lunaci_clear_elementor_cache( (int) $id );
		echo "restored _elementor_data of post $id\n";
	}
	wp_update_post( array( 'ID' => LUNACI_ABOUT_ES, 'post_name' => $b['slug'] ) );
	echo 'slug: ' . get_post_field( 'post_name', LUNACI_ABOUT_ES ) . "\n";
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	wp_cache_flush();
	echo "ROLLBACK DONE\n";
	exit( 0 );
}

$fail = 0;
$page = get_post( LUNACI_ABOUT_ES );
if ( ! $page || 'publish' !== $page->post_status || 'about-us-es' !== $page->post_name ) {
	echo 'FAIL post 680 is not the published page about-us-es (slug=' . ( $page ? $page->post_name : '-' ) . ")\n";
	$fail = 1;
}
$new_html = (string) file_get_contents( getenv( 'LUNACI_WIDGET_HTML' ) );
if ( md5( $new_html ) !== LUNACI_NEW_MD5 ) {
	echo "FAIL new widget HTML is not the reviewed file (md5 " . md5( $new_html ) . ")\n";
	$fail = 1;
}
$raw  = (string) get_post_meta( LUNACI_ABOUT_ES, '_elementor_data', true );
$data = json_decode( $raw, true );
$hit  = 0;
$swap = function ( &$els ) use ( &$swap, &$hit, $new_html, &$fail ) {
	foreach ( $els as &$el ) {
		if ( ( $el['id'] ?? '' ) === LUNACI_WIDGET && 'html' === ( $el['widgetType'] ?? '' ) ) {
			$cur = (string) ( $el['settings']['html'] ?? '' );
			if ( md5( $cur ) !== LUNACI_OLD_MD5 ) {
				echo 'FAIL widget ' . LUNACI_WIDGET . ' changed since the diagnostic (md5 ' . md5( $cur ) . ")\n";
				$fail = 1;
			}
			$el['settings']['html'] = $new_html;
			$hit++;
		}
		if ( ! empty( $el['elements'] ) ) {
			$swap( $el['elements'] );
		}
	}
};
if ( is_array( $data ) ) {
	$swap( $data );
}
if ( 1 !== $hit ) {
	echo "FAIL widget " . LUNACI_WIDGET . " found $hit times in post 680\n";
	$fail = 1;
}
do_action( 'wpml_switch_language', 'es' );
$taken = get_page_by_path( LUNACI_NEW_SLUG );
do_action( 'wpml_switch_language', null );
if ( $taken && (int) $taken->ID !== LUNACI_ABOUT_ES ) {
	echo "FAIL slug " . LUNACI_NEW_SLUG . " already used by post {$taken->ID}\n";
	$fail = 1;
}

// Other pages that hard-code the old URL (never the front page, in any language).
global $wpdb;
$front = array_filter( array( (int) get_option( 'page_on_front' ) ) );
foreach ( $front as $f ) {
	foreach ( (array) apply_filters( 'wpml_get_element_translations', null, apply_filters( 'wpml_element_trid', null, $f, 'post_page' ), 'post_page' ) as $t ) {
		$front[] = (int) $t->element_id;
	}
}
$front  = array_unique( $front );
$others = array();
$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_elementor_data' AND meta_value LIKE %s", '%about-us-es%' ) );
foreach ( $rows as $r ) {
	$id = (int) $r->post_id;
	if ( LUNACI_ABOUT_ES === $id ) {
		continue;
	}
	$title = get_the_title( $id );
	if ( in_array( $id, $front, true ) ) {
		echo "SKIP post $id '$title': front page (hard rule), its link goes through the 301\n";
		continue;
	}
	$n = substr_count( $r->meta_value, 'https:\/\/lunacibarcelona.com\/es\/about-us-es\/' ) + substr_count( $r->meta_value, $old_url );
	echo "PLAN post $id '$title' (" . get_post_status( $id ) . "): $n link(s) -> $new_url\n";
	$others[ $id ] = $r->meta_value;
}
echo 'front page ids: ' . implode( ',', $front ) . "\n";
echo "PLAN post 680: widget " . LUNACI_WIDGET . " -> Spanish copy; slug about-us-es -> " . LUNACI_NEW_SLUG . "\n";
if ( $fail ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}

$b = array( 'slug' => $page->post_name, 'data' => array( LUNACI_ABOUT_ES => $raw ) + $others );
if ( false === file_put_contents( $backup, wp_json_encode( $b ) ) ) {
	echo "ABORT: cannot write backup $backup\n";
	exit( 1 );
}
echo "backup: $backup\n";

update_post_meta( LUNACI_ABOUT_ES, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
lunaci_clear_elementor_cache( LUNACI_ABOUT_ES );
wp_update_post( array( 'ID' => LUNACI_ABOUT_ES, 'post_name' => LUNACI_NEW_SLUG ) );
foreach ( $others as $id => $val ) {
	$val = str_replace( array( 'https:\/\/lunacibarcelona.com\/es\/about-us-es\/', $old_url ), array( 'https:\/\/lunacibarcelona.com\/es\/' . LUNACI_NEW_SLUG . '\/', $new_url ), $val );
	update_post_meta( $id, '_elementor_data', wp_slash( $val ) );
	lunaci_clear_elementor_cache( $id );
}
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();

$bad   = 0;
$check = json_decode( (string) get_post_meta( LUNACI_ABOUT_ES, '_elementor_data', true ), true );
$found = '';
array_walk_recursive( $check, function ( $v, $k ) use ( &$found, $new_html ) { if ( 'html' === $k && $v === $new_html ) { $found = 'yes'; } } );
if ( 'yes' !== $found ) {
	echo "VERIFY FAIL: widget HTML not saved\n";
	$bad = 1;
}
if ( LUNACI_NEW_SLUG !== get_post_field( 'post_name', LUNACI_ABOUT_ES ) ) {
	echo "VERIFY FAIL: slug\n";
	$bad = 1;
}
do_action( 'wpml_switch_language', 'es' );
echo 'permalink: ' . get_permalink( LUNACI_ABOUT_ES ) . "\n";
do_action( 'wpml_switch_language', null );
foreach ( array_keys( $others ) as $id ) {
	if ( false !== strpos( (string) get_post_meta( $id, '_elementor_data', true ), 'about-us-es' ) ) {
		echo "VERIFY FAIL: post $id still links about-us-es\n";
		$bad = 1;
	}
}
echo $bad ? "APPLY FINISHED WITH ERRORS\n" : "APPLY DONE\n";
exit( $bad );
