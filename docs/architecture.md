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
| A6 | Manufacturers use WooCommerce's native `product_brand` taxonomy instead of `lpg_manufacturer` (see §11.1) | Accepted by Lead 2026-11-01 |

---

## 11. Phase 3 interface contract (Lead Architect, 2026-11-01)

These names and data formats are binding for Phase 3. Changing them needs Lead approval and an update to this section.

### 11.1 Adjustment to §4.4: manufacturers use WooCommerce Brands (A6)

WooCommerce 11.2 ships a native **`product_brand`** taxonomy (hierarchical, REST-enabled, `/brand/` archives, built-in filter block). Reusing it follows the "don't recreate WooCommerce functionality" rule, so there is **no `lpg_manufacturer` taxonomy**. `lpg_cylinder_brand` (exchange-form options) moves to Phase 5, where it is used.

### 11.2 Taxonomy

| Slug | Object | Settings | Seeded terms |
|---|---|---|---|
| `lpg_segment` | `product` | non-hierarchical, public, `show_in_rest`, `show_admin_column`, rewrite `segment`, caps = WooCommerce `manage_product_terms`/`edit_product_terms`/`delete_product_terms`/`assign_product_terms` | `household` "Household", `commercial` "Commercial", `industrial` "Industrial". Created on install if missing; never overwritten |

### 11.3 Spec profiles

- **CPT `lpg_spec_profile`:** `public` false, `show_ui` true, menu under Products, `supports` title only, `show_in_rest` false, `capability_type` `lpg_spec_profile` with `map_meta_cap` true. The primitive caps are granted to `administrator` and `shop_manager` on install and removed on uninstall.
- **Field definitions:** post meta **`_lpg_fields`**, an ordered list of:
  ```
  { key: string    /^[a-z][a-z0-9_]{0,27}$/  (≤28 chars: also the pa_ attribute name)
    label: string
    type: "text" | "number" | "select" | "boolean"
    unit: string            (optional, e.g. "mbar", "kg", "kW")
    options: string[]       (select only)
    filterable: bool        (mirrored to a WooCommerce global attribute)
    help: string }          (optional admin hint)
  ```
  Keys are unique within a profile. The same key in different profiles means the same thing and shares one `pa_{key}` attribute.
- **Category default:** `product_cat` term meta **`lpg_spec_profile_id`** (int).
- **Product override:** post meta **`_lpg_spec_profile_id`** (int; 0 or empty = inherit).
- **Resolution order:**
  1. The product override.
  2. Otherwise, the product's categories ordered by depth (deepest first), then `term_id` ascending. For each category, walk up its ancestors; the first term with a profile wins.
  3. Otherwise, no profile.
- **Values:** post meta **`_lpg_spec_{key}`**, stored on the parent product only (v1: no per-variation specs). Stored form:

  | Type | Stored as |
  |---|---|
  | text | `sanitize_text_field` |
  | number | numeric string (`.` decimal) |
  | select | must be one of the field's `options` |
  | boolean | `yes` / `no` |

  Empty means not set; it is never displayed and the meta is deleted.
- **Filterable sync:** when a profile is saved, a missing global attribute `pa_{key}` is created (`wc_create_attribute`, type select, label = field label). When a product is saved, each filterable field with a value is set as product attribute `pa_{key}`. The term name is the display value (e.g. `37 mbar`, `Yes`), `is_visible` false, `is_variation` false. Other attributes on the product are left untouched. Clearing a value removes the attribute.
- **Seed (`wp lpg seed`):** a WP-CLI command, never automatic. Idempotent. It creates:
  - The four profiles from the brief (Regulator, Burner, Gas Cooker, Cylinder), with **field definitions only and no values**.
  - The starter `product_cat` tree from §4.1, with default profiles assigned (Regulators → Regulator, Burners → Burner, Cookers & Ovens → Gas Cooker, Cylinders → Cylinder).

### 11.4 Other product meta

