# Implementation Plan & Status Record

This is the Lead Architect's central record of completed and pending work, known issues, agent assignments and next tasks.
Architecture details are in [architecture.md](architecture.md).

## Current status

| Phase | Status |
|---|---|
| 1 Discovery & Architecture | **Complete** — approved by the owner 2026-11-01 (all decisions accepted) |
| 2 Foundation | **Complete** (2026-11-01): see the Phase 2 report below |
| 3 Product catalogue | In progress. **3A (data layer and admin) complete 2026-11-01**: lpg-domain built it; Lead and lpg-security reviewed it; 81 tests pass. 3B (blocks) waits on Node ≥ 22.22.2 |
| 4–8 | Not started |

## Agent roles and coordination

| Agent | Owns (writes) | Reviews |
|---|---|---|
| Lead Architect | `docs/`, `CLAUDE.md`, plugin `src/Plugin.php`, shared interfaces (hooks, meta keys, REST contracts) | Every change before a phase is closed |
| WooCommerce/LPG Domain | plugin `src/` (except Plugin.php), plugin `blocks/` render logic | — |
| UI/UX Theme | `themes/lpg-storefront/`, block styling | — |
| Security | Read-only review; files findings | All plugin PHP, REST, forms, uploads |
| QA/Perf/SEO | `tests/`, `docs/test-plan.md` | Runs tests; reports executed, passed, failed and untested |

Rules in practice:
- Shared interfaces (meta keys, hook names, REST shapes, block attributes) are added to `architecture.md` **before** code depends on them.
- Domain and Theme work in parallel only on disjoint directories. Block markup and classes are the contract between them.
- Each task report covers: task, work done, files, decisions, tests, open issues, blockers.
- A phase closes only after Lead review and QA confirmation. Nothing is reported as passing unless it was run.

## Pre-requisites before Phase 2 — resolved 2026-11-01

1. D1–D6: accepted as proposed.
2. Repo layout (A1): approved; `git init` in the outer folder happens in Phase 2.
3. Dev-only installs (WP-CLI, Composer dev packages, `@wordpress/scripts`): approved.
4. Herd link: done. Salts: regenerated. Dedicated DB user: optional, the owner's choice.

Setup tweaks for Phase 2: Post-name permalinks, Africa/Nairobi timezone, `WP_MEMORY_LIMIT` 256M, remove Hello Dolly and sample content.

## Agent definitions

Specialist agents are defined in `.claude/agents/` and all load `CLAUDE.md`, `docs/architecture.md` and this file before working:
`lpg-domain` (WooCommerce/LPG domain), `lpg-theme` (UI/UX theme), `lpg-security` (read-only review), `lpg-qa` (QA, performance, SEO).
The main Claude Code session acts as Lead Architect.

## Roadmap

### Phase 2 — Foundation
| Task | Agent | Acceptance |
|---|---|---|
| Herd link, salts, `WP_DEBUG`/`WP_ENVIRONMENT_TYPE=local`, WordPress install | Lead | Site loads at gas-retailer.test, admin login works |
| Install and configure WooCommerce (KES currency, Kenya store address, offline payments only) | Domain | Shop, cart and checkout pages work; test order with COD |
| Git repo, `.gitignore`, composer/npm dev tooling, `phpcs.xml.dist` | Lead | `composer lint` and `composer test` run (empty suite OK) |
| Plugin skeleton: bootstrap, autoloader, WooCommerce dependency check, HPOS declaration, uninstall | Domain | Activates cleanly; shows a notice and stays inactive without WooCommerce; PHPCS clean |
| Theme skeleton: theme.json tokens placeholder, header/footer parts, index/front-page | Theme | Activates; passes Theme Check basics |
| Test bootstrap: WP test suite against a `gas_retailer_test` DB | QA | One sample integration test passes |

### Phase 3 — Product catalogue
Taxonomy `lpg_segment` (manufacturers = native `product_brand`, A6) → spec profiles CPT plus seeding (field definitions only) → product spec panel → attribute sync for filterable fields → compatibility, documents, warranty → enquiry-only rule → `spec-table`, `compatible-products`, `product-documents` blocks. Demo products are labelled **[DEMO]** and have no invented specs.

