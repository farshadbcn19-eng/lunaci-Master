<?php
global $wpdb;

$pid = 57;

// The user-provided FINAL widget content, transferred to the server as a raw file via scp.
$new_html = file_get_contents('/tmp/rebuild-page57-ref.html');
if ($new_html === false || strlen($new_html) < 5000) {
    echo "ABORT: failed to read reference content or content too short\n";
    exit;
}
echo "reference content length: " . strlen($new_html) . "\n";

// Reapply the two legitimate, user-approved fixes on top of the reference content.
$label_to_path = array(
    'Products'               => '/products/',
    'Explore the Collection' => '/products/',
    'Shop All Products'      => '/products/',
    'View Best Sellers'      => '/products/',
    'All Products'           => '/products/',
    'Lip'                    => '/product-category/lips/',
    'Face'                   => '/product-category/face/',
    'Eye'                    => '/product-category/eyes/',
);
$variants = array(
    array('attrs' => '', 'label' => 'Products'),
    array('attrs' => ' class="btn-gold"', 'label' => 'Explore the Collection'),
    array('attrs' => ' class="btn-out"', 'label' => 'Shop All Products'),
    array('attrs' => ' class="btn-out ln-rv"', 'label' => 'View Best Sellers'),
    array('attrs' => '', 'label' => 'All Products'),
    array('attrs' => '', 'label' => 'Lip'),
    array('attrs' => '', 'label' => 'Face'),
    array('attrs' => '', 'label' => 'Eye'),
);

$applied = 0;
foreach ($variants as $v) {
    $label = $v['label'];
    $attrs = $v['attrs'];
    $new_url = 'https://lunacibarcelona.com' . $label_to_path[$label];
    $search  = 'href="https://lunacibarcelona.com/collections-2"' . $attrs . '>' . $label . '<';
    $replace = 'href="' . $new_url . '"' . $attrs . '>' . $label . '<';
    $n = substr_count($new_html, $search);
    if ($n > 0) {
        $new_html = str_replace($search, $replace, $new_html);
        $applied += $n;
    }
}
echo "link fix occurrences applied: $applied (expected 8)\n";

$logo_search  = '<img src="https://lunacibarcelona.com/wp-content/uploads/2026/05/b320427b-bdbd-4220-926d-c2fecce7e9e4.jpeg" alt="LUNACI Barcelona" style="height:90px;width:auto;mix-blend-mode:lighten;">';
$logo_replace = '<img src="https://lunacibarcelona.com/wp-content/uploads/2026/05/b320427b-bdbd-4220-926d-c2fecce7e9e4.jpeg" alt="LUNACI Barcelona" width="160" height="90" style="height:90px;width:auto;mix-blend-mode:lighten;">';
$n = substr_count($new_html, $logo_search);
if ($n > 0) {
    $new_html = str_replace($logo_search, $logo_replace, $new_html);
    echo "logo fix applied: $n occurrence(s)\n";
} else {
    echo "WARNING: logo search pattern not found in reference content - logo dims NOT applied\n";
}

// Load current _elementor_data, locate the html widget, and swap its content only.
$ed = get_post_meta($pid, '_elementor_data', true);
$decoded = json_decode($ed, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "ABORT: current _elementor_data is not valid JSON: " . json_last_error_msg() . "\n";
    exit;
}

$replaced_count = 0;
function replace_html_widget(&$nodes, $new_html, &$replaced_count) {
    if (!is_array($nodes)) return;
    foreach ($nodes as &$n) {
        if (isset($n['widgetType']) && $n['widgetType'] === 'html' && isset($n['settings']['html'])) {
            $n['settings']['html'] = $new_html;
            $replaced_count++;
        }
        if (isset($n['elements'])) replace_html_widget($n['elements'], $new_html, $replaced_count);
    }
}
replace_html_widget($decoded, $new_html, $replaced_count);
echo "html widget nodes replaced: $replaced_count (expected 1)\n";

if ($replaced_count !== 1) {
    echo "ABORT: expected exactly 1 html widget node, found $replaced_count - not writing\n";
    exit;
}

$new_json = json_encode($decoded);
if ($new_json === false) {
    echo "ABORT: json_encode failed: " . json_last_error_msg() . "\n";
    exit;
}
json_decode($new_json);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "ABORT: rebuilt JSON failed self-validation: " . json_last_error_msg() . "\n";
    exit;
}
echo "rebuilt _elementor_data length: " . strlen($new_json) . " (was " . strlen($ed) . ")\n";

update_post_meta($pid, '_elementor_data', wp_slash($new_json));

$verify = get_post_meta($pid, '_elementor_data', true);
json_decode($verify);
$valid_now = json_last_error() === JSON_ERROR_NONE;
echo "post-write verification json_valid=" . ($valid_now ? 'YES' : 'NO - STILL BROKEN') . "\n";

if ($valid_now) {
    echo "contains ln-hero__wordmark: " . (strpos($verify, 'ln-hero__wordmark') !== false ? 'yes' : 'NO') . "\n";
    echo "contains ln-collection__g: " . (strpos($verify, 'ln-collection__g') !== false ? 'yes' : 'NO') . "\n";
    echo "contains 'Stay Connected With': " . (strpos($verify, 'Stay Connected With') !== false ? 'yes' : 'NO') . "\n";
    echo "contains width=\\\"160\\\": " . (strpos($verify, 'width=\"160\"') !== false ? 'yes' : 'NO') . "\n";
    echo "contains remaining collections-2 links: " . substr_count($verify, 'collections-2') . " (expected 0)\n";

    delete_post_meta($pid, '_elementor_element_cache');
    echo "cleared _elementor_element_cache\n";
} else {
    echo "NOT clearing cache since data is still invalid\n";
}

echo "DONE\n";