| Meta key | Type | Admin UI |
|---|---|---|
| `_lpg_compatible_ids` | int[] product IDs | "Linked Products" tab, WooCommerce product search select. **Two-way:** adding or removing B on A mirrors onto B. Filter `lpg_compatibility_bidirectional` (default true) |
| `_lpg_documents` | list of `{attachment_id:int, type:"datasheet"\|"manual"\|"certificate"\|"other", label:string}` | Meta box with media picker. Only `application/pdf` attachments are accepted on save; requires `upload_files` to add |
| `_lpg_warranty` | string (`sanitize_textarea_field`) | General tab |
| `_lpg_enquiry_only` | `yes` / `no` | General tab checkbox |

Recommended products = native upsells. Accessories = native cross-sells. Manufacturer = `product_brand`.

### 11.5 Enquiry-only rule

`woocommerce_is_purchasable` returns false when `_lpg_enquiry_only` is `yes` or the price is empty. Filter **`lpg_is_enquiry_only`** `( bool $enquiry_only, WC_Product $product )`. The price stored in the database is never changed.

### 11.6 Public PHP API (used by plugin blocks; the theme must not call it)

```php
LPG\Commerce\Specs\ProductSpecs::get( int $product_id ): array
// → list of [ 'key', 'label', 'type', 'unit', 'value' (stored), 'display' (formatted, unescaped) ]
//   only fields that have a value, in profile order.
LPG\Commerce\Specs\ProfileResolver::for_product( int $product_id ): ?int   // profile post ID
LPG\Commerce\Commerce\EnquiryOnly::is_enquiry_only( WC_Product $product ): bool
LPG\Commerce\Relations\Compatibility::get_ids( int $product_id ): int[]   // published, visible only
LPG\Commerce\Relations\Documents::get( int $product_id ): array          // valid PDF attachments only
```

### 11.7 Services (registered in `Plugin::SERVICES`, Lead-owned)

`Taxonomies\Segment`, `Specs\ProfilePostType`, `Specs\ProductSpecsPanel`, `Specs\AttributeSync`, `Relations\Compatibility`, `Relations\Documents`, `Commerce\EnquiryOnly`, `Commerce\ProductFields` (warranty and enquiry-only fields), `Seo\StructuredData`, `Blocks\Registry`. WP-CLI: `Cli\SeedCommand`, registered only when `WP_CLI` is defined.

### 11.8 Structured data

`Seo\StructuredData` hooks `woocommerce_structured_data_product` and adds one `additionalProperty` `PropertyValue` per spec (`name`, `value`, `unitText`). It adds nothing else.

### 11.9 Blocks (Phase 3B, after Node ≥ 22.22.2 is available)

These are dynamic blocks built with `@wordpress/scripts`. Source is in `blocks/<name>/` (block.json, index.js, render.php) and output in `build/blocks/<name>/`, registered through the metadata collection manifest. Each block uses `usesContext: ["postId"]`, and the editor preview uses ServerSideRender.

| Block | Attributes | Renders |
|---|---|---|
| `lpg/spec-table` | `heading` (string, default "Technical specifications") | `<table>` of `ProductSpecs::get()`. Outputs nothing if there are no specs |
| `lpg/compatible-products` | `heading`, `columns` 2–6 (default 4), `limit` 1–12 (default 8) | Product cards (image, name, price/"Enquire") |
| `lpg/product-documents` | `heading` | A list of PDF links with type and file size |

CSS class prefix: `lpg-` (e.g. `.lpg-spec-table`). The theme styles these classes and never changes their markup.

### 11.10 Uninstall

When `delete_data_on_uninstall` is on, uninstall deletes:
- `lpg_spec_profile` posts
- all `_lpg_*` post meta
- `lpg_spec_profile_id` term meta
- `lpg_segment` terms

Global `pa_*` attributes are kept, because WooCommerce owns them.

### 11.12 Clarifications (Lead, 2026-11-01, answering QA's open questions)

