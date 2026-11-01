# Test Plan — Kenya LPG Commerce Platform

Owner: lpg-qa. Living document: update the status column whenever a case is run.
Sources: [architecture.md](architecture.md) (§11 is the binding Phase 3 contract) and [implementation-plan.md](implementation-plan.md).

**Rule:** a case is marked Passed only when it was actually run, with the date and evidence. Nothing is inferred from code reading.

---

## 1. Scope and approach

### 1.1 Test levels

| Code | Level | Tooling | Where |
|---|---|---|---|
| UNIT | Pure unit (no WP state) | PHPUnit 9.6 | `tests/integration/` (or `tests/unit/` if added), e.g. MessageBuilder, validators |
| INT | Integration | PHPUnit + wp-phpunit 7.1.3 + WooCommerce, `WP_UnitTestCase` | `tests/integration/*Test.php` against the isolated `gas_retailer_test` DB (prefix `wptests_`, user `gas_test`). Run with `composer test` |
| CLI | Scripted checks | WP-CLI from `gas-retailer/`, `curl` against http://gas-retailer.test | Site DB `gas_retailer_db`. Use `[DEMO]` data and delete it afterwards |
| MAN | Manual | Browser (desktop + 360 px mobile viewport), wp-admin, screen reader / keyboard | Local site |
| E2E | End-to-end | Playwright (Phase 7, D6) | `tests/e2e/` |

Static checks run on every phase: `composer lint` (WPCS 3 + PHPCompatibilityWP, PHP 8.1+) and `composer audit`.

### 1.2 Environments

| Env | Stack | Used for |
|---|---|---|
| Local | Herd, PHP 8.3.16, MySQL 9.0.1, WP 7.1.4, WC 11.2.1 (HPOS on, KES, KE only, COD only), `WP_ENVIRONMENT_TYPE=local` | All levels |
| Production target | Shared cPanel, PHP 8.3, MySQL 8.0 or MariaDB, no Redis/object cache, traffic-driven WP-Cron | Phase 7/8: one full pass on MariaDB or MySQL 8.0 (risk R2) plus the performance cases with a constrained profile (PERF-*) |

### 1.3 Fixtures used by catalogue cases

Test fixtures are created inside the test (test DB only) and are clearly not real product data.

| Fixture | Definition |
|---|---|
| FX-PROFILE | `lpg_spec_profile` "Test Profile" with `_lpg_fields`: `test_pressure` (number, unit `mbar`, filterable), `test_type` (select, options `["A","B"]`, filterable), `test_note` (text, not filterable), `test_flag` (boolean, filterable) |
| FX-CATS | `product_cat` tree Parent > Child > Grandchild, plus an unrelated Sibling category |
| FX-PRODUCT | Simple product "[DEMO] Test Product", regular price 1000 KES, in stock, published |
| FX-SEEDED | `wp lpg seed` (or `SeedCommand` invoked in-test) already run once |
| FX-PDF / FX-IMG | A small valid PDF attachment and a JPEG attachment in the media library |

### 1.4 Entry and exit criteria

| Phase | Entry | Exit |
|---|---|---|
| 2 Foundation | Phase 1 approved | ENV-* passed; lint clean; one integration test passes. **Met 2026-11-01** |
| 3A Catalogue (data/admin) | §11 contract published; Phase 2 closed | All Phase 3 INT cases in §2.1 (CAT, SEC-0xx for profiles/docs, SEO-001..005) passed; lint clean; no open Critical/High defects; security review of new PHP done |
| 3B Catalogue blocks | Node ≥ 22.22.2 installed; 3A closed; `build/` committed | BLK-* passed; blocks register from the manifest; editor preview renders; no PHP notices in `logs/debug.log` |
| 4 Storefront | Design system signed off; Phase 3 closed; SEO plugin chosen (D5) | UX-*, SEO-1xx, A11Y spot checks for templates passed; mobile pass done |
| 5 Workflows | Phase 4 closed; REST shapes for `lpg/v1` in architecture.md | ENQ-*, RFQ-*, EXC-*, relevant SEC-* passed |
| 6 Administration | Phase 5 closed | ADM-* and capability cases passed |
| 7 QA | Phases 3–6 closed | Every case run at least once (Passed/Failed/Blocked with reason); Playwright journeys green; perf and a11y results recorded; no open Critical/High defects |
| 8 Deployment | Phase 7 closed | Smoke subset (ENV-005..007, COM-003, RFQ-001, PERF-001) re-run on the production host |

Severity used in the defect log: Critical (data loss, security breach, checkout down), High (core flow broken, no workaround), Medium (workaround exists), Low (cosmetic).

---

## 2. Test case catalogue

Status values: **Not run** · **Passed (date, evidence)** · **Failed (DEF-nnn)** · **Blocked (reason)**.

