<?php

define('ABSPATH', __DIR__);
$network = 1;
$blog = 2;
$meta = [];
$connection = ['client_id' => 'CENTRAL', 'client_secret' => 'SECRET', 'webhook_secret' => 'HOOK'];
function get_current_network_id() { return $GLOBALS['network']; }
function get_current_blog_id() { return $GLOBALS['blog']; }
function get_network_option($id, $key, $default) { return $key === 'lewm_network_connection' ? $GLOBALS['connection'] : ['levels_title' => 'Network title']; }
function get_option($key, $default) { return ['client_id' => 'LOCAL', 'client_secret' => 'BAD', 'levels_title' => 'Shop title']; }
function wp_parse_args($value, $defaults) { return array_merge($defaults, $value); }
function update_user_meta($user, $key, $value) { $GLOBALS['meta'][$user][$key] = $value; }
function get_user_meta($user, $key, $single) { return $GLOBALS['meta'][$user][$key] ?? ''; }
function sanitize_text_field($value) { return strip_tags($value); }
function esc_url_raw($value) { return $value; }
function untrailingslashit($value) { return rtrim($value, '/'); }
function get_user_by($field, $id) { return (object) ['ID' => $id, 'user_email' => 'customer@example.com']; }
class LEWM_Loyalty_Engage_Client {
    public function request($path) {
        if ($path !== '/contact/customer%40example.com/loyalty_status') { throw new RuntimeException('Wrong customer identity.'); }
        return ['success' => true, 'body' => ['user_data' => ['le_available_coins' => 40]]];
    }
}
require_once dirname(__DIR__) . '/includes/class-lewm-network.php';
require_once dirname(__DIR__) . '/includes/class-lew-settings.php';
require_once dirname(__DIR__) . '/includes/class-lew-storefront.php';
function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }

check(LEWM_Settings::get_settings()['client_id'] === 'CENTRAL', 'Local credentials must never override the network.');
check(LEWM_Settings::get_settings()['levels_title'] === 'Network title', 'Presentation must use network settings, not local overrides.');
$saved = LEWM_Network::sanitize_connection(['api_base_url' => 'https://example.com/api/', 'client_id' => 'NEW', 'client_secret' => '', 'webhook_secret' => 'x<y'], $connection);
check($saved['client_secret'] === 'SECRET' && $saved['webhook_secret'] === 'x<y', 'Secrets must remain opaque and blank must preserve them.');
try {
    LEWM_Network::sanitize_connection(['api_base_url' => 'http://example.com'], []);
    throw new RuntimeException('HTTP API must be rejected.');
} catch (InvalidArgumentException $expected) {}

LEWM_Network::sync_customer(7, ['available_coins' => 100, 'points' => 2000, 'current_tier' => 'Bourgondier', 'unexpected' => 'bad']);
$first = LEWM_Network::reference('ORDER', 100);
$blog = 3;
check($first !== LEWM_Network::reference('ORDER', 100), 'Equal order IDs on different sites must not collide.');
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_availableCoins') === '100', 'Another shop must see the same coins.');
LEWM_Network::refresh_customer(7);
$blog = 2;
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_availableCoins') === '40', 'API refresh in one shop must update the other shop.');
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_points') === '2000', 'Coin update must not change level points.');
LEWM_Network::sync_customer(7, ['available_coins' => 0]);
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_availableCoins') === '0', 'Zero balance must sync.');
LEWM_Network::sync_customer(7, ['available_coins' => -1, 'points' => ['invalid']]);
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_availableCoins') === '0', 'Invalid balance must not overwrite valid coins.');
check(!isset($meta[7]['lewm_network_1_unexpected']), 'Only known loyalty fields may be stored.');
LEWM_Network::sync_customer(7, LEWM_Network::status_data(['currentPoints' => 178, 'availableCoins' => 278, 'currentTier' => 'test', 'nextTier' => 'test2', 'pointsRemainingToNextTier' => 322, 'reservedCoins' => 0, 'expiringPoints30d' => 0]));
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_points') === '178', 'Flat API currentPoints must map to level points.');
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_availableCoins') === '278', 'Flat API availableCoins must map to redeemable coins.');
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_points_to_next_tier') === '322', 'Flat API remaining points must map to next-level progress.');
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_current_tier') === 'test', 'Flat API currentTier must map to shared title.');
check(LEWM_Network::status_data(['user_data' => ['le_points' => 12]]) === ['le_points' => 12], 'Legacy wrapped status must still work.');
$network = 2;
check(LEWM_Storefront::get_customer_loyalty_value(7, 'lew_availableCoins') === '', 'Another network must not see this network balance.');
echo "Network settings, cross-shop balances and unique references passed.\n";
