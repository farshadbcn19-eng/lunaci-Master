<?php
/**
 * Guarded fix: the homepage's SEO title tag (what Google shows in search
 * results and what appears in the browser tab) was just the raw page
 * title "Home" / "Inicio" - no keyword match for what a customer would
 * actually search ("luxury cosmetics Barcelona"), and no brand message.
 *
 * Confirmed via a live inspection: AIOSEO (the active SEO plugin, v4.9.10)
 * stores per-post SEO overrides in a dedicated wp_aioseo_posts table, not
 * postmeta. Both the EN homepage (post 57) and ES homepage (post 772,
 * "Inicio") have an empty `title` column there, so AIOSEO falls back to
 * the raw WordPress page title. Setting `title` here is the same action
 * as filling in AIOSEO's own "SEO Title" field in the page editor.
 *
 * The EN description was already set to a good on-brand line; left as-is.
 * The ES description is only set if it's currently empty.
 */

global $wpdb;

$table = $wpdb->prefix . 'aioseo_posts';

$targets = array(
	57  => array(
		'title'       => 'LUNACI Barcelona | Luxury Cosmetics Barcelona',
		'description' => 'LUNACI Barcelona — Mediterranean luxury beauty. Every woman is seen. But your presence is remembered.',
	),
	772 => array(
		'title'       => 'LUNACI Barcelona | Cosmética de Lujo en Barcelona',
		'description' => 'LUNACI Barcelona — Belleza de lujo mediterránea. Toda mujer es vista. Pero tu presencia siempre se recuerda.',
	),
);

echo "--- STEP A: PREPARE ---\n";
$current = array();
foreach ( $targets as $post_id => $data ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, post_id, title, description FROM `{$table}` WHERE post_id = %d", $post_id ), ARRAY_A );
	if ( ! $row ) {
		echo "ABORT: no wp_aioseo_posts row for post_id={$post_id}\n";
		exit( 1 );
	}
	if ( '' !== trim( (string) $row['title'] ) ) {
		echo "ABORT: post_id={$post_id} already has an SEO title set (\"{$row['title']}\") - refusing to overwrite\n";
		exit( 1 );
	}
	echo "post_id={$post_id}: current title empty (OK), current description: \"{$row['description']}\"\n";
	$current[ $post_id ] = $row;
}
echo "OK: preconditions satisfied\n";

echo "\n--- STEP B: COMMIT ---\n";
foreach ( $targets as $post_id => $data ) {
	$recheck = $wpdb->get_row( $wpdb->prepare( "SELECT title, description FROM `{$table}` WHERE post_id = %d", $post_id ), ARRAY_A );
	if ( '' !== trim( (string) $recheck['title'] ) ) {
		echo "ABORT: post_id={$post_id} title changed since STEP A (concurrent edit) - refusing to write\n";
		exit( 1 );
	}

	$update_data   = array( 'title' => $data['title'] );
	$update_format = array( '%s' );

	if ( '' === trim( (string) $recheck['description'] ) ) {
		$update_data['description'] = $data['description'];
		$update_format[]            = '%s';
		echo "post_id={$post_id}: description also empty - will set it too\n";
	} else {
		echo "post_id={$post_id}: description already set - leaving it as-is\n";
	}

	$updated = $wpdb->update( $table, $update_data, array( 'post_id' => $post_id ), $update_format, array( '%d' ) );
	if ( false === $updated ) {
		echo "ABORT: update failed for post_id={$post_id}: {$wpdb->last_error}\n";
		exit( 1 );
	}
	echo "OK: updated post_id={$post_id}, rows affected: {$updated}\n";
}

echo "\n--- STEP C: VERIFY ---\n";
foreach ( $targets as $post_id => $data ) {
	$verify = $wpdb->get_row( $wpdb->prepare( "SELECT title, description FROM `{$table}` WHERE post_id = %d", $post_id ), ARRAY_A );
	echo "post_id={$post_id}: title=\"{$verify['title']}\" description=\"{$verify['description']}\"\n";
	if ( $verify['title'] !== $data['title'] ) {
		echo "FAIL: title mismatch for post_id={$post_id}\n";
		exit( 1 );
	}
}

echo "\n=====================================================================\n";
echo "FINAL RESULT: SUCCESS\n";
