# Deployment checklist (Phase 8, started in Phase 4)

These items are collected during development so Phase 8 doesn't miss them. Configuration that lives in the database (and so isn't in Git) is marked **[DB]**.

## Code and plugins
- [ ] Deploy only the tracked custom code: `lpg-commerce-core` (including the committed `build/`) and `lpg-storefront`.
- [ ] Install WooCommerce (version per architecture §1/§12) and **Yoast SEO free** (§12.3) on the server. Neither is in Git.
- [ ] Never upload `node_modules/`, `vendor/`, `tests/`, `logs/`, `wp-config.php` from dev, or `debug.log`.
- [ ] Clear the pattern cache after deploying the theme (`wp_get_theme()->delete_pattern_cache()`), so newly added pattern files register.

## wp-config / server
- [ ] `WP_DEBUG` false, `WP_DEBUG_LOG` pointing outside `public_html`, `WP_ENVIRONMENT_TYPE` `production`.
- [ ] Fresh salts, a dedicated DB user with privileges on the site DB only (F4).
- [ ] HTTPS everywhere (`FORCE_SSL_ADMIN`). If TLS is terminated at a proxy, set `$_SERVER['HTTPS']` correctly.
- [ ] A real cron (cPanel cron calling `wp-cron.php`) with `DISABLE_WP_CRON` true.
- [ ] SMTP for order and RFQ emails.
- [ ] Daily backups (DB and uploads), with one restore actually tested.

## Database / settings [DB]
- [ ] Search-replace the dev URL (`http://gas-retailer.test`) to the production URL, including serialized data (`wp search-replace`).
- [ ] Check for Site Editor overrides: `wp post list --post_type=wp_template,wp_template_part,wp_navigation`. Saved edits may contain dev URLs (security I2).
- [ ] `blog_public` = 1 (allow indexing) **only at launch**.
- [ ] Yoast: author archives **disabled** (security L1; already done on dev). Exclude cart, checkout and my-account from the sitemap and set them noindex. Set title and meta-description templates with real content. The homepage title template is `%%sitename%% %%page%%` until a tagline exists; add `%%sep%% %%sitedesc%%` back once the client provides one (DEF-008).
- [ ] WooCommerce: store address and currency (KES); shipping zone "Nairobi" per architecture §13.9 (flat KSh 300, free over KSh 10,000, local pickup); payment gateways (M-Pesa scheduled by the owner).
- [ ] **LPG settings** (WooCommerce → Settings → LPG): WhatsApp number, phone, Sales and Owner notification emails, address, hours. These are entered on the server; they are not in Git.
- [ ] Run `wp lpg seed` once (idempotent), then replace the placeholder content with the owner's real content (the W-series in owner-decisions.md).

## Caching and proxies (Phase 5 security M2)
- [ ] The page cache must **never** store logged-in responses: bypass on `wordpress_logged_in_*`, `wp-*`, and WooCommerce session/cart cookies (`woocommerce_*`, `wp_woocommerce_session_*`).
- [ ] Cache TTL is **under 12 hours** for pages with the RFQ, exchange or enquiry blocks (they embed the anonymous REST nonce), or exclude those pages from the page cache.
- [ ] Behind Cloudflare or a host reverse proxy, define `LPG_TRUSTED_PROXIES` in wp-config (proxy IPs/CIDRs). Otherwise every visitor shares one rate-limit bucket and real users can be blocked.
- [ ] Set up SPF/DKIM/DMARC for the sending domain, so RFQ notifications aren't marked as spam.

## Security hardening
- [ ] A strong admin password plus 2FA; login rate limiting (host or plugin) (security L1).
- [ ] Block anonymous `/wp/v2/users` (plugin hardening service; scheduled for Phase 5).
- [ ] Re-run `composer audit` and `npm audit --omit=dev` before release (dev-only advisories, L2: `npm audit fix` without `--force`).
- [ ] After any WooCommerce upgrade: re-check the mini-cart drawer keyboard fix (DEF-004 depends on WooCommerce's `--is-hidden` drawer class names).
- [ ] Do **not** deploy `wp-content/mu-plugins/dev-mail-to-log.php` (dev-only mail catcher). Configure real SMTP instead.