### 2.0 Foundation (Phase 2)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| ENV-001 | 2 | INT | Test DB configured | `composer test` | Suite runs; `test_plugin_boots_with_woocommerce_loaded`, `test_services_filter_ignores_non_service_classes`, `test_install_stores_version_and_settings_without_autoload`, `test_install_does_not_overwrite_existing_settings`, `test_hpos_compatibility_is_declared` pass | Passed (2026-11-01, Phase 2 report: 5 tests, 9 assertions, all passed) |
| ENV-002 | 2 | CLI | Copy of `tests/wp-tests-config.php` pointing at the site DB | Run PHPUnit with that config | Bootstrap refuses to run, exit 1, no tables dropped | Passed (2026-11-01, Phase 2 report: refuses site-DB config, exit 1) |
| ENV-003 | 2 | CLI | Dev deps installed | `composer lint` | 0 errors, 0 warnings on plugin, theme, tests | Passed (2026-11-01, Phase 2 report: PHPCS 0/0) |
| ENV-004 | 2 | CLI | — | `composer audit` | No advisories | Passed (2026-11-01, Phase 2 report: clean) |
| ENV-005 | 2 | CLI | Site running | `curl -o /dev/null -w '%{http_code}'` on `/` and `/shop/` | 200 and 200 | Passed (2026-11-01, Phase 2 report) |
| ENV-006 | 2 | CLI | Site running | curl a non-existent URL | 404 status and theme 404 template | Passed (2026-11-01, Phase 2 report) |
| ENV-007 | 2 | CLI | `logs/debug.log` exists | Try `/logs/debug.log`, `/wp-content/debug.log`, `/../logs/debug.log` | None return the log (404/403) | Passed (2026-11-01, Phase 2 report: not reachable over HTTP) |
| ENV-008 | 2 | INT | WooCommerce not loaded (simulated) | Boot plugin | Admin notice shown; no services registered; no fatal | Not run |
| ENV-009 | 2 | MAN | Theme active | Theme Check basics; Site Editor opens each template without "block contains invalid content" | No errors | Not run (listed as not tested in Phase 2 report) |
| ENV-010 | 2 | MAN | — | Plugin deactivate/activate cycle; uninstall with and without `delete_data_on_uninstall` | Clean activation; options and caps removed on uninstall; business data kept unless the setting is on | Not run |

### 2.1 Product catalogue (Phase 3A)

#### Taxonomies and brands

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-001 | 3A | INT | Plugin active | `get_taxonomy('lpg_segment')` | Registered on `product`; non-hierarchical; public; `show_in_rest` true; `show_admin_column` true; rewrite slug `segment`; caps `manage_product_terms`/`edit_product_terms`/`delete_product_terms`/`assign_product_terms` | Not run |
| CAT-002 | 3A | INT | Fresh install | Run installer | Terms `household` "Household", `commercial` "Commercial", `industrial` "Industrial" exist | Not run |
| CAT-003 | 3A | INT | Segment terms exist; rename "Household" to "Homes", delete `industrial` | Run installer again | "Homes" not renamed back (never overwritten); missing `industrial` recreated; no duplicates | Not run |
| CAT-004 | 3A | INT | FX-PRODUCT | Assign `household` and `commercial` | Product has both terms; `/segment/household/` and `/segment/commercial/` both list it | Not run |
| CAT-005 | 3A | INT | Plugin active | Check `taxonomy_exists('lpg_manufacturer')`, `taxonomy_exists('product_brand')` | `lpg_manufacturer` does **not** exist (A6); `product_brand` exists (WooCommerce) | Not run |
| CAT-006 | 3A | INT | — | `taxonomy_exists('lpg_cylinder_brand')` | Not registered in Phase 3 (moved to Phase 5) | Not run |
| CAT-007 | 3A | INT | User with `edit_posts` only (author) | Try `wp_insert_term` / assign `lpg_segment` via capability check | Denied (`manage_product_terms` / `assign_product_terms` required) | Not run |

#### Spec profile CPT

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-010 | 3A | INT | Plugin active | `get_post_type_object('lpg_spec_profile')` | `public` false, `show_ui` true, menu under Products, supports title only, `show_in_rest` false, `capability_type` `lpg_spec_profile`, `map_meta_cap` true | Not run |
| CAT-011 | 3A | INT | After install | Check primitive caps on roles | `administrator` and `shop_manager` have all `*_lpg_spec_profiles` caps; `editor`, `author`, `customer` do not | Not run |
| CAT-012 | 3A | INT | Shop manager logged in | `current_user_can('edit_post', $profile_id)`; editor same | Shop manager true, editor false | Not run |
| CAT-013 | 3A | CLI | Site running, profile exists | `curl /wp-json/wp/v2/lpg_spec_profile` and `?rest_route=/wp/v2/types/lpg_spec_profile` | 404 / not exposed | Not run |
| CAT-014 | 3A | INT | FX-PROFILE | Save profile via admin handler | `_lpg_fields` stored as ordered list with `key,label,type,unit,options,filterable,help`; order preserved | Not run |
| CAT-015 | 3A | INT | — | Save field keys `Pressure`, `1abc`, `a-b`, a 29-char key, empty key | Each rejected (regex `/^[a-z][a-z0-9_]{0,27}$/`); valid fields in same save are kept; admin gets an error | Not run |
| CAT-016 | 3A | INT | — | Save key of exactly 28 chars `a` + 27 chars | Accepted | Not run |
| CAT-017 | 3A | INT | — | Save two fields with key `test_type` in one profile | Duplicate rejected; only one kept | Not run |
| CAT-018 | 3A | INT | — | Save field with type `date` | Rejected (only text/number/select/boolean) | Not run |
| CAT-019 | 3A | INT | — | Save `select` field with empty `options` | Rejected or flagged (select requires options) | Not run |
| CAT-020 | 3A | INT | — | Save label `<script>alert(1)</script>` | Stored sanitized; rendered escaped in admin | Not run |

