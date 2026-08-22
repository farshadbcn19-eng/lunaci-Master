<?php
global $wpdb;

// guarded publish: only touch posts that are (a) in this known list,
// (b) currently status=draft, and (c) linked via WPML as the 'es' translation
// of the expected English trid. Abort entirely if any check fails.
$expected = array(
	765 => array( 'trid' => 759, 'title' => 'Envío' ),
	766 => array( 'trid' => 760, 'title' => 'Devoluciones' ),
	768 => array( 'trid' => 676, 'title' => 'Términos de Servicio' ),
	769 => array( 'trid' => 3,   'title' => 'Política de Privacidad' ),
	770 => array( 'trid' => 60,  'title' => 'Contacto' ),
	771 => array( 'trid' => 61,  'title' => 'Productos' ),
	772 => array( 'trid' => 57,  'title' => 'Inicio' ),
);

$any_mismatch = false;
foreach ( $expected as $post_id => $spec ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		echo "MISMATCH: post_id=$post_id not found\n";
		$any_mismatch = true;
		continue;
	}
	if ( $post->post_status !== 'draft' ) {
		echo "MISMATCH: post_id=$post_id status is '{$post->post_status}', expected 'draft'\n";
		$any_mismatch = true;
		continue;
	}
	if ( $post->post_title !== $spec['title'] ) {
		echo "MISMATCH: post_id=$post_id title is '{$post->post_title}', expected '{$spec['title']}'\n";
		$any_mismatch = true;
		continue;
	}
	$trid_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT trid, language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = 'post_page'",
		$post_id
	), ARRAY_A );
	if ( ! $trid_row || (int) $trid_row['trid'] !== $spec['trid'] || $trid_row['language_code'] !== 'es' ) {
		echo "MISMATCH: post_id=$post_id WPML link not as expected: " . print_r( $trid_row, true ) . "\n";
		$any_mismatch = true;
		continue;
	}
	echo "OK: post_id=$post_id ('{$spec['title']}') verified draft + correct WPML link (trid={$spec['trid']})\n";
}

if ( $any_mismatch ) {
	echo "\nABORT: one or more guard checks failed - no publish performed\n";
	exit(1);
}

echo "\nAll guard checks passed. Publishing...\n\n";

foreach ( $expected as $post_id => $spec ) {
	$result = wp_update_post( array(
		'ID'          => $post_id,
		'post_status' => 'publish',
	), true );
	if ( is_wp_error( $result ) ) {
		echo "FAILED to publish post_id=$post_id: " . $result->get_error_message() . "\n";
	} else {
		echo "Published post_id=$post_id ('{$spec['title']}')\n";
	}
}

echo "\n=== Final status check ===\n";
foreach ( $expected as $post_id => $spec ) {
	$post = get_post( $post_id );
	echo "post_id=$post_id title='{$post->post_title}' status={$post->post_status} url=" . get_permalink( $post_id ) . "\n";
}

echo "\ndone\n";
