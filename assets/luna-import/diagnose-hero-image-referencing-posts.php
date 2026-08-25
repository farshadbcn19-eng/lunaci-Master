<?php
/**
 * Read-only diagnostic, follow-up to the attachment 552/307 investigation.
 * The previous diagnostic found 17 posts (besides 307/552's own postmeta)
 * whose _elementor_data still contains the string "lunaci-about-hero.png":
 * 310, 312, 313, 314, 316, 317, 318, 320, 321, 322, 478, 480, 482, 678,
 * 682, 683, 684. Before considering deletion of attachments 307 and/or 552
 * (now that pages 59 and 680 have both been switched to the new image),
 * this checks whether any of those 17 posts is a LIVE, PUBLISHED page that
 * still renders this image directly by URL (which physical-file deletion
 * would break), as opposed to a revision/autosave/trashed/template item
 * (which is safe to ignore, per the attachment-794/795 precedent).
 */

global $wpdb;

$ids = array( 310, 312, 313, 314, 316, 317, 318, 320, 321, 322, 478, 480, 482, 678, 682, 683, 684 );

echo "=====================================================================\n";
echo "post_type / post_status / post_parent / post_title for each referencing post\n";
echo "=====================================================================\n";

$live_published_others = array();

foreach ( $ids as $id ) {
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT ID, post_type, post_status, post_parent, post_title, post_modified FROM {$wpdb->posts} WHERE ID = %d", $id ),
		ARRAY_A
	);
	if ( ! $row ) {
		echo "ID={$id}: NOT FOUND\n";
		continue;
	}
	echo "ID={$row['ID']} type={$row['post_type']} status={$row['post_status']} parent={$row['post_parent']} title=\"{$row['post_title']}\" modified={$row['post_modified']}\n";

	if ( 'publish' === $row['post_status'] && ! in_array( $row['post_type'], array( 'revision' ), true ) ) {
		$live_published_others[] = $id;
	}
}

echo "\n=====================================================================\n";
echo "Summary\n";
echo "=====================================================================\n";
if ( empty( $live_published_others ) ) {
	echo "SAFE: none of these 17 posts is a live, published (non-revision) post.\n";
} else {
	echo "WARNING: the following posts ARE published and not revisions - investigate further before any deletion:\n";
	foreach ( $live_published_others as $id ) {
		echo "  - {$id}\n";
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