#### Profile resolution order

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-030 | 3A | INT | FX-PRODUCT, two profiles P1, P2; category default P1 | Set `_lpg_spec_profile_id` = P2; call `ProfileResolver::for_product()` | Returns P2 (override wins) | Not run |
| CAT-031 | 3A | INT | As above | Set override to `0`, then delete the meta | Both cases return P1 (0/empty = inherit) | Not run |
| CAT-032 | 3A | INT | FX-CATS; Parent has `lpg_spec_profile_id` P1; product only in Grandchild | Resolve | P1 (walks ancestors) | Not run |
| CAT-033 | 3A | INT | Parent → P1, Grandchild → P2; product in Grandchild | Resolve | P2 (nearest ancestor first) | Not run |
| CAT-034 | 3A | INT | Product in Child (P1, depth 1) and Sibling (P2, depth 0) | Resolve | P1 (deepest category first) | Not run |
| CAT-035 | 3A | INT | Product in two categories of equal depth, each with a profile, lower `term_id` → P1 | Resolve | P1 (`term_id` ascending tiebreak); result is stable across calls | Not run |
| CAT-036 | 3A | INT | Product in Grandchild (no profile, no ancestor profile) and Sibling (P2) | Resolve | P2 (first branch yields nothing, next category checked) | Not run |
| CAT-037 | 3A | INT | No override, no category profiles | Resolve | `null`; spec tab shows "no profile" message; `ProductSpecs::get()` returns `[]` | Not run |
| CAT-038 | 3A | INT | Override points to a deleted / trashed / non-`lpg_spec_profile` post ID | Resolve | Negative: does not return an invalid ID (falls back to category chain or null) — confirm behaviour with Lead (Q3) | Not run |

#### Spec values

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-040 | 3A | INT | FX-PRODUCT resolved to FX-PROFILE | Save `test_pressure`=`37`, `test_type`=`A`, `test_note`=`hello`, `test_flag` checked | Meta `_lpg_spec_test_pressure`=`37`, `_lpg_spec_test_type`=`A`, `_lpg_spec_test_note`=`hello`, `_lpg_spec_test_flag`=`yes` | Not run |
| CAT-041 | 3A | INT | As above | Save `test_flag` unchecked | `_lpg_spec_test_flag`=`no` (or deleted — see Q4) | Not run |
| CAT-042 | 3A | INT | — | Save number `2.5`, `2,5`, `abc`, `1e3`, `-1` | `2.5` stored; non-numeric `abc` rejected/not stored; `2,5` handling per Lead decision (Q5); stored form uses `.` decimal | Not run |
| CAT-043 | 3A | INT | — | Save select value `C` (not in options) and `a` (case differs) | Rejected; previous value unchanged | Not run |
| CAT-044 | 3A | INT | — | Save text `<b>x</b><script>y</script>` | Stored via `sanitize_text_field` (tags stripped) | Not run |
| CAT-045 | 3A | INT | Value previously set | Save empty string | Meta `_lpg_spec_{key}` deleted (not stored empty) | Not run |
| CAT-046 | 3A | INT | Variable product with 2 variations | Save specs | Meta only on parent; none on variations | Not run |
| CAT-047 | 3A | INT | Values set | `ProductSpecs::get($id)` | Only fields with values, in profile order, each with `key,label,type,unit,value,display`; `display` for number includes unit (e.g. `37 mbar`), boolean `Yes`/`No` | Not run |
| CAT-048 | 3A | INT | Values set; product moved to category resolving to a different profile | `ProductSpecs::get()` | Returns only fields of the new profile; old `_lpg_spec_*` meta not deleted (data retained) — confirm (Q6) | Not run |
| CAT-049 | 3A | INT | User without `edit_post` on product | POST spec values to product save | Not saved (nonce + capability check) | Not run |
| CAT-050 | 3A | INT | — | Save product with invalid / missing nonce | Spec meta unchanged | Not run |
| CAT-051 | 3A | INT | — | `wp_load_alloptions()` after saving specs | No `_lpg_spec_*` data in autoloaded options; specs read with one `get_post_meta($id)` call | Not run |

#### Attribute sync (filterable fields)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-060 | 3A | INT | No `pa_test_pressure` exists | Save FX-PROFILE | Global attributes `pa_test_pressure`, `pa_test_type`, `pa_test_flag` created via `wc_create_attribute`, type select, label = field label; none for `test_note` | Not run |
| CAT-061 | 3A | INT | Attribute already exists | Save profile again | No duplicate attribute; existing label not changed (or as Lead specifies) | Not run |
| CAT-062 | 3A | INT | Same key `test_type` in two profiles | Save both | One shared `pa_test_type` | Not run |
| CAT-063 | 3A | INT | CAT-040 values | Save product | Product attributes include `pa_test_pressure` term `37 mbar`, `pa_test_type` term `A`, `pa_test_flag` term `Yes`; each `is_visible` false, `is_variation` false | Not run |
| CAT-064 | 3A | INT | Product already has a custom local attribute and an unrelated `pa_colour` (used for variations) | Save specs | Other attributes untouched, including variation attributes | Not run |
| CAT-065 | 3A | INT | `pa_test_pressure` set | Clear `test_pressure` and save | `pa_test_pressure` removed from the product; term itself may remain | Not run |
| CAT-066 | 3A | INT | Non-filterable `test_note` set | Save | No `pa_test_note` attribute created or assigned | Not run |
| CAT-067 | 3A | CLI | Two `[DEMO]` products with different `test_type` | Shop archive with `?filter_test_type=a` (native layered nav) | Only matching product listed (native WC filtering, no custom query) | Not run |
| CAT-068 | 3A | INT | — | Value change `37` → `50` | Product now has term `50 mbar` only (old term unassigned) | Not run |

