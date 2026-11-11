<?php

if (!defined('ABSPATH')) {
    exit;
}

class LEWM_Settings
{
    public const OPTION_KEY = 'lewm_settings';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function boot_network(): void
    {
        add_action('network_admin_menu', [self::class, 'register_network_menu'], 20);
        add_action('network_admin_edit_lewm_settings', [self::class, 'save_network']);
    }

    public static function defaults(): array
    {
        return [
            'module_enabled' => 'yes',
            'api_base_url' => 'https://app.loyaltyengage.tech/api/v1',
            'client_id' => '',
            'client_secret' => '',
            'webhook_secret' => '',
            'logger_enabled' => 'no',
            'order_export_enabled' => 'yes',
            'purchase_export_statuses' => 'processing,completed',
            'return_export_enabled' => 'yes',
            'review_export_enabled' => 'no',
            'customer_sync_enabled' => 'yes',
            'discount_reward_mode' => 'woocommerce_coupon',
            'max_loyalty_products_per_cart' => '0',
            'minimum_order_value' => '0',
            'minimum_order_value_message' => 'Je winkelmand moet minimaal {{minimum}} zijn om loyalty rewards toe te voegen. Huidig subtotaal: {{current}}.',
            'loyalty_product_removed_message' => 'Je loyalty reward is verwijderd omdat je winkelmand onder {{minimum}} is gekomen. Huidig subtotaal: {{current}}.',
            'pdp_points_enabled' => 'no',
            'pdp_points_per_currency_unit' => '1',
            'pdp_points_label' => 'Met dit product verdien je {{points}} punten.',
            'free_shipping_enabled' => 'no',
            'free_shipping_tiers' => '',
            'expose_customer_meta' => 'yes',
            'account_block_title' => 'Loyalty overzicht',
            'levels_title' => 'Jouw titel',
            'levels_intro' => 'Bekijk welke levels je al hebt bereikt en welke nog voor je liggen.',
            'rewards_title' => 'Cadeaus en cadeaubonnen',
            'rewards_intro' => 'Je hebt {{coins}} coins om in te wisselen. Ontdek alle rewards, ook wanneer je nog coins of een hoger level nodig hebt.',
            'loyalty_custom_css' => '',
            'le_current_tier_enabled' => 'yes',
            'le_current_tier_label' => 'Huidige tier',
            'le_points_enabled' => 'yes',
            'le_points_label' => 'Punten',
            'le_available_coins_enabled' => 'yes',
            'le_available_coins_label' => 'Beschikbare coins',
            'le_next_tier_enabled' => 'yes',
            'le_next_tier_label' => 'Volgende tier',
            'le_points_to_next_tier_enabled' => 'yes',
            'le_points_to_next_tier_label' => 'Punten tot volgende tier',
            'le_reserved_coins_enabled' => 'no',
            'le_reserved_coins_label' => 'Gereserveerde coins',
            'le_expiring_points_30d_enabled' => 'no',
            'le_expiring_points_30d_label' => 'Punten die binnen 30 dagen verlopen',
            'loyalty_shop_personalization_enabled' => 'no',
            'loyalty_shop_personalization_tags' => '',
            'loyalty_shop_display_mode' => 'prioritize_matches',
            'loyalty_levels' => '',
        ];
    }

    public static function get_settings(): array
    {
        $settings = wp_parse_args((array) get_network_option(get_current_network_id(), self::OPTION_KEY, []), self::defaults());
        return array_replace($settings, LEWM_Network::connection());
    }

    public static function register_menu(): void
    {
        add_menu_page(
            'Loyalty Engage',
            'Loyalty Engage',
            'manage_woocommerce',
            'loyalty-engage',
            static function (): void { echo '<div class="wrap"><h1>Loyalty Engage Multisite</h1><p>Alle instellingen worden centraal beheerd via Netwerkbeheer &gt; Loyalty Engage. Wijzigingen gelden voor alle shops in dit netwerk.</p></div>'; },
            'dashicons-awards'
        );
    }

