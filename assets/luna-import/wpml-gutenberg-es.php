<?php
/**
 * WPML String Translation for the four Spanish Gutenberg pages (shipping,
 * returns, terms, privacy). The English package strings (gutenberg-<EN id>)
 * are out of date, so a WPML rebuild of a Spanish page would bring back old
 * English text. For each package this script:
 *  1. extracts every text block of the current English page the way WPML
 *     names it (md5(blockName . value)); the extraction is validated against
 *     the existing strings, which must match byte for byte;
 *  2. keeps the strings that still exist, adds the missing ones (cloned from
 *     an existing row of the same package), deletes the obsolete ones;
 *  3. registers the matching block of the live Spanish page as the complete
 *     (status 10) Spanish translation of each string.
 * Direct database writes only: no WordPress or WPML save runs, so no page is
 * rebuilt or changed. The Spanish pages are verified unchanged.
 * Also prints, read-only, the elementor-62/63/64 strings (cart, checkout,
 * account) next to the Spanish pages' widget values.
 *
 * LUNACI_MODE=dry-run | apply | rollback (backup: $LUNACI_BACKUP_DIR/wpml-gutenberg-es-backup.json)
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$st    = $wpdb->prefix . 'icl_strings';
$stt   = $wpdb->prefix . 'icl_string_translations';
$bk    = rtrim( $dir, '/' ) . '/wpml-gutenberg-es-backup.json';
$pairs = array( 759 => 765, 760 => 766, 676 => 768, 3 => 769 ); // EN => ES

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $bk ), true );
	if ( ! is_array( $b ) || empty( $b['strings'] ) ) {
		echo "ABORT: backup unreadable: $bk\n";
		exit( 1 );
	}
	foreach ( array_keys( $pairs ) as $en ) {
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $st WHERE context=%s", 'gutenberg-' . $en ) );
		foreach ( $ids as $id ) {
			$wpdb->delete( $stt, array( 'string_id' => (int) $id ) );
			$wpdb->delete( $st, array( 'id' => (int) $id ) );
		}
	}
	foreach ( $b['strings'] as $row ) {
		$wpdb->insert( $st, $row );
	}
	foreach ( $b['translations'] as $row ) {
		$wpdb->insert( $stt, $row );
	}
	wp_cache_flush();
	echo 'ROLLBACK OK: restored ' . count( $b['strings'] ) . ' strings and ' . count( $b['translations'] ) . " translations\n";
	exit( 0 );
}

// Value of a block as WPML stores it: the inner XML of the block's single
// top-level element, entities decoded, XHTML-style void tags.
function lunaci_g_value( $inner_html ) {
	$html = trim( (string) $inner_html );
	if ( '' === $html ) {
		return null;
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="lunaci-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$root = $doc->getElementById( 'lunaci-root' );
	$el   = null;
	foreach ( $root ? $root->childNodes : array() as $n ) {
		if ( XML_ELEMENT_NODE === $n->nodeType ) {
			if ( $el ) {
				return null; // more than one element: not a simple text block
			}
			$el = $n;
		} elseif ( XML_TEXT_NODE === $n->nodeType && '' !== trim( $n->nodeValue ) ) {
			return null;
		}
	}
	if ( ! $el ) {
		return null;
	}
	$out = '';
	foreach ( $el->childNodes as $c ) {
		$out .= $doc->saveXML( $c );
	}
	$out = trim( $out );
	return '' === trim( wp_strip_all_tags( $out ) ) ? null : $out;
}
function lunaci_g_blocks( $content ) {
	$flat = array();
	$walk = function ( $blocks ) use ( &$walk, &$flat ) {
		foreach ( $blocks as $b ) {
			if ( $b['blockName'] && empty( $b['innerBlocks'] ) ) {
				$v = lunaci_g_value( $b['innerHTML'] );
				if ( null !== $v ) {
					$flat[] = array( 'block' => $b['blockName'], 'value' => $v, 'name' => md5( $b['blockName'] . $v ) );
				}
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( $content ) );
	return $flat;
}

$fail   = 0;
$plan   = array();
$backup = array( 'strings' => array(), 'translations' => array() );
foreach ( $pairs as $en_id => $es_id ) {
	$ctx  = 'gutenberg-' . $en_id;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $st WHERE context=%s ORDER BY id", $ctx ), ARRAY_A );
	$by   = array();
	foreach ( $rows as $r ) {
		$by[ $r['name'] ] = $r;
		$backup['strings'][] = $r;
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $stt WHERE string_id=%d", $r['id'] ), ARRAY_A ) as $t ) {
			$backup['translations'][] = $t;
		}
	}
	$en = lunaci_g_blocks( get_post( $en_id )->post_content );
	$es = lunaci_g_blocks( get_post( $es_id )->post_content );
	echo "\n== $ctx (EN $en_id -> ES $es_id): " . count( $rows ) . ' strings now, EN blocks ' . count( $en ) . ', ES blocks ' . count( $es ) . "\n";
	if ( ! $rows || count( $en ) !== count( $es ) ) {
		echo "FAIL: no package rows or block counts differ\n";
		$fail = 1;
		continue;
	}
	// Validate the extraction: every existing string whose name matches a
	// block must carry exactly that value.
	$kept = 0;
	$new  = array();
	$seen = array();
	foreach ( $en as $i => $b ) {
		$e = $es[ $i ];
		if ( $e['block'] !== $b['block'] ) {
			echo "FAIL: block $i type differs ({$b['block']} vs {$e['block']})\n";
			$fail = 1;
		}
		list( $wen, $wes ) = lunaci_seo_es_text_counts( $e['value'] );
		if ( $wen > $wes && $wen >= 2 ) {
			echo "FAIL: ES block $i looks English: {$e['value']}\n";
			$fail = 1;
		}
		if ( isset( $seen[ $b['name'] ] ) ) {
			echo "NOTE: block $i repeats an earlier block (same string)\n";
			continue;
		}
		$seen[ $b['name'] ] = 1;
		if ( isset( $by[ $b['name'] ] ) ) {
			if ( $by[ $b['name'] ]['value'] !== $b['value'] ) {
				echo "FAIL: extraction differs from WPML for string {$by[ $b['name'] ]['id']}\n";
				$fail = 1;
			}
			$kept++;
		} else {
			$new[] = $i;
			echo "ADD   block $i {$b['block']}: " . substr( $b['value'], 0, 110 ) . "\n";
		}
		echo "  es  block $i: " . substr( $e['value'], 0, 110 ) . "\n";
	}
	$obsolete = array_diff_key( $by, $seen );
	foreach ( $obsolete as $r ) {
		echo "DROP  string {$r['id']} (no longer on the English page): " . substr( $r['value'], 0, 110 ) . "\n";
	}
	echo "summary $ctx: kept $kept (extraction validated on these), add " . count( $new ) . ', drop ' . count( $obsolete ) . ', Spanish translations to register ' . count( $seen ) . "\n";
	if ( $kept < 2 ) {
		echo "FAIL: too few existing strings to validate the extraction\n";
		$fail = 1;
	}
	$plan[ $en_id ] = array( 'ctx' => $ctx, 'en' => $en, 'es' => $es, 'by' => $by, 'obsolete' => $obsolete, 'template' => $rows[0], 'es_id' => $es_id );
}

// domain_name_context_md5: which formula does WPML use here?
$t  = $plan ? reset( $plan )['template'] : null;
$fn = null;
if ( $t ) {
	foreach ( array(
		'ctx.name.gctx'  => fn( $c, $n, $g ) => md5( $c . $n . $g ),
		'ctx.name'       => fn( $c, $n, $g ) => md5( $c . $n ),
	) as $label => $f ) {
		if ( $f( $t['context'], $t['name'], (string) $t['gettext_context'] ) === $t['domain_name_context_md5'] ) {
			$fn = $f;
			echo "domain_name_context_md5 formula: $label\n";
			break;
		}
	}
}
if ( ! $fn ) {
	echo "FAIL: domain_name_context_md5 formula not identified\n";
	$fail = 1;
}

echo "\n== elementor strings (read-only)\n";
foreach ( array( 62 => 610, 63 => 611, 64 => 612 ) as $en_id => $es_id ) {
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, name, value, status FROM $st WHERE context=%s", 'elementor-' . $en_id ) ) as $r ) {
		echo "elementor-$en_id string {$r->id} {$r->name} status={$r->status}: " . substr( str_replace( "\n", '\\n', $r->value ), 0, 160 ) . "\n";
	}
	$raw = (string) get_post_meta( $es_id, '_elementor_data', true );
	echo "  ES $es_id _elementor_data: " . substr( str_replace( "\n", '\\n', $raw ), 0, 400 ) . "\n";
}

if ( $fail || count( $plan ) !== 4 ) {
	echo "\nABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "\nDRY-RUN OK: nothing written\n";
	exit( 0 );
}

// apply
if ( false === file_put_contents( $bk, wp_json_encode( $backup ) ) ) {
	echo "ABORT: cannot write backup $bk\n";
	exit( 1 );
}
echo "backup: $bk (" . count( $backup['strings'] ) . ' strings, ' . count( $backup['translations'] ) . " translations)\n";
$md5_before = array();
foreach ( $pairs as $es_id ) {
	$md5_before[ $es_id ] = md5( get_post( $es_id )->post_content );
}
$now  = current_time( 'mysql', true );
$bad  = 0;
foreach ( $plan as $en_id => $p ) {
	foreach ( $p['obsolete'] as $r ) {
		$wpdb->delete( $stt, array( 'string_id' => (int) $r['id'] ) );
		$wpdb->delete( $st, array( 'id' => (int) $r['id'] ) );
	}
	$done = array();
	foreach ( $p['en'] as $i => $b ) {
		if ( isset( $done[ $b['name'] ] ) ) {
			continue;
		}
		$done[ $b['name'] ] = 1;
		$es_value = $p['es'][ $i ]['value'];
		$plain    = wp_strip_all_tags( $b['value'] );
		if ( isset( $p['by'][ $b['name'] ] ) ) {
			$sid = (int) $p['by'][ $b['name'] ]['id'];
			$wpdb->update( $st, array( 'location' => $i + 1, 'status' => 10 ), array( 'id' => $sid ) );
		} else {
			$row = $p['template'];
			unset( $row['id'] );
			$row['name']                    = $b['name'];
			$row['value']                   = $b['value'];
			$row['location']                = $i + 1;
			$row['title']                   = str_replace( '/', '-', $b['block'] ) . "-$i: " . $b['block'];
			$row['type']                    = false !== strpos( $b['value'], '<' ) ? 'VISUAL' : ( strlen( $b['value'] ) > 75 ? 'AREA' : 'LINE' );
			$row['status']                  = 10;
			$row['domain_name_context_md5'] = $fn( $row['context'], $row['name'], (string) $row['gettext_context'] );
			if ( array_key_exists( 'word_count', $row ) ) {
				$row['word_count'] = str_word_count( $plain );
			}
			$wpdb->insert( $st, $row );
			$sid = (int) $wpdb->insert_id;
		}
		$wpdb->delete( $stt, array( 'string_id' => $sid, 'language' => 'es' ) );
		$wpdb->insert( $stt, array( 'string_id' => $sid, 'language' => 'es', 'status' => 10, 'value' => $es_value, 'translation_date' => $now ) );
		// verify
		$chk = $wpdb->get_row( $wpdb->prepare( "SELECT s.value en, t.value es, s.status s1, t.status s2 FROM $st s JOIN $stt t ON t.string_id=s.id AND t.language='es' WHERE s.id=%d AND s.context=%s AND s.name=%s", $sid, $p['ctx'], $b['name'] ) );
		if ( ! $chk || $chk->en !== $b['value'] || $chk->es !== $es_value || 10 !== (int) $chk->s1 || 10 !== (int) $chk->s2 ) {
			echo "FAIL verify {$p['ctx']} block $i (string $sid)\n";
			$bad = 1;
		}
	}
	$n  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $st WHERE context=%s", $p['ctx'] ) );
	$nd = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $st s JOIN $stt t ON t.string_id=s.id AND t.language='es' AND t.status=10 WHERE s.context=%s", $p['ctx'] ) );
	echo "VERIFY {$p['ctx']}: $n strings, $nd with complete Spanish translation" . ( $n === $nd && $n === count( $done ) ? ' OK' : ' MISMATCH' ) . "\n";
	$bad = $bad || $n !== $nd || $n !== count( $done );
}
wp_cache_flush();
foreach ( $pairs as $es_id ) {
	clean_post_cache( $es_id );
	if ( md5( get_post( $es_id )->post_content ) !== $md5_before[ $es_id ] ) {
		echo "FAIL: Spanish page $es_id changed\n";
		$bad = 1;
	}
}
echo $bad ? "APPLY FINISHED WITH ERRORS (rollback available)\n" : "APPLY OK: 4 packages synced, all strings have complete Spanish translations, Spanish pages unchanged\n";
exit( $bad ? 1 : 0 );
