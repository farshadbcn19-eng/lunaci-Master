<?php
$slug = 'test-heading-probe-xyz';
$content = '<!-- wp:heading -->
<h2 class="wp-block-heading">Test Heading</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Test paragraph.</p>
<!-- /wp:paragraph -->';

echo "T1: start\n";
$new_id = wp_insert_post( array(
	'post_title'   => 'Heading Probe',
	'post_name'    => $slug,
	'post_content' => $content,
	'post_status'  => 'draft',
	'post_type'    => 'page',
), true );
echo "T2: result=" . var_export($new_id, true) . "\n";
if (!empty($new_id) && !is_wp_error($new_id)) {
	wp_delete_post($new_id, true);
	echo "T3: cleaned up\n";
}
echo "T4: done\n";
