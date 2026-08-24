<?php
/**
 * Read-only diagnostic: find the origin of every image referenced on the
 * Products page (post 61) - both <img> tags in the Elementor HTML widget
 * and CSS background-image url()s in the WPCode snippet (post 483) - and
 * check whether each one has a matching Media Library attachment record,
 * or is only a raw file on disk with no attachment post (which would
 * explain why it can't be found by searching the Media Library UI).
 */

global $wpdb;

// filename => known/expected upload month path (from the live URLs already seen)
$candidate_files = array(
	'lunaci_3462_retouched-1-scaled.jpg'        => '2026/05',
	'lunaimport-product-hero-luna-1.jpg'        => '2026/08',
	'lunaci-foundation.jpg'                     => '2026/06',
	'lunaci-mascara-volume.jpg'                 => '2026/06',
	'lunaci-lipstick.png'                       => '2026/06',
	'lunaci-nail-polish.jpg'                    => '2026/06',
	'lunaci-blush.jpg'                          => '2026/06',
	'b320427b-bdbd-4220-926d-c2fecce7e9e4.jpeg' => '2026/05',
);

echo "=====================================================================\n";
echo "For each candidate filename: check wp_posts (attachment records)\n";
echo "=====================================================================\n";

foreach ( $candidate_files as $file => $month_path ) {
	echo "\n--- {$file} ---\n";

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_title, post_status, post_date, guid FROM {$wpdb->posts}
			 WHERE post_type = 'attachment' AND guid LIKE %s",
			'%' . $wpdb->esc_like( $file ) . '%'
		),
		ARRAY_A
	);

	if ( ! $rows ) {
		echo "NOT FOUND as a Media Library attachment (no wp_posts row with post_type=attachment and this filename in guid)\n";
	} else {
		foreach ( $rows as $r ) {
			$edit_link = admin_url( 'post.php?post=' . $r['ID'] . '&action=edit' );
			echo "FOUND: attachment ID={$r['ID']}  title=\"{$r['post_title']}\"  status={$r['post_status']}  uploaded={$r['post_date']}\n";
			echo "  guid: {$r['guid']}\n";
			echo "  edit link: {$edit_link}\n";
		}
	}
}

echo "\n=====================================================================\n";
echo "Filesystem check: does the physical file exist under wp-content/uploads?\n";
echo "=====================================================================\n";

$upload_dir = wp_upload_dir();
$base_dir   = $upload_dir['basedir'];

foreach ( $candidate_files as $file => $month_path ) {
	$path   = $base_dir . '/' . $month_path . '/' . $file;
	$exists = file_exists( $path );
	echo "\n--- {$file} ---\n";
	echo $exists ? "on disk at: {$path} (size=" . filesize( $path ) . " bytes)\n" : "NOT found at expected path: {$path}\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