### Phase 4 — Storefront
Design system sign-off → header, nav, search → homepage patterns → segment landing pages → category/archive templates with WooCommerce filter blocks → single product template → responsive pass.

### Phase 5 — Business workflows
`lpg_cylinder_brand` taxonomy → WhatsApp MessageBuilder plus `enquiry-button` → `lpg_enquiry` log + REST → RFQ CPT/statuses/REST/form/email → exchange form → privacy exporter/eraser.

### Phase 6 — Administration
LPG settings tab, admin list columns and filters for RFQs and enquiries, RFQ status workflow, capability review, operator walkthrough doc.

### Phase 7 — QA
Execute docs/test-plan.md: functional, security review (Security agent), performance on a shared-hosting profile, accessibility (WCAG 2.2 AA spot checks), SEO, Playwright journeys.

### Phase 8 — Deployment
cPanel guide: DB, HTTPS, SMTP, backups, caching, production wp-config, deploy (Git or file copy of the two custom directories), rollback, update procedure, migration notes for VPS/AWS.

## Risks

| # | Risk | Mitigation |
|---|---|---|
| R1 | WordPress 7.1 is recent; WooCommerce and SEO plugin support must be confirmed | Check the "Tested up to" headers at install; pin versions in docs |
| R2 | Local MySQL 9.0 vs production MySQL 8.0/MariaDB | Avoid version-specific SQL (none planned); test once on MariaDB before Phase 8 |
| R3 | Shared hosting limits (memory, no object cache, WP-Cron depends on traffic) | No heavy work on page load; emails sent inline; document a real cron via cPanel |
| R4 | Unverified technical specs or claims entering the catalogue | Spec fields hold supplier data only; demo content is labelled |
| R5 | RFQ and enquiry personal data (Kenya DPA 2019) | Consent notice, privacy exporter/eraser, retention setting, private CPTs |
| R6 | Form spam on RFQ and exchange forms | Honeypot, rate limit, nonce; Akismet optional |
| R7 | Logging a WhatsApp enquiry is best-effort (the customer may not send the message) | Log as "initiated", not "sent"; document this for the operator |

## Known issues
- Phase 2 security review (lpg-security, 2026-11-01): no critical or high findings.
  - Fixed: F1, a fail-closed test DB guard in `tests/bootstrap.php`.
  - Fixed: F2, the debug log moved to `logs/` outside the web root.
  - Fixed: F3, ABSPATH guards added to the theme patterns.
  - Fixed: F5, the DB username removed from the docs.
  - F4: partly fixed. The test DB has its own test-only user (`gas_test`). The site DB still uses the shared user; switching is the owner's choice.
- Theme: viewport and visual checks, Site Editor block validation and Theme Check have not been run yet (Phase 4/7).
- `@wordpress/scripts` needs Node 22 or newer (local is 20.17). The install is deferred to Phase 3.
- No shipping zones yet, so checkout charges no delivery (Phase 5).
- Deployment checklist (Phase 8): `WP_DEBUG` off in production, logs outside `public_html`, never upload `debug.log`.

## Phase 2 report (2026-11-01)

**Agents:** Lead Architect (environment, WooCommerce, plugin skeleton, tooling, tests); lpg-theme (theme skeleton); lpg-security (review).

**Delivered:**
- **Git:** repo in the outer folder, pushed to origin/main. WordPress core, WooCommerce, uploads and secrets are ignored.
- **WordPress settings:** environment type `local`, debug log at `logs/`, 256M memory limit, Post name permalinks, Africa/Nairobi timezone, sample content removed.
- **WooCommerce 11.2.1:** HPOS on; KES with 0 decimals; Kenya/Nairobi; sells and ships to KE only; tax off; COD is the only payment method; onboarding skipped.
- **Tooling:** composer dev tooling (WPCS 3.4.1, PHPCompatibilityWP, PHPUnit 9.6, wp-phpunit 7.1.3), `phpcs.xml.dist`, `phpunit.xml.dist`.
- **Plugin `lpg-commerce-core` 0.1.0:** bootstrap, its own autoloader, Service contract, Plugin registry with the `lpg_services` filter and `lpg_loaded` action, Installer (settings and version stored without autoload), uninstall, WooCommerce dependency check, HPOS and cart/checkout blocks compatibility.
- **Theme `lpg-storefront` 0.1.0:** placeholder theme.json tokens, 6 templates, header and footer, 3 patterns.

