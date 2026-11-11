<?php

if (!defined('ABSPATH')) {
    exit;
}

class LEWM_Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        LEWM_Settings::boot();
        LEWM_Outbox::boot();
        LEWM_Webhooks::boot();
        LEWM_Shipping::boot();
        LEWM_Storefront::boot();
        LEWM_Personalizations::boot();
    }
}
