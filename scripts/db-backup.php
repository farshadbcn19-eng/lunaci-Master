<?php
/**
 * Full database backup in pure PHP (wp eval-file), for hosts where
 * `wp db export` / mysqldump is not available.
 *
 * Usage: LUNACI_BACKUP_FILE=/path/database.sql.gz wp eval-file db-backup.php
 * Writes DROP/CREATE TABLE + INSERT statements for every table in the
 * WordPress database, gzip-compressed. Exits 1 on any error.
 */

global $wpdb;

$file = getenv( 'LUNACI_BACKUP_FILE' );
if ( ! $file ) {
	fwrite( STDERR, "LUNACI_BACKUP_FILE is not set\n" );
	exit( 1 );
}

$gz = gzopen( $file, 'wb6' );
if ( ! $gz ) {
	fwrite( STDERR, "cannot open $file\n" );
	exit( 1 );
}

gzwrite( $gz, "-- LUNACI database backup " . gmdate( 'c' ) . " (" . DB_NAME . ")\n" );
gzwrite( $gz, "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\n" );

$tables = $wpdb->get_col( 'SHOW TABLES' );
$rows   = 0;
foreach ( $tables as $table ) {
	$create = $wpdb->get_row( "SHOW CREATE TABLE `$table`", ARRAY_N );
	if ( empty( $create[1] ) ) {
		fwrite( STDERR, "SHOW CREATE TABLE failed for $table\n" );
		exit( 1 );
	}
	gzwrite( $gz, "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n" );

	$offset = 0;
	$chunk  = 500;
	do {
		$batch = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` LIMIT %d OFFSET %d", $chunk, $offset ), ARRAY_A );
		if ( $wpdb->last_error ) {
			fwrite( STDERR, "read failed for $table: {$wpdb->last_error}\n" );
			exit( 1 );
		}
		foreach ( $batch as $row ) {
			$values = array();
			foreach ( $row as $value ) {
				$values[] = null === $value ? 'NULL' : "'" . $wpdb->remove_placeholder_escape( $wpdb->_real_escape( $value ) ) . "'";
			}
			gzwrite( $gz, "INSERT INTO `$table` VALUES (" . implode( ',', $values ) . ");\n" );
			++$rows;
		}
		$offset += $chunk;
	} while ( count( $batch ) === $chunk );
	gzwrite( $gz, "\n" );
}

gzwrite( $gz, "SET foreign_key_checks = 1;\n" );
gzclose( $gz );

echo 'tables: ' . count( $tables ) . ", rows: $rows\n";
