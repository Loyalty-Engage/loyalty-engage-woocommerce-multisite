<?php
// Optional customer snippet; paste without the opening PHP tag into Code Snippets.
// The plugin itself does not automatically modify Elementor Purchase Summary widgets.
add_filter('elementor/widget/render_content', static function ($content, $widget) {
    if ($widget->get_name() !== 'woocommerce-purchase-summary' || str_contains($content, 'custom-loyalty-points') ||
        !shortcode_exists('loyalty_engage_order_points')) {
        return $content;
    }
    $points = do_shortcode('[loyalty_engage_order_points]');
    if ($points === '') { return $content; }
    $extra = '<li class="woocommerce-order-overview__points custom-loyalty-points">Verwachte Smaakpunten<strong>' . esc_html($points) . '</strong></li>';
    $pattern = '~(<ul\b[^>]*\bclass=(["\'])[^"\']*\bwoocommerce-order-overview\b[^"\']*\2[^>]*>)(.*?)(</ul>)~is';
    return preg_replace_callback($pattern, static function ($matches) use ($extra) {
        return $matches[1] . $matches[3] . $extra . $matches[4];
    }, $content, 1) ?? $content;
}, 10, 2);