#### Compatibility, documents, warranty, relations

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-070 | 3A | INT | Products A, B, C | Set A `_lpg_compatible_ids` = [B] | B's `_lpg_compatible_ids` contains A | Not run |
| CAT-071 | 3A | INT | A↔B linked | Remove B from A | A removed from B; B's other links untouched | Not run |
| CAT-072 | 3A | INT | Filter `lpg_compatibility_bidirectional` returns false | Set A → [C] | C unchanged | Not run |
| CAT-073 | 3A | INT | — | Set A → [A] (self), [999999] (non-existent), [a page ID], `"abc"` | Self, non-existent and non-product IDs dropped; stored as int[] | Not run |
| CAT-074 | 3A | INT | A → [B (draft), C (private), D (catalog visibility hidden), E (published)] | `Compatibility::get_ids(A)` | Returns [E] only | Not run |
| CAT-075 | 3A | INT | A↔B linked | Trash/delete B | `get_ids(A)` excludes B; no fatal | Not run |
| CAT-076 | 3A | INT | A↔B linked | Mirror on save must not loop | Saving A triggers B update once; no recursion / timeout | Not run |
| CAT-077 | 3A | INT | FX-PDF | Save `_lpg_documents` = [{FX-PDF, `datasheet`, "Datasheet"}] | Stored as list of `{attachment_id:int, type, label}` | Not run |
| CAT-078 | 3A | INT | FX-IMG | Save document pointing to a JPEG | Rejected (only `application/pdf`) | Not run |
| CAT-079 | 3A | INT | — | Save type `brochure`, label with HTML, non-existent attachment ID | Invalid type rejected; label sanitized; missing attachment dropped | Not run |
| CAT-080 | 3A | INT | User without `upload_files` but can edit product | Add a document | Not added; existing documents unchanged | Not run |
| CAT-081 | 3A | INT | Document saved, then attachment deleted / mime changed | `Documents::get()` | Only valid PDF attachments returned | Not run |
| CAT-082 | 3A | INT | — | Save `_lpg_warranty` multi-line text with `<script>` | Stored via `sanitize_textarea_field` (newlines kept, tags stripped) | Not run |
| CAT-083 | 3A | INT | — | Set upsells and cross-sells on A | Native `_upsell_ids` / `_crosssell_ids` used; plugin adds no parallel meta | Not run |
| CAT-084 | 3A | INT | — | Assign `product_brand` term to product | Native WC brand stored; no `_lpg_manufacturer` meta written | Not run |
| CAT-085 | 3A | MAN | Admin | Product edit: "LPG Specifications" tab renders profile fields; "Linked Products" tab has compatibility product-search select; General tab has warranty + enquiry-only; documents meta box with media picker | All present, labelled, save correctly | Not run |

#### Enquiry-only rule

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-090 | 3A | INT | FX-PRODUCT | Set `_lpg_enquiry_only`=`yes` | `is_purchasable()` false; `EnquiryOnly::is_enquiry_only()` true; `_price`/`_regular_price` unchanged in DB | Not run |
| CAT-091 | 3A | INT | `_lpg_enquiry_only`=`no`, price empty | Check | Not purchasable; enquiry-only true | Not run |
| CAT-092 | 3A | INT | `no`, price 1000 | Check | Purchasable | Not run |
| CAT-093 | 3A | INT | Price `0` | Check | Purchasable (0 is not empty) — confirm (Q7) | Not run |
| CAT-094 | 3A | INT | Filter `lpg_is_enquiry_only` returns false for a `yes` product, and true for a normal one | Check | Filter result wins; receives `(bool, WC_Product)` | Not run |
| CAT-095 | 3A | INT | Variable product, one variation with empty price | Check variation and parent | Variation not purchasable; behaviour of parent per Lead (Q8) | Not run |
| CAT-096 | 3A | CLI | Enquiry-only `[DEMO]` product | Store API `POST /wp-json/wc/store/v1/cart/add-item` with its ID; also `?add-to-cart=ID` | Rejected with error; cart unchanged | Not run |
| CAT-097 | 3A | INT | Product in cart, then switched to enquiry-only | Load cart / checkout | Item removed or checkout blocked by WC purchasable check | Not run |