| # | Question | Ruling |
|---|---|---|
| C1 | Profile save with some invalid field rows | Invalid rows are dropped and valid rows are saved. An admin notice lists each rejected row and the reason |
| C2 | Product override points at a missing, trashed or wrong-type post | The override is ignored and resolution falls back to the category chain |
| C3 | Boolean "not set" vs "No" | The boolean UI is a select (— / Yes / No), so empty means not set and the meta is deleted. `no` is stored, displayed as "No" and synced as the `pa_` term "No" |
| C4 | Number input | `2,5` is normalised to `2.5`. Negative values are allowed. Exponents, thousands separators and any non-numeric text are rejected (value not saved, admin notice) |
| C5 | Orphaned values after a profile or field change | `_lpg_spec_*` meta is **kept** but not displayed (only fields of the current profile show). `pa_` attributes added by LPG are tracked in product meta **`_lpg_synced_attributes`** (string[] of taxonomy names). On save, tracked attributes that are no longer a filterable field with a value are removed. Untracked attributes are never touched |
| C6 | Price `0` | Not empty: `0` is a valid price. Only `''` counts as empty |
| C7 | Variable products | `_lpg_enquiry_only` on the parent applies to all its variations (also filter `woocommerce_variation_is_purchasable`). Empty variation prices are already non-purchasable in WooCommerce natively |
| C8 | Seed behaviour | Creates missing items only. Never overwrites existing profiles, field definitions or category assignments |
| C9 | Seeded field reference list | The brief's lists (Part 2.3), reproduced in the Phase 3A task: Regulator, Burner, Gas Cooker and Cylinder fields as named there. Brand and manufacturer come from `product_brand` |
| C10 | Field label change | Does **not** rename an existing `pa_` attribute, which may be shared across profiles. Admins rename it under Products → Attributes |
| C11 | Warranty in two places (Gas Cooker spec field and the `_lpg_warranty` General-tab field) | Single source: `_lpg_warranty`. The `warranty` spec field is removed from the seed and from the dev site's Gas Cooker profile |
| C12 | Phase 3A security review (L1–L4) | All fixed in 3A: compatibility only writes onto products the user can edit (L1); reserved field keys, starting with `profile_id` (L2); numbers capped at 20 significant digits, with a JSON-LD fallback (L3); spec-profile caps granted only on activation or first install (L4). `Documents::get()` also skips `woocommerce_uploads/` files, and a deleted product is removed from other products' compatibility lists |

**Phase 3B block output rules (from the security review):** WooCommerce REST `meta_data` can write `_lpg_*` keys without our sanitising, so blocks must escape everything on output: `esc_html` for labels, warranty and spec display, `esc_url` for document links, `esc_attr` for attributes. Never echo meta raw.

**§11.9 additions (Lead, 2026-11-01, from the Phase 3B build):**
- **Heading defaults:** "Technical specifications", "Compatible products", "Product documents". They are translated in PHP. An empty heading hides the `<h2>`.
- **Stable CSS classes for the theme:**
  - `lpg-spec-table__{heading,table,row,label,value}`, plus `__row--{key}`
  - `lpg-compatible-products__{heading,list,item,link,image,name,price}`, plus `__price--enquire` and the inline `--lpg-columns`
  - `lpg-product-documents__{heading,list,item,link,label,meta}`, plus `__item--{type}`
- **Attribute ranges:** enforced by the PHP clamp and the editor controls, not by block.json `minimum`/`maximum`, because those would make the block-renderer REST endpoint return 400.
| C13 | DEF-001: a document row with an unknown type | **Normalise to `other`** (current behaviour is accepted). The admin UI only offers valid types, so an unknown type can only come from crafted or REST input, and keeping the PDF is more useful than dropping it |
| C14 | DEF-002: `&` appears as `&amp;` inside the product JSON-LD | This comes from WooCommerce core's encoding, not our code. Accepted for now; reviewed in the Phase 4 SEO pass (check how Google's Rich Results Test reads it) |
| C15 | Phase 3B security review | M1: blocks render nothing for password-protected products until the password is entered. H1: blocks render only `is_viewable()` products. SD1: no spec structured data for password-protected products. Note: the theme stylesheet loads on every page; trimming that belongs to the Phase 4 performance pass |
| C16 | Q12: password-protected products as compatible-product cards on *other* pages | **Keep showing them** (name, image, price, link), matching WooCommerce's own related, upsell and product grids. The card reveals nothing behind the password, and the link leads to the password form |

## 12. Phase 4 interface contract (Lead, 2026-11-01)

