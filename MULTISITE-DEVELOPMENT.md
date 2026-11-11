# Loyalty Engage WooCommerce Multisite

Status: validated in a local WordPress/WooCommerce multisite sandbox with an API double; not yet validated against a real Loyalty Engage tenant.
Version: 0.4.0-alpha.2. The single-site project and release stream are unchanged.

## Separate identity

- Plugin directory and main file: loyalty-engage-woocommerce-multisite.
- PHP classes and constants use LEWM_ instead of LEW_.
- Update URI, release asset name and updater cache are distinct from single-site.
- Dedicated repository and release workflow: Loyalty-Engage/loyalty-engage-woocommerce-multisite. The workflow packages only the multisite entrypoint, includes and assets; local fixtures and credentials are excluded from the ZIP.
- Alpha versions are published as prereleases, not offered through the normal stable WordPress updater.
- Running alongside the single-site plugin is not supported; multisite runtime refuses to start when the single-site plugin is loaded.

## Implemented

- All plugin preferences and credentials are centrally managed with capability and nonce checks, section-specific saves and no shop overrides.
- Network-scoped shared customer balances and levels, authenticated callback and API refresh after claims.
- Site-isolated pending coupons, outbox tables and cron jobs.
- Shop-qualified purchase and review references.
- Existing/new-site initialization and network deactivation cleanup.
- Local regression tests for shared balances, settings precedence and reference isolation.
- Central personalization tenant and rules with shop selection and site-qualified page targets.
- Actual WordPress 7.1.4 / WooCommerce 11.2.2 / PHP 8.3 / MariaDB 11.4 integration tests: two shops, callbacks, shared balances, coupon application/isolation, zero-priced physical gifts, order and refund outboxes, duplicate local refund hooks, new-site initialization and network deactivation/reactivation.
- Browser-verified network personalization page and successful rule save.
- Read-only real-tenant check confirmed the flat loyalty-status contract (currentPoints, availableCoins, currentTier, nextTier, pointsRemainingToNextTier). Both flat and legacy user_data-wrapped responses are supported and covered by regression tests. Successful authentication/catalog reads do not yet validate purchases or refunds against the real API.

## Required before a production release

- Confirm purchase, physical-purchase and review reference formats against the real API; verify the inherited Return contract and refund deduplication.
- Validate authenticated customer callbacks against the actual Loyalty Engage tenant.
- Loyalty Engage must enforce atomic coin redemption; verify concurrent requests with the tenant.
- Verify currency, SKU mapping and coupon scope separately for every shop.
- Test at least two subsites, duplicate order IDs, shared customers and concurrent redemptions.

Do not treat the local tests as proof of complete WordPress Multisite compatibility.
