<?php
/**
 * English About page (post 59), HTML widget ce307e5, two copy corrections:
 *  - quote -> the official tagline "Every woman is seen. But your presence is remembered."
 *  - "less, when done perfectly, is always more" -> "less, done with intention, is always more"
 *    ("perfect" is forbidden language, Appendix C).
 * Direct database write (no kses / Elementor hooks), md5-guarded and verified.
 *
 * LUNACI_MODE=dry-run | apply | rollback (rollback: $LUNACI_BACKUP_DIR/about-en-copy-backup.json)
 */

global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = getenv( 'LUNACI_BACKUP_DIR' );
$file = (string) getenv( 'LUNACI_WIDGET_HTML' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$backup  = rtrim( $dir, '/' ) . '/about-en-copy-backup.json';
$old_md5 = '206ff92a7e42aeef788f3d5be21d1fd1';
$new_md5 = 'ff0108b06bc5f05ffcee2bff8ce8bc1f';

function lunaci_e_raw() {
	global $wpdb;
	return (string) $wpdb->get_var( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=59 AND meta_key='_elementor_data'" );
}
function lunaci_e_widget( $raw, $new_html = null ) {
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
function lunaci_e_flush() {
	delete_post_meta( 59, '_elementor_element_cache' );
	delete_post_meta( 59, '_elementor_css' );
	wp_cache_delete( 59, 'post_meta' );
	clean_post_cache( 59 );
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	wp_cache_flush();
}

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $backup ), true );
	if ( ! is_array( $b ) || empty( $b['about_raw'] ) ) {
		echo "ABORT: no backup at $backup\n";
		exit( 1 );
	}
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $b['about_raw'] ), array( 'post_id' => 59, 'meta_key' => '_elementor_data' ) );
	lunaci_e_flush();
	$ok = lunaci_e_raw() === $b['about_raw'];
	echo 'post 59 restored: ' . ( $ok ? 'identical to backup' : 'MISMATCH' ) . "\n";
	echo $ok ? "ROLLBACK DONE\n" : "ROLLBACK FINISHED WITH ERRORS\n";
	exit( $ok ? 0 : 1 );
}

$new = (string) file_get_contents( $file );
$raw = lunaci_e_raw();
$w   = lunaci_e_widget( $raw, $new );
$fail = 0;
if ( md5( $new ) !== $new_md5 ) {
	echo 'FAIL source file is not the reviewed one (md5 ' . md5( $new ) . ")\n";
	$fail = 1;
}
if ( 1 !== $w['hits'] || $old_md5 !== $w['md5'] ) {
	echo "FAIL post 59 widget ce307e5: hits={$w['hits']} md5={$w['md5']} (expected $old_md5)\n";
	$fail = 1;
}
echo "PLAN post 59 widget ce307e5: official tagline in the quote; 'less, done with intention, is always more'\n";
if ( $fail ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}
if ( false === file_put_contents( $backup, wp_json_encode( array( 'about_raw' => $raw ) ) ) ) {
	echo "ABORT: cannot write backup $backup\n";
	exit( 1 );
}
echo "backup: $backup\n";
$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $w['json'] ), array( 'post_id' => 59, 'meta_key' => '_elementor_data' ) );
lunaci_e_flush();
$ok = lunaci_e_widget( lunaci_e_raw() )['md5'] === $new_md5;
echo $ok ? "VERIFY OK: post 59 widget byte-identical to the reviewed file\nAPPLY DONE\n" : "VERIFY FAIL: post 59 widget\nAPPLY FINISHED WITH ERRORS\n";
exit( $ok ? 0 : 1 );
