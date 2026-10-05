<?php
/**
 * Category archive intro copy (seo/phase-d-cat-intro.json) into option
 * lunaci_cat_intro, rendered by mu-plugins/lunaci-seo.php item 6.
 *
 * LUNACI_MODE=dry-run   preconditions and plan only
 * LUNACI_MODE=apply     write; old option value saved to $LUNACI_BACKUP_DIR/cat-intro-backup.json
 * LUNACI_MODE=rollback  restore the option from that backup
 */

$mode = getenv( 'LUNACI_MODE' );
$dir  = getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
if ( ! function_exists( 'lunaci_seo_cat_intro' ) ) {
	echo "ABORT: lunaci-seo.php item 6 not loaded (deploy the mu-plugin first)\n";
	exit( 1 );
}
$backup = rtrim( $dir, '/' ) . '/cat-intro-backup.json';

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $backup ), true );
	if ( ! is_array( $b ) || ! array_key_exists( 'cat_intro', $b ) ) {
		echo "ABORT: no backup at $backup\n";
		exit( 1 );
	}
	if ( null === $b['cat_intro'] ) {
		delete_option( 'lunaci_cat_intro' );
	} else {
		update_option( 'lunaci_cat_intro', $b['cat_intro'], false );
	}
	wp_cache_flush();
	echo "ROLLBACK DONE (lunaci_cat_intro " . ( null === $b['cat_intro'] ? 'deleted' : 'restored' ) . ")\n";
	exit( 0 );
}

$intro = json_decode( (string) file_get_contents( getenv( 'LUNACI_INTRO_JSON' ) ), true );
$want  = array( 'lips' => 'en', 'face' => 'en', 'eyes' => 'en', 'nails' => 'en', 'labios' => 'es', 'rostro' => 'es', 'ojos' => 'es', 'unas' => 'es' );
if ( ! is_array( $intro ) || array_keys( $intro ) !== array_keys( $want ) ) {
	echo "ABORT: intro JSON must hold exactly: " . implode( ',', array_keys( $want ) ) . "\n";
	exit( 1 );
}
$fail = 0;
foreach ( $want as $slug => $lang ) {
	do_action( 'wpml_switch_language', $lang );
	$t = get_term_by( 'slug', $slug, 'product_cat' );
	do_action( 'wpml_switch_language', null );
	$v = $intro[ $slug ];
	if ( ! $t || empty( $v['lead'] ) || empty( $v['heading'] ) || count( (array) $v['body'] ) < 2 ) {
		echo "FAIL $slug: term " . ( $t ? 'ok' : 'missing' ) . " or incomplete copy\n";
		$fail = 1;
		continue;
	}
	preg_match_all( '/\{\{([^}|]+)\|/', implode( ' ', $v['body'] ), $m );
	foreach ( $m[1] as $path ) {
		// url_to_postid() does not resolve WPML's /es/ prefix under WP-CLI, so
		// look the product up by slug and confirm its permalink is this path.
		$pslug = basename( untrailingslashit( $path ) );
		$p     = get_posts( array( 'name' => $pslug, 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'suppress_filters' => true ) );
		$id   = $p ? $p[0]->ID : 0;
		do_action( 'wpml_switch_language', $lang );
		$link = $id ? untrailingslashit( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) ) : '';
		do_action( 'wpml_switch_language', null );
		if ( ! $id || $link !== untrailingslashit( $path ) ) {
			echo "FAIL $slug: link $path does not resolve to a published post\n";
			$fail = 1;
		}
	}
	echo "PLAN $slug (term {$t->term_id}, $lang): lead + '" . $v['heading'] . "', " . count( $m[1] ) . " product links\n";
}
$current = get_option( 'lunaci_cat_intro', null );
echo 'current lunaci_cat_intro: ' . ( null === $current ? 'unset' : 'set (' . count( (array) $current ) . ' entries)' ) . "\n";
if ( $fail ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}
if ( false === file_put_contents( $backup, wp_json_encode( array( 'cat_intro' => $current ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) ) {
	echo "ABORT: cannot write backup $backup\n";
	exit( 1 );
}
echo "backup: $backup\n";
update_option( 'lunaci_cat_intro', $intro, false );
wp_cache_flush();
$ok = get_option( 'lunaci_cat_intro' ) === $intro;
echo $ok ? "APPLY DONE\n" : "VERIFY FAIL: option differs\n";
exit( $ok ? 0 : 1 );
