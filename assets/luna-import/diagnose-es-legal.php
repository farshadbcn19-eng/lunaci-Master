<?php
/**
 * Read-only. Spanish non-Elementor pages (legal, shipping/returns, others):
 * WPML pairing, translation status, duplicate flags, language of the content,
 * Gutenberg string packages.
 */
global $wpdb;
$tr  = $wpdb->prefix . 'icl_translations';
$ts  = $wpdb->prefix . 'icl_translation_status';
$st  = $wpdb->prefix . 'icl_strings';
$stt = $wpdb->prefix . 'icl_string_translations';

function lunaci_d_counts( $html ) {
	$t  = ' ' . strtolower( preg_replace( '/\s+/', ' ', wp_strip_all_tags( preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', (string) $html ) ) ) ) . ' ';
	$en = 0;
	$es = 0;
	foreach ( array( 'the', 'and', 'your', 'with', 'our', 'for', 'of', 'is', 'every', 'that', 'we' ) as $w ) {
		$en += substr_count( $t, " $w " );
	}
	foreach ( array( 'el', 'la', 'de', 'tu', 'con', 'para', 'los', 'las', 'que', 'y', 'una', 'del' ) as $w ) {
		$es += substr_count( $t, " $w " );
	}
	return array( $en, $es, str_word_count( $t ) );
}

echo "privacy option: " . get_option( 'wp_page_for_privacy_policy' ) . " | terms: " . get_option( 'woocommerce_terms_page_id' ) . "\n";
echo "WPML page post type sync: " . wp_json_encode( ( get_option( 'icl_sitepress_settings' )['custom_posts_sync_option']['page'] ?? 'n/a' ) ) . "\n";
echo "WPML translation editor setting (doc_translation_method): " . wp_json_encode( get_option( 'icl_sitepress_settings' )['doc_translation_method'] ?? 'n/a' ) . "\n\n";

// All published Spanish pages that are not built with Elementor.
$rows = $wpdb->get_results(
	"SELECT p.ID, p.post_name, p.post_title, p.post_modified_gmt, t.trid, t.source_language_code
	 FROM {$wpdb->posts} p JOIN $tr t ON t.element_id=p.ID AND t.element_type='post_page'
	 WHERE t.language_code='es' AND p.post_status='publish' AND p.post_type='page'
	 ORDER BY p.ID"
);
foreach ( $rows as $r ) {
	$builder = get_post_meta( $r->ID, '_elementor_edit_mode', true );
	$en_id   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM $tr WHERE trid=%d AND language_code='en'", $r->trid ) );
	$es_p    = get_post( $r->ID );
	$en_p    = $en_id ? get_post( $en_id ) : null;
	list( $en, $es, $wc ) = lunaci_d_counts( $es_p->post_content );
	$tsr     = $wpdb->get_row( $wpdb->prepare( "SELECT status, needs_update, translation_service, md5 FROM $ts s JOIN $tr t ON t.translation_id=s.translation_id WHERE t.element_id=%d AND t.element_type='post_page'", $r->ID ) );
	$dup     = get_post_meta( $r->ID, '_icl_lang_duplicate_of', true );
	$pkg     = $en_id ? $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, SUM(EXISTS(SELECT 1 FROM $stt x WHERE x.string_id=s.id AND x.language='es' AND x.status=10)) done FROM $st s WHERE s.context=%s", 'gutenberg-' . $en_id ) ) : null;
	printf(
		"ES %d /%s/ \"%s\" builder=%s | EN %d /%s/ | ES words=%d en=%d es=%d blocks=%s | EN modified %s ES modified %s | ts=%s dup=%s | gutenberg pkg strings=%s es-done=%s\n",
		$r->ID, $r->post_name, $r->post_title, $builder ? $builder : 'none',
		$en_id, $en_p ? $en_p->post_name : '-', $wc, $en, $es, has_blocks( $es_p->post_content ) ? 'yes' : 'no',
		$en_p ? $en_p->post_modified_gmt : '-', $r->post_modified_gmt,
		$tsr ? "status={$tsr->status},needs_update={$tsr->needs_update},svc={$tsr->translation_service}" : 'none',
		$dup ? $dup : 'no',
		$pkg ? $pkg->n : '-', $pkg ? (int) $pkg->done : '-'
	);
}
echo "\nES guard option: " . get_option( 'lunaci_es_guard', 'off' ) . " | guard log entries: " . count( (array) get_option( 'lunaci_es_guard_log', array() ) ) . "\n";
