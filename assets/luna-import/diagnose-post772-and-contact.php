<?php
/**
 * READ-ONLY. Two things to explain before retrying the guarded fix:
 * 1. fix-homepage-h1-and-links.php found 0 occurrences of the hero
 *    wordmark AND the "Ver Más Vendidos" button in post 772 (ES
 *    homepage), even though the very first round-2 diagnostic
 *    (2026-09-08, earlier today) confirmed both were present. Did post
 *    772 change, or is the widget-walker missing something?
 * 2. It found only 2 occurrences of the EN /contact link pattern inside
 *    'html' widgetType elements, but the live rendered page has 3 -
 *    where does the 3rd one actually come from?
 */

global $wpdb;

echo "--- post 772 current state ---\n";
$data772 = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", 772, '_elementor_data'
) );
echo 'length: ' . strlen( (string) $data772 ) . "\n";
echo "contains 'ln-hero__wordmark': " . ( strpos( (string) $data772, 'ln-hero__wordmark' ) !== false ? 'yes' : 'no' ) . "\n";
echo "contains 'Ver Más Vendidos': " . ( strpos( (string) $data772, 'Ver Más Vendidos' ) !== false ? 'yes' : 'no' ) . "\n";

$decoded772 = json_decode( (string) $data772, true );
echo 'valid JSON: ' . ( is_array( $decoded772 ) ? 'yes' : 'no - ' . json_last_error_msg() ) . "\n";

// Walk and report every widgetType found, with html-widget content lengths.
function lunaci_report_widgets( array $elements, array &$out, int $depth = 0 ) {
	foreach ( $elements as $el ) {
		$type = $el['widgetType'] ?? ( $el['elType'] ?? 'unknown' );
		$has_html = isset( $el['settings']['html'] ) ? strlen( $el['settings']['html'] ) : null;
		$out[] = str_repeat( '  ', $depth ) . "elType={$el['elType']} widgetType={$type}" . ( $has_html !== null ? " html_len={$has_html}" : '' );
		if ( ! empty( $el['elements'] ) ) {
			lunaci_report_widgets( $el['elements'], $out, $depth + 1 );
		}
	}
}
if ( is_array( $decoded772 ) ) {
	$report = array();
	lunaci_report_widgets( $decoded772, $report );
	echo "\nwidget tree for post 772:\n" . implode( "\n", $report ) . "\n";
}

echo "\n--- EN post 57: locate the 3rd /contact occurrence ---\n";
$data57 = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", 57, '_elementor_data'
) );
$decoded57 = json_decode( (string) $data57, true );
$widgets57 = array();
function lunaci_collect_html( array $elements, array &$out ) {
	foreach ( $elements as $el ) {
		if ( isset( $el['widgetType'], $el['settings']['html'] ) && $el['widgetType'] === 'html' ) {
			$out[] = $el['settings']['html'];
		}
		if ( ! empty( $el['elements'] ) ) {
			lunaci_collect_html( $el['elements'], $out );
		}
	}
}
lunaci_collect_html( $decoded57, $widgets57 );
echo 'html widgets found in post 57: ' . count( $widgets57 ) . "\n";
$total_contact = 0;
foreach ( $widgets57 as $i => $html ) {
	$c = substr_count( $html, 'href="https://lunacibarcelona.com/contact"' );
	$total_contact += $c;
	echo "  widget[{$i}] length=" . strlen( $html ) . " contact_occurrences={$c}\n";
}
echo "total contact occurrences across html widgets: {$total_contact}\n";

echo "\nOK: read-only diagnostic complete\n";
