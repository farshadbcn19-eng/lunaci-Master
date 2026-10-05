<?php
/**
 * Phase C: SEO meta titles and descriptions (seo/phase-c-meta.json).
 *
 * Posts and pages: wp_aioseo_posts.title / .description by post ID. Each
 * URL's post ID is pinned (from the 2026-10-05 read-only diagnostic) and
 * must still resolve to that URL. A row is only written while it holds the
 * value seen in the diagnostic, so nothing edited since is overwritten.
 * Categories: option lunaci_cat_meta (read by lunaci-seo.php item 5).
 *
 * LUNACI_MODE=dry-run   preconditions and plan only
 * LUNACI_MODE=apply     write; old values saved to $LUNACI_BACKUP_DIR/phase-c-meta-backup.json
 * LUNACI_MODE=rollback  restore from that backup JSON
 */

global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
if ( ! function_exists( 'lunaci_seo_cat_meta' ) ) {
	echo "ABORT: lunaci-seo.php item 5 not loaded (deploy the mu-plugin first)\n";
	exit( 1 );
}
$table  = $wpdb->prefix . 'aioseo_posts';
$backup = rtrim( $dir, '/' ) . '/phase-c-meta-backup.json';

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $backup ), true );
	if ( ! is_array( $b ) || ! isset( $b['posts'] ) ) {
		echo "ABORT: no backup at $backup\n";
		exit( 1 );
	}
	foreach ( $b['posts'] as $pid => $old ) {
		$wpdb->update( $table, array( 'title' => $old['title'], 'description' => $old['description'] ), array( 'post_id' => (int) $pid ) );
		echo "restored post $pid\n";
	}
	if ( null === $b['cat_meta'] ) {
		delete_option( 'lunaci_cat_meta' );
	} else {
		update_option( 'lunaci_cat_meta', $b['cat_meta'], false );
	}
	echo "restored lunaci_cat_meta\n";
	wp_cache_flush();
	echo "ROLLBACK DONE\n";
	exit( 0 );
}

$rows = json_decode( (string) file_get_contents( getenv( 'LUNACI_META_JSON' ) ), true );
if ( ! is_array( $rows ) || count( $rows ) !== 46 ) {
	echo "ABORT: expected 46 entries in the meta JSON\n";
	exit( 1 );
}

// URL => post ID, from the read-only diagnostic of 2026-10-05.
$ids = array(
	'/product/foundation/' => 324, '/product/compact-powder/' => 325, '/product/blusher/' => 326,
	'/product/lipgloss-velvet/' => 327, '/product/lip-fix/' => 328, '/product/lipstick/' => 329,
	'/product/nail-polish-long-lasting/' => 502, '/product/eyebrow-pencil/' => 497, '/product/lip-pencil/' => 491,
	'/product/eye-pencil/' => 501, '/product/mascara-length/' => 330, '/product/mascara-volume/' => 331,
	'/product/eye-liner/' => 332,
	'/es/producto/base-de-maquillaje/' => 686, '/es/producto/polvo-compacto-lunaci/' => 718, '/es/producto/colorete/' => 687,
	'/es/producto/brillo-de-labios-velvet-lunaci/' => 723, '/es/producto/fijador-de-labios-lunaci/' => 711,
	'/es/producto/pintalabios/' => 685, '/es/producto/esmalte-de-unas-larga-duracion-lunaci/' => 742,
	'/es/producto/lapiz-de-cejas-lunaci/' => 737, '/es/producto/delineador-de-labios-lunaci/' => 731,
	'/es/producto/lapiz-de-ojos-lunaci/' => 741, '/es/producto/mascara-de-pestanas-alargadora-lunaci/' => 728,
	'/es/producto/mascara-de-pestanas-voluminizadora-lunaci/' => 729, '/es/producto/delineador-de-ojos-lunaci/' => 730,
	'/products/' => 61, '/es/productos/' => 771, '/es/contacto/' => 770, '/es/about-us-es/' => 680,
	'/privacy-policy/' => 3, '/es/politica-de-privacidad/' => 769, '/terms-of-service/' => 676,
	'/es/terminos-de-servicio/' => 768, '/returns/' => 760, '/es/devoluciones/' => 766,
	'/shipping/' => 759, '/es/envio/' => 765,
);
// Values seen in the diagnostic for the rows that were not empty.
$expected = array(
	61  => array( 'title' => '#post_title #separator_sa #site_title' ),
);

