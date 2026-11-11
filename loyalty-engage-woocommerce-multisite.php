<?php
/**
 * Plugin Name: Loyalty Engage for WooCommerce Multisite
 * Description: Loyalty Engage integration for WooCommerce with order export, returns, loyalty rewards, discount redemption and customer sync.
 * Version: 0.4.0
 * Network: true
 * Author: Loyalty Engage B.V.
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Update URI: https://github.com/Loyalty-Engage/loyalty-engage-woocommerce-multisite
 * Text Domain: loyalty-engage-woocommerce-multisite
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LEWM_PLUGIN_VERSION', '0.4.0');
define('LEWM_PLUGIN_FILE', __FILE__);
define('LEWM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LEWM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once LEWM_PLUGIN_DIR . 'includes/class-lewm-network.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-activator.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-loyalty-engage-client.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-logger.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-outbox.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-settings.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-rest-api.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-shipping.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-webhooks.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-storefront.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-personalizations.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-plugin.php';
require_once LEWM_PLUGIN_DIR . 'includes/class-lew-updater.php';

LEWM_Updater::boot();

register_activation_hook(__FILE__, static function (): void {
    if (!is_multisite()) { wp_die('WordPress Multisite is vereist.'); }
    LEWM_Network::each_site([LEWM_Activator::class, 'activate']);
});
register_deactivation_hook(__FILE__, static function (): void {
    LEWM_Network::each_site([LEWM_Activator::class, 'deactivate']);
});

function lewm_boot_plugin(): void
{
    if (class_exists('LEW_Plugin')) {
        $notice = static function (): void {
            echo '<div class="notice notice-error"><p>Deactivate the single-site Loyalty Engage plugin before using the Multisite edition. The Multisite edition has not started.</p></div>';
        };
        add_action('admin_notices', $notice);
        add_action('network_admin_notices', $notice);
        return;
    }

    if (!is_multisite()) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>Loyalty Engage Multisite requires WordPress Multisite.</p></div>';
        });
        return;
    }

    LEWM_Network::boot();
    // Serve the shared authenticated customer callback even on a non-shop main site.
    LEWM_Rest_API::boot();
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>Loyalty Engage for WooCommerce requires WooCommerce to be active.</p></div>';
        });
        return;
    }

    LEWM_Activator::maybe_upgrade();
    LEWM_Plugin::instance()->boot();
}

add_action('plugins_loaded', 'lewm_boot_plugin');
