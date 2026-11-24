<?php

$callback = null;
$points = '150';
function add_filter($hook, $function, $priority, $args) { $GLOBALS['callback'] = $function; }
function shortcode_exists($tag) { return true; }
function do_shortcode($shortcode) { return $GLOBALS['points']; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES); }
require dirname(__DIR__) . '/examples/elementor-purchase-summary-points.php';
$widget = new class { public function get_name() { return 'woocommerce-purchase-summary'; } };
foreach (["'", '"'] as $quote) {
    $content = '<ul class=' . $quote . 'woocommerce-order-overview woocommerce-thankyou-order-details order_details' . $quote . '><li>Order</li></ul><p>After</p>';
    $result = $callback($content, $widget);
    if (substr_count($result, 'custom-loyalty-points') !== 1 || !str_contains($result, '<strong>150</strong>') || !str_ends_with($result, '<p>After</p>')) {
        throw new RuntimeException('Purchase summary snippet must add one field while preserving existing content.');
    }
    if ($callback($result, $widget) !== $result) { throw new RuntimeException('Snippet must not duplicate its field.'); }
    $points = '';
    if ($callback($content, $widget) !== $content) { throw new RuntimeException('Unavailable order points must not add an empty field.'); }
    $points = '150';
}
$other = new class { public function get_name() { return 'icon-list'; } };
if ($callback('<ul class="woocommerce-order-overview"></ul>', $other) !== '<ul class="woocommerce-order-overview"></ul>') { throw new RuntimeException('Snippet must not modify unrelated widgets.'); }
echo "Elementor purchase summary snippet tests passed.\n";
