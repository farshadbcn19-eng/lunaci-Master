<?php
/**
 * Read-only: fix-eyeliner-real-photo.php aborted because no published
 * product title matched '%yeliner%'. List ALL published WooCommerce
 * products to find the correct title/ID for the eyeliner SKU.
 */

global $wpdb;

$all = $wpdb->get_results(
	"SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'product' ORDER BY ID",
	ARRAY_A
);
foreach ( $all as $p ) {
	echo "ID={$p['ID']} title='{$p['post_title']}' status={$p['post_status']}\n";
}

echo "\nOK: read-only diagnostic complete\n";