#### Seed command

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-100 | 3A | INT/CLI | Clean test DB | Run `SeedCommand` (CLI: `wp lpg seed`) | Profiles Regulator, Burner, Gas Cooker, Cylinder created with `_lpg_fields` only; zero products and zero `_lpg_spec_*` values created | Not run |
| CAT-101 | 3A | INT | Clean DB | Run seed | §4.1 `product_cat` tree created; term meta `lpg_spec_profile_id`: Regulators→Regulator, Burners→Burner, Cookers & Ovens→Gas Cooker, Cylinders→Cylinder | Not run |
| CAT-102 | 3A | INT | FX-SEEDED | Run seed again | Same counts of profiles, terms, term meta; no duplicates; exit success | Not run |
| CAT-103 | 3A | INT | FX-SEEDED; admin edited Regulator fields and renamed a category | Run seed again | Edits preserved (not overwritten) — confirm (Q9) | Not run |
| CAT-104 | 3A | INT | Plugin activated / admin page loaded | No seed command run | No profiles or categories created automatically | Not run |
| CAT-105 | 3A | INT | Non-CLI request | Check `WP_CLI` undefined | `Cli\SeedCommand` not registered | Not run |
| CAT-106 | 3A | INT | Seeded profiles | Inspect `_lpg_fields` | Field definitions match the brief exactly; no extra invented fields, units or values (R4) — Lead to supply the reference list | Not run |

#### Uninstall (§11.10)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-110 | 3A | INT | Data present; `delete_data_on_uninstall` on | Run uninstall routine | `lpg_spec_profile` posts, all `_lpg_*` post meta, `lpg_spec_profile_id` term meta, `lpg_segment` terms deleted; `pa_*` attributes kept; products, categories, orders kept | Not run |
| CAT-111 | 3A | INT | Setting off | Run uninstall | Options and caps removed; all business data kept | Not run |

### 2.2 Catalogue blocks (Phase 3B)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| BLK-001 | 3B | INT | `build/` present | `WP_Block_Type_Registry` | `lpg/spec-table`, `lpg/compatible-products`, `lpg/product-documents` registered from the metadata collection manifest; dynamic (render callback); `usesContext` includes `postId` | Not run |
| BLK-002 | 3B | INT | Product with specs | Render `lpg/spec-table` with postId | `<table>` with class `lpg-spec-table`, default heading "Technical specifications", one row per `ProductSpecs::get()` entry in order | Not run |
| BLK-003 | 3B | INT | Product without specs / no profile | Render | Empty string (no heading, no wrapper) | Not run |
| BLK-004 | 3B | INT | Spec label/value/heading contain `<script>`, `"onmouseover=` | Render | All output escaped | Not run |
| BLK-005 | 3B | INT | No postId context / postId of a page | Render each block | No output, no PHP warning | Not run |
| BLK-006 | 3B | INT | A with 10 compatible products | Render `lpg/compatible-products` defaults | 8 cards, 4 columns; each has image, name, price; enquiry-only items show "Enquire" instead of price/add-to-cart | Not run |
| BLK-007 | 3B | INT | — | Render with `columns` 1, 7, `limit` 0, 13, `"abc"` | Clamped to 2–6 / 1–12 or attribute rejected by schema | Not run |
| BLK-008 | 3B | INT | Compatible list contains draft/hidden products | Render | Only published, visible products | Not run |
| BLK-009 | 3B | INT | Product with 2 PDFs (datasheet, manual) + 1 invalid entry | Render `lpg/product-documents` | List of 2 links with type label and human file size; invalid entry skipped; links point to attachment URL | Not run |
| BLK-010 | 3B | MAN | Editor | Insert each block in single-product template | ServerSideRender preview shows; no "invalid content"; block attributes editable | Not run |
| BLK-011 | 3B | CLI | Built assets | `ls build/blocks/*`; front-end page with no LPG block | Built files committed; no LPG block JS/CSS enqueued on pages that don't use the blocks | Not run |
| BLK-012 | 3B | MAN | Theme switched to Twenty Twenty-Five | View product with blocks | Blocks still render (A2) | Not run |

### 2.3 Customer experience (Phase 4)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| UX-001 | 4 | CLI/MAN | Homepage built | Load `/` | 200; hero, segment tiles, product grids, safety/trust strips, RFQ CTA render; no PHP notices | Not run |
| UX-002 | 4 | MAN | Header | Navigate Household/Commercial/Industrial | Each goes to the segment landing page/archive | Not run |
| UX-003 | 4 | MAN | Header | LPG Accessories, Safety Equipment, Refills & Exchange | Go to category archives | Not run |
| UX-004 | 4 | CLI | Demo products | `/?s=regulator&post_type=product` and header search | Matching products; product-search-results template; empty query and no-result query handled | Not run |
| UX-005 | 4 | MAN | Filterable attributes | Apply attribute, price, brand, segment filters on archive | Results narrow correctly; URL reflects filters; clearing works | Not run |
| UX-006 | 4 | MAN | Product with all data | Single product page | Gallery, price or Enquire, spec table, documents, compatible products, upsells, warranty, brand shown | Not run |
| UX-007 | 4 | MAN | Mobile 360×800 and 768 px | Home, archive, product, cart, checkout | No horizontal scroll; nav usable; tap targets ≥ 24×24 px; tables scroll or reflow | Not run |
| UX-008 | 4 | MAN | — | 404 and empty category | Helpful 404 and "no products" states | Not run |
| UX-009 | 4 | CLI | Segment term | `/segment/household/` | 200, lists tagged products, uses `taxonomy-lpg_segment` template | Not run |