### 12.1 Enquiry label in product listings (owner proxy P-015)

For products where `EnquiryOnly::is_enquiry_only()` is true, the plugin (new service `Commerce\EnquiryDisplay`) changes **front-end display only**:
- **Price:** the price HTML is replaced by `<span class="lpg-enquire-label">Enquire</span>` in WooCommerce loops (block product template `woocommerce/product-price` and classic loops) **and** on the single product page. Admin screens are unchanged and the stored price is never modified.
- **Card button:** the add-to-cart / "Read more" button (block `woocommerce/product-button`, classic `woocommerce_loop_add_to_cart_link`) becomes a plain link to the product, `<a class="lpg-enquire-link wp-element-button is-style-outline" href="{permalink}">View details</a>`. The real enquiry/WhatsApp button arrives in Phase 5.
- **Not changed, and recorded:** Store API and REST prices, and product JSON-LD `offers`. The JSON-LD keeps the stored price, if any (owner question W-012).
- **Classes:** `lpg-enquire-label` and `lpg-enquire-link`. The theme styles them; the existing `lpg-compatible-products__price--enquire` stays.

### 12.2 Navigation
- "Request a quote" is shown **only if a published page with slug `request-a-quote` exists** (resolved at render time), per P-013 #1. No placeholder page is created; Phase 5 creates the page.
- Submenus open on tap, per P-014.

### 12.3 SEO plugin (D5 / P-010): Yoast SEO free 28.8 (Lead, 2026-11-01)
- **Why Yoast:** both Yoast and Rank Math free are tested to WP 7.1.4. Yoast **leaves WooCommerce's product JSON-LD in place**, so our spec `additionalProperty` (§11.8) keeps working; this was checked on a rendered product with 1 property and offers present alongside Yoast's own graph. Rank Math's free WooCommerce module replaces WooCommerce's schema and would drop it.
- **Checked on the dev site:** with indexing temporarily on, canonical is correct on home, shop, segment and category. While `blog_public=0`, pages are `noindex` and Yoast omits the canonical, as expected.
- **Cost:** shop time to first byte went from 0.28 s to 0.37 s locally, still under the 0.55 s baseline. Recorded for the Phase 4 performance pass.
- No paid add-ons. Tracking is off. Meta-description templates are set later with real content (owner content, W-series).
- **The plugin is not tracked in Git** (A1): deployment installs it. It is listed in the Phase 8 checklist.

### 12.4 Warranty block (P-009 #4)
- **Block:** `lpg/product-warranty`, dynamic, same rules as §11.9 (`Markup::product()`; renders '' for a password-protected, non-viewable or empty product).
- **Markup:** `<p class="lpg-product-warranty"><span class="lpg-product-warranty__label">Warranty:</span> <span class="lpg-product-warranty__value">{esc_html(_lpg_warranty), line breaks preserved}</span></p>`.
- **Attribute:** `label` (string, default "Warranty:").
- **Placement:** the theme puts it in the single-product summary, after the product meta.
- Empty `label` hides the label span (same as an empty heading hides the `<h2>`). Confirmed by Lead.
| C17 | The theme removes WooCommerce's classic `woocommerce_upsell_display` hook during block-template rendering (via `woocommerce_blocks_hook_compatibility_additional_data`) to stop a duplicate upsells list | **Accepted in the theme.** It is presentation (which markup renders) and changes no data or behaviour. Recorded as the one documented exception to the "functions.php = enqueue/supports/patterns" rule |

## 13. Phase 5 interface contract (Lead, 2026-11-01): business workflows

Builds on §5. Owner inputs are recorded in docs/owner-decisions.md (W-002/W-003, cylinder options, notification emails, W-007). **No personal contact data goes in the repo.** It lives in `lpg_settings` and the client edits it in the dashboard.

### 13.1 Settings: `lpg_settings` (one option, `autoload` = no)

