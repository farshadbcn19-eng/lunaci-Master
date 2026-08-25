<?php
/**
 * Guarded fix: the `wpcode_snippets` wp_options row is a cached/mirrored
 * copy of every WPCode snippet's data that the plugin actually renders
 * from on the frontend - separate from (and not automatically kept in
 * sync with) the `wp_posts.post_content` for each snippet's own post.
 *
 * PR #233 correctly added `!important` to the `.prod-img { height: 100% }`
 * rule inside post 483's post_content (confirmed via direct DB read-back),
 * but PR #235/#236 diagnostics proved the live Products page still serves
 * the OLD CSS (no !important) because it's actually rendered from this
 * `wpcode_snippets` option, which was never touched by the earlier fix.
 *
 * This is PHP-serialized data (`a:2:{s:16:"site_wide_header";...}`), so a
 * raw string replace on the option's raw text would corrupt the `code`
 * field's `s:<length>:"..."` byte-length prefix once the fragment's length
 * changes. Instead: unserialize -> locate the snippet entry with id===483
 * (searching every group, in case of duplication) -> patch its `code`
 * string in memory -> update_option(), which re-serializes correctly.
 */

global $wpdb;

$old_fragment = '.prod-img { width: 100%; height: 100%; object-fit: cover; transition: transform 1s cubic-bezier(.25,.46,.45,.94); }';
$new_fragment = '.prod-img { width: 100%; height: 100% !important; object-fit: cover; transition: transform 1s cubic-bezier(.25,.46,.45,.94); }';

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read + locate + validate preconditions\n";
echo "=====================================================================\n";

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'wpcode_snippets' )
);
if ( null === $raw ) {
	echo "ABORT: wpcode_snippets option not found\n";
	exit( 1 );
}

$decoded = @unserialize( $raw );
if ( false === $decoded && 'b:0;' !== $raw ) {
	echo "ABORT: could not unserialize wpcode_snippets option\n";
	exit( 1 );
}
if ( ! is_array( $decoded ) ) {
	echo "ABORT: unserialized value is not an array\n";
	exit( 1 );
}

// Walk every group -> every snippet entry, find ones whose 'code' contains the old fragment.
$matches = array(); // list of [group, index, id, title, old_count, new_count]
foreach ( $decoded as $group => $entries ) {
	if ( ! is_array( $entries ) ) continue;
	foreach ( $entries as $idx => $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['code'] ) || ! is_string( $entry['code'] ) ) continue;
		$oc = substr_count( $entry['code'], $old_fragment );
		$nc = substr_count( $entry['code'], $new_fragment );
		if ( $oc > 0 || $nc > 0 ) {
			$matches[] = array(
				'group'     => $group,
				'index'     => $idx,
				'id'        => $entry['id'] ?? null,
				'title'     => $entry['title'] ?? null,
				'old_count' => $oc,
				'new_count' => $nc,
			);
		}
	}
}

echo "entries referencing the old or new fragment: " . count( $matches ) . "\n";
foreach ( $matches as $m ) {
	echo "  group={$m['group']} index={$m['index']} id=" . var_export( $m['id'], true ) . " title=" . var_export( $m['title'], true ) . " old_count={$m['old_count']} new_count={$m['new_count']}\n";
}

$to_fix = array_filter( $matches, function( $m ) { return $m['old_count'] > 0; } );
if ( 1 !== count( $to_fix ) ) {
	echo "ABORT: expected exactly 1 entry containing the old fragment, found " . count( $to_fix ) . " - refusing to proceed\n";
	exit( 1 );
}
$target = array_values( $to_fix )[0];
if ( 1 !== $target['old_count'] ) {
	echo "ABORT: target entry has old_count={$target['old_count']}, expected exactly 1 - refusing to proceed\n";
	exit( 1 );
}
if ( 0 !== $target['new_count'] ) {
	echo "ABORT: target entry already contains the new fragment - refusing to proceed (already fixed?)\n";
	exit( 1 );
}
if ( 483 !== $target['id'] ) {
	echo "ABORT: target entry id is {$target['id']}, expected 483 - refusing to proceed (unexpected snippet)\n";
	exit( 1 );
}
echo "OK: preconditions satisfied - target is group='{$target['group']}' index={$target['index']} id=483\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-check, patch in memory, write\n";
echo "=====================================================================\n";

$fresh_raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'wpcode_snippets' )
);
if ( $fresh_raw !== $raw ) {
	echo "ABORT: option changed since STEP A (concurrent edit detected) - refusing to write\n";
	exit( 1 );
}
echo "PASS: race-condition guard confirms content unchanged\n";

$group = $target['group'];
$index = $target['index'];

$old_code = $decoded[ $group ][ $index ]['code'];
$new_code = str_replace( $old_fragment, $new_fragment, $old_code );
if ( substr_count( $new_code, $new_fragment ) !== 1 || false !== strpos( $new_code, $old_fragment ) ) {
	echo "ABORT: in-memory replacement verification failed\n";
	exit( 1 );
}
$decoded[ $group ][ $index ]['code'] = $new_code;

$updated = update_option( 'wpcode_snippets', $decoded );
echo "update_option() returned: " . var_export( $updated, true ) . "\n";

wp_cache_delete( 'wpcode_snippets', 'options' );
wp_cache_flush();
echo "OK: caches cleared\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back\n";
echo "=====================================================================\n";

$verify_raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'wpcode_snippets' )
);
$verify_decoded = @unserialize( $verify_raw );
$verify_code    = is_array( $verify_decoded ) ? ( $verify_decoded[ $group ][ $index ]['code'] ?? '' ) : '';

$has_new = 1 === substr_count( $verify_code, $new_fragment );
$has_old = false !== strpos( $verify_code, $old_fragment );

echo "old fragment gone: " . ( ! $has_old ? 'yes' : 'no' ) . "   new fragment present(x1): " . ( $has_new ? 'yes' : 'no' ) . "\n";

if ( $has_new && ! $has_old ) {
	echo "\nFINAL RESULT: SUCCESS\n";
} else {
	echo "\nFINAL RESULT: FAILURE - see lines above\n";
	exit( 1 );
}
