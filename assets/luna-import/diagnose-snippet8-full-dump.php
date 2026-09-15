<?php
global $wpdb;

$snippet_id = 8;
$snippets_table = $wpdb->prefix . 'snippets';

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT id, name, active, scope, code FROM $snippets_table WHERE id = %d", $snippet_id ),
	ARRAY_A
);

if ( ! $row ) {
	echo "ERROR: no snippet row found with id=$snippet_id\n";
	exit( 1 );
}

echo "id={$row['id']}  name=\"{$row['name']}\"  active={$row['active']}  scope={$row['scope']}\n";
echo "code length=" . strlen( $row['code'] ) . "\n";

$target = 'background:linear-gradient(to bottom,rgba(11,11,11,.95),transparent)';
echo "occurrences of target gradient rule: " . substr_count( $row['code'], $target ) . "\n";

echo "\n===== BEGIN FULL CODE =====\n";
echo $row['code'];
echo "\n===== END FULL CODE =====\n";