### 2.4 Commerce (native WooCommerce, Phases 2/4/5)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| COM-001 | 4 | E2E | Purchasable `[DEMO]` product | Add to cart from archive and product page | Cart count updates; item in cart | Not run |
| COM-002 | 4 | E2E | Item in cart | Change qty, remove item; exceed stock | Totals update; over-stock qty rejected | Not run |
| COM-003 | 2 | CLI | `[DEMO]` product, stock 5, COD enabled | Store API cart + checkout with qty 2, COD | Order created, status "processing" | Passed (2026-11-01, Phase 2 report: COD order reached "processing"; test data deleted) |
| COM-004 | 2 | CLI | As COM-003 | Check stock after order | Stock 5 → 3 | Passed (2026-11-01, Phase 2 report: stock 5 → 3) |
| COM-005 | 4 | E2E | — | Block checkout as guest with KE address | Only COD offered; KES, 0 decimals; non-KE country not selectable | Not run |
| COM-006 | 4 | E2E | — | Checkout with missing required fields / invalid phone/email | Validation errors, no order | Not run |
| COM-007 | 5 | MAN | Order exists | Admin HPOS orders screen: view, change status, add note | Works; no PHP notices | Not run |
| COM-008 | 5 | MAN | SMTP or mail catcher | Place order; complete it | Customer "processing" and "completed" emails and admin "new order" email sent (check mail log) | Not run |
| COM-009 | 4 | INT | Stock 0, backorders off | Add to cart | Rejected; product shows out of stock | Not run |
| COM-010 | 4 | MAN | Product with cross-sells | View cart | Cross-sells (accessories) shown | Not run |
| COM-011 | 4 | MAN | Grouped product (package) | Add children to cart | Each child added with own price/stock (D3) | Not run |
| COM-012 | 5 | MAN | Shipping zones defined (Phase 5) | Checkout | Correct delivery charge per zone | Blocked (no shipping zones until Phase 5) |

### 2.5 Enquiries, RFQ, exchange (Phase 5–6)

REST shapes beyond §5 are not yet in architecture.md; refine these cases when they are.

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| ENQ-001 | 5 | UNIT | — | MessageBuilder with name, SKU, URL, template | Correct text; `wa.me/<number>?text=` with URL-encoded message; number digits only (e.g. `2547…`) | Not run |
| ENQ-002 | 5 | UNIT | — | Name with `&`, `#`, emoji, newline; missing SKU | Encoded correctly; template handles missing placeholder without stray tokens | Not run |
| ENQ-003 | 5 | E2E | Enquiry-only product | Click Enquire, edit textarea, open | WhatsApp link uses edited text; opens in new tab | Not run |
| ENQ-004 | 5 | INT | — | POST enquiry log via REST | Private `lpg_enquiry` (type `product`) created, logged as "initiated" (R7) | Not run |
| ENQ-005 | 5 | E2E | Logging endpoint returns 500 | Click Enquire | WhatsApp still opens | Not run |
| ENQ-006 | 5 | INT | No WhatsApp number configured | Render enquiry-button | Graceful fallback (no broken link) | Not run |
| RFQ-001 | 5 | INT | Valid nonce | `POST /wp-json/lpg/v1/rfq` with contact, organisation, segment, line items, requirements, location | 201; private `lpg_rfq` with status `lpg-new`; meta stored; admin email via `wp_mail` | Not run |
| RFQ-002 | 5 | INT | — | Missing required fields; invalid email/phone; qty 0/negative; 500 line items; oversized text | 400 with field errors; no post created | Not run |
| RFQ-003 | 5 | INT | — | Honeypot filled | Rejected silently or 400; no post | Not run |
| RFQ-004 | 5 | INT | — | Missing/invalid REST nonce | 401/403 | Not run |
| RFQ-005 | 5 | INT | — | Exceed per-IP rate limit | 429 after threshold; transient expires | Not run |
| RFQ-006 | 5 | INT | — | Line item with `product_id` of draft/non-product | Stored without the invalid product link | Not run |
| RFQ-007 | 6 | MAN | RFQs exist | Admin list: columns, filters, status workflow `lpg-new`→`lpg-review`→`lpg-quoted`→`lpg-closed` | Works; shop manager can, editor cannot | Not run |
| RFQ-008 | 5 | E2E | — | RFQ form journey on mobile | Submit, success message, consent notice visible | Not run |
| EXC-001 | 5 | E2E | `lpg_cylinder_brand` terms + size term meta | Brand → size → exchange/new → location → submit | `lpg_enquiry` type `exchange` logged; WhatsApp opens with pre-filled message | Not run |
| EXC-002 | 5 | INT | — | Size not belonging to brand; empty location | Rejected | Not run |
| EXC-003 | 5 | INT | — | Privacy exporter/eraser for an email with RFQ + enquiry | Export includes data; erase removes/anonymises it | Not run |

