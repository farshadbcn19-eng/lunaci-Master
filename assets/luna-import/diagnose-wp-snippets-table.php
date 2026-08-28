<?php
/**
 * Read-only diagnostic: a custom table "wp_snippets" was found in the DB,
 * belonging to the separate "Code Snippets" plugin (distinct from WPCode's
 * wp_posts-based storage, which we already ruled out). Inspect its schema
 * and search its code column for the Face category banner markup.
 */

global $wpdb;

$table = $wpdb->prefix . 'snippets';

echo "=== Table structure: {$table} ===\n";
$cols = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A );
foreach ( $cols as $c ) {
	echo "{$c['Field']} ({$c['Type']})\n";
}

echo "\n=== All rows: id, name, active, scope (code column length only) ===\n";
$rows = $wpdb->get_results( "SELECT id, name, active, scope, LENGTH(code) AS code_len FROM {$table} ORDER BY id", ARRAY_A );
if ( empty( $rows ) ) {
	echo "(no rows)\n";
} else {
	foreach ( $rows as $r ) {
		echo "id={$r['id']} name=\"{$r['name']}\" active={$r['active']} scope={$r['scope']} code_len={$r['code_len']}\n";
	}
}

echo "\n=== Rows whose code contains 'lunaci-category-banner' or 'category-face' or 'product-category' ===\n";
$hits = $wpdb->get_results(
	"SELECT id, name, active, scope, code FROM {$table} WHERE code LIKE '%lunaci-category-banner%' OR code LIKE '%category-face%' OR code LIKE '%product-category%' OR code LIKE '%product_cat%'",
	ARRAY_A
);
if ( empty( $hits ) ) {
	echo "(no matches)\n";
} else {
	foreach ( $hits as $h ) {
		echo "\n----- snippet id={$h['id']} name=\"{$h['name']}\" active={$h['active']} scope={$h['scope']} -----\n";
		echo $h['code'] . "\n";
		echo "----- end snippet id={$h['id']} -----\n";
	}
}

echo "\nOK: wp_snippets table diagnostic complete\n";
