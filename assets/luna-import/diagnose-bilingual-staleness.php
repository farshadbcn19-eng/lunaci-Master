<?php
/**
 * Read-only, targeted diagnostic: for each EN/ES page pair, check whether
 * specific content markers that were added to the EN page in recent PRs
 * (Our Origin section, Luna hero/collection images, products hero banner,
 * ingredients image, full-bleed container fix) are present in the EN
 * _elementor_data but MISSING from the ES translation's _elementor_data -
 * proving the ES translation is a stale snapshot that predates those
 * changes. No writes are performed.
 */

global $wpdb;

function get_elementor_data( $post_id ) {
	$data = get_post_meta( $post_id, '_elementor_data', true );
	return is_string( $data ) ? $data : '';
}

function check_markers( $label, $en_id, $es_id, $markers ) {
	$en_data = get_elementor_data( $en_id );
	$es_data = get_elementor_data( $es_id );

	echo "\n--- {$label}: EN post_id={$en_id} (len=" . strlen( $en_data ) . ") vs ES post_id={$es_id} (len=" . strlen( $es_data ) . ") ---\n";

	if ( '' === $en_data ) {
		echo "  EN _elementor_data is empty - skipping marker checks\n";
		return;
	}
	if ( '' === $es_data ) {
		echo "  ES _elementor_data is EMPTY - translation has no Elementor content at all\n";
	}

	foreach ( $markers as $marker ) {
		$in_en = false !== strpos( $en_data, $marker );
		$in_es = false !== strpos( $es_data, $marker );
		$flag  = '';
		if ( $in_en && ! $in_es ) {
			$flag = '  <== MISSING FROM ES (stale translation)';
		} elseif ( ! $in_en && ! $in_es ) {
			$flag = '  (not in either - marker may be wrong, ignore)';
		}
		echo "  marker \"{$marker}\": EN=" . ( $in_en ? 'yes' : 'no' ) . " ES=" . ( $in_es ? 'yes' : 'no' ) . "{$flag}\n";
	}
}

echo "=====================================================================\n";
echo "Home page (trid=57): EN post 57 vs ES post 772\n";
echo "=====================================================================\n";
check_markers(
	'Home',
	57,
	772,
	array(
		'ln-origin',
		'Our Origin',
		'Crafted in Barcelona',
		'lunaimport-hero-luna.jpg',
		'lunaimport-origin-crafted-barcelona-luna.jpg',
		'lunaimport-collection-eyes-luna.jpg',
		'lunaimport-collection-face-luna.jpg',
		'lunaimport-collection-lips-luna.jpg',
		'lunaimport-collection-nails-luna.jpg',
		'ln-why2__title',
	)
);

echo "\n=====================================================================\n";
echo "Products page (trid=61): EN post 61 vs ES post 771\n";
echo "=====================================================================\n";
check_markers(
	'Products',
	61,
	771,
	array(
		'elementor-element-0329089.e-con-boxed',
		'lunaimport-product-hero-luna-1.jpg',
		'lpHeroKB',
		'lunaimport-products-ingredients-luna-1.jpg',
	)
);

echo "\n=====================================================================\n";
echo "About Us page (trid=59): EN post 59 vs ES post 680\n";
echo "=====================================================================\n";
check_markers(
	'About Us',
	59,
	680,
	array(
		'dorada',
	)
);

echo "\n=====================================================================\n";
echo "Contact page (trid=60): EN post 60 vs ES post 770\n";
echo "=====================================================================\n";
check_markers(
	'Contact',
	60,
	770,
	array()
);
$contact_en = get_elementor_data( 60 );
$contact_es = get_elementor_data( 770 );
echo "  (no known recent-edit markers tracked for Contact yet - lengths only: EN=" . strlen( $contact_en ) . " ES=" . strlen( $contact_es ) . ")\n";

echo "\n=====================================================================\n";
echo "Raw post_modified / post_modified_gmt for all 4 EN/ES pairs (informational only -\n";
echo "known unreliable for content-currency since direct \$wpdb->update() writes used\n";
echo "throughout this project's fix scripts do not bump post_modified)\n";
echo "=====================================================================\n";
foreach ( array( 57 => 772, 61 => 771, 59 => 680, 60 => 770 ) as $en_id => $es_id ) {
	$en = get_post( $en_id );
	$es = get_post( $es_id );
	echo "EN {$en_id}: modified={$en->post_modified_gmt}  |  ES {$es_id}: modified={$es->post_modified_gmt}\n";
}

echo "\nOK: read-only staleness diagnostic complete, no writes performed\n";