### 2.6 Security

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| SEC-001 | 3A | INT | Guest, customer, author | Access `edit.php?post_type=lpg_spec_profile`, edit a profile | Denied | Not run |
| SEC-002 | 3A | INT | Product save | Forged POST without nonce for specs, compatibility, documents, warranty, enquiry-only | Nothing saved | Not run |
| SEC-003 | 3A | INT | User with `edit_products` but not on that product (`edit_post` false) | Save LPG meta | Nothing saved | Not run |
| SEC-004 | 3A | CLI | — | Upload `.php`, `.svg`, `.html`, and a PHP file renamed `.pdf` as product document | Rejected by WP upload rules or by the PDF-only check (real mime) | Not run |
| SEC-005 | 3A | CLI | Data present | `curl /wp-json/wp/v2/types`, `/wp/v2/lpg_spec_profile`, `/wp/v2/lpg_rfq`, `/wp/v2/lpg_enquiry` | Private CPTs not exposed | Not run |
| SEC-006 | 3A | CLI | Product with specs | `curl /wp-json/wp/v2/product/<id>` and `/wc/store/v1/products/<id>` | No `_lpg_*` meta leaked beyond intended public data | Not run |
| SEC-007 | 5 | INT | — | `lpg/v1` routes: every write route has `permission_callback` (no `__return_true` for writes without nonce) | Verified via `rest_get_server()->get_routes()` | Not run |
| SEC-008 | 5 | INT | — | Read RFQ/enquiry data via REST as guest/customer | 401/403 | Not run |
| SEC-009 | 6 | INT | — | Caps `edit_lpg_rfqs`, `read_lpg_rfq`, enquiry caps per role | Only administrator and shop_manager; removed on uninstall | Not run |
| SEC-010 | 3A–5 | INT | — | XSS payloads in all stored text fields, rendered in admin and front end | Escaped everywhere | Not run |
| SEC-011 | 7 | CLI | — | Directory listing / direct access to plugin PHP files (`src/*.php`, `blocks/*/render.php`) | ABSPATH guard; no output/fatal path disclosure | Not run |
| SEC-012 | 7 | MAN | lpg-security | Full review of plugin PHP, REST, forms, uploads | No open Critical/High | Not run |

### 2.7 Performance

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| PERF-001 | 7 | CLI | Warm OPcache, no page cache | `curl -w '%{time_starttransfer}'` ×5 on home, archive, product, cart | TTFB median < 600 ms locally; record values for production comparison | Not run |
| PERF-002 | 3B/7 | CLI/INT | Query Monitor (dev only) or `SAVEQUERIES` | Product page with spec table, 8 compatible products, documents | Query count recorded; no N+1 (compatible products primed in one query; specs via one `get_post_meta`) | Not run |
| PERF-003 | 3A/7 | CLI | Plugin active | `wp db query "SELECT option_name, LENGTH(option_value) FROM wp_options WHERE autoload IN ('yes','on','auto-on','auto') AND option_name LIKE 'lpg%'"` | Only the WhatsApp-number entry (if split) autoloaded; `lpg_settings` not autoloaded; total autoload size recorded | Not run |
| PERF-004 | 4/7 | MAN | — | Lighthouse mobile on home and product | LCP < 2.5 s, CLS < 0.1, total page weight recorded; images lazy, sized, responsive `srcset` | Not run |
| PERF-005 | 4/7 | CLI | — | Count enqueued JS/CSS on home | No jQuery from theme; LPG block assets only where used | Not run |
| PERF-006 | 3A | INT | 200 products | Save a profile with filterable fields | Completes within PHP `max_execution_time` 30 s and 256M; does not resave all products on page load | Not run |
| PERF-007 | 7 | CLI | Production-like profile: `memory_limit=128M`, no object cache, `DISABLE_WP_CRON` + real cron | Browse key pages, place order, submit RFQ | No memory errors; emails sent inline | Not run |
| PERF-008 | 8 | MAN | Page cache plugin/LiteSpeed on host | Cart, checkout, my-account, REST excluded from cache; nonce pages not cached stale | Correct behaviour | Not run |

### 2.8 SEO

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| SEO-001 | 3A | INT | Product with specs (number with unit, select, boolean, text) | Apply `woocommerce_structured_data_product` filter (`WC_Structured_Data::generate_product_data`) | One `additionalProperty` `PropertyValue` per spec with `name` = label, `value`, `unitText` = unit when present | Not run |
| SEO-002 | 3A | INT | Product without specs / no profile | Generate | No `additionalProperty` key added; rest of WC markup unchanged | Not run |
| SEO-003 | 3A | INT | Product that already has other markup keys | Generate | Plugin adds nothing besides `additionalProperty` (offers, brand, etc. untouched) | Not run |
| SEO-004 | 3A | INT | Spec values containing quotes, `</script>` | Generate + render JSON-LD | Valid JSON; no script break-out | Not run |
| SEO-005 | 3A | CLI | `[DEMO]` product | curl product page, extract `application/ld+json`, validate with schema.org validator / Rich Results test | Valid Product; `additionalProperty` present | Not run |
| SEO-006 | 3A | INT | Enquiry-only product with empty price | Generate | No invalid `offers` (empty price) — record WC behaviour | Not run |
| SEO-101 | 4 | CLI | SEO plugin chosen | Home, archive, product, segment page | Unique `<title>`, meta description, single canonical, OG tags; no duplicate titles/descriptions from theme + plugin | Not run |
| SEO-102 | 4 | CLI | Post-name permalinks | Check URLs | `/product/<slug>/`, `/product-category/...`, `/segment/<slug>/`, `/brand/<slug>/` resolve 200 | Not run |
| SEO-103 | 4 | CLI | — | `/wp-sitemap.xml` or SEO plugin sitemap | Includes products, product_cat, `lpg_segment`, `product_brand`; excludes `lpg_spec_profile`, `lpg_rfq`, `lpg_enquiry`, cart/checkout/account | Not run |
| SEO-104 | 4 | CLI | — | `curl` rendered pages, grep `<img` | Every content image has meaningful `alt`; decorative images `alt=""` | Not run |
| SEO-105 | 4 | CLI | — | `/robots.txt`; `WP_ENVIRONMENT_TYPE` local | Not blocking production crawl; one H1 per page | Not run |

