# Architecture — Kenya LPG Commerce Platform

Status: **Phase 1 approved by the owner on 2026-11-01.** All decisions A1–A5 and D1–D6 are accepted as proposed.
Any change to a decision needs the owner's approval and a new row in the decision log (§10).

---

## 1. Environment findings (2026-11-01)

| Item | Found | Notes |
|---|---|---|
| WordPress core | 7.1.4, files present in `gas-retailer/` | **Not installed** — database has 0 tables |
| Database | MySQL 9.0.1 local, `gas_retailer_db` exists, reachable with wp-config credentials | Production target is MySQL 8.0 / MariaDB — see risk R2 |
| PHP | 8.3.16 (Herd), extensions incl. mysqli, gd, imagick, intl, zip, OPcache | Matches the PHP 8.3 hosting target |
| WooCommerce | Not installed | Install latest stable that declares WP 7.1 support |
| Plugins present | Akismet, Hello Dolly (defaults) | Remove Hello Dolly; Akismet only if needed for spam |
| Themes present | twentytwentythree/four/five | Keep one default theme as a fallback |
| Local web server | Laravel Herd 1.11.1 | Herd *parks* `gas-retailer/gas-retailer/`, so it serves its sub-folders, not WordPress. `gas-retailer.test` returns 404. Needs `herd link` instead |
| WP-CLI | Not installed | Needed for scripted setup and tests (dev-only) |
| Composer 2.7.7, Node 20.17, npm 10.9 | Present | Dev tooling only, never runtime dependencies |
| Docker | Installed, daemon not running | Not needed if Herd is used |
| Git | 2.37.1; **no repository yet** | Created in Phase 2 |
| wp-config.php | DB settings filled in, `WP_DEBUG` false | **All 8 auth keys/salts are still placeholders**; the DB user is shared with another project |

---

## 2. Repository and directory layout

The Git repo sits in the **outer** folder. WordPress core is in the web root and is not tracked. Only our code is tracked.

```
gas-retailer/                      ← Git repo root (not web-served)
├── CLAUDE.md
├── docs/                          ← architecture, plan, test plan, deployment
├── composer.json                  ← dev tools only: PHPCS + WPCS, PHPUnit, polyfills
├── phpcs.xml.dist
├── package.json                   ← @wordpress/scripts for block/editor builds
├── tests/                         ← PHPUnit (integration) + e2e (later)
└── gas-retailer/                  ← WordPress web root (ABSPATH)
    ├── wp-config.php              ← untracked; secrets stay out of Git
    ├── wp-admin/, wp-includes/    ← untracked (core)
    └── wp-content/
        ├── plugins/
        │   ├── woocommerce/                 ← untracked (third-party)
        │   └── lpg-commerce-core/           ← TRACKED: all business logic
        └── themes/
            └── lpg-storefront/              ← TRACKED: presentation only
```

`.gitignore` ignores everything under `gas-retailer/` except the two custom directories. Uploads are never committed.
Built assets (`build/`) **are** committed, because cPanel deployments have no Node.

---

## 3. Layer boundaries

| Concern | Owner |
|---|---|
| Products, categories, variations, cart, checkout, orders, stock, customer emails, accounts | **WooCommerce (native)** |
| Segments, spec profiles and values, compatibility, enquiry-only rule, RFQs, enquiry logging, WhatsApp link generation, LPG settings, REST endpoints, **LPG blocks** | **lpg-commerce-core plugin** |
| theme.json design tokens, templates, template parts, patterns, layout, styling | **lpg-storefront theme** |

**Key rule:** the plugin renders every LPG-specific UI as **dynamic blocks**: spec table, compatible products, enquiry button, RFQ form and exchange form. The theme only *places* these blocks in templates and patterns. If the theme is switched, the data stays and the blocks still work in any block theme.

---

## 4. Product data model

### 4.1 Classification — D1 (accepted)

Proposal: use two independent axes so no product is duplicated.

- **`product_cat`** (native, hierarchical) = *what it is*: Cylinders, Cookers & Ovens, Burners, Regulators, Hoses & Fittings, Manifolds & Changeover, Valves & Piping, Gauges & Monitoring, Safety Equipment, LPG Accessories, Refills & Exchange, … with subcategories.
- **`lpg_segment`** (custom taxonomy on `product`, non-hierarchical, public, has archives) = *who it is for*: Household, Commercial, Industrial. A product can have several segments. For example, a regulator can be tagged both Household and Commercial.

Navigation items "Household / Commercial / Industrial" go to segment landing pages: a block-editable page plus a segment archive. "LPG Accessories", "Safety Equipment" and "Refills & Exchange" go to category archives.
*Alternative:* make the three segments top-level `product_cat` terms, as the brief suggested. That's simpler, but it forces products to be duplicated or assigned to several categories, and it mixes "type" with "audience". The faceted filtering also gets worse.

### 4.2 Category-specific specifications — D2 (accepted)

