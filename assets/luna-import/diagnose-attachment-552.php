<?php
/**
 * Read-only diagnostic, follow-up to the About-hero-banner fix: attachment
 * 307's own file list (lunaci-about-hero.png + its 7 registered sizes)
 * exactly matches attachment 552's metadata file list, so the guarded fix
 * (fix-about-hero-banner.php, STEP D) correctly refused to delete 307. This
 * script investigates attachment 552 itself - whether it's a genuine
 * separate upload that legitimately shares these filenames, or a stale/
 * duplicate database row (like attachments 794/795 earlier this session) -
 * so a safe remediation order can be decided before any deletion is
 * attempted.
 */

global $wpdb;

$ids = array( 307, 552 );

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
echo "PART 2: _wp_attached_file + _wp_attachment_metadata for both\n";
echo "=====================================================================\n";
foreach ( $ids as $id ) {
	$attached_file = get_post_meta( $id, '_wp_attached_file', true );
	echo "ID={$id} _wp_attached_file = {$attached_file}\n";
	$meta = wp_get_attachment_metadata( $id );
	echo "ID={$id} _wp_attachment_metadata:\n";
	echo "  file: " . ( isset( $meta['file'] ) ? $meta['file'] : '(none)' ) . "\n";
	echo "  width x height: " . ( isset( $meta['width'] ) ? $meta['width'] : '?' ) . " x " . ( isset( $meta['height'] ) ? $meta['height'] : '?' ) . "\n";
	if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size_name => $size_data ) {
			echo "  size={$size_name} file=" . ( isset( $size_data['file'] ) ? $size_data['file'] : '?' ) . "\n";
		}
	}
	echo "\n";
}

echo "=====================================================================\n";
echo "PART 3: Physical files on disk for both attachments' registered paths\n";
echo "=====================================================================\n";
$upload_dir = wp_upload_dir();
foreach ( $ids as $id ) {
	$attached_file = get_post_meta( $id, '_wp_attached_file', true );
	if ( ! $attached_file ) {
		echo "ID={$id}: no _wp_attached_file, skipping\n";
		continue;
	}
	$full_path = trailingslashit( $upload_dir['basedir'] ) . $attached_file;
	echo "ID={$id} path={$full_path}\n";
	echo "  exists: " . ( file_exists( $full_path ) ? 'yes (size=' . filesize( $full_path ) . ' bytes, mtime=' . date( 'Y-m-d H:i:s', filemtime( $full_path ) ) . ')' : 'no' ) . "\n";
}

echo "\n=====================================================================\n";
echo "PART 4: Is attachment 552 referenced anywhere (post_content / postmeta)?\n";
echo "=====================================================================\n";
$referencing_posts = $wpdb->get_results(
	"SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts} WHERE ID != 552 AND (post_content LIKE '%wp-image-552%' OR post_content LIKE '%attachment_552%')",
	ARRAY_A
);
echo "wp_posts.post_content referencing attachment ID 552 directly: " . count( $referencing_posts ) . "\n";
foreach ( $referencing_posts as $p ) {
	echo "  - ID={$p['ID']} type={$p['post_type']} status={$p['post_status']} title=\"{$p['post_title']}\"\n";
}

$meta_552 = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_attached_file'", 552 ) );
if ( $meta_552 ) {
	$filename_only = basename( $meta_552 );
	$referencing_meta = $wpdb->get_results(
		$wpdb->prepare( "SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE post_id != %d AND meta_value LIKE %s", 552, '%' . $wpdb->esc_like( $filename_only ) . '%' ),
		ARRAY_A
	);
	echo "\nOther wp_postmeta rows referencing '{$filename_only}' (attachment 552's own filename): " . count( $referencing_meta ) . "\n";
	foreach ( $referencing_meta as $m ) {
		echo "  - post_id={$m['post_id']} meta_key={$m['meta_key']}\n";
	}
}

echo "\n=====================================================================\n";
echo "PART 5: Is attachment 552 used as a featured image or in any widget HTML?\n";
echo "=====================================================================\n";
$featured_of = $wpdb->get_results(
	$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", '552' ),
	ARRAY_A
);
echo "Posts using 552 as featured image (_thumbnail_id): " . count( $featured_of ) . "\n";
foreach ( $featured_of as $f ) {
	echo "  - post_id={$f['post_id']}\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
