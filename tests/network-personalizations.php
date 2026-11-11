<?php

define('ABSPATH', __DIR__);
$site_id = 2;
$saved = ['tenant_id' => '9e8f3289-46bc-46cd-8e89-340640053a87', 'rules' => [
    ['key' => 'shop-a', 'site_ids' => [2], 'page_ids_by_site' => [2 => [17], 3 => [18]]],
    ['key' => 'shop-b', 'site_ids' => [3]],
    ['key' => 'everywhere', 'all_sites' => 'yes'],
    ['key' => 'unassigned'],
]];
function get_current_network_id() { return 1; }
function get_current_blog_id() { return $GLOBALS['site_id']; }
function get_network_option($network, $key, $default) { return $GLOBALS['saved']; }
function wp_parse_args($saved, $defaults) { return array_merge($defaults, $saved); }
require_once dirname(__DIR__) . '/includes/class-lew-personalizations.php';
$settings = LEWM_Personalizations::get_settings();
if (array_column($settings['rules'], 'key') !== ['shop-a', 'everywhere'] || $settings['rules'][0]['page_ids'] !== '17') {
    throw new RuntimeException('Shop A must get only its assigned rules and local page IDs.');
}
$site_id = 3;
if (array_column(LEWM_Personalizations::get_settings()['rules'], 'key') !== ['shop-b', 'everywhere']) {
    throw new RuntimeException('Shop A rules must not leak to shop B.');
}
$site_id = 99;
if (array_column(LEWM_Personalizations::get_settings()['rules'], 'key') !== ['everywhere']) {
    throw new RuntimeException('Future shops must only get all-site rules.');
}
if (count(LEWM_Personalizations::get_settings(true)['rules']) !== 4) {
    throw new RuntimeException('Network admin must see all rules.');
}
echo "Network personalization targeting tests passed.\n";