- A **spec profile** (Regulator, Burner, Gas Cooker, Cylinder, …) is a list of field definitions: `key`, `label`, `type` (text, number, select, boolean), `unit`, `options`, `filterable`.
- Profiles are stored in a non-public CPT `lpg_spec_profile` that admins can edit. The four profiles from the brief are seeded as *field definitions only*, with no values.
- Each `product_cat` term can have a default profile (term meta `lpg_spec_profile_id`). A product can override it (`_lpg_spec_profile_id`).
- Values are stored **per product in post meta** `_lpg_spec_{key}`, not autoloaded, and read in one `get_post_meta()` call.
- Fields marked `filterable` are **also written to a WooCommerce global attribute** (`pa_{key}`, not used for variations). Storefront filtering then uses native WooCommerce product filter blocks and layered navigation, with no custom query code.
- Admin UI: one "LPG Specifications" tab in the WooCommerce product data panel. It renders the fields of the product's profile.

*Alternative:* define profiles in PHP config only. That's simpler, but adding a new equipment type would need a developer.

### 4.3 Relationships

| Relationship | Storage |
|---|---|
| Recommended products | Native **Upsells** (`_upsell_ids`) |
| Related accessories | Native **Cross-sells** (`_crosssell_ids`) — also shown on the cart |
| Compatible products | `_lpg_compatible_ids` (array of product IDs). Optional two-way sync on save |
| Equipment packages / bundles | Native **Grouped product** for v1. D3 (accepted): paid WooCommerce Product Bundles only if a single bundle price or stock is needed |

No custom tables. The catalogue is expected to have hundreds to low thousands of products, which is well within postmeta's limits.

### 4.4 Documents and warranty

- `_lpg_documents`: a list of `{attachment_id, type: datasheet|manual|certificate, label}`. Files go into the media library, uploaded by users with `upload_files`, and are restricted to PDF.
- `_lpg_manufacturer`: term in the `lpg_manufacturer` taxonomy (gives brand archives and filtering).
- `_lpg_warranty`: free text, entered from supplier information only.

### 4.5 Enquiry-only products

- `_lpg_enquiry_only` (yes/no). If it is "yes", or the price is empty, a `woocommerce_is_purchasable` filter returns false. The add-to-cart button is replaced with the **Enquire on WhatsApp** block.
- Nothing about the price is changed in the database.

---

## 5. Business workflows (plugin)

| Workflow | Design |
|---|---|
| **WhatsApp product enquiry** | The enquiry block builds a `https://wa.me/<number>?text=` link from a message template (product name, SKU, URL). The customer can edit the text in a textarea before opening WhatsApp. The click is logged to `lpg_enquiry` through REST (see below). WhatsApp opens even if logging fails |
| **RFQ (commercial/industrial)** | Form block → `POST /wp-json/lpg/v1/rfq` (REST nonce, honeypot, per-IP rate limit with transients, server-side validation) → creates a private `lpg_rfq` post. Custom statuses: `lpg-new`, `lpg-review`, `lpg-quoted`, `lpg-closed`. Fields in meta: contact details, organisation, segment, line items `[{product_id?, description, qty}]`, technical requirements, delivery location. Admin email through `wp_mail` |
| **Cylinder exchange / refill** | Form block: brand (`lpg_cylinder_brand` taxonomy) → size (term meta list) → exchange or new → location → logged as `lpg_enquiry` (type `exchange`) → opens WhatsApp with a pre-filled message. No delivery management |
| **Enquiry log** | Private CPT `lpg_enquiry` (types: product, exchange). Gives admins the required "review enquiries" screens with list-table filters |
| **Standard purchase** | Pure WooCommerce. During development, only Cash on Delivery / BACS (offline) gateways |
| **M-Pesa** | D4 (accepted): a separate `lpg-mpesa` gateway plugin later, using Daraja sandbox first, because gateways are replaceable integrations. Not in scope until it's deliberately scheduled |

**Access control:** custom capabilities `edit_lpg_rfqs`, `read_lpg_rfq`, … (`capability_type` `lpg_rfq` and `lpg_enquiry`, `map_meta_cap`). They are granted to `administrator` and `shop_manager` on activation and removed on uninstall. CPTs use `public => false`, `show_in_rest => false`, so no data is exposed through the core REST API.

**Personal data:** RFQs and enquiries hold names, phone numbers and locations. Forms show a consent notice, and the data is registered with WordPress's privacy exporter and eraser (Kenya Data Protection Act 2019 — see risk R5).

---

## 6. Plugin specification — `lpg-commerce-core`

- PHP namespace `LPG\Commerce`. Prefix `lpg_` for hooks, options, meta (`_lpg_` for hidden meta) and handles. Text domain `lpg-commerce-core`.
- Requires PHP 8.1+, WP 6.x+ and WooCommerce (checked on load; shows an admin notice and stays inactive if WooCommerce is missing). Declares HPOS compatibility.
- A small PSR-4 autoloader is included in the plugin. **There is no runtime Composer dependency**, so deployment is a plain file copy.