| Key | Type | Notes |
|---|---|---|
| `whatsapp_number` | digits, international format (`2547XXXXXXXX`) | Set on dev. Validation: `/^[1-9]\d{7,14}$/` |
| `contact_phone` | same format | Set on dev (same number) |
| `notify_sales_email` | email | **Primary** recipient (To). Set on dev |
| `notify_owner_email` | email | Secondary (Cc). Placeholder. Set on dev |
| `shop_address` | text (multi-line) | Placeholder `[Shop address, Nairobi]` |
| `opening_hours` | text (multi-line) | Placeholder `[Opening hours]` |
| `wa_template_product` | text | Default: "Hello, I'm interested in {product} ({sku}). {url}" |
| `wa_template_exchange` | text | Default: "Hello, I'd like a cylinder {type}: {brand} {size}. Delivery: {location}." |
| `privacy_policy_version` | string | Saved with each RFQ consent |
| `enquiry_retention_months` | int, 0 = off | Default 24 (W-007 ⚖, pending client) |
| `delete_data_on_uninstall` | bool | Existing |

- **Admin UI:** a **WooCommerce → Settings → "LPG" tab** (WC Settings API), with capability `manage_woocommerce`.
- **Getter:** `LPG\Commerce\Settings\Settings::get( string $key ): mixed` (defaults applied).

### 13.2 Cylinder brands
- **Taxonomy `lpg_cylinder_brand`:** not attached to products (`object_type` = `[]`); `public` false, `show_ui` true, menu under Products, caps = WooCommerce `manage_product_terms` etc.
- **Sizes:** term meta `lpg_sizes`, a string[] such as `["6kg","13kg"]`, edited on the term screen (one per line).
- **Seed** (`wp lpg seed`, create-missing only): K-Gas, Total, Pro Gas, each with 6kg and 13kg.
- **Read API:** `CylinderBrands::all(): array` → `[ [ 'id', 'name', 'slug', 'sizes' => string[] ] ]`.

### 13.3 Records (private CPTs, custom caps per §5)

| CPT | Purpose | Statuses |
|---|---|---|
| `lpg_rfq` | Quote requests | `lpg-new`, `lpg-review`, `lpg-quoted`, `lpg-closed` |
| `lpg_enquiry` | Logged WhatsApp product and exchange enquiries (**"initiated", not "sent"**, R7) | `lpg-new`, `lpg-handled` |

- **Caps:** `capability_type` = `lpg_rfq` / `lpg_enquiry`, `map_meta_cap` true. Granted to administrator and shop_manager on activation only (as L4), removed on uninstall.
- **CPT settings:** `public`, `publicly_queryable` and `show_in_rest` all **false**, and `exclude_from_search` true.
- **RFQ meta:** `_lpg_contact` {name, organisation, phone, email}, `_lpg_segment` (term slug), `_lpg_items` [{product_id?:int, description:string, qty:int}], `_lpg_requirements`, `_lpg_project`, `_lpg_location`, `_lpg_comments`, `_lpg_consent` {at: ISO8601, policy_version}.
- **Enquiry meta:** `_lpg_type` (product|exchange), `_lpg_product_id`, `_lpg_brand`, `_lpg_size`, `_lpg_exchange_type` (exchange|new), `_lpg_location`.
- **Admin:**
  - List tables with columns (date, name/organisation, segment or type, status) and filters by status and segment.
  - Read-only detail meta boxes, plus a status change.
  - Phone and email shown as `tel:` / `mailto:` links, and a "Reply on WhatsApp" link.

### 13.4 REST (namespace `lpg/v1`)

| Route | Method | Auth | Body | Result |
|---|---|---|---|---|
| `/rfq` | POST | public, nonce `X-WP-Nonce` (`wp_rest`) | the §13.3 fields + `consent: true` + `hp` (honeypot, must be empty) | 201 `{ id, message }` |
| `/enquiry` | POST | public, nonce | `type`, product_id or brand/size/exchange_type, location | 201 `{ id, whatsapp_url }` |

- **Validation:** required fields and lengths; email format; KE phone; qty 1–10 000; `product_id` must be a published product; brand and size must exist in `CylinderBrands::all()`; consent required for RFQ.
- **Rate limit:** per IP **and** per route, 5 per 10 minutes (transients) → 429.
- **Honeypot:** a filled honeypot returns a fake 201 and stores nothing.
- **Errors:** WP_Error with `rest_invalid_param`; field-level messages are translatable.