### 2.9 Accessibility (WCAG 2.2 AA spot checks, Phases 4–7)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| A11Y-001 | 4 | MAN | — | Keyboard-only: header nav, mega-menu, search, filters, add to cart, checkout | All reachable, logical order, visible focus (2.4.7, 2.4.11), no traps | Not run |
| A11Y-002 | 4 | MAN | — | Contrast check of theme.json palette (text, buttons, links, focus) | ≥ 4.5:1 text, ≥ 3:1 UI components | Not run |
| A11Y-003 | 3B | MAN | — | Spec table markup | `<table>` with caption or heading association, `<th scope>` | Not run |
| A11Y-004 | 5 | MAN | — | RFQ and exchange forms with screen reader (VoiceOver) | Labels, required indicators, errors announced and linked to fields (3.3.1, 3.3.3) | Not run |
| A11Y-005 | 4 | MAN | — | Zoom 200% / 320 px reflow | No loss of content (1.4.10) | Not run |
| A11Y-006 | 4 | MAN | — | Target size on mobile | ≥ 24×24 px (2.5.8) | Not run |
| A11Y-007 | 7 | E2E | Playwright + axe-core | Run axe on key pages | No serious/critical violations | Not run |
| A11Y-008 | 5 | MAN | — | WhatsApp links/buttons | Accessible name says it opens WhatsApp / new window | Not run |

### 2.10 Administration (Phase 6)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| ADM-001 | 6 | INT | — | Save LPG settings tab (WhatsApp number, templates, recipients) | Sanitized; invalid number/email rejected; non-autoloaded except number | Not run |
| ADM-002 | 6 | MAN | Enquiries exist | Enquiry list filters by type/date | Correct results | Not run |
| ADM-003 | 6 | MAN | Shop manager | Full operator walkthrough | All tasks possible without admin role | Not run |

---

## 3. Defect log

| ID | Date | Case | Severity | Summary | Steps to reproduce | Owner | Status |
|---|---|---|---|---|---|---|---|
| — | | | | | | | |

---

## 4. Not covered / risks

| Item | Notes |
|---|---|
| Production DB | Only MySQL 9.0 locally. One pass on MariaDB / MySQL 8.0 is needed before Phase 8 (R2). |
| Real WhatsApp delivery | We can only check the generated link. Whether the customer actually sends the message is outside our control (R7). |
| Email delivery | Local mail is not delivered. Use a mail catcher or log `wp_mail`. Real SMTP is checked in Phase 8. |
| M-Pesa | Out of scope until it is scheduled (D4). |
| Real product data accuracy | Tests check storage and display, not whether supplier specs are correct (R4). |
| Third-party code | WooCommerce core and the SEO plugin are tested only where we integrate with them. |
| Browser matrix | Planned: Chrome, Safari iOS, Firefox, Android Chrome. Older browsers are not covered. |
| Load testing | No load/concurrency tests. Rate limiting is checked functionally only. |
| Translations | Strings are i18n-ready, but no translation is tested. |
| Shared test DB | The suite drops tables. Only one agent can run `composer test` at a time. |
| Per-variation specs | Out of scope for v1 (§11.3). |

---

## 5. Open questions (for the Lead)

| # | Question | Affects |
|---|---|---|
| Q1 | The Phase 2 report and Phase 3 roadmap still list `lpg_manufacturer` / `lpg_cylinder_brand` for Phase 3; §11.1 supersedes them. Plan follows §11. | CAT-005/006 |
| Q2 | Should profile save fail as a whole on one invalid field, or keep valid fields and report errors? | CAT-015..019 |
| Q3 | Override pointing at a deleted/trashed/wrong-type post: fall back to the category chain or return null? | CAT-038 |
| Q4 | Boolean unchecked: store `no`, or delete the meta? If `no` is stored, is it displayed and synced as `pa_*` term "No"? | CAT-041, CAT-063 |
| Q5 | Number input `2,5`: normalise to `2.5` or reject? Negative and exponent values allowed? | CAT-042 |
| Q6 | When a product's resolved profile changes, are orphaned `_lpg_spec_*` meta and synced `pa_*` attributes kept or removed? Same question when a field is deleted from a profile or loses `filterable`. | CAT-048, CAT-065 |
| Q7 | Is a price of `0` "empty" for the enquiry-only rule? | CAT-093 |
| Q8 | Variable products: is the empty-price rule applied per variation, and what does the parent report? | CAT-095 |
| Q9 | Does `wp lpg seed` only create missing items, or also restore changed field definitions/term assignments? | CAT-103 |
| Q10 | Please supply the reference field list for the four seeded profiles so CAT-106 can assert it. | CAT-106 |
| Q11 | Should `pa_*` label be updated when a field label changes in the profile? | CAT-061 |
