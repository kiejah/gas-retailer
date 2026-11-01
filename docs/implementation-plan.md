# Implementation Plan & Status Record

This is the Lead Architect's central record of completed and pending work, known issues, agent assignments and next tasks.
Architecture details are in [architecture.md](architecture.md).

## Current status

| Phase | Status |
|---|---|
| 1 Discovery & Architecture | **Complete** — approved by the owner 2026-11-01 (all decisions accepted) |
| 2 Foundation | In progress: WordPress installed and verified at http://gas-retailer.test (Herd link, salts regenerated, 2026-11-01). Ready to continue |
| 3–8 | Not started |

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
Taxonomies (segment, manufacturer, cylinder brand) → spec profiles CPT plus seeding (field definitions only) → product spec panel → attribute sync for filterable fields → compatibility, documents, warranty → enquiry-only rule → `spec-table`, `compatible-products`, `product-documents` blocks. Demo products are labelled **[DEMO]** and have no invented specs.

### Phase 4 — Storefront
Design system sign-off → header, nav, search → homepage patterns → segment landing pages → category/archive templates with WooCommerce filter blocks → single product template → responsive pass.

### Phase 5 — Business workflows
WhatsApp MessageBuilder plus `enquiry-button` → `lpg_enquiry` log + REST → RFQ CPT/statuses/REST/form/email → exchange form → privacy exporter/eraser.

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
- None yet (no code written).

## Next tasks
- Await review of Phase 1 and decisions on the pre-requisites above.