    public static function register_settings(): void
    {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => self::defaults(),
        ]);
    }

    public static function sanitize(array $settings): array
    {
        $defaults = self::defaults();
        $merged = wp_parse_args($settings, $defaults);
        $current = (array) get_network_option(get_current_network_id(), self::OPTION_KEY, []);
        $is_enabled = static function ($value): string {
            return in_array($value, [1, '1', true, 'true', 'yes', 'on'], true) ? 'yes' : 'no';
        };
        $sanitize_secret = static function ($value): string {
            // Secrets are opaque values: HTML/text sanitizers can corrupt valid characters such as "<".
            return trim(str_replace(["\r", "\n", "\0"], '', (string) $value));
        };
        $sanitize_decimal = static function ($value): string {
            $number = is_numeric($value) ? (float) $value : 0.0;
            return (string) max(0, $number);
        };
        $sanitize_int = static function ($value): string {
            return (string) max(0, absint($value));
        };
        $api_base_url = esc_url_raw(trim((string) $merged['api_base_url']));
        if ($api_base_url === '') {
            $api_base_url = $defaults['api_base_url'];
        }

        $client_secret = $sanitize_secret($merged['client_secret']);
        if ($client_secret === '' && !empty($current['client_secret'])) {
            $client_secret = (string) $current['client_secret'];
        }

        $webhook_secret = $sanitize_secret($merged['webhook_secret']);
        if ($webhook_secret === '' && !empty($current['webhook_secret'])) {
            $webhook_secret = (string) $current['webhook_secret'];
        }

        $sanitized = [
            'module_enabled' => $is_enabled($merged['module_enabled']),
            'api_base_url' => untrailingslashit($api_base_url),
            'client_id' => sanitize_text_field((string) $merged['client_id']),
            'client_secret' => $client_secret,
            'webhook_secret' => $webhook_secret,
            'logger_enabled' => $is_enabled($merged['logger_enabled']),
            'order_export_enabled' => $is_enabled($merged['order_export_enabled']),
            'purchase_export_statuses' => sanitize_text_field((string) $merged['purchase_export_statuses']),
            'return_export_enabled' => $is_enabled($merged['return_export_enabled']),
            'review_export_enabled' => $is_enabled($merged['review_export_enabled']),
            'customer_sync_enabled' => $is_enabled($merged['customer_sync_enabled']),
            'discount_reward_mode' => in_array($merged['discount_reward_mode'], ['woocommerce_coupon', 'email_gift_card'], true)
                ? $merged['discount_reward_mode']
                : $defaults['discount_reward_mode'],
            'max_loyalty_products_per_cart' => $sanitize_int($merged['max_loyalty_products_per_cart']),
            'minimum_order_value' => $sanitize_decimal($merged['minimum_order_value']),
            'minimum_order_value_message' => sanitize_textarea_field((string) $merged['minimum_order_value_message']),
            'loyalty_product_removed_message' => sanitize_textarea_field((string) $merged['loyalty_product_removed_message']),
            'pdp_points_enabled' => $is_enabled($merged['pdp_points_enabled']),
            'pdp_points_per_currency_unit' => $sanitize_decimal($merged['pdp_points_per_currency_unit']),
            'pdp_points_label' => sanitize_text_field((string) $merged['pdp_points_label']),
            'free_shipping_enabled' => $is_enabled($merged['free_shipping_enabled']),
            'free_shipping_tiers' => sanitize_text_field((string) $merged['free_shipping_tiers']),
            'expose_customer_meta' => $is_enabled($merged['expose_customer_meta']),
            'account_block_title' => sanitize_text_field((string) $merged['account_block_title']),
            'levels_title' => sanitize_text_field((string) $merged['levels_title']),
            'levels_intro' => sanitize_textarea_field((string) $merged['levels_intro']),
            'rewards_title' => sanitize_text_field((string) $merged['rewards_title']),
            'rewards_intro' => sanitize_textarea_field((string) $merged['rewards_intro']),
            'loyalty_custom_css' => self::sanitize_custom_css((string) $merged['loyalty_custom_css']),
            'le_current_tier_enabled' => $is_enabled($merged['le_current_tier_enabled']),
            'le_current_tier_label' => sanitize_text_field((string) $merged['le_current_tier_label']),
            'le_points_enabled' => $is_enabled($merged['le_points_enabled']),
            'le_points_label' => sanitize_text_field((string) $merged['le_points_label']),
            'le_available_coins_enabled' => $is_enabled($merged['le_available_coins_enabled']),
            'le_available_coins_label' => sanitize_text_field((string) $merged['le_available_coins_label']),
            'le_next_tier_enabled' => $is_enabled($merged['le_next_tier_enabled']),
            'le_next_tier_label' => sanitize_text_field((string) $merged['le_next_tier_label']),
            'le_points_to_next_tier_enabled' => $is_enabled($merged['le_points_to_next_tier_enabled']),
            'le_points_to_next_tier_label' => sanitize_text_field((string) $merged['le_points_to_next_tier_label']),
            'le_reserved_coins_enabled' => $is_enabled($merged['le_reserved_coins_enabled']),
            'le_reserved_coins_label' => sanitize_text_field((string) $merged['le_reserved_coins_label']),
            'le_expiring_points_30d_enabled' => $is_enabled($merged['le_expiring_points_30d_enabled']),
            'le_expiring_points_30d_label' => sanitize_text_field((string) $merged['le_expiring_points_30d_label']),
            'loyalty_shop_personalization_enabled' => $is_enabled($merged['loyalty_shop_personalization_enabled']),
            'loyalty_shop_personalization_tags' => sanitize_text_field((string) $merged['loyalty_shop_personalization_tags']),
            'loyalty_shop_display_mode' => $merged['loyalty_shop_display_mode'] === 'only_matching' ? 'only_matching' : 'prioritize_matches',
            'loyalty_levels' => sanitize_textarea_field((string) $merged['loyalty_levels']),
        ];
        return array_diff_key($sanitized, array_flip(LEWM_Network::KEYS));
    }

    public static function sanitize_custom_css(string $css): string
    {
        // Never allow custom CSS to close the inline style element.
        return str_replace('<', '', wp_strip_all_tags($css));
    }

    public static function is_module_enabled(): bool
    {
        $settings = self::get_settings();
        return ($settings['module_enabled'] ?? 'yes') === 'yes';
    }

    public static function sections(): array
    {
        return [
            'algemeen' => ['title' => 'Algemeen', 'fields' => ['module_enabled', 'logger_enabled']],
            'orders' => ['title' => 'Orders en synchronisatie', 'fields' => ['order_export_enabled', 'purchase_export_statuses', 'return_export_enabled', 'review_export_enabled', 'customer_sync_enabled']],
            'rewards' => ['title' => 'Rewards en winkelwagen', 'fields' => ['discount_reward_mode', 'max_loyalty_products_per_cart', 'minimum_order_value', 'minimum_order_value_message', 'loyalty_product_removed_message', 'free_shipping_enabled', 'free_shipping_tiers', 'loyalty_shop_personalization_enabled', 'loyalty_shop_personalization_tags', 'loyalty_shop_display_mode']],
            'levels' => ['title' => 'Levels', 'fields' => ['loyalty_levels']],
            'teksten' => ['title' => 'Teksten en weergave', 'fields' => array_merge(['pdp_points_enabled', 'pdp_points_per_currency_unit', 'pdp_points_label', 'expose_customer_meta', 'account_block_title', 'levels_title', 'levels_intro', 'rewards_title', 'rewards_intro'], array_values(array_filter(array_keys(self::defaults()), static fn ($key) => str_starts_with($key, 'le_'))))],
            'stijl' => ['title' => 'Vormgeving', 'fields' => ['loyalty_custom_css']],
        ];
    }

    public static function register_network_menu(): void
    {
        foreach (self::sections() as $key => $section) {
            add_submenu_page('lewm-network', $section['title'], $section['title'], 'manage_network_options', 'lewm-settings-' . $key, static function () use ($key): void { self::render_page($key); });
        }
    }

    public static function is_toggle(string $key): bool
    {
        return str_ends_with($key, '_enabled') || $key === 'expose_customer_meta';
    }

    public static function prepare_section(string $section, array $input, array $current): array
    {
        $sections = self::sections();
        if (!isset($sections[$section])) { throw new InvalidArgumentException('Onbekende instellingensectie.'); }
        foreach ($sections[$section]['fields'] as $key) {
            if (self::is_toggle($key)) {
                $current[$key] = isset($input[$key]) && is_scalar($input[$key]) ? $input[$key] : 'no';
            } elseif (isset($input[$key]) && is_scalar($input[$key])) {
                $current[$key] = $input[$key];
            }
        }
        return self::sanitize($current);
    }

    public static function save_network(): void
    {
        if (!current_user_can('manage_network_options')) { wp_die('Geen toegang.'); }
        check_admin_referer('lewm_settings');
        $section = isset($_POST['section']) && is_string($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : '';
        $input = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        try { $saved = self::prepare_section($section, $input, self::get_settings()); }
        catch (InvalidArgumentException $e) { wp_die(esc_html($e->getMessage())); }
        update_network_option(get_current_network_id(), self::OPTION_KEY, $saved);
        wp_safe_redirect(network_admin_url('admin.php?page=lewm-settings-' . $section . '&updated=1'));
        exit;
    }

    private static function labels(): array
    {
        return [
            'module_enabled' => 'Loyalty Engage inschakelen', 'logger_enabled' => 'Logging inschakelen',
            'order_export_enabled' => 'Bestellingen exporteren', 'purchase_export_statuses' => 'Orderstatussen voor export',
            'return_export_enabled' => 'Retouren exporteren', 'review_export_enabled' => 'Reviews exporteren', 'customer_sync_enabled' => 'Klanten synchroniseren',
            'discount_reward_mode' => 'Aflevering cadeaubonnen', 'max_loyalty_products_per_cart' => 'Maximum rewards per winkelwagen',
            'minimum_order_value' => 'Minimaal regulier subtotaal', 'minimum_order_value_message' => 'Melding bij te laag subtotaal',
            'loyalty_product_removed_message' => 'Melding bij verwijderen reward', 'free_shipping_enabled' => 'Gratis verzending op basis van level',
            'free_shipping_tiers' => 'Levels met gratis verzending', 'loyalty_shop_personalization_enabled' => 'Rewards rangschikken op producttags',
            'loyalty_shop_personalization_tags' => 'Producttags', 'loyalty_shop_display_mode' => 'Weergave van gematchte rewards',
            'loyalty_levels' => 'Alle levels', 'pdp_points_enabled' => 'Punten op productpagina tonen',
            'pdp_points_per_currency_unit' => 'Levelpunten per valuta-eenheid', 'pdp_points_label' => 'Puntentekst productpagina',
            'expose_customer_meta' => 'Loyalty-overzicht tonen', 'account_block_title' => 'Titel overzicht',
            'levels_title' => 'Titel levels', 'levels_intro' => 'Ondertitel levels', 'rewards_title' => 'Titel rewards', 'rewards_intro' => 'Ondertitel rewards',
            'loyalty_custom_css' => 'Eigen CSS',
            'le_current_tier' => 'Huidige titel', 'le_points' => 'Levelpunten', 'le_available_coins' => 'Beschikbare coins',
            'le_next_tier' => 'Volgende titel', 'le_points_to_next_tier' => 'Punten tot volgende titel',
            'le_reserved_coins' => 'Gereserveerde coins', 'le_expiring_points_30d' => 'Punten die binnen 30 dagen verlopen',
        ];
    }

    public static function render_page(string $section = 'algemeen'): void
    {
        if (!current_user_can('manage_network_options')) { wp_die('Geen toegang.'); }
        $sections = self::sections();
        if (!isset($sections[$section])) { return; }
        $settings = self::get_settings();
        $labels = self::labels();
        $choices = [
            'discount_reward_mode' => ['woocommerce_coupon' => 'WooCommerce-coupon automatisch toepassen', 'email_gift_card' => 'Cadeaukaart per e-mail, niet in winkelwagen'],
            'loyalty_shop_display_mode' => ['prioritize_matches' => 'Alle rewards, matches eerst', 'only_matching' => 'Alleen matches'],
        ];
        $help = [
            'purchase_export_statuses' => 'Komma-gescheiden, bijvoorbeeld processing,completed.',
            'max_loyalty_products_per_cart' => '0 betekent geen maximum. Coins worden uitsluitend door Loyalty Engage afgeschreven.',
            'minimum_order_value' => 'Regulier winkelwagensubtotaal, exclusief loyaltyproducten.',
            'minimum_order_value_message' => 'Variabelen: {{minimum}} en {{current}}.',
            'loyalty_product_removed_message' => 'Variabelen: {{minimum}} en {{current}}.',
            'free_shipping_tiers' => 'Komma-gescheiden levelnamen.',
            'loyalty_shop_personalization_tags' => 'Komma-gescheiden producttags. Producten en SKU-koppelingen blijven onderdeel van de betreffende WooCommerce-shop.',
            'loyalty_levels' => "Een level per regel in oplopende volgorde: naam|benodigde punten. Bijvoorbeeld Starter|0, Bourgondier|100 en Chef|1000 (ieder op een eigen regel).",
            'pdp_points_label' => 'Gebruik {{points}}. Dit is een schatting van levelpunten, exclusief campagnebonussen.',
            'rewards_intro' => 'Gebruik {{coins}} voor het gedeelde inwisselbare saldo.',
            'loyalty_custom_css' => 'Alleen CSS, zonder style-tags. Begin selectors met #lew-loyalty-page. Geldt voor alle shops.',
        ];
        ?>
        <div class="wrap lewm-network-settings">
            <h1><?php echo esc_html($sections[$section]['title']); ?></h1>
            <p>Centraal beheerd voor alle shops in dit netwerk. Shops kunnen deze instellingen niet overschrijven.</p>
            <?php if (isset($_GET['updated'])) : ?><div class="notice notice-success"><p>Netwerkinstellingen opgeslagen.</p></div><?php endif; ?>
            <nav class="nav-tab-wrapper">
                <?php foreach ($sections as $key => $item) : ?>
                    <a class="nav-tab<?php echo $key === $section ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(network_admin_url('admin.php?page=lewm-settings-' . $key)); ?>"><?php echo esc_html($item['title']); ?></a>
                <?php endforeach; ?>
            </nav>
            <form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=lewm_settings')); ?>">
                <?php wp_nonce_field('lewm_settings'); ?>
                <input type="hidden" name="section" value="<?php echo esc_attr($section); ?>">
                <table class="form-table" role="presentation">
                <?php foreach ($sections[$section]['fields'] as $key) :
                    $base = preg_replace('/_(enabled|label)$/', '', $key);
                    $label = $labels[$key] ?? (($labels[$base] ?? $base) . (str_ends_with($key, '_label') ? ' - label' : ' tonen'));
                    $value = (string) $settings[$key];
                    ?>
                    <tr><th scope="row"><label for="lewm-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td>
                    <?php if (self::is_toggle($key)) : ?>
                        <label><input id="lewm-<?php echo esc_attr($key); ?>" type="checkbox" name="settings[<?php echo esc_attr($key); ?>]" value="yes" <?php checked($value, 'yes'); ?>> Ingeschakeld</label>
                    <?php elseif (isset($choices[$key])) : ?>
                        <select id="lewm-<?php echo esc_attr($key); ?>" name="settings[<?php echo esc_attr($key); ?>]">
                        <?php foreach ($choices[$key] as $option => $text) : ?><option value="<?php echo esc_attr($option); ?>" <?php selected($value, $option); ?>><?php echo esc_html($text); ?></option><?php endforeach; ?>
                        </select>
                    <?php elseif (in_array($key, ['loyalty_custom_css', 'loyalty_levels', 'minimum_order_value_message', 'loyalty_product_removed_message', 'levels_intro', 'rewards_intro'], true)) : ?>
                        <textarea class="large-text<?php echo $key === 'loyalty_custom_css' ? ' code' : ''; ?>" rows="<?php echo $key === 'loyalty_custom_css' ? '10' : '4'; ?>" id="lewm-<?php echo esc_attr($key); ?>" name="settings[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea($value); ?></textarea>
                    <?php else : ?>
                        <input class="regular-text" type="text" id="lewm-<?php echo esc_attr($key); ?>" name="settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                    <?php endif; ?>
                    <?php if (isset($help[$key])) : ?><p class="description"><?php echo esc_html($help[$key]); ?></p><?php endif; ?>
                    </td></tr>
                <?php endforeach; ?>
                </table>
                <?php submit_button('Opslaan voor alle shops'); ?>
            </form>
        </div>
        <?php
    }
}
