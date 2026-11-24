<?php

define('ABSPATH', __DIR__);
class LEWM_Settings {
    public static bool $enabled = true;
    public static array $settings = ['pdp_points_per_currency_unit' => 1, 'pdp_points_enabled' => 'no', 'pdp_usp_label' => 'Ontvang {{points}} Smaakpunten'];
    public static function is_module_enabled(): bool { return self::$enabled; }
    public static function get_settings(): array { return self::$settings; }
}
class WC_Product {
    public bool $variable = false;
    public float $price = 59.95;
    public function is_type($type) { return $this->variable; }
    public function get_variation_price($which, $display) { return $which === 'min' ? 10 : 30; }
}
$product = new WC_Product();
$product_page = true;
function is_product() { return $GLOBALS['product_page']; }
function wc_get_product($id) { return $GLOBALS['product']; }
function get_queried_object_id() { return 9; }
function wc_get_price_to_display($product) { return $product->price; }
function apply_filters($hook, $value, ...$args) { return $value; }
function number_format_i18n($value) { return number_format($value, 0, ',', '.'); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES); }
function wp_enqueue_script($handle) {}
require_once dirname(__DIR__) . '/includes/class-lew-storefront.php';
$html = LEWM_Storefront::product_usp_shortcode();
if (!str_contains($html, '>Ontvang 59 Smaakpunten</span>') || str_contains($html, '<p') || str_contains($html, 'style=')) {
    throw new RuntimeException('USP must be inline inherited text, even when automatic product points are disabled.');
}
$product->variable = true;
if (!str_contains(LEWM_Storefront::product_usp_shortcode(), 'Ontvang 10 - 30 Smaakpunten')) {
    throw new RuntimeException('Variable products must show a points range before selection.');
}
LEWM_Settings::$settings['pdp_usp_label'] = '<script>bad()</script> {X}';
if (str_contains(LEWM_Storefront::product_usp_shortcode(), '<script>')) { throw new RuntimeException('USP labels must escape HTML.'); }
$product_page = false;
if (LEWM_Storefront::product_usp_shortcode() !== '') { throw new RuntimeException('USP must not guess a product on unrelated pages.'); }
$product_page = true;
LEWM_Settings::$enabled = false;
if (LEWM_Storefront::product_usp_shortcode() !== '') { throw new RuntimeException('Disabled module must not output the USP.'); }
LEWM_Settings::$enabled = true;
LEWM_Settings::$settings['pdp_points_per_currency_unit'] = 0;
if (LEWM_Storefront::product_usp_shortcode() !== '') { throw new RuntimeException('Disabled earning rate must not output a claim.'); }
echo "Inline product USP shortcode tests passed.\n";
