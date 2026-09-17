<?php
/**
 * READ-ONLY. Follow-up to diagnose-shop-code-snippets-table.php, which
 * found the "Code Snippets" plugin's wp_snippets table and identified
 * snippet id=6 ("LUNACI Shop Design", active, global scope,
 * code_len=11373) as the one whose code contains "/shop/" - almost
 * certainly the source of the category filter bar's "All" button and
 * possibly the /shop/ -> /products/ redirect too. Dumps its full code
 * (previously truncated to 3000 chars), plus id=7 ("Lunaci Category
 * Banners") for context since it's likely related.
 */

global $wpdb;
$table = $wpdb->prefix . 'snippets';

foreach ( array( 6, 7 ) as $id ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, code FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	if ( ! $row ) {
		echo "id={$id}: not found\n";
		continue;
	}
	echo "===== snippet id={$row['id']} name={$row['name']} =====\n";
	echo $row['code'] . "\n";
	echo "===== end snippet id={$row['id']} =====\n\n";
}
