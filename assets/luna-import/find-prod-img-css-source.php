<?php
/**
 * Read-only diagnostic: the previous diagnostic found NO occurrence of
 * ".prod-img" anywhere in post 61's own widget HTML/CSS (the string
 * "prod-img" as a CLASS ATTRIBUTE does appear on the <img> tag itself,
 * but no CSS RULE for it exists inline) - meaning the sizing/cropping CSS
 * for .prod-img-wrap / .prod-img must live in a separate, likely global,
 * stylesheet. This searches wp_posts (any post_type, e.g. WPCode
 * snippets) and wp_options for any content containing "prod-img" to
 * locate the actual source of this CSS.
 */

global $wpdb;

echo "=====================================================================\n";
echo "PART 1: Search wp_posts (any post_type) for 'prod-img'\n";
echo "=====================================================================\n";
$posts = $wpdb->get_results(
	"SELECT ID, post_type, post_title, post_status, LENGTH(post_content) as len FROM {$wpdb->posts} WHERE post_content LIKE '%prod-img%'",
	ARRAY_A
);
echo "Found " . count( $posts ) . " posts\n";
foreach ( $posts as $p ) {
	echo "  ID={$p['ID']} type={$p['post_type']} status={$p['post_status']} title=\"{$p['post_title']}\" content_len={$p['len']}\n";
}

echo "\n=====================================================================\n";
echo "PART 2: Search wp_postmeta for 'prod-img' (e.g. WPCode code field)\n";
echo "=====================================================================\n";
$meta = $wpdb->get_results(
	"SELECT post_id, meta_key, LENGTH(meta_value) as len FROM {$wpdb->postmeta} WHERE meta_value LIKE '%prod-img%'",
	ARRAY_A
);
echo "Found " . count( $meta ) . " postmeta rows\n";
foreach ( $meta as $m ) {
	echo "  post_id={$m['post_id']} meta_key={$m['meta_key']} value_len={$m['len']}\n";
}

echo "\n=====================================================================\n";
echo "PART 3: Search wp_options for 'prod-img' (e.g. Additional CSS / customizer)\n";
echo "=====================================================================\n";
$options = $wpdb->get_results(
	"SELECT option_id, option_name, LENGTH(option_value) as len FROM {$wpdb->options} WHERE option_value LIKE '%prod-img%'",
	ARRAY_A
);
echo "Found " . count( $options ) . " options\n";
foreach ( $options as $o ) {
	echo "  option_id={$o['option_id']} option_name={$o['option_name']} value_len={$o['len']}\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