### 13.5 WhatsApp
- **Builder:** `WhatsApp\MessageBuilder` is pure (no WordPress calls beyond formatting) and unit-testable. `link( string $number, string $text ): string` → `https://wa.me/{digits}?text={rawurlencode}`.
- **Placeholders:** `{product}`, `{sku}`, `{url}`, `{brand}`, `{size}`, `{type}`, `{location}`. Unknown placeholders are removed, and the result is trimmed to 1 000 characters.
- **No number configured:** the WhatsApp UI hides itself (graceful degradation).

### 13.6 Blocks (dynamic, built, `lpg-` classes, escape everything, §11.9 rules)

| Block | Where | Behaviour |
|---|---|---|
| `lpg/enquiry-button` | Single product (theme places it next to price/add-to-cart) | Shows for enquiry-only products (and optionally all products, via attribute `showFor`: enquiry-only\|all). It opens an inline, editable textarea pre-filled from `wa_template_product`. "Open WhatsApp" POSTs `/enquiry` (best effort), then navigates to `whatsapp_url`. No account needed |
| `lpg/rfq-form` | The `request-a-quote` page | The fields from §13.3, with multiple item rows (add/remove), segment select (commercial/industrial/household), consent checkbox, honeypot. Uses the REST nonce; shows success and error states inline. Progressive enhancement: without JS it shows the contact number and email instead |
| `lpg/exchange-form` | A "Refills & Exchange" page/section | Brand → size (filtered by brand) → exchange/new → location → "Continue on WhatsApp" (POST `/enquiry`, then redirect) |
| `lpg/whatsapp-link` | Header (P-017) and footer | A "WhatsApp us" link plus the tel: link; renders nothing if no number is set |
| `lpg/business-info` | Footer and contact page | Address, hours and phone from settings, with placeholders shown as-is |

- **Front-end JS:** small `viewScript` modules per block, vanilla JS (wp-scripts), no jQuery.
- **Performance:** loaded only where the block renders.

### 13.7 Notifications
- **On a new RFQ:** `wp_mail` To `notify_sales_email`, Cc `notify_owner_email`, `Reply-To` = the customer's email.
- **Content:** a plain-text summary plus a link to the admin record. **No raw HTML from user input.**
- **New exchange or product enquiry:** an optional digest. Phase 5 sends an immediate short email for exchange requests only (they are time-sensitive); product enquiries are logged only.

### 13.8 Privacy (W-007, draft pending client approval)
- **Exporters and erasers:** WordPress personal-data exporters/erasers for `lpg_rfq` and `lpg_enquiry`, matched by email or phone.
- **Retention:** a daily cron deletes `lpg_enquiry` and `lpg_rfq` records older than `enquiry_retention_months` that have status `lpg-new`/`lpg-handled`/`lpg-closed`. It is off when the setting is 0, and **stays off until the client approves the retention period**.
- **Consent text:** from docs/legal/privacy-notice-draft.md Part A, stored as translatable defaults and marked DRAFT in the admin until approved.

### 13.9 Shipping (W-003, WooCommerce data, not code)
- A **"Nairobi" zone** (Kenya → Nairobi County, `KE:KE30`) with:
  - **Flat rate KSh 300**;
  - **Free shipping, minimum order KSh 10,000**;
  - **Local pickup** (address = placeholder).
- No shipping outside the zone (customers outside Nairobi see "no shipping options", with a message pointing to WhatsApp).
- Configured on dev with WP-CLI and documented in the deployment checklist.

### 13.10 Hardening
- **`Security\RestHardening`:** anonymous requests to `/wp/v2/users` (list and single) get 401. Logged-in users with `list_users` are unaffected (security L1).

### 13.11 Phase 5 services (registered in `Plugin::SERVICES`, Lead-owned)
- `Settings\SettingsTab` (the WooCommerce settings tab; `Settings\Settings` is a static getter, not a service).
- `Taxonomies\CylinderBrand`.
- `Enquiries\RfqPostType`, `Enquiries\EnquiryPostType`, `Enquiries\AdminScreens`, `Enquiries\Notifications`.
- `Rest\RfqController`, `Rest\EnquiryController`.
- `Privacy\Exporters`, `Privacy\Retention`.
- `Security\RestHardening`.
- New blocks are added to `Blocks\Registry::BLOCKS`.

