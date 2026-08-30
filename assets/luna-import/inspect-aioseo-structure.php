<?php
global $wpdb;

echo "--- aioseo_options option (searching for homePage / global siteTitle keys) ---\n";
$options_raw = get_option( 'aioseo_options' );
if ( $options_raw ) {
	$decoded = json_decode( $options_raw, true );
	if ( is_array( $decoded ) ) {
		echo "decoded as JSON successfully\n";
		if ( isset( $decoded['searchAppearance']['global'] ) ) {
			echo "searchAppearance.global:\n";
			echo json_encode( $decoded['searchAppearance']['global'], JSON_PRETTY_PRINT ) . "\n";
		}
		if ( isset( $decoded['searchAppearance']['homePage'] ) ) {
			echo "searchAppearance.homePage:\n";
			echo json_encode( $decoded['searchAppearance']['homePage'], JSON_PRETTY_PRINT ) . "\n";
		}
	} else {
		echo 'raw value (first 2000 chars): ' . substr( $options_raw, 0, 2000 ) . "\n";
	}
} else {
	echo "aioseo_options option not found/empty\n";
}

echo "\n--- wp_aioseo_posts table (per-post SEO data, newer AIOSEO versions) ---\n";
$aioseo_table = $wpdb->prefix . 'aioseo_posts';
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $aioseo_table ) ) );
if ( $exists ) {
	echo "table exists: {$aioseo_table}\n";
	$cols = $wpdb->get_col( "DESCRIBE `{$aioseo_table}`", 0 );
	echo 'columns: ' . implode( ', ', $cols ) . "\n";
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$aioseo_table}` WHERE post_id = %d", 57 ), ARRAY_A );
	if ( $row ) {
		echo "row for post_id=57:\n";
		foreach ( $row as $k => $v ) {
			echo "  {$k}: " . ( is_string( $v ) ? substr( $v, 0, 200 ) : $v ) . "\n";
		}
	} else {
		echo "no row for post_id=57 yet\n";
	}
} else {
	echo "table {$aioseo_table} does not exist - this AIOSEO version uses postmeta or aioseo_options only\n";
}

echo "\n--- ES homepage (page_on_front for ES, if WPML) ---\n";
$icl_table = $wpdb->prefix . 'icl_translations';
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$icl_table}'" ) ) {
	$es_home = $wpdb->get_var( $wpdb->prepare(
		"SELECT t2.element_id FROM {$icl_table} t1 JOIN {$icl_table} t2 ON t1.trid = t2.trid
		 WHERE t1.element_id = %d AND t1.element_type = 'post_page' AND t2.language_code = 'es'",
		57
	) );
	echo 'ES homepage post ID: ' . ( $es_home ?: '(not found)' ) . "\n";
	if ( $es_home ) {
		$es_post = get_post( $es_home );
		echo 'ES homepage title: ' . ( $es_post ? $es_post->post_title : '(missing)' ) . "\n";
	}
}

echo "\n--- AIOSEO plugin version ---\n";
if ( defined( 'AIOSEO_VERSION' ) ) {
	echo 'AIOSEO_VERSION: ' . AIOSEO_VERSION . "\n";
} else {
	echo "AIOSEO_VERSION constant not defined\n";
}
