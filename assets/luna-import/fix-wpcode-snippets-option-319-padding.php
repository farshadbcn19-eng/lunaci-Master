<?php
/**
 * Guarded fix: the desktop hero padding fix was correctly written to
 * post 319's own post_content (wp_posts), but never rendered live -
 * exactly the same root cause as the earlier Blusher CSS bug (PR #237).
 * WPCode mirrors every snippet's code into the `wpcode_snippets` option
 * (used for actual frontend rendering, independent of post_content), and
 * a diagnostic just found the precise nested path for this snippet:
 * wpcode_snippets['site_wide_header'][1] where id===319, with both a
 * 'code' field and a 'compiled_code' field (the latter likely used at
 * render time). Patch both fields in memory, in place, then
 * update_option() - never raw string-replace on serialized bytes.
 */

$option_name = 'wpcode_snippets';
$location    = 'site_wide_header';
$target_id   = 319;

$needle   = 'padding:0 5% 8vh !important;';
$addition = "\n@media (min-width:1025px){.lna-hero__content{padding:0 5% 12vh !important;}}\n";

echo "=====================================================================\n";
echo "STEP A: PREPARE - locate entry and confirm baseline\n";
echo "=====================================================================\n";

$snippets = get_option( $option_name );
if ( ! is_array( $snippets ) || ! isset( $snippets[ $location ] ) || ! is_array( $snippets[ $location ] ) ) {
	echo "ABORT: option structure unexpected\n";
	exit( 1 );
}

$found_index = null;
foreach ( $snippets[ $location ] as $idx => $entry ) {
	if ( is_array( $entry ) && isset( $entry['id'] ) && (int) $entry['id'] === $target_id ) {
		$found_index = $idx;
		break;
	}
}

if ( null === $found_index ) {
	echo "ABORT: entry with id={$target_id} not found under {$location}\n";
	exit( 1 );
}

echo "found entry at [{$location}][{$found_index}]\n";

$entry = $snippets[ $location ][ $found_index ];

$fields_to_patch = array();
foreach ( array( 'code', 'compiled_code' ) as $field ) {
	if ( ! isset( $entry[ $field ] ) || ! is_string( $entry[ $field ] ) || '' === $entry[ $field ] ) {
		echo "  field '{$field}': not present, not a string, or empty, skipping\n";
		continue;
	}
	$occurrences     = substr_count( $entry[ $field ], $needle );
	$already_patched = false !== strpos( $entry[ $field ], '@media (min-width:1025px){.lna-hero__content{padding:0 5% 12vh !important;}}' );
	echo "  field '{$field}': length=" . strlen( $entry[ $field ] ) . " base_rule_occurrences={$occurrences} already_patched=" . ( $already_patched ? 'yes' : 'no' ) . "\n";
	if ( $already_patched ) {
		continue;
	}
	if ( 1 !== $occurrences ) {
		echo "ABORT: field '{$field}' has {$occurrences} occurrences of base rule, expected exactly 1\n";
		exit( 1 );
	}
	$fields_to_patch[] = $field;
}

if ( empty( $fields_to_patch ) ) {
	echo "OK: all relevant fields already patched - nothing to do\n";
	echo "\nFINAL RESULT: SUCCESS (no-op, already fixed)\n";
	exit( 0 );
}

echo "OK: will patch fields: " . implode( ', ', $fields_to_patch ) . "\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - patch in memory, write back via update_option()\n";
echo "=====================================================================\n";

$fresh_snippets = get_option( $option_name );
$fresh_entry    = $fresh_snippets[ $location ][ $found_index ];
if ( ! isset( $fresh_entry['id'] ) || (int) $fresh_entry['id'] !== $target_id ) {
	echo "ABORT: race check failed, entry at that index no longer matches id={$target_id}\n";
	exit( 1 );
}

foreach ( $fields_to_patch as $field ) {
	$race_occurrences = substr_count( $fresh_entry[ $field ], $needle );
	if ( 1 !== $race_occurrences ) {
		echo "ABORT: race check failed for field '{$field}', occurrences now {$race_occurrences}\n";
		exit( 1 );
	}
	$fresh_snippets[ $location ][ $found_index ][ $field ] = $fresh_entry[ $field ] . $addition;
}

$updated = update_option( $option_name, $fresh_snippets );
echo "update_option() returned: " . var_export( $updated, true ) . "\n";

if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}
echo "OK: caches cleared\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - re-read from DB and confirm the patch is present\n";
echo "=====================================================================\n";

$verify_snippets = get_option( $option_name );
$verify_entry     = isset( $verify_snippets[ $location ][ $found_index ] ) ? $verify_snippets[ $location ][ $found_index ] : null;

$all_ok = true;
if ( ! $verify_entry || ! isset( $verify_entry['id'] ) || (int) $verify_entry['id'] !== $target_id ) {
	echo "FAILURE: entry not found on verify read\n";
	$all_ok = false;
} else {
	foreach ( $fields_to_patch as $field ) {
		$has_override = false !== strpos( $verify_entry[ $field ], '@media (min-width:1025px){.lna-hero__content{padding:0 5% 12vh !important;}}' );
		$base_intact  = 1 === substr_count( $verify_entry[ $field ], $needle );
		echo "  field '{$field}': override_present=" . ( $has_override ? 'yes' : 'no' ) . " base_intact=" . ( $base_intact ? 'yes' : 'no' ) . "\n";
		if ( ! $has_override || ! $base_intact ) {
			$all_ok = false;
		}
	}
}

if ( $all_ok ) {
	echo "\nFINAL RESULT: SUCCESS\n";
} else {
	echo "\nFINAL RESULT: FAILURE - see lines above\n";
	exit( 1 );
}
