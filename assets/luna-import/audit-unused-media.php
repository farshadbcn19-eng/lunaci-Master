<?php
/**
 * Read-only audit: find media library attachments that appear to be
 * genuinely unused anywhere on the site, so they're safe to delete.
 *
 * "Used" is checked via several independent signals, since a naive
 * "search for the ID number" check is too loose (false positives with
 * prices/page-IDs) and a naive "only check post_content" check is too
 * narrow (misses hardcoded banner IDs in wp_snippets PHP, Elementor
 * JSON data, WooCommerce galleries, and theme logo/site-icon options).
 */

global $wpdb;

echo "--- gathering attachments ---\n";
$attachments = $wpdb->get_results(
	"SELECT p.ID, p.post_title, p.guid, p.post_date, p.post_parent, pm.meta_value AS attached_file
	 FROM {$wpdb->posts} p
	 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
	 WHERE p.post_type = 'attachment'
	 ORDER BY p.ID ASC",
	ARRAY_A
);
echo 'total attachments: ' . count( $attachments ) . "\n\n";

echo "--- building corpus (post_content, postmeta, snippets, options) ---\n";

// 1) all post_content (any status, any post type) - joined into one haystack
$all_content = $wpdb->get_col( "SELECT post_content FROM {$wpdb->posts} WHERE post_type != 'attachment'" );
$content_haystack = implode( "\n", $all_content );

// 2) all postmeta values that look like they could carry media refs (elementor data, galleries, thumbnails, ACF-style)
$meta_rows = $wpdb->get_results(
	"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
	 WHERE meta_key IN ('_thumbnail_id', '_product_image_gallery', '_elementor_data', '_elementor_css')
	    OR meta_key LIKE '%image%'
	    OR meta_key LIKE '%photo%'
	    OR meta_key LIKE '%logo%'
	    OR meta_key LIKE '%banner%'",
	ARRAY_A
);
$meta_haystack = '';
$thumbnail_ids = array();
$gallery_ids   = array();
foreach ( $meta_rows as $row ) {
	$meta_haystack .= $row['meta_value'] . "\n";
	if ( '_thumbnail_id' === $row['meta_key'] ) {
		$thumbnail_ids[ (int) $row['meta_value'] ] = true;
	}
	if ( '_product_image_gallery' === $row['meta_key'] ) {
		foreach ( explode( ',', $row['meta_value'] ) as $gid ) {
			$gid = trim( $gid );
			if ( $gid !== '' ) {
				$gallery_ids[ (int) $gid ] = true;
			}
		}
	}
}

// 3) wp_snippets code (custom Code Snippets plugin table - hardcoded banner IDs live here)
$snippets_table = $wpdb->prefix . 'snippets';
$snippets_code  = $wpdb->get_col( "SELECT code FROM `{$snippets_table}`" );
$snippets_haystack = implode( "\n", $snippets_code );

// 4) relevant options (logo, site icon, elementor kit)
$option_names = array( 'site_icon', 'theme_mods_hello-elementor-child', 'theme_mods_hello-elementor' );
$options_haystack = '';
foreach ( $option_names as $oname ) {
	$options_haystack .= (string) get_option( $oname, '' ) . "\n";
}
// theme_mods are serialized arrays - also grab as text via direct option row
$mods_rows = $wpdb->get_results( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'theme_mods_%'", ARRAY_A );
foreach ( $mods_rows as $r ) {
	$options_haystack .= $r['option_value'] . "\n";
}

echo 'content haystack length: ' . strlen( $content_haystack ) . "\n";
echo 'meta haystack length: ' . strlen( $meta_haystack ) . "\n";
echo 'snippets haystack length: ' . strlen( $snippets_haystack ) . "\n";
echo 'options haystack length: ' . strlen( $options_haystack ) . "\n";
echo 'thumbnail_id refs: ' . count( $thumbnail_ids ) . ', gallery refs: ' . count( $gallery_ids ) . "\n\n";

echo "--- checking each attachment ---\n";

$unused = array();
$used_count = 0;

foreach ( $attachments as $att ) {
	$id = (int) $att['ID'];

	$basename = $att['attached_file'] ? basename( $att['attached_file'] ) : basename( $att['guid'] );
	$basename_noext = preg_replace( '/\.[a-zA-Z0-9]+$/', '', $basename );

	$is_used = false;
	$reason  = '';

	if ( isset( $thumbnail_ids[ $id ] ) ) {
		$is_used = true;
		$reason  = 'featured image (_thumbnail_id)';
	} elseif ( isset( $gallery_ids[ $id ] ) ) {
		$is_used = true;
		$reason  = 'product gallery image';
	} elseif ( $basename_noext !== '' && (
		false !== strpos( $content_haystack, $basename_noext ) ||
		false !== strpos( $meta_haystack, $basename_noext ) ||
		false !== strpos( $snippets_haystack, $basename_noext ) ||
		false !== strpos( $options_haystack, $basename_noext )
	) ) {
		$is_used = true;
		$reason  = 'filename referenced in content/meta/snippets/options';
	} elseif (
		false !== strpos( $content_haystack, "wp-image-{$id}" ) ||
		false !== strpos( $content_haystack, "\"id\":{$id}" ) ||
		false !== strpos( $content_haystack, "\"id\": {$id}" ) ||
		false !== strpos( $meta_haystack, "\"id\":{$id}" ) ||
		false !== strpos( $meta_haystack, "\"id\": {$id}" ) ||
		false !== strpos( $snippets_haystack, "=> {$id}," ) ||
		false !== strpos( $snippets_haystack, "=>{$id}," ) ||
		false !== strpos( $snippets_haystack, "MEDIA_ID={$id}" )
	) {
		$is_used = true;
		$reason  = 'ID referenced via wp-image-/JSON id/snippet assignment pattern';
	}

	if ( $is_used ) {
		$used_count++;
	} else {
		$unused[] = array(
			'id'          => $id,
			'title'       => $att['post_title'],
			'file'        => $basename,
			'date'        => $att['post_date'],
			'post_parent' => (int) $att['post_parent'],
		);
	}
}

echo "used: {$used_count}\n";
echo 'unused (candidates): ' . count( $unused ) . "\n\n";

echo "--- UNUSED CANDIDATES (id | title | file | date | post_parent) ---\n";
foreach ( $unused as $u ) {
	echo "{$u['id']} | {$u['title']} | {$u['file']} | {$u['date']} | parent={$u['post_parent']}\n";
}