$plan_posts = array();
$cat_meta   = array();
$fail       = 0;
foreach ( $rows as $r ) {
	$url = $r['url'];
	if ( preg_match( '#/(product-category|categoria-producto)/([^/]+)/$#', $url, $m ) ) {
		do_action( 'wpml_switch_language', 0 === strpos( $url, '/es/' ) ? 'es' : 'en' );
		$t = get_term_by( 'slug', $m[2], 'product_cat' );
		do_action( 'wpml_switch_language', null );
		if ( ! $t ) {
			echo "FAIL term not found: $url\n";
			$fail = 1;
			continue;
		}
		$cat_meta[ $m[2] ] = array( 'title' => $r['title'], 'desc' => $r['desc'] );
		echo "PLAN term {$m[2]} (id {$t->term_id})\n";
		continue;
	}
	$pid = $ids[ $url ] ?? 0;
	do_action( 'wpml_switch_language', 0 === strpos( $url, '/es/' ) ? 'es' : 'en' );
	$got = $pid ? untrailingslashit( (string) wp_parse_url( get_permalink( $pid ), PHP_URL_PATH ) ) : '';
	if ( ! $pid || $got !== untrailingslashit( $url ) || 'publish' !== get_post_status( $pid ) ) {
		echo "FAIL $url: post $pid resolves to '$got'\n";
		$fail = 1;
		continue;
	}
	do_action( 'wpml_switch_language', null );
	$cur = $wpdb->get_row( $wpdb->prepare( "SELECT title,description FROM $table WHERE post_id=%d", $pid ), ARRAY_A );
	if ( ! $cur ) {
		echo "FAIL $url: no aioseo row for post $pid\n";
		$fail = 1;
		continue;
	}
	$set = array();
	foreach ( array( 'title' => 'title', 'desc' => 'description' ) as $k => $col ) {
		if ( null === $r[ $k ] ) {
			continue;
		}
		$want = $expected[ $pid ][ $col ] ?? null;
		if ( $cur[ $col ] !== $want && $cur[ $col ] !== $r[ $k ] ) {
			echo "FAIL $url: $col changed since the diagnostic: " . wp_json_encode( $cur[ $col ] ) . "\n";
			$fail = 1;
			continue 2;
		}
		$set[ $col ] = $r[ $k ];
	}
	$plan_posts[ $pid ] = array( 'url' => $url, 'old' => $cur, 'set' => $set );
	echo "PLAN post $pid $url: " . implode( ',', array_keys( $set ) ) . "\n";
}
echo 'planned: ' . count( $plan_posts ) . ' posts, ' . count( $cat_meta ) . " terms\n";
if ( $fail || count( $plan_posts ) !== 38 || count( $cat_meta ) !== 8 ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}

$b = array( 'posts' => array(), 'cat_meta' => get_option( 'lunaci_cat_meta', null ) );
foreach ( $plan_posts as $pid => $p ) {
	$b['posts'][ $pid ] = $p['old'];
}
if ( false === file_put_contents( $backup, wp_json_encode( $b, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) ) {
	echo "ABORT: cannot write backup $backup\n";
	exit( 1 );
}
echo "backup: $backup\n";

foreach ( $plan_posts as $pid => $p ) {
	$wpdb->update( $table, array_merge( $p['set'], array( 'updated' => current_time( 'mysql', true ) ) ), array( 'post_id' => $pid ) );
}
update_option( 'lunaci_cat_meta', $cat_meta, false );
wp_cache_flush();

$bad = 0;
foreach ( $plan_posts as $pid => $p ) {
	$now = $wpdb->get_row( $wpdb->prepare( "SELECT title,description FROM $table WHERE post_id=%d", $pid ), ARRAY_A );
	foreach ( $p['set'] as $col => $v ) {
		if ( $now[ $col ] !== $v ) {
			echo "VERIFY FAIL post $pid $col\n";
			$bad = 1;
		}
	}
}
if ( get_option( 'lunaci_cat_meta' ) !== $cat_meta ) {
	echo "VERIFY FAIL lunaci_cat_meta\n";
	$bad = 1;
}
echo $bad ? "APPLY FINISHED WITH ERRORS\n" : "APPLY DONE\n";
exit( $bad );