**Tests executed:**

| Test | Result |
|---|---|
| PHPUnit | 5 tests, 9 assertions, all passed |
| Test DB guard | Refuses a site-DB config (exit 1) |
| PHPCS (plugin, theme, tests) | 0 errors and 0 warnings |
| Store API checkout | `[DEMO]` product, COD order reached "processing", stock went from 5 to 3 (test data deleted afterwards) |
| HTTP | Home page and /shop/ return 200; 404 page returns 404 |
| debug.log | Not reachable over HTTP |
| composer audit | Clean |

**Not tested:** theme viewport and visual checks, Site Editor block validation, Theme Check.

**Next phase:** Phase 3, the product catalogue. It starts with the `lpg_segment` taxonomy and spec profiles (manufacturers use `product_brand` per A6; `lpg_cylinder_brand` moved to Phase 5), which are lpg-domain work. Before Phase 3 the owner should install Node 22 or newer for the block build.

## Phase 3A summary (2026-11-01)
- **Built:** `lpg_segment`, spec profiles (CPT, field editor, category defaults), the product spec panel, attribute sync, two-way compatibility, PDF documents, warranty, enquiry-only, the structured-data specs, `wp lpg seed`, uninstall.
- **Rulings:** §11.12 C1–C12. The security review found 4 low findings (L1–L4), all fixed.
- **Tests:** `composer test` passes 81 tests (231 assertions). `composer lint` is clean. On the dev site, the seed is idempotent across 3 runs and the debug log is clean.
- **Not tested:** admin JS in a browser (row editor, media picker), a real uninstall run.

## Phase 3B report (2026-11-01)

**Agents:** lpg-domain (blocks), lpg-theme (single-product template, block CSS), lpg-security (review: M1/H1/SD1, fixed as C15), lpg-qa (rendered-block acceptance; browser run in progress), Lead (build setup, ESLint config, Registry repeat-registration fix, reviews), lpg-owner-proxy (gate conditions P-001/P-002).

**Delivered:**
- **Blocks:** `lpg/spec-table`, `lpg/compatible-products`, `lpg/product-documents`. They are dynamic, with PHP renderers that escape all output. They render nothing for password-protected, non-viewable or empty products.
- **Build:** `@wordpress/scripts` 36.1.0, with `build/blocks` committed (21 files, including the manifest and editor scripts). Node 22.23.4 via nvm.
- **Theme:** a `single-product` block template that places the blocks after the summary and around the product tabs, plus `lpg-blocks.css` (3.2 KB, theme tokens only).
- **Rulings:** C13–C16.

**Tests executed (fresh run 2026-11-01, after the build):**

| Check | Result |
|---|---|
| `composer test` | 104 tests, 369 assertions, all passed |
| `composer lint` | exit 0 |
| `npm run lint:js` | exit 0 |
| Rendered-page acceptance (test-plan §2.12, AB-01..11) | 9/9 areas passed: spec table position and units, documents, compatible cards and the Enquire label, empty product, password protection, draft returns 404, escaping on a rendered page, block CSS only where used, no PHP notices |
| Product page time to first byte | median 0.552 s |
| debug.log | No new lines (56; all older known entries) |

**Not tested / deferred** (owner proxy P-002):
- BLK-012 theme switch → Phase 7
- A real uninstall run → Phase 7
- DEF-002 / SEO-005 → Phase 4 SEO pass
- Browser matrix beyond Chrome → Phase 7
- Theme stylesheet loading on every page → Phase 4 performance

**Pending for the Phase 3 gate (P-001):**
- Browser checks: the editor (BLK-010), the admin JS (CAT-085), and the product page at 375/768/1280
- Mapping the CAT INT rows in the test plan to the PHPUnit tests that cover them

## Next tasks
- Close the Phase 3 gate (browser results → owner-proxy approval).
- Phase 4 kickoff per the owner-proxy direction (P-003..P-011 in docs/owner-decisions.md):
  - The design-system checkpoint first: tokens and contrast, plus the header, a product card and the spec table at 375 and 1280.
  - Templates start after the proxy's one-round reply.
