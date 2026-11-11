<?php

define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('LEWM_PLUGIN_VERSION', '0.4.0-alpha.2');
define('LEWM_PLUGIN_FILE', 'loyalty-engage-woocommerce-multisite/loyalty-engage-woocommerce-multisite.php');
function plugin_basename($file) { return $file; }
function get_site_transient($key) { return false; }
function set_site_transient($key, $value, $ttl) {}
function wp_remote_get($url, $args) {
    if ($url !== 'https://api.github.com/repos/Loyalty-Engage/loyalty-engage-woocommerce-multisite/releases/latest') {
        throw new RuntimeException('Multisite must only consult its own repository.');
    }
    return $GLOBALS['response'];
}
function is_wp_error($value) { return false; }
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return json_encode($response['body']); }
function esc_url_raw($value) { return $value; }
function sanitize_text_field($value) { return $value; }
function sanitize_textarea_field($value) { return $value; }
require_once dirname(__DIR__) . '/includes/class-lew-updater.php';

$release = [
    'tag_name' => 'v0.4.0',
    'assets' => [['name' => 'loyalty-engage-woocommerce-multisite.zip', 'browser_download_url' => 'https://example.com/plugin.zip']],
];
$response = ['status' => 200, 'body' => $release];
$update = LEWM_Updater::check_for_update(false, [], LEWM_PLUGIN_FILE, []);
if ($update['version'] !== '0.4.0' || $update['package'] !== 'https://example.com/plugin.zip' || !version_compare($update['version'], LEWM_PLUGIN_VERSION, '>')) {
    throw new RuntimeException('The previous plugin version must receive the installable release asset.');
}
if (LEWM_Updater::check_for_update('unchanged', [], 'other/plugin.php', []) !== 'unchanged') {
    throw new RuntimeException('Other plugins must not be affected.');
}
if (LEWM_Updater::check_for_update('unchanged', [], 'loyalty-engage-woocommerce/loyalty-engage-woocommerce.php', []) !== 'unchanged') {
    throw new RuntimeException('Multisite updates must never replace the single-site plugin.');
}
foreach ([['draft' => true], ['prerelease' => true], ['assets' => []], ['tag_name' => 'invalid'], ['assets' => [['name' => 'loyalty-engage-woocommerce.zip', 'browser_download_url' => 'https://example.com/single.zip']]]] as $override) {
    $response['body'] = array_replace($release, $override);
    if (LEWM_Updater::check_for_update(false, [], LEWM_PLUGIN_FILE, []) !== false) {
        throw new RuntimeException('Invalid or unpublished release must not be offered.');
    }
}
$response = ['status' => 503, 'body' => $release];
if (LEWM_Updater::check_for_update(false, [], LEWM_PLUGIN_FILE, []) !== false) {
    throw new RuntimeException('Failed release lookup must not offer an update.');
}
echo "Updater release-selection tests passed.\n";
