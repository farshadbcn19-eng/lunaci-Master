<?php
/**
 * Read-only diagnostic: the "lunaci-category-banner" markup rendering on
 * https://lunacibarcelona.com/product-category/face/ was not found in any
 * theme/plugin file on disk, nor in any published WPCode snippet's
 * post_content. It must therefore live in the database somewhere else:
 * a different post_type/status, wp_options (autoloaded code-injection
 * plugin storage), a custom table (e.g. a "Code Snippets" plugin table),
 * or postmeta. Search broadly and report every hit.
 */

global $wpdb;

$needle = 'lunaci-category-banner';

echo "=== wp_posts: ANY post_type/status containing '{$needle}' in post_content ===\n";
$rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT ID, post_type, post_status, post_title, LENGTH(post_content) AS len FROM {$wpdb->posts} WHERE post_content LIKE %s",
		'%' . $wpdb->esc_like( $needle ) . '%'
	),
	ARRAY_A
);
if ( empty( $rows ) ) {
	echo "(no matches)\n";
} else {
	foreach ( $rows as $r ) {
		echo "ID={$r['ID']} type={$r['post_type']} status={$r['post_status']} title=\"{$r['post_title']}\" len={$r['len']}\n";
	}
}

echo "\n=== wp_postmeta: any meta_value containing '{$needle}' ===\n";
$rows2 = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT post_id, meta_key, LENGTH(meta_value) AS len FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
		'%' . $wpdb->esc_like( $needle ) . '%'
	),
	ARRAY_A
);
if ( empty( $rows2 ) ) {
	echo "(no matches)\n";
} else {
	foreach ( $rows2 as $r ) {
		echo "post_id={$r['post_id']} meta_key={$r['meta_key']} len={$r['len']}\n";
	}
}

echo "\n=== wp_options: any option_value containing '{$needle}' ===\n";
$rows3 = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT option_name, autoload, LENGTH(option_value) AS len FROM {$wpdb->options} WHERE option_value LIKE %s",
		'%' . $wpdb->esc_like( $needle ) . '%'
	),
	ARRAY_A
);
if ( empty( $rows3 ) ) {
	echo "(no matches)\n";
} else {
	foreach ( $rows3 as $r ) {
		echo "option_name={$r['option_name']} autoload={$r['autoload']} len={$r['len']}\n";
	}
}

echo "\n=== wp_termmeta: any meta_value containing '{$needle}' ===\n";
$rows4 = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT term_id, meta_key, LENGTH(meta_value) AS len FROM {$wpdb->termmeta} WHERE meta_value LIKE %s",
		'%' . $wpdb->esc_like( $needle ) . '%'
	),
	ARRAY_A
);
if ( empty( $rows4 ) ) {
	echo "(no matches)\n";
} else {
	foreach ( $rows4 as $r ) {
		echo "term_id={$r['term_id']} meta_key={$r['meta_key']} len={$r['len']}\n";
	}
}

echo "\n=== all tables in DB with 'snippet' or 'code' in the name ===\n";
$tables = $wpdb->get_col( "SHOW TABLES LIKE '%snippet%'" );
$tables2 = $wpdb->get_col( "SHOW TABLES LIKE '%code%'" );
$all_tables = array_unique( array_merge( $tables, $tables2 ) );
if ( empty( $all_tables ) ) {
	echo "(none found)\n";
} else {
	foreach ( $all_tables as $t ) {
		echo "table: {$t}\n";
	}
}

echo "\n=== ALL wp_posts post_types present in DB (distinct) ===\n";
$types = $wpdb->get_col( "SELECT DISTINCT post_type FROM {$wpdb->posts}" );
echo implode( ', ', $types ) . "\n";

echo "\n=== full content dump of any wp_posts hit above (if any) ===\n";
if ( ! empty( $rows ) ) {
	foreach ( $rows as $r ) {
		$content = get_post_field( 'post_content', $r['ID'] );
		echo "\n----- post ID={$r['ID']} full content -----\n";
		echo $content . "\n";
		echo "----- end post ID={$r['ID']} -----\n";
	}
}

echo "\nOK: DB-wide search complete\n";
