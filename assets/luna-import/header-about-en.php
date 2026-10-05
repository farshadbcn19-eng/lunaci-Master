<?php
/**
 * (1) Code Snippet 8 (LUNACI Global Header): menu labels and links follow the
 *     WPML language; English output byte-identical.
 * (2) English About page (post 59), HTML widget ce307e5: the Spanish lines
 *     under the English copy removed (the Spanish page is /es/sobre-nosotros/).
 * (3) Option lunaci_es_copyright = on (lunaci-seo.php item 8: Spanish theme
 *     footer copyright on Spanish pages).
 *
 * Writes go straight to the database (no kses / Elementor / save hooks; under
 * WP-CLI those sanitised HTML before) and are verified byte for byte. Each
 * target must still hold exactly what the read-only diagnostic read (md5).
 *
 * LUNACI_MODE=dry-run | apply | rollback (rollback: $LUNACI_BACKUP_DIR/header-about-en-backup.json)
 */

global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = getenv( 'LUNACI_BACKUP_DIR' );
$src  = rtrim( (string) getenv( 'LUNACI_SRC_DIR' ), '/' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir || ! $src ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback), LUNACI_BACKUP_DIR and LUNACI_SRC_DIR\n";
	exit( 1 );
}
$snip_table = $wpdb->prefix . 'snippets';
$backup     = rtrim( $dir, '/' ) . '/header-about-en-backup.json';
$snip_old   = 'bd4eb60425cb667e7b43fb0df51f5be9';
$snip_new   = 'cad011419a8fd69d00fa8fbe07d87416';
$wid_old    = 'd9f0480e07abeccc0b59179594b8192d';
$wid_new    = '206ff92a7e42aeef788f3d5be21d1fd1';

function lunaci_h_meta( $id ) {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_elementor_data'", $id ) );
}
function lunaci_h_widget( $raw, $new_html = null ) {
	$data = json_decode( $raw, true );
	$out  = array( 'md5' => 'missing', 'hits' => 0, 'json' => null );
	$walk = function ( &$els ) use ( &$walk, &$out, $new_html ) {
		foreach ( $els as &$el ) {
			if ( ( $el['id'] ?? '' ) === 'ce307e5' && 'html' === ( $el['widgetType'] ?? '' ) ) {
				$out['md5'] = md5( (string) ( $el['settings']['html'] ?? '' ) );
				$out['hits']++;
				if ( null !== $new_html ) {
					$el['settings']['html'] = $new_html;
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	if ( is_array( $data ) ) {
		$walk( $data );
		$out['json'] = wp_json_encode( $data );
	}
	return $out;
}
function lunaci_h_flush( $id ) {
	delete_post_meta( $id, '_elementor_element_cache' );
	delete_post_meta( $id, '_elementor_css' );
	wp_cache_delete( $id, 'post_meta' );
	clean_post_cache( $id );
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	wp_cache_flush();
}

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $backup ), true );
	if ( ! is_array( $b ) || ! isset( $b['snippet_code'], $b['about_raw'] ) ) {
		echo "ABORT: no backup at $backup\n";
		exit( 1 );
	}
	$wpdb->update( $snip_table, array( 'code' => $b['snippet_code'] ), array( 'id' => 8 ) );
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $b['about_raw'] ), array( 'post_id' => 59, 'meta_key' => '_elementor_data' ) );
	if ( null === $b['es_copyright'] ) {
		delete_option( 'lunaci_es_copyright' );
	} else {
		update_option( 'lunaci_es_copyright', $b['es_copyright'], true );
	}
	lunaci_h_flush( 59 );
	$ok1 = md5( (string) $wpdb->get_var( "SELECT code FROM $snip_table WHERE id=8" ) ) === md5( $b['snippet_code'] );
	$ok2 = lunaci_h_meta( 59 ) === $b['about_raw'];
	echo 'snippet 8 restored: ' . ( $ok1 ? 'identical to backup' : 'MISMATCH' ) . "\n";
	echo 'post 59 restored: ' . ( $ok2 ? 'identical to backup' : 'MISMATCH' ) . "\n";
	echo ( $ok1 && $ok2 ) ? "ROLLBACK DONE\n" : "ROLLBACK FINISHED WITH ERRORS\n";
	exit( ( $ok1 && $ok2 ) ? 0 : 1 );
}

$fail     = 0;
$new_code = (string) file_get_contents( "$src/snippet-8.php" );
$new_wid  = (string) file_get_contents( "$src/about-en-ce307e5.html" );
if ( md5( $new_code ) !== $snip_new || md5( $new_wid ) !== $wid_new ) {
	echo "FAIL source files are not the reviewed ones\n";
	$fail = 1;
}
$row = $wpdb->get_row( "SELECT code, active FROM $snip_table WHERE id=8", ARRAY_A );
if ( ! $row || md5( $row['code'] ) !== $snip_old ) {
	echo 'FAIL snippet 8 changed since the diagnostic (md5 ' . ( $row ? md5( $row['code'] ) : '-' ) . ")\n";
	$fail = 1;
}
$raw = lunaci_h_meta( 59 );
$w   = lunaci_h_widget( $raw, $new_wid );
if ( 1 !== $w['hits'] || $wid_old !== $w['md5'] ) {
	echo "FAIL post 59 widget ce307e5: hits={$w['hits']} md5={$w['md5']}\n";
	$fail = 1;
}
echo "PLAN snippet 8 (active={$row['active']}): language-aware menu\n";
echo "PLAN post 59 widget ce307e5: remove the Spanish lines\n";
echo 'PLAN option lunaci_es_copyright: ' . get_option( 'lunaci_es_copyright', '(unset)' ) . " -> on\n";
if ( $fail ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}

$b = array( 'snippet_code' => $row['code'], 'about_raw' => $raw, 'es_copyright' => get_option( 'lunaci_es_copyright', null ) );
if ( false === file_put_contents( $backup, wp_json_encode( $b ) ) ) {
	echo "ABORT: cannot write backup $backup\n";
	exit( 1 );
}
echo "backup: $backup\n";
$wpdb->update( $snip_table, array( 'code' => $new_code ), array( 'id' => 8 ) );
$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $w['json'] ), array( 'post_id' => 59, 'meta_key' => '_elementor_data' ) );
update_option( 'lunaci_es_copyright', 'on', true );
lunaci_h_flush( 59 );

$bad = 0;
if ( md5( (string) $wpdb->get_var( "SELECT code FROM $snip_table WHERE id=8" ) ) !== $snip_new ) {
	echo "VERIFY FAIL: snippet 8\n";
	$bad = 1;
}
if ( lunaci_h_widget( lunaci_h_meta( 59 ) )['md5'] !== $wid_new ) {
	echo "VERIFY FAIL: post 59 widget\n";
	$bad = 1;
}
echo $bad ? "APPLY FINISHED WITH ERRORS\n" : "VERIFY OK: snippet 8 and post 59 widget byte-identical to the reviewed files\nAPPLY DONE\n";
exit( $bad );
