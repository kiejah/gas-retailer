# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

WordPress + WooCommerce storefront for an LPG **equipment and accessories** supplier in Kenya. It serves household, commercial and industrial customers. The product catalogue is the core of the business. Cylinder refill and exchange is a minor, WhatsApp-driven side flow. Do not design it as a gas-delivery app.

- `docs/architecture.md`: layer boundaries, data model, plugin and theme specs, decision log. **Read before changing any data model, meta key, hook or REST contract.**
- `docs/implementation-plan.md`: phase status, agent ownership, risks and next tasks. Update it when work completes.

**Team:** the main session is the Lead Architect: it owns architecture, shared interfaces and `docs/`, reviews all work, and closes phases. Specialists in `.claude/agents/`: `lpg-domain` (plugin), `lpg-theme` (theme), `lpg-security` (read-only review), `lpg-qa` (tests, perf, SEO). While the owner is away, `lpg-owner-proxy` makes provisional owner decisions and phase-gate approvals, logged in `docs/owner-decisions.md`. Its limits are in its definition file. Each task given to a specialist must state the objective, inputs, files, dependencies and acceptance criteria. Only one agent edits a given directory at a time.

All decisions in the architecture decision log (A1–A5, D1–D6) were accepted by the owner on 2026-11-01. Changing one needs the owner's approval.

Work is **phase-gated**. Finish the current phase, report (work done, files, decisions, tests run with results, open issues), and wait for review before starting the next.

## Layout

- This folder is the repo root (not web-served). `gas-retailer/` is the WordPress web root.
- Only `gas-retailer/wp-content/plugins/lpg-commerce-core/` and `gas-retailer/wp-content/themes/lpg-storefront/` are our code. WordPress core, WooCommerce, uploads and `wp-config.php` are untracked. Never edit core or WooCommerce files.

## Architecture rules

- **Business logic lives only in the plugin** (`lpg-commerce-core`, namespace `LPG\Commerce`, prefix `lpg_` / hidden meta `_lpg_`, text domain `lpg-commerce-core`). The theme (`lpg-storefront`, block theme) is presentation only: theme.json, templates, parts, patterns.
- LPG UI (spec table, compatible products, documents, enquiry button, RFQ form, exchange form) is delivered as **plugin dynamic blocks**. The theme places them and never re-implements them.
- Use native WooCommerce first: upsells = recommended products, cross-sells = accessories, grouped products = packages, global `pa_` attributes = filterable specs.
- No custom DB tables. Use CPTs, taxonomies and meta. RFQs and enquiries are private CPTs with custom capabilities.
- Plugin has no runtime Composer dependency (own autoloader). Composer and npm are dev-only. Commit built `build/` assets, because cPanel has no Node.
- Target shared cPanel hosting: no Redis, no background workers, nothing expensive on page load, non-autoloaded options.

## Content rules

Never invent technical specifications, certifications, ratings, manufacturer logos or claims. Demo products must be labelled `[DEMO]`.
Payments: offline gateways only (COD/BACS) until M-Pesa is deliberately scheduled. Never add live credentials.

## Local environment

- Site: http://gas-retailer.test (Herd link, PHP 8.3). MySQL 9.0 on localhost, site DB `gas_retailer_db`. Production targets MySQL 8.0/MariaDB, so avoid version-specific SQL.
- WordPress 7.1.4 and WooCommerce 11.2.1 (HPOS on, KES, Kenya-only, COD only). `WP_ENVIRONMENT_TYPE=local`.
- Debug log: `logs/debug.log` in the repo root, deliberately outside the web root.
- Run `wp` (WP-CLI) from `gas-retailer/`. The user installs system-level tools themselves: give them the command instead of running brew or global installs.

## Commands (run from repo root)

```bash
composer lint                      # PHPCS (WPCS 3 + PHPCompatibilityWP) on plugin, theme, tests
composer lint:fix                  # phpcbf auto-fix
composer test                      # PHPUnit integration suite (WP test suite + WooCommerce)
vendor/bin/phpunit --filter test_install_does_not_overwrite_existing_settings   # single test
vendor/bin/phpunit tests/integration/PluginTest.php                             # single file
```

- Tests use `tests/wp-tests-config.php` (git-ignored; template in `tests/wp-tests-config.sample.php`). It points at the separate `gas_retailer_test` DB with prefix `wptests_` and a MySQL user that can only reach that DB.
- The test suite drops tables on every run. `tests/bootstrap.php` refuses to run unless the DB name ends in `_test` and the prefix is `wptests_`. Never weaken that guard.
- Test classes go in `tests/integration/*Test.php` and extend `WP_UnitTestCase`.
- Block build (Node ≥ 22.22.2; local Node is via nvm at `~/.nvm/versions/node/v22.23.4/bin`, which non-interactive shells don't load, so prefix `PATH`):
  ```bash
  npm run build      # blocks/ → lpg-commerce-core/build/blocks (commit build/; cPanel has no Node)
  npm run start      # watch mode
  npm run lint:js    # ESLint (eslint.config.cjs marks @wordpress/* as core modules, provided by WordPress)
  ```
  Without `build/blocks/blocks-manifest.php`, `Blocks\Registry` registers blocks from source with no editor scripts: the front end works but the blocks don't appear in the editor.

## Dev-site test fixtures

- Name every fixture `[QA TEST]` and delete it with `--force`, **by the IDs you created**. Never delete by filename pattern or glob in `uploads/`.
- **Never touch the WooCommerce placeholder** (attachment #8, `uploads/woocommerce-placeholder*.webp`). It was deleted twice; the cause was the test suite sharing the dev uploads folder (DEF-009, fixed: `tests/bootstrap.php` now redirects uploads to a temp dir). Never remove that redirect. To restore it, copy `plugins/woocommerce/assets/images/placeholder.webp` to `uploads/woocommerce-placeholder.webp`, then run `wp media regenerate 8 --yes`.
- **Dev mail never sends:** `wp-content/mu-plugins/dev-mail-to-log.php` (untracked, local only) writes every `wp_mail` to `logs/mail.log`. Check that file to see notifications. Don't remove it on dev, and never deploy it.
