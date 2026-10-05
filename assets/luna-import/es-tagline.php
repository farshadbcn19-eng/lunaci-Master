<?php
/**
 * Spanish tagline -> the official form of "Every woman is seen. But your
 * presence is remembered.":
 *   "Todas las mujeres son vistas. Pero tu presencia es recordada."
 *   -> "Toda mujer es vista. Pero tu presencia es recordada."
 * in the HTML widget of /es/sobre-nosotros/ (post 680, ce307e5) and of the
 * Spanish front page (post 772, 9b0a463; owner's explicit instruction). Only
 * that sentence changes. Each widget must still hold the HTML seen before
 * (md5); writes are direct (no hooks: a wp_update_post() earlier let WPML
 * rebuild post 772 from English) and verified byte for byte.
 *
 * LUNACI_MODE=dry-run | apply | rollback (rollback: $LUNACI_BACKUP_DIR/es-tagline-backup.json)
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$old_s   = 'Todas las mujeres son vistas. Pero tu presencia es recordada.';
$new_s   = 'Toda mujer es vista. Pero tu presencia es recordada.';
$targets = array(
	680 => array( 'widget' => 'ce307e5', 'md5' => '949c002dbebd85f75b3bb631aa7e287d', 'hits' => 1 ),
	772 => array( 'widget' => '9b0a463', 'md5' => '4b44bc601f9bfea7fa077fe86469f032', 'hits' => null ),
);
$backup = rtrim( $dir, '/' ) . '/es-tagline-backup.json';

function lunaci_t_raw( $id ) {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_elementor_data'", $id ) );
}
function lunaci_t_swap( $raw, $wid, $old_s, $new_s ) {
	$data = json_decode( $raw, true );
	$res  = array( 'found' => 0, 'md5' => 'missing', 'hits' => 0, 'new_md5' => null, 'ctx' => array(), 'json' => null );
	$walk = function ( &$els ) use ( &$walk, &$res, $wid, $old_s, $new_s ) {
		foreach ( $els as &$el ) {
			if ( ( $el['id'] ?? '' ) === $wid && 'html' === ( $el['widgetType'] ?? '' ) ) {
				$h            = (string) ( $el['settings']['html'] ?? '' );
				$res['found']++;
				$res['md5']   = md5( $h );
				$res['hits']  = substr_count( $h, $old_s );
				$off          = 0;
				while ( false !== ( $p = strpos( $h, $old_s, $off ) ) ) {
					$res['ctx'][] = trim( preg_replace( '/\s+/', ' ', substr( $h, max( 0, $p - 90 ), strlen( $old_s ) + 120 ) ) );
					$off          = $p + 1;
				}
				$n              = str_replace( $old_s, $new_s, $h );
				$res['new_md5'] = md5( $n );
				$el['settings']['html'] = $n;
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	if ( is_array( $data ) ) {
		$walk( $data );
		$res['json'] = wp_json_encode( $data );
	}
	return $res;
}
function lunaci_t_widget_md5( $id, $wid ) {
	$md5  = 'missing';
	$walk = function ( $els ) use ( &$walk, &$md5, $wid ) {
		foreach ( (array) $els as $el ) {
			if ( ( $el['id'] ?? '' ) === $wid ) {
				$md5 = md5( (string) ( $el['settings']['html'] ?? '' ) );
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	$walk( json_decode( lunaci_t_raw( $id ), true ) );
	return $md5;
}
function lunaci_t_flush( $id ) {
	delete_post_meta( $id, '_elementor_element_cache' );
	delete_post_meta( $id, '_elementor_css' );
	wp_cache_delete( $id, 'post_meta' );
	clean_post_cache( $id );
}

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $backup ), true );
	if ( ! is_array( $b ) || empty( $b ) ) {
		echo "ABORT: no backup at $backup\n";
		exit( 1 );
	}
	$bad = 0;
	foreach ( $b as $id => $raw ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $raw ), array( 'post_id' => (int) $id, 'meta_key' => '_elementor_data' ) );
		lunaci_t_flush( (int) $id );
		$ok  = lunaci_t_raw( (int) $id ) === $raw;
		$bad = $bad || ! $ok;
		echo "post $id restored: " . ( $ok ? 'identical to backup' : 'MISMATCH' ) . "\n";
	}
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	wp_cache_flush();
	echo $bad ? "ROLLBACK FINISHED WITH ERRORS\n" : "ROLLBACK DONE\n";
	exit( $bad ? 1 : 0 );
}

$fail = 0;
$plan = array();
foreach ( $targets as $id => $t ) {
	$raw = lunaci_t_raw( $id );
	$r   = lunaci_t_swap( $raw, $t['widget'], $old_s, $new_s );
	echo "post $id widget {$t['widget']}: found={$r['found']} md5={$r['md5']} occurrences={$r['hits']}\n";
	foreach ( $r['ctx'] as $c ) {
		echo "    ...$c...\n";
	}
	if ( 1 !== $r['found'] || $r['md5'] !== $t['md5'] || $r['hits'] < 1 || ( null !== $t['hits'] && $r['hits'] !== $t['hits'] ) ) {
		echo "FAIL post $id: widget not in the expected state\n";
		$fail = 1;
		continue;
	}
	$plan[ $id ] = array( 'raw' => $raw, 'json' => $r['json'], 'new_md5' => $r['new_md5'], 'widget' => $t['widget'] );
	echo "PLAN post $id: {$r['hits']} x tagline -> '$new_s' (new widget md5 {$r['new_md5']})\n";
}
if ( $fail ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}
$b = array();
foreach ( $plan as $id => $p ) {
	$b[ $id ] = $p['raw'];
}
if ( false === file_put_contents( $backup, wp_json_encode( $b ) ) ) {
	echo "ABORT: cannot write backup $backup\n";
	exit( 1 );
}
echo "backup: $backup\n";
foreach ( $plan as $id => $p ) {
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $p['json'] ), array( 'post_id' => $id, 'meta_key' => '_elementor_data' ) );
	lunaci_t_flush( $id );
}
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();
$bad = 0;
foreach ( $plan as $id => $p ) {
	$now = lunaci_t_widget_md5( $id, $p['widget'] );
	$ok  = $now === $p['new_md5'];
	$bad = $bad || ! $ok;
	echo "VERIFY post $id: " . ( $ok ? 'OK, widget byte-identical to the planned result' : "FAIL ($now)" ) . "\n";
}
echo $bad ? "APPLY FINISHED WITH ERRORS\n" : "APPLY DONE\n";
exit( $bad ? 1 : 0 );
