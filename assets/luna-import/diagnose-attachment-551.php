<?php
/**
 * Read-only diagnostic, mirroring diagnose-attachment-552.php from earlier
 * this session: attachment 306 (lunaci-about-story.png, the old About Us
 * "Our Story" image, now replaced and confirmed unused live) shares its
 * exact 8 physical files with attachment 551. Investigate whether 551 is
 * a genuine duplicate database row (like 552 was for 307) that's safe to
 * delete alongside 306, or whether it's actually used somewhere.
 */

global $wpdb;

$ids = array( 306, 551 );

echo "=====================================================================\n";
echo "PART 1: Basic post row info for both attachments\n";
echo "=====================================================================\n";
foreach ( $ids as $id ) {
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT ID, post_title, post_status, post_date, post_modified, post_parent, guid FROM {$wpdb->posts} WHERE ID = %d", $id ),
		ARRAY_A
	);
	if ( ! $row ) {
		echo "ID={$id}: NOT FOUND\n";
		continue;
	}
	echo "ID={$row['ID']} title=\"{$row['post_title']}\" status={$row['post_status']} parent={$row['post_parent']}\n";
	echo "  date={$row['post_date']}  modified={$row['post_modified']}\n";
	echo "  guid={$row['guid']}\n";
}

echo "\n=====================================================================\n";
echo "PART 2: Is attachment 551 referenced anywhere (post_content / postmeta)?\n";
echo "=====================================================================\n";
$referencing_posts = $wpdb->get_results(
	"SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts} WHERE ID != 551 AND (post_content LIKE '%wp-image-551%' OR post_content LIKE '%attachment_551%')",
	ARRAY_A
);
echo "wp_posts.post_content referencing attachment ID 551 directly: " . count( $referencing_posts ) . "\n";
foreach ( $referencing_posts as $p ) {
	echo "  - ID={$p['ID']} type={$p['post_type']} status={$p['post_status']} title=\"{$p['post_title']}\"\n";
}

$featured_of = $wpdb->get_results(
	$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", '551' ),
	ARRAY_A
);
echo "Posts using 551 as featured image (_thumbnail_id): " . count( $featured_of ) . "\n";
foreach ( $featured_of as $f ) {
	echo "  - post_id={$f['post_id']}\n";
}

echo "\n=====================================================================\n";
echo "PART 3: post_type/post_status of posts referencing 'lunaci-about-story.png' filename\n";
echo "=====================================================================\n";
$referencing_meta = $wpdb->get_results(
	$wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE post_id NOT IN (%d, %d) AND meta_value LIKE %s", 306, 551, '%' . $wpdb->esc_like( 'lunaci-about-story.png' ) . '%' ),
	ARRAY_A
);
echo "Other wp_postmeta rows referencing 'lunaci-about-story.png': " . count( $referencing_meta ) . "\n";
$live_published_others = array();
foreach ( $referencing_meta as $m ) {
	$post_id = (int) $m['post_id'];
	$row     = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_type, post_status, post_parent, post_title FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A );
	if ( ! $row ) {
		echo "  - post_id={$post_id}: NOT FOUND\n";
		continue;
	}
	echo "  - ID={$row['ID']} type={$row['post_type']} status={$row['post_status']} parent={$row['post_parent']} title=\"{$row['post_title']}\"\n";
	if ( 'publish' === $row['post_status'] && 'revision' !== $row['post_type'] ) {
		$live_published_others[] = $post_id;
	}
}

echo "\n=====================================================================\n";
echo "Summary\n";
echo "=====================================================================\n";
if ( empty( $live_published_others ) ) {
	echo "SAFE: none of the other referencing posts is a live, published (non-revision) post.\n";
} else {
	echo "WARNING: the following posts ARE published and not revisions - investigate further:\n";
	foreach ( $live_published_others as $pid ) {
		echo "  - {$pid}\n";
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
