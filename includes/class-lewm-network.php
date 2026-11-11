<?php

if (!defined('ABSPATH')) { exit; }

class LEWM_Network
{
    public const OPTION = 'lewm_network_connection';
    public const KEYS = ['api_base_url', 'client_id', 'client_secret', 'webhook_secret'];

    public static function boot(): void
    {
        LEWM_Settings::boot_network();
        LEWM_Personalizations::boot_network();
        add_action('network_admin_menu', [self::class, 'menu']);
        add_action('network_admin_edit_lewm_connection', [self::class, 'save']);
        add_action('wp_initialize_site', [self::class, 'initialize_site'], 200);
    }

    public static function connection(): array
    {
        return array_replace(array_fill_keys(self::KEYS, ''),
            ['api_base_url' => 'https://app.loyaltyengage.tech/api/v1'],
            array_intersect_key((array) get_network_option(get_current_network_id(), self::OPTION, []), array_flip(self::KEYS)));
    }

    public static function meta_key(string $key): string
    {
        return 'lewm_network_' . get_current_network_id() . '_' . $key;
    }

    public static function reference(string $type, int $id): string
    {
        return 'N' . get_current_network_id() . '-S' . get_current_blog_id() . '-' . $type . '-' . $id;
    }

    public static function sync_customer(int $user_id, array $data): void
    {
        $fields = [
            'points' => 'lew_points', 'le_points' => 'lew_points', 'currentpoints' => 'lew_points',
            'available_coins' => 'lew_availableCoins', 'availablecoins' => 'lew_availableCoins', 'le_available_coins' => 'lew_availableCoins',
            'current_tier' => 'lew_current_tier', 'currenttier' => 'lew_current_tier', 'le_current_tier' => 'lew_current_tier',
            'next_tier' => 'lew_next_tier', 'nexttier' => 'lew_next_tier', 'le_next_tier' => 'lew_next_tier',
            'points_to_next_tier' => 'lew_points_to_next_tier', 'pointstonexttier' => 'lew_points_to_next_tier', 'le_points_to_next_tier' => 'lew_points_to_next_tier', 'pointsremainingtonexttier' => 'lew_points_to_next_tier',
            'reserved_coins' => 'lew_reserved_coins', 'le_reserved_coins' => 'lew_reserved_coins', 'reservedcoins' => 'lew_reserved_coins',
            'expiring_points_30d' => 'lew_expiring_points_30d', 'le_expiring_points_30d' => 'lew_expiring_points_30d', 'expiringpoints30d' => 'lew_expiring_points_30d',
        ];
        foreach ($data as $key => $value) {
            $key = strtolower((string) $key);
            $meta = $fields[$key] ?? null;
            if ($meta === null || !is_scalar($value)) { continue; }
            $value = sanitize_text_field((string) $value);
            if (!in_array($meta, ['lew_current_tier', 'lew_next_tier'], true) && (!is_numeric($value) || (float) $value < 0)) { continue; }
            update_user_meta($user_id, self::meta_key($meta), $value);
        }
    }

    public static function refresh_customer(int $user_id): void
    {
        $user = get_user_by('id', $user_id);
        if (!$user || !$user->user_email) { return; }
        $client = new LEWM_Loyalty_Engage_Client();
        $response = $client->request('/contact/' . rawurlencode($user->user_email) . '/loyalty_status');
        if (!empty($response['success'])) {
            self::sync_customer($user_id, self::status_data($response['body'] ?? null));
        }
    }

    public static function status_data($body): array
    {
        if (!is_array($body)) { return []; }
        return isset($body['user_data']) && is_array($body['user_data']) ? $body['user_data'] : $body;
    }

    public static function refresh_current_customer(): void
    {
        if (!is_user_logged_in()) { return; }
        $user_id = get_current_user_id();
        $key = self::meta_key('last_refresh_attempt');
        if ((int) get_user_meta($user_id, $key, true) > time() - 30) { return; }
        update_user_meta($user_id, $key, time());
        self::refresh_customer($user_id);
    }