**Phase 5 split.** 5A (lpg-domain): settings, cylinder brands, MessageBuilder, RestHardening. 5B (lpg-domain): records, REST, notifications, admin, privacy (owns the Installer/uninstall changes for caps). 5C: blocks (lpg-domain) plus theme placement and pages (lpg-theme). Then security review and QA acceptance (including A11Y-005, per P-018).

**Shipping (§13.9) is configured on dev (Lead, 2026-11-02):** zone "Nairobi" (KE:KE30) with flat_rate KSh 300, free_shipping min KSh 10,000, local_pickup (placeholder address).
| C18 | Phase 5A choices | Accepted: `privacy_policy_version` default `draft-2026-11-01` (bump when the client approves the privacy text); cylinder size labels over 20 chars are truncated, not rejected |
| C19 | Phase 5B choices | Accepted:<br>• the record menus sit under WooCommerce;<br>• the enquiry stores the brand **name**;<br>• the RFQ and enquiry body contracts as built (phone normalised to `254…`, items 1–50);<br>• privacy matching is by email only, and the eraser deletes matching RFQs;<br>• no enquiry exporter (enquiries hold no contact data);<br>• retention covers `lpg-new`/`lpg-handled`/`lpg-closed` per §13.8, with **default 0 (off)** until the client approves W-007.<br>**WhatsApp edited text:** the enquiry button builds the final `wa.me` link **in the browser** from the visitor's edited text; the shop number is already public on the page. `/enquiry` is still called (best effort) to log that the enquiry was initiated, **without** the message text (data minimisation). No `message` field is added |
| C20 | Dev mail safety | A local-only must-use plugin, `wp-content/mu-plugins/dev-mail-to-log.php` (untracked), short-circuits `wp_mail` to `logs/mail.log` when `WP_ENVIRONMENT_TYPE=local`, because macOS postfix really sends. Tests run as non-local, so mail tests still use MockPHPMailer. **Never deploy it** |
| C21 | Phase 5C choices | Accepted: RFQ items are description and quantity only (no product picker); the front-end `lpg:whatsapp` cancellable DOM event is allowed (testing and analytics), with no PHP interface; per-field REST error keys (`contact.email`, `items.N.qty`) |
| C22 | Phase 5 security review | M1: IPv6 /64 keying; a global per-route hourly ceiling (records still saved, emails suppressed, one volume alert); honeypot checked before rate-limit writes; a separate bucket for invalid bodies; optional `LPG_TRUSTED_PROXIES`. L1: encoded mailto plus stricter email characters. L2: enquiries only for visible, non-password products. L3: new `public_email` setting; `notify_sales_email` is never printed on the front end. Success responses drop `id`. M2 (caching/proxy) → deployment checklist |

### 13.12 Phase 5 additions (from C22)
- **Setting `public_email`:** optional public contact email (default `''`). The no-JS RFQ fallback shows it, or the phone only if it is empty. `notify_*_email` is never printed on the front end.
- **Filter `lpg_global_rate_limit`** `( int $limit, string $route )`, where `$route` is `rfq` (default 30/hour) or `enquiry` (60/hour). This is the site-wide ceiling: above it, records are still saved, notifications are suppressed, and one "unusual volume" alert goes to Sales per route per hour.
- **Constant `LPG_TRUSTED_PROXIES`** (wp-config, an array of IPs/CIDRs). When REMOTE_ADDR is a trusted proxy, the client IP comes from `CF-Connecting-IP`, else the right-most untrusted `X-Forwarded-For`. Without it, only REMOTE_ADDR is used.
- **Per-IP budgets** (IPv6 by /64): 5 accepted and 20 invalid submissions per 10 minutes. Honeypot hits are not counted.
- **Responses:** `/rfq` returns `{ message }`; `/enquiry` returns `{ message, whatsapp_url }`. No IDs.
