<?php
/**
 * READ-ONLY. Every other source has been ruled out for the category
 * filter bar's "lunaci-filter-btn" markup and the /shop/ -> /products/
 * redirect: mu-plugins, active+parent theme files, regular plugins
 * directory content, wp_posts, wp_postmeta, wp_options, wp_termmeta,
 * wp_term_taxonomy.description, elementor_library templates, and a
 * raw filesystem grep across the entire webroot (including uploads).
 * The "Code Snippets" plugin (separate from WPCode) stores its own
 * snippets in a dedicated custom table (wp_snippets), not as posts -
 * checking that directly, since nothing else has queried it.
 */

global $wpdb;

$table = $wpdb->prefix . 'snippets';
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
echo "table {$table} exists: " . var_export( (bool) $exists, true ) . "\n";

if ( $exists ) {
	$rows = $wpdb->get_results( "SELECT id, name, active, scope, LENGTH(code) as code_len FROM {$table}", ARRAY_A );
	foreach ( (array) $rows as $r ) {
		echo "id={$r['id']} name={$r['name']} active={$r['active']} scope={$r['scope']} code_len={$r['code_len']}\n";
	}

	echo "\n--- snippets whose code mentions 'lunaci-filter-btn' or '/shop/' ---\n";
	$matches = $wpdb->get_results(
		$wpdb->prepare( "SELECT id, name, code FROM {$table} WHERE code LIKE %s OR code LIKE %s", '%lunaci-filter-btn%', '%/shop/%' ),
		ARRAY_A
	);
	foreach ( (array) $matches as $m ) {
		echo "MATCH id={$m['id']} name={$m['name']}\n";
		echo substr( $m['code'], 0, 3000 ) . "\n---\n";
	}
	if ( ! $matches ) {
		echo "(no matches)\n";
	}
}

echo "OK: read-only diagnostic complete\n";