    public static function menu(): void
    {
        add_menu_page('Loyalty Engage Multisite', 'Loyalty Engage', 'manage_network_options', 'lewm-network', [self::class, 'render'], 'dashicons-awards');
        add_submenu_page('lewm-network', 'Koppeling', 'Koppeling', 'manage_network_options', 'lewm-network', [self::class, 'render']);
    }

    public static function render(): void
    {
        if (!current_user_can('manage_network_options')) { wp_die('Geen toegang.'); }
        $settings = self::connection();
        echo '<div class="wrap"><h1>Loyalty Engage: netwerkkoppeling</h1><p>Alle instellingen worden centraal beheerd voor dit netwerk. Klanten delen hun saldo en levels; producten, bestellingen en coupons blijven gekoppeld aan hun eigen shop.</p>';
        echo '<p>Klantupdate-endpoint: <code>' . esc_html(get_rest_url(get_main_site_id(), 'loyalty-engage/v1/customer-update')) . '</code></p>';
        echo '<form method="post" action="' . esc_url(network_admin_url('edit.php?action=lewm_connection')) . '">';
        wp_nonce_field('lewm_connection');
        foreach (self::KEYS as $key) {
            $secret = in_array($key, ['client_secret', 'webhook_secret'], true);
            echo '<p><label for="lewm-' . esc_attr($key) . '">' . esc_html($key) . '</label><br><input class="regular-text" id="lewm-' . esc_attr($key) . '" name="connection[' . esc_attr($key) . ']" type="' . ($secret ? 'password' : 'text') . '" autocomplete="new-password" value="' . ($secret ? '' : esc_attr($settings[$key])) . '"></p>';
        }
        echo '<p>Laat een geheim leeg om de bestaande waarde te behouden. Configureer de callback met de gedeelde webhook secret. Gebruik voor dezelfde klant hetzelfde e-mailadres in alle shops.</p>';
        submit_button();
        echo '</form></div>';
    }

    public static function sanitize_connection(array $input, array $current): array
    {
        $url = untrailingslashit(esc_url_raw(trim((string) ($input['api_base_url'] ?? ''))));
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new InvalidArgumentException('De API-URL moet HTTPS gebruiken.');
        }
        $result = ['api_base_url' => $url, 'client_id' => sanitize_text_field((string) ($input['client_id'] ?? ''))];
        foreach (['client_secret', 'webhook_secret'] as $key) {
            $value = trim(str_replace(["\r", "\n", "\0"], '', (string) ($input[$key] ?? '')));
            $result[$key] = $value !== '' ? $value : (string) ($current[$key] ?? '');
        }
        return $result;
    }

    public static function save(): void
    {
        if (!current_user_can('manage_network_options')) { wp_die('Geen toegang.'); }
        check_admin_referer('lewm_connection');
        $input = isset($_POST['connection']) && is_array($_POST['connection']) ? wp_unslash($_POST['connection']) : [];
        try { $settings = self::sanitize_connection($input, self::connection()); }
        catch (InvalidArgumentException $e) { wp_die(esc_html($e->getMessage())); }
        update_network_option(get_current_network_id(), self::OPTION, $settings);
        wp_safe_redirect(network_admin_url('admin.php?page=lewm-network&updated=1'));
        exit;
    }

    public static function initialize_site($site): void
    {
        if ((int) $site->site_id !== get_current_network_id()) { return; }
        switch_to_blog((int) $site->blog_id);
        try { LEWM_Activator::activate(); }
        finally { restore_current_blog(); }
    }

    public static function each_site(callable $callback): void
    {
        $offset = 0;
        do {
            $ids = get_sites(['network_id' => get_current_network_id(), 'fields' => 'ids', 'number' => 100, 'offset' => $offset]);
            foreach ($ids as $id) {
                switch_to_blog((int) $id);
                try { $callback(); } finally { restore_current_blog(); }
            }
            $offset += count($ids);
        } while (count($ids) === 100);
    }
}
