<?php
/**
 * Restore the Spanish front page (post 772) to its state in the full database
 * backup taken at 2026-10-05 17:55:58 UTC (before the first Spanish About
 * apply). That run's wp_update_post() on post 680 let WPML rebuild post 772
 * from the English original at 17:56:10 (revision 839), so /es/ showed the
 * English hero once Elementor's element cache was cleared.
 *
 * The backup's INSERT lines for post 772 are loaded into temporary tables by
 * MySQL itself (exact unescaping), compared with the live rows, and the
 * changed content is written back directly (no hooks), then verified.
 *
 * LUNACI_MODE=dry-run | apply   LUNACI_DUMP=<path to database.sql.gz>
 * Rollback of this restore: the post-incident values are saved to
 * $LUNACI_BACKUP_DIR/es-home-restore-backup.json first.
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dump = (string) getenv( 'LUNACI_DUMP' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply' ), true ) || ! is_readable( $dump ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply), LUNACI_DUMP (readable) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$pm = $wpdb->postmeta;
$po = $wpdb->posts;
$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS lunaci_tmp_pm" );
$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS lunaci_tmp_po" );
$wpdb->query( "CREATE TEMPORARY TABLE lunaci_tmp_pm LIKE `$pm`" );
$wpdb->query( "CREATE TEMPORARY TABLE lunaci_tmp_po LIKE `$po`" );
$gz     = gzopen( $dump, 'rb' );
$p_meta = "INSERT INTO `$pm` VALUES ('";
$p_post = "INSERT INTO `$po` VALUES ('772',";
$n_meta = 0;
$n_post = 0;
while ( ! gzeof( $gz ) ) {
	$line = gzgets( $gz, 64 * 1024 * 1024 );
	if ( false === $line ) {
		break;
	}
	if ( 0 === strpos( $line, $p_meta ) && preg_match( "/^INSERT INTO `[^`]+` VALUES \\('\\d+','772','/", $line ) ) {
		$wpdb->query( str_replace( "INSERT INTO `$pm`", 'INSERT INTO lunaci_tmp_pm', $line ) );
		$n_meta++;
	} elseif ( 0 === strpos( $line, $p_post ) ) {
		$wpdb->query( str_replace( "INSERT INTO `$po`", 'INSERT INTO lunaci_tmp_po', $line ) );
		$n_post++;
	}
}
gzclose( $gz );
echo "backup rows for post 772: $n_meta postmeta, $n_post posts\n";
if ( $n_meta < 3 || 1 !== $n_post ) {
	echo "ABORT: backup rows for post 772 not found\n";
	exit( 1 );
}

function lunaci_r_lang( $raw ) {
	$h = '';
	$w = function ( $els ) use ( &$w, &$h ) {
		foreach ( (array) $els as $el ) {
			if ( ( $el['id'] ?? '' ) === '9b0a463' ) {
				$h = (string) ( $el['settings']['html'] ?? '' );
			}
			if ( ! empty( $el['elements'] ) ) {
				$w( $el['elements'] );
			}
		}
	};
	$w( json_decode( (string) $raw, true ) );
	return array( false !== strpos( $h, 'Inspirada en el esp' ) ? 'SPANISH' : ( false !== strpos( $h, 'Inspired by the Mediterranean' ) ? 'ENGLISH' : '?' ), md5( $h ) );
}

// Compare backup vs live, per meta key and for the post row.
$old_meta = $wpdb->get_results( "SELECT meta_key, meta_value FROM lunaci_tmp_pm ORDER BY meta_id", ARRAY_A );
$restore  = array();
foreach ( $old_meta as $r ) {
	$live = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM `$pm` WHERE post_id = 772 AND meta_key = %s", $r['meta_key'] ) );
	$same = 1 === count( $live ) && $live[0] === $r['meta_value'];
	echo ( $same ? 'same     ' : 'CHANGED  ' ) . $r['meta_key'] . ' (backup ' . strlen( (string) $r['meta_value'] ) . ' bytes, live ' . ( $live ? strlen( (string) $live[0] ) : 'missing' ) . ")\n";
	if ( ! $same && in_array( $r['meta_key'], array( '_elementor_data', '_elementor_page_settings', '_elementor_version', '_elementor_pro_version', '_wp_page_template', '_elementor_edit_mode', '_elementor_template_type' ), true ) ) {
		$restore[ $r['meta_key'] ] = array( 'old' => $r['meta_value'], 'live' => $live ? $live[0] : null );
	}
}
foreach ( $wpdb->get_col( "SELECT DISTINCT meta_key FROM `$pm` WHERE post_id = 772" ) as $k ) {
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM lunaci_tmp_pm WHERE meta_key = %s', $k ) ) ) {
		echo "NEW      $k (not in backup)\n";
	}
}
$old_post  = $wpdb->get_row( 'SELECT post_title, post_content, post_modified, post_modified_gmt, post_name, post_status FROM lunaci_tmp_po', ARRAY_A );
$live_post = $wpdb->get_row( "SELECT post_title, post_content, post_modified, post_modified_gmt, post_name, post_status FROM `$po` WHERE ID = 772", ARRAY_A );
foreach ( $old_post as $k => $v ) {
	echo ( $v === $live_post[ $k ] ? 'same     ' : 'CHANGED  ' ) . "posts.$k" . ( in_array( $k, array( 'post_modified', 'post_name', 'post_status', 'post_title' ), true ) ? " ($v -> {$live_post[$k]})" : '' ) . "\n";
}
list( $bl, $bm ) = lunaci_r_lang( $old_meta ? $wpdb->get_var( "SELECT meta_value FROM lunaci_tmp_pm WHERE meta_key = '_elementor_data'" ) : '' );
list( $ll, $lm ) = lunaci_r_lang( get_post_meta( 772, '_elementor_data', true ) );
echo "hero widget 9b0a463: backup=$bl ($bm) live=$ll ($lm)\n";
echo "=== posts modified since 2026-10-05 17:50 (site time)\n";
foreach ( $wpdb->get_results( "SELECT ID, post_type, post_status, post_title, post_modified FROM `$po` WHERE post_modified >= '2026-10-05 17:50:00' ORDER BY post_modified" ) as $r ) {
	echo "  {$r->ID} {$r->post_type}/{$r->post_status} '{$r->post_title}' {$r->post_modified}\n";
}
if ( 'SPANISH' !== $bl ) {
	echo "ABORT: the backup's hero widget is not Spanish\n";
	exit( 1 );
}
echo 'PLAN restore meta: ' . implode( ', ', array_keys( $restore ) ) . "; posts.post_content and post_modified from the backup\n";
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN OK: nothing written\n";
	exit( 0 );
}

$save = array( 'meta' => array(), 'post' => $live_post );
foreach ( $restore as $k => $v ) {
	$save['meta'][ $k ] = $v['live'];
}
$bk = rtrim( $dir, '/' ) . '/es-home-restore-backup.json';
if ( false === file_put_contents( $bk, wp_json_encode( $save ) ) ) {
	echo "ABORT: cannot write $bk\n";
	exit( 1 );
}
echo "post-incident values saved: $bk\n";
foreach ( $restore as $k => $v ) {
	$wpdb->update( $pm, array( 'meta_value' => $v['old'] ), array( 'post_id' => 772, 'meta_key' => $k ) );
}
$wpdb->update( $po, array( 'post_content' => $old_post['post_content'], 'post_modified' => $old_post['post_modified'], 'post_modified_gmt' => $old_post['post_modified_gmt'] ), array( 'ID' => 772 ) );
delete_post_meta( 772, '_elementor_element_cache' );
delete_post_meta( 772, '_elementor_css' );
wp_cache_delete( 772, 'post_meta' );
clean_post_cache( 772 );
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();
$bad = 0;
foreach ( $restore as $k => $v ) {
	$now = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `$pm` WHERE post_id = 772 AND meta_key = %s", $k ) );
	if ( $now !== $v['old'] ) {
		echo "VERIFY FAIL: $k\n";
		$bad = 1;
	}
}
list( $nl, $nm ) = lunaci_r_lang( get_post_meta( 772, '_elementor_data', true ) );
echo "hero widget now: $nl ($nm)\n";
if ( 'SPANISH' !== $nl || $nm !== $bm ) {
	$bad = 1;
}
echo $bad ? "RESTORE FINISHED WITH ERRORS\n" : "VERIFY OK: post 772 restored byte for byte from the 17:55:58 backup\nRESTORE DONE\n";
exit( $bad );