```
lpg-commerce-core/
├── lpg-commerce-core.php        bootstrap, constants, dependency check
├── uninstall.php                removes options and caps; keeps business data unless the "delete data" setting is on
├── src/
│   ├── Plugin.php               service registration (one place, explicit)
│   ├── Taxonomies/              Segment, Manufacturer, CylinderBrand
│   ├── Specs/                   ProfilePostType, ProfileRepository, ProductSpecsPanel, AttributeSync
│   ├── Relations/               Compatibility, Documents
│   ├── Commerce/                EnquiryOnly (purchasable filter)
│   ├── Enquiries/               RfqPostType, EnquiryPostType, Statuses, AdminColumns, Notifications
│   ├── Rest/                    RfqController, EnquiryController (namespace lpg/v1)
│   ├── WhatsApp/                MessageBuilder (pure, unit-testable)
│   ├── Settings/                WooCommerce settings tab "LPG": WhatsApp number, templates, recipients
│   └── Privacy/                 exporter/eraser
├── blocks/                      block.json + render.php per block (dynamic, server-rendered)
│   ├── spec-table/  compatible-products/  product-documents/
│   ├── enquiry-button/  rfq-form/  exchange-form/
├── assets/src → build/          @wordpress/scripts; front-end JS kept minimal
└── languages/
```

Settings live in one option array, `lpg_settings`, with `autoload = false` except the WhatsApp number.

---

## 7. Theme specification — `lpg-storefront`

- **Block theme** (Full Site Editing). `theme.json` holds the whole design system: colour palette, type scale, spacing scale, radii, shadows, layout widths and button/form element styles.
- Templates: `front-page`, `page`, `index`, `404`, `search`, plus WooCommerce block templates (`single-product`, `archive-product`, `taxonomy-product_cat`, `taxonomy-lpg_segment`, `product-search-results`). Cart and checkout use WooCommerce's own blocks.
- Template parts: `header` (logo, mega-nav, product search, WhatsApp CTA), `footer` (business info, safety notice, contact).
- Patterns: hero, segment tiles, featured/new/popular product grids (WooCommerce Product Collection block), safety strip, delivery info, trust strip, RFQ CTA.
- The theme has **no PHP business logic**. `functions.php` only does asset enqueueing, theme support and pattern categories.
- Performance: system font stack or 1 self-hosted variable font, no jQuery dependency in the theme, CSS from theme.json plus one small stylesheet, and lazy images through core.
- Visual direction: an industrial/technical supplier look, not a delivery app. The design system is produced as a Phase 4 kickoff deliverable for review.

---

## 8. SEO

Rely on WordPress/WooCommerce core output: canonicals, WooCommerce product JSON-LD and the core sitemaps. D5 (accepted): add one SEO plugin (e.g. Yoast or Rank Math) for meta titles, descriptions and Open Graph. The plugin then adds only spec-based `additionalProperty` entries to WooCommerce's product structured data through `woocommerce_structured_data_product`.

---

## 9. Coding standards and tooling

- WordPress Coding Standards (WPCS 3.x) through PHPCS, plus PHPCompatibilityWP targeting PHP 8.1+.
- Escape late, sanitize early. Nonces and capability checks on every write. `$wpdb->prepare` for any direct SQL (none expected).
- PHPUnit: pure unit tests (MessageBuilder, validation) plus integration tests against the WordPress test suite on a separate local test database.
- E2E (Phase 7): Playwright for checkout, RFQ and exchange journeys (D6, accepted).

---

## 10. Decision log

| # | Decision | Status |
|---|---|---|
| A1 | Git repo is the outer folder; WordPress core, WooCommerce and uploads are untracked | Accepted 2026-11-01 |
| A2 | All LPG UI is delivered as plugin dynamic blocks; the theme only composes them | Accepted 2026-11-01 |
| A3 | No custom DB tables; CPTs, taxonomies and meta only | Accepted 2026-11-01 |
| A4 | Native upsells and cross-sells are reused for recommended products and accessories | Accepted 2026-11-01 |
| A5 | Plugin has no runtime Composer dependency | Accepted 2026-11-01 |
| D1 | Two-axis classification (`product_cat` + `lpg_segment`) | Accepted 2026-11-01 |
| D2 | Admin-editable spec profiles, with filterable fields mirrored to `pa_` attributes | Accepted 2026-11-01 |
| D3 | Grouped products for packages (no paid Bundles extension) | Accepted 2026-11-01 |
| D4 | M-Pesa as a separate plugin, scheduled later | Accepted 2026-11-01 |
| D5 | Exactly one SEO plugin (Yoast or Rank Math). Pick it in Phase 4 after checking WP 7.1 support; LPG code only extends WooCommerce product structured data | Accepted 2026-11-01 |
| D6 | Test stack: PHPUnit + WP test suite now, Playwright in Phase 7 | Accepted 2026-11-01 |
