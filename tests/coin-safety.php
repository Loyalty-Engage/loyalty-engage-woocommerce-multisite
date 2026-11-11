<?php

define('ABSPATH', __DIR__);
function get_current_network_id() { return 1; }
require_once dirname(__DIR__) . '/includes/class-lewm-network.php';
require_once dirname(__DIR__) . '/includes/class-lew-rest-api.php';
require_once dirname(__DIR__) . '/includes/class-lew-storefront.php';

$meta = ['lewm_network_1_lew_points' => '2000', 'lewm_network_1_lew_available_coins' => '10'];
function get_user_meta($id, $key, $single) { return $GLOBALS['meta'][$key] ?? ''; }
function sanitize_key($key) { return strtolower($key); }

if (LEWM_Storefront::get_customer_loyalty_value(1, 'lew_availableCoins') !== '10') {
    throw new RuntimeException('The snake-case coin balance must be supported.');
}
unset($meta['lewm_network_1_lew_available_coins']);
if (LEWM_Storefront::get_customer_loyalty_value(1, 'lew_availableCoins') !== '') {
    throw new RuntimeException('Missing coins must never fall back to level points.');
}
$meta['lewm_network_1_lew_availableCoins'] = '0';
if (LEWM_Storefront::get_customer_loyalty_value(1, 'lew_availableCoins') !== '0') {
    throw new RuntimeException('Zero coins must remain zero.');
}

// Failed claims must not touch storage or instantiate a WooCommerce coupon.
$failed = ['success' => false, 'body' => ['discountCode' => 'REJECTED', 'discountAmount' => 25]];
$store = new ReflectionMethod(LEWM_Rest_API::class, 'maybe_store_discount_code');
$store->invoke(null, 'GIFT', 'test@example.com', $failed);
$create = new ReflectionMethod(LEWM_Rest_API::class, 'maybe_create_coupon');
if ($create->invoke(null, 'GIFT', 'test@example.com', [], $failed) !== ['applied' => false, 'pending' => false]) {
    throw new RuntimeException('Failed claims must not create or apply coupons.');
}

echo "Coin balance and failed-claim safety tests passed.\n";
