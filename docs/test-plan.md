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
| CAT-001 | 3A | INT | Plugin active | `get_taxonomy('lpg_segment')` | Registered on `product`; non-hierarchical; public; `show_in_rest` true; `show_admin_column` true; rewrite slug `segment`; caps `manage_product_terms`/`edit_product_terms`/`delete_product_terms`/`assign_product_terms` | Passed (2026-11-01, composer test 104/369; partial: `show_admin_column` and the manage/edit/delete term caps are not asserted): CatalogueSetupTest::test_segment_taxonomy_registered_per_contract |
| CAT-002 | 3A | INT | Fresh install | Run installer | Terms `household` "Household", `commercial` "Commercial", `industrial` "Industrial" exist | Passed (2026-11-01, composer test 104/369; partial: only the slugs are asserted, not the names "Commercial"/"Industrial"): CatalogueSetupTest::test_install_seeds_three_segments_and_never_overwrites |
| CAT-003 | 3A | INT | Segment terms exist; rename "Household" to "Homes", delete `industrial` | Run installer again | "Homes" not renamed back (never overwritten); missing `industrial` recreated; no duplicates | Passed (2026-11-01, composer test 104/369; partial: rename kept and no duplicates asserted; recreating a deleted `industrial` is not tested): CatalogueSetupTest::test_install_seeds_three_segments_and_never_overwrites |
| CAT-004 | 3A | INT | FX-PRODUCT | Assign `household` and `commercial` | Product has both terms; `/segment/household/` and `/segment/commercial/` both list it | Passed (2026-11-01, CLI on dev site, partial: only `household` assigned; `/segment/household/` 200 lists it, `/segment/commercial/` does not. See AC-04) |
| CAT-005 | 3A | INT | Plugin active | Check `taxonomy_exists('lpg_manufacturer')`, `taxonomy_exists('product_brand')` | `lpg_manufacturer` does **not** exist (A6); `product_brand` exists (WooCommerce) | Not run |
| CAT-006 | 3A | INT | — | `taxonomy_exists('lpg_cylinder_brand')` | Not registered in Phase 3 (moved to Phase 5) | Not run |
| CAT-007 | 3A | INT | User with `edit_posts` only (author) | Try `wp_insert_term` / assign `lpg_segment` via capability check | Denied (`manage_product_terms` / `assign_product_terms` required) | Not run |

#### Spec profile CPT

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-010 | 3A | INT | Plugin active | `get_post_type_object('lpg_spec_profile')` | `public` false, `show_ui` true, menu under Products, supports title only, `show_in_rest` false, `capability_type` `lpg_spec_profile`, `map_meta_cap` true | Passed (2026-11-01, composer test 104/369; partial: menu placement, `supports` and `map_meta_cap` are not asserted): CatalogueSetupTest::test_profile_post_type_is_private |
| CAT-011 | 3A | INT | After install | Check primitive caps on roles | `administrator` and `shop_manager` have all `*_lpg_spec_profiles` caps; `editor`, `author`, `customer` do not | Passed (2026-11-01, composer test 104/369; partial: only the presence of the caps on administrator/shop_manager is asserted; their absence on editor/author/customer is not): CatalogueSetupTest::test_caps_granted_to_shop_manager_and_administrator, CatalogueSetupTest::test_first_install_grants_caps_but_upgrade_does_not_regrant |
| CAT-012 | 3A | INT | Shop manager logged in | `current_user_can('edit_post', $profile_id)`; editor same | Shop manager true, editor false | Passed (2026-11-01, composer test 104/369): CatalogueSetupTest::test_caps_granted_to_shop_manager_and_administrator |
| CAT-013 | 3A | CLI | Site running, profile exists | `curl /wp-json/wp/v2/lpg_spec_profile` and `?rest_route=/wp/v2/types/lpg_spec_profile` | 404 / not exposed | Not run |
| CAT-014 | 3A | INT | FX-PROFILE | Save profile via admin handler | `_lpg_fields` stored as ordered list with `key,label,type,unit,options,filterable,help`; order preserved | Not run |
| CAT-015 | 3A | INT | — | Save field keys `Pressure`, `1abc`, `a-b`, a 29-char key, empty key | Each rejected (regex `/^[a-z][a-z0-9_]{0,27}$/`); valid fields in same save are kept; admin gets an error | Passed (2026-11-01, composer test 104/369; partial: an empty key with a non-empty label is not tested): ProductSpecsTest::test_definition_validation_drops_invalid_rows_and_keeps_valid_ones |
| CAT-016 | 3A | INT | — | Save key of exactly 28 chars `a` + 27 chars | Accepted | Not run |
| CAT-017 | 3A | INT | — | Save two fields with key `test_type` in one profile | Duplicate rejected; only one kept | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_definition_validation_drops_invalid_rows_and_keeps_valid_ones |
| CAT-018 | 3A | INT | — | Save field with type `date` | Rejected (only text/number/select/boolean) | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_definition_validation_drops_invalid_rows_and_keeps_valid_ones (type `colour` used instead of `date`) |
| CAT-019 | 3A | INT | — | Save `select` field with empty `options` | Rejected or flagged (select requires options) | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_definition_validation_drops_invalid_rows_and_keeps_valid_ones |
| CAT-020 | 3A | INT | — | Save label `<script>alert(1)</script>` | Stored sanitized; rendered escaped in admin | Not run |

#### Profile resolution order

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-030 | 3A | INT | FX-PRODUCT, two profiles P1, P2; category default P1 | Set `_lpg_spec_profile_id` = P2; call `ProfileResolver::for_product()` | Returns P2 (override wins) | Passed (2026-11-01, composer test 104/369): ProfileResolverTest::test_override_wins_over_category; also CLI on dev site, AC-01 |
| CAT-031 | 3A | INT | As above | Set override to `0`, then delete the meta | Both cases return P1 (0/empty = inherit) | Not run |
| CAT-032 | 3A | INT | FX-CATS; Parent has `lpg_spec_profile_id` P1; product only in Grandchild | Resolve | P1 (walks ancestors) | Passed (2026-11-01, composer test 104/369): ProfileResolverTest::test_walks_up_ancestors; also CLI on dev site, AC-01 |
| CAT-033 | 3A | INT | Parent → P1, Grandchild → P2; product in Grandchild | Resolve | P2 (nearest ancestor first) | Not run |
| CAT-034 | 3A | INT | Product in Child (P1, depth 1) and Sibling (P2, depth 0) | Resolve | P1 (deepest category first) | Passed (2026-11-01, composer test 104/369): ProfileResolverTest::test_deepest_category_wins |
| CAT-035 | 3A | INT | Product in two categories of equal depth, each with a profile, lower `term_id` → P1 | Resolve | P1 (`term_id` ascending tiebreak); result is stable across calls | Passed (2026-11-01, composer test 104/369; partial: stability across repeated calls is not asserted): ProfileResolverTest::test_same_depth_uses_lowest_term_id |
| CAT-036 | 3A | INT | Product in Grandchild (no profile, no ancestor profile) and Sibling (P2) | Resolve | P2 (first branch yields nothing, next category checked) | Not run |
| CAT-037 | 3A | INT | No override, no category profiles | Resolve | `null`; spec tab shows "no profile" message; `ProductSpecs::get()` returns `[]` | Passed (2026-11-01, composer test 104/369; partial: the admin "no profile" message is not asserted): ProfileResolverTest::test_no_profile_returns_null, ProductSpecsTest::test_get_skips_value_no_longer_valid_and_returns_empty_without_profile |
| CAT-038 | 3A | INT | Override points to a deleted / trashed / non-`lpg_spec_profile` post ID | Resolve | Negative: does not return an invalid ID (falls back to category chain or null) — confirm behaviour with Lead (Q3) | Passed (2026-11-01, composer test 104/369): ProfileResolverTest::test_invalid_override_falls_back_to_category, ProfileResolverTest::test_trashed_category_profile_is_skipped_and_walk_continues (C2: falls back to the category chain) |

#### Spec values

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-040 | 3A | INT | FX-PRODUCT resolved to FX-PROFILE | Save `test_pressure`=`37`, `test_type`=`A`, `test_note`=`hello`, `test_flag` checked | Meta `_lpg_spec_test_pressure`=`37`, `_lpg_spec_test_type`=`A`, `_lpg_spec_test_note`=`hello`, `_lpg_spec_test_flag`=`yes` | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_panel_apply_saves_deletes_and_rejects, ProductSpecsTest::test_panel_save_through_woocommerce_hook_with_nonce, ProductSpecsTest::test_value_sanitisation; also CLI, AC-02 |
| CAT-041 | 3A | INT | As above | Save `test_flag` unchecked | `_lpg_spec_test_flag`=`no` (or deleted — see Q4) | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_value_sanitisation (`boolean no is a value`), ProductSpecsTest::test_panel_apply_saves_deletes_and_rejects; also CLI, AC-02 |
| CAT-042 | 3A | INT | — | Save number `2.5`, `2,5`, `abc`, `1e3`, `-1` | `2.5` stored; non-numeric `abc` rejected/not stored; `2,5` handling per Lead decision (Q5); stored form uses `.` decimal | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_value_sanitisation (2.5, 2,5, -10, 1e3, 1,000, 37 mbar, 20/21-digit cases); also CLI, AC-02 |
| CAT-043 | 3A | INT | — | Save select value `C` (not in options) and `a` (case differs) | Rejected; previous value unchanged | Passed (2026-11-01, composer test 104/369; partial: a value differing only in case is not tested in PHPUnit (it was on CLI, AC-02)): ProductSpecsTest::test_value_sanitisation (`select invalid`), ProductSpecsTest::test_panel_apply_saves_deletes_and_rejects (previous value kept) |
| CAT-044 | 3A | INT | — | Save text `<b>x</b><script>y</script>` | Stored via `sanitize_text_field` (tags stripped) | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_value_sanitisation (`text sanitised`: `<b>Brass</b>` becomes `Brass`); `<script>` covered on CLI, AC-02 |
| CAT-045 | 3A | INT | Value previously set | Save empty string | Meta `_lpg_spec_{key}` deleted (not stored empty) | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_panel_apply_saves_deletes_and_rejects; also CLI, AC-02 |
| CAT-046 | 3A | INT | Variable product with 2 variations | Save specs | Meta only on parent; none on variations | Not run |
| CAT-047 | 3A | INT | Values set | `ProductSpecs::get($id)` | Only fields with values, in profile order, each with `key,label,type,unit,value,display`; `display` for number includes unit (e.g. `37 mbar`), boolean `Yes`/`No` | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_get_returns_shape_in_profile_order_with_display_formatting |
| CAT-048 | 3A | INT | Values set; product moved to category resolving to a different profile | `ProductSpecs::get()` | Returns only fields of the new profile; old `_lpg_spec_*` meta not deleted (data retained) — confirm (Q6) | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_get_returns_shape_in_profile_order_with_display_formatting (orphan kept but hidden), AttributeSyncTest::test_tracked_attribute_removed_when_profile_changes_but_value_kept (C5) |
| CAT-049 | 3A | INT | User without `edit_post` on product | POST spec values to product save | Not saved (nonce + capability check) | Not run |
| CAT-050 | 3A | INT | — | Save product with invalid / missing nonce | Spec meta unchanged | Passed (2026-11-01, composer test 104/369): ProductSpecsTest::test_panel_save_ignored_without_valid_nonce |
| CAT-051 | 3A | INT | — | `wp_load_alloptions()` after saving specs | No `_lpg_spec_*` data in autoloaded options; specs read with one `get_post_meta($id)` call | Not run |

#### Attribute sync (filterable fields)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-060 | 3A | INT | No `pa_test_pressure` exists | Save FX-PROFILE | Global attributes `pa_test_pressure`, `pa_test_type`, `pa_test_flag` created via `wc_create_attribute`, type select, label = field label; none for `test_note` | Passed (2026-11-01, composer test 104/369; partial: only creation of the `pa_` attribute and its taxonomy is asserted; type, label and the absence of an attribute for non-filterable fields are not): ProductSpecsTest::test_save_fields_creates_missing_global_attribute |
| CAT-061 | 3A | INT | Attribute already exists | Save profile again | No duplicate attribute; existing label not changed (or as Lead specifies) | Not run |
| CAT-062 | 3A | INT | Same key `test_type` in two profiles | Save both | One shared `pa_test_type` | Not run |
| CAT-063 | 3A | INT | CAT-040 values | Save product | Product attributes include `pa_test_pressure` term `37 mbar`, `pa_test_type` term `A`, `pa_test_flag` term `Yes`; each `is_visible` false, `is_variation` false | Passed (2026-11-01, composer test 104/369): AttributeSyncTest::test_add_update_and_clear, AttributeSyncTest::test_boolean_no_is_synced_as_no_term; also CLI, AC-03 |
| CAT-064 | 3A | INT | Product already has a custom local attribute and an unrelated `pa_colour` (used for variations) | Save specs | Other attributes untouched, including variation attributes | Passed (2026-11-01, composer test 104/369; partial: local and untracked same-taxonomy attributes are preserved; a variation attribute is not tested): AttributeSyncTest::test_other_attributes_are_preserved, AttributeSyncTest::test_untracked_attribute_with_same_taxonomy_is_never_touched |
| CAT-065 | 3A | INT | `pa_test_pressure` set | Clear `test_pressure` and save | `pa_test_pressure` removed from the product; term itself may remain | Passed (2026-11-01, composer test 104/369): AttributeSyncTest::test_add_update_and_clear |
| CAT-066 | 3A | INT | Non-filterable `test_note` set | Save | No `pa_test_note` attribute created or assigned | Passed (2026-11-01, composer test 104/369): AttributeSyncTest::test_non_filterable_field_is_not_synced; also CLI, AC-03 |
| CAT-067 | 3A | CLI | Two `[DEMO]` products with different `test_type` | Shop archive with `?filter_test_type=a` (native layered nav) | Only matching product listed (native WC filtering, no custom query) | Passed (2026-11-01, CLI on dev site, `/shop/?filter_qa_kind=qa-a` lists only P2; `=qa-b` only P4; `filter_qa_flag=yes` only P2. See AC-03) |
| CAT-068 | 3A | INT | — | Value change `37` → `50` | Product now has term `50 mbar` only (old term unassigned) | Passed (2026-11-01, composer test 104/369): AttributeSyncTest::test_add_update_and_clear (37 → 30 mbar); also CLI, AC-03 |

#### Compatibility, documents, warranty, relations

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-070 | 3A | INT | Products A, B, C | Set A `_lpg_compatible_ids` = [B] | B's `_lpg_compatible_ids` contains A | Passed (2026-11-01, composer test 104/369): CompatibilityTest::test_two_way_add_and_remove, CompatibilityTest::test_save_through_process_product_meta_hook; also CLI, AC-06 |
| CAT-071 | 3A | INT | A↔B linked | Remove B from A | A removed from B; B's other links untouched | Passed (2026-11-01, composer test 104/369): CompatibilityTest::test_two_way_add_and_remove, CompatibilityTest::test_mirroring_keeps_other_links_of_the_linked_product; also CLI, AC-06 |
| CAT-072 | 3A | INT | Filter `lpg_compatibility_bidirectional` returns false | Set A → [C] | C unchanged | Passed (2026-11-01, composer test 104/369): CompatibilityTest::test_bidirectional_filter_disables_mirroring |
| CAT-073 | 3A | INT | — | Set A → [A] (self), [999999] (non-existent), [a page ID], `"abc"` | Self, non-existent and non-product IDs dropped; stored as int[] | Passed (2026-11-01, composer test 104/369; partial: self, duplicates, 0, a post ID and a missing ID are asserted; the `"abc"` input is not): CompatibilityTest::test_two_way_add_and_remove, CompatibilityTest::test_rejects_non_products |
| CAT-074 | 3A | INT | A → [B (draft), C (private), D (catalog visibility hidden), E (published)] | `Compatibility::get_ids(A)` | Returns [E] only | Passed (2026-11-01, composer test 104/369; partial: draft and catalog-hidden are asserted; private is not): CompatibilityTest::test_get_ids_returns_published_visible_only |
| CAT-075 | 3A | INT | A↔B linked | Trash/delete B | `get_ids(A)` excludes B; no fatal | Passed (2026-11-01, composer test 104/369; partial: permanent delete cleans the other products' lists and trash keeps the links; `get_ids(A)` after B is trashed is not asserted): CompatibilityTest::test_permanent_delete_removes_id_from_other_products, CompatibilityTest::test_trashing_keeps_links |
| CAT-076 | 3A | INT | A↔B linked | Mirror on save must not loop | Saving A triggers B update once; no recursion / timeout | Not run |
| CAT-077 | 3A | INT | FX-PDF | Save `_lpg_documents` = [{FX-PDF, `datasheet`, "Datasheet"}] | Stored as list of `{attachment_id:int, type, label}` | Passed (2026-11-01, composer test 104/369): DocumentsTest::test_apply_stores_on_product, DocumentsTest::test_rejects_non_pdf_attachments_and_sanitises; also CLI, AC-09 |
| CAT-078 | 3A | INT | FX-IMG | Save document pointing to a JPEG | Rejected (only `application/pdf`) | Passed (2026-11-01, composer test 104/369): DocumentsTest::test_rejects_non_pdf_attachments_and_sanitises (JPEG); also CLI, AC-09 |
| CAT-079 | 3A | INT | — | Save type `brochure`, label with HTML, non-existent attachment ID | Invalid type rejected; label sanitized; missing attachment dropped | Passed (2026-11-01; expected behaviour per Lead ruling C13: unknown type is normalised to `other`; DocumentsTest::test_unknown_type_becomes_other) |
| CAT-080 | 3A | INT | User without `upload_files` but can edit product | Add a document | Not added; existing documents unchanged | Passed (2026-11-01, composer test 104/369; partial: asserted at the `sanitize_rows(..., can_add=false)` level, not with a real user who lacks `upload_files`): DocumentsTest::test_adding_requires_upload_capability |
| CAT-081 | 3A | INT | Document saved, then attachment deleted / mime changed | `Documents::get()` | Only valid PDF attachments returned | Passed (2026-11-01, composer test 104/369): DocumentsTest::test_get_returns_valid_entries_only, DocumentsTest::test_get_skips_protected_downloads_and_sanitises_label |
| CAT-082 | 3A | INT | — | Save `_lpg_warranty` multi-line text with `<script>` | Stored via `sanitize_textarea_field` (newlines kept, tags stripped) | Passed (2026-11-01, composer test 104/369): EnquiryOnlyTest::test_product_fields_apply_and_hook |
| CAT-083 | 3A | INT | — | Set upsells and cross-sells on A | Native `_upsell_ids` / `_crosssell_ids` used; plugin adds no parallel meta | Not run |
| CAT-084 | 3A | INT | — | Assign `product_brand` term to product | Native WC brand stored; no `_lpg_manufacturer` meta written | Not run |
| CAT-085 | 3A | MAN | Admin | Product edit: "LPG Specifications" tab renders profile fields; "Linked Products" tab has compatibility product-search select; General tab has warranty + enquiry-only; documents meta box with media picker | All present, labelled, save correctly | Not run |

#### Enquiry-only rule

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-090 | 3A | INT | FX-PRODUCT | Set `_lpg_enquiry_only`=`yes` | `is_purchasable()` false; `EnquiryOnly::is_enquiry_only()` true; `_price`/`_regular_price` unchanged in DB | Passed (2026-11-01, composer test 104/369): EnquiryOnlyTest::test_flagged_product_is_not_purchasable_and_price_unchanged; also CLI, AC-07 |
| CAT-091 | 3A | INT | `_lpg_enquiry_only`=`no`, price empty | Check | Not purchasable; enquiry-only true | Passed (2026-11-01, composer test 104/369): EnquiryOnlyTest::test_empty_price_is_enquiry_only; also CLI, AC-07 |
| CAT-092 | 3A | INT | `no`, price 1000 | Check | Purchasable | Passed (2026-11-01, composer test 104/369): EnquiryOnlyTest::test_normal_priced_product_is_purchasable; also CLI, AC-07 |
| CAT-093 | 3A | INT | Price `0` | Check | Purchasable (0 is not empty) — confirm (Q7) | Passed (2026-11-01, composer test 104/369): EnquiryOnlyTest::test_zero_price_is_not_empty; also CLI, AC-07 |
| CAT-094 | 3A | INT | Filter `lpg_is_enquiry_only` returns false for a `yes` product, and true for a normal one | Check | Filter result wins; receives `(bool, WC_Product)` | Passed (2026-11-01, composer test 104/369; partial: only the false→true direction is asserted; true→false and the `(bool, WC_Product)` arguments are not): EnquiryOnlyTest::test_filter_can_override |
| CAT-095 | 3A | INT | Variable product, one variation with empty price | Check variation and parent | Variation not purchasable; behaviour of parent per Lead (Q8) | Passed (2026-11-01, composer test 104/369; partial: the parent flag applying to a variation (C7) is asserted; a variation with an empty price is not tested): EnquiryOnlyTest::test_parent_flag_applies_to_variations |
| CAT-096 | 3A | CLI | Enquiry-only `[DEMO]` product | Store API `POST /wp-json/wc/store/v1/cart/add-item` with its ID; also `?add-to-cart=ID` | Rejected with error; cart unchanged | Passed (2026-11-01, CLI on dev site, Store API add-item → 400 `woocommerce_rest_product_not_purchasable` for flagged and empty-price products; `?add-to-cart=21` leaves cart empty. See AC-07) |
| CAT-097 | 3A | INT | Product in cart, then switched to enquiry-only | Load cart / checkout | Item removed or checkout blocked by WC purchasable check | Not run |

#### Seed command

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-100 | 3A | INT/CLI | Clean test DB | Run `SeedCommand` (CLI: `wp lpg seed`) | Profiles Regulator, Burner, Gas Cooker, Cylinder created with `_lpg_fields` only; zero products and zero `_lpg_spec_*` values created | Passed (2026-11-01, composer test 104/369; partial: zero `_lpg_spec_*` values is not asserted explicitly (0 products is)): CatalogueSetupTest::test_seed_creates_definitions_only_and_maps_categories, CatalogueSetupTest::test_seed_is_idempotent |
| CAT-101 | 3A | INT | Clean DB | Run seed | §4.1 `product_cat` tree created; term meta `lpg_spec_profile_id`: Regulators→Regulator, Burners→Burner, Cookers & Ovens→Gas Cooker, Cylinders→Cylinder | Passed (2026-11-01, composer test 104/369; partial: 11 categories created and 4 assignments counted, but only the Regulators → Regulator mapping is asserted by name): CatalogueSetupTest::test_seed_creates_definitions_only_and_maps_categories, CatalogueSetupTest::test_seed_is_idempotent |
| CAT-102 | 3A | INT | FX-SEEDED | Run seed again | Same counts of profiles, terms, term meta; no duplicates; exit success | Passed (2026-11-01, composer test 104/369): CatalogueSetupTest::test_seed_is_idempotent; also CLI, AC-10 |
| CAT-103 | 3A | INT | FX-SEEDED; admin edited Regulator fields and renamed a category | Run seed again | Edits preserved (not overwritten) — confirm (Q9) | Passed (2026-11-01, composer test 104/369; partial: existing field definitions and category assignment are preserved; a renamed category is not tested): CatalogueSetupTest::test_seed_never_overwrites_existing_assignment_or_profile |
| CAT-104 | 3A | INT | Plugin activated / admin page loaded | No seed command run | No profiles or categories created automatically | Not run |
| CAT-105 | 3A | INT | Non-CLI request | Check `WP_CLI` undefined | `Cli\SeedCommand` not registered | Not run |
| CAT-106 | 3A | INT | Seeded profiles | Inspect `_lpg_fields` | Field definitions match the brief exactly; no extra invented fields, units or values (R4) — Lead to supply the reference list | Passed (2026-11-01, composer test 104/369; partial: only the Regulator key list is asserted; Burner, Gas Cooker and Cylinder are not): CatalogueSetupTest::test_seed_creates_definitions_only_and_maps_categories |

#### Uninstall (§11.10)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| CAT-110 | 3A | INT | Data present; `delete_data_on_uninstall` on | Run uninstall routine | `lpg_spec_profile` posts, all `_lpg_*` post meta, `lpg_spec_profile_id` term meta, `lpg_segment` terms deleted; `pa_*` attributes kept; products, categories, orders kept | Not run |
| CAT-111 | 3A | INT | Setting off | Run uninstall | Options and caps removed; all business data kept | Not run |

### 2.2 Catalogue blocks (Phase 3B)

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| BLK-001 | 3B | INT | `build/` present | `WP_Block_Type_Registry` | `lpg/spec-table`, `lpg/compatible-products`, `lpg/product-documents` registered from the metadata collection manifest; dynamic (render callback); `usesContext` includes `postId` | Passed (2026-11-01, composer test 104/369; partial: registration, render callback and `usesContext` are asserted, but no test explicitly asserts registration through the built manifest (`register_blocks()` takes that path only when `build/blocks-manifest.php` exists); `usesContext` is `[postId, postType]`): BlocksTest::test_registry_registers_blocks_from_source_without_editor_script, BlocksTest::test_registry_register_blocks_registers_all_three, BlocksTest::test_block_json_files_are_valid; also dev site render, see AB-08 (built manifest present on dev site) |
| BLK-002 | 3B | INT | Product with specs | Render `lpg/spec-table` with postId | `<table>` with class `lpg-spec-table`, default heading "Technical specifications", one row per `ProductSpecs::get()` entry in order | Passed (2026-11-01, composer test 104/369): BlocksTest::test_spec_table_rows_order_and_display, BlocksTest::test_render_block_with_post_id_context_produces_table; also dev site render, see AB-01 |
| BLK-003 | 3B | INT | Product without specs / no profile | Render | Empty string (no heading, no wrapper) | Passed (2026-11-01, composer test 104/369): BlocksTest::test_renderers_return_empty_without_data; also dev site render, see AB-04 |
| BLK-004 | 3B | INT | Spec label/value/heading contain `<script>`, `"onmouseover=` | Render | All output escaped | Passed (2026-11-01, composer test 104/369): BlocksTest::test_spec_table_escapes_raw_meta, BlocksTest::test_compatible_products_escape_names_and_heading, BlocksTest::test_documents_escape_labels_and_urls; also dev site render, see AB-07 |
| BLK-005 | 3B | INT | No postId context / postId of a page | Render each block | No output, no PHP warning | Passed (2026-11-01, composer test 104/369): BlocksTest::test_renderers_return_empty_without_a_product; also dev site render, see AB-08 |
| BLK-006 | 3B | INT | A with 10 compatible products | Render `lpg/compatible-products` defaults | 8 cards, 4 columns; each has image, name, price; enquiry-only items show "Enquire" instead of price/add-to-cart | Passed (2026-11-01, composer test 104/369; partial: cards with image, name, price and the Enquire label are asserted; the 10-linked → 8-card default is not): BlocksTest::test_compatible_products_cards, BlocksTest::test_compatible_products_show_enquire_for_enquiry_only, BlocksTest::test_render_block_compatible_products_applies_supports_and_columns; also dev site render, see AB-03 |
| BLK-007 | 3B | INT | — | Render with `columns` 1, 7, `limit` 0, 13, `"abc"` | Clamped to 2–6 / 1–12 or attribute rejected by schema | Passed (2026-11-01, composer test 104/369): BlocksTest::test_compatible_products_limit_and_columns_are_clamped; also dev site render, see AB-08 |
| BLK-008 | 3B | INT | Compatible list contains draft/hidden products | Render | Only published, visible products | Passed (2026-11-01, composer test 104/369; partial: only a draft is linked in the render test; hidden is covered at the `get_ids` level by CompatibilityTest::test_get_ids_returns_published_visible_only): BlocksTest::test_compatible_products_empty_when_only_hidden_or_draft_products_are_linked, BlocksTest::test_draft_product_renders_only_for_users_who_can_edit_it; also dev site render, see AB-03 |
| BLK-009 | 3B | INT | Product with 2 PDFs (datasheet, manual) + 1 invalid entry | Render `lpg/product-documents` | List of 2 links with type label and human file size; invalid entry skipped; links point to attachment URL | Passed (2026-11-01, composer test 104/369): BlocksTest::test_documents_list_only_pdfs_with_type_and_size, BlocksTest::test_documents_empty_when_only_non_pdfs_are_stored; also dev site render, see AB-02/AB-08 |
| BLK-010 | 3B | MAN | Editor | Insert each block in single-product template | ServerSideRender preview shows; no "invalid content"; block attributes editable | Passed (2026-11-01, Chrome via Claude in Chrome. Post editor: all 3 blocks found by inserter search "LPG"; `lpg/spec-table` and `lpg/compatible-products` inserted, `isValid` true, ServerSideRender returns empty for a post so the `EmptyResponsePlaceholder` shows; Heading TextControl present; columns/limit RangeControls write 6/1 and typed 20 is clamped to 12. Site Editor single-product template: 53 blocks, 0 invalid, 0 `core/missing`, 0 warnings, LPG blocks in §11.9 order. Template not saved, 0 dirty entities. See BR-B7/BR-B8) |
| BLK-011 | 3B | CLI | Built assets | `ls build/blocks/*`; front-end page with no LPG block | Built files committed; no LPG block JS/CSS enqueued on pages that don't use the blocks | Passed (2026-11-01, dev site, partial: `build/blocks/{spec-table,compatible-products,product-documents}` present with `style-index.css` for the two styled blocks; repo is not a git repo here, so "committed" was not verified. `/` and `/shop/` load no LPG block CSS or JS; only the theme's `lpg-blocks.css` loads site-wide (C15 note, Phase 4). See AB-09) |
| BLK-012 | 3B | MAN | Theme switched to Twenty Twenty-Five | View product with blocks | Blocks still render (A2) | Not run, deferred to Phase 7 (switching the live theme is out of scope for the Phase 3 browser run) |

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
| SEC-002 | 3A | INT | Product save | Forged POST without nonce for specs, compatibility, documents, warranty, enquiry-only | Nothing saved | Passed (2026-11-01, composer test 104/369; partial: a forged nonce is asserted only for the spec panel; compatibility, documents, warranty and enquiry-only are not): ProductSpecsTest::test_panel_save_ignored_without_valid_nonce |
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
| PERF-003 | 3A/7 | CLI | Plugin active | `wp db query "SELECT option_name, LENGTH(option_value) FROM wp_options WHERE autoload IN ('yes','on','auto-on','auto') AND option_name LIKE 'lpg%'"` | Only the WhatsApp-number entry (if split) autoloaded; `lpg_settings` not autoloaded; total autoload size recorded | Passed (2026-11-01, CLI on dev site, `lpg_commerce_version` and `lpg_settings` both autoload `off`; 0 autoloaded `lpg%` options; site autoload 297 options / 65,534 bytes. See AC-11) |
| PERF-004 | 4/7 | MAN | — | Lighthouse mobile on home and product | LCP < 2.5 s, CLS < 0.1, total page weight recorded; images lazy, sized, responsive `srcset` | Not run |
| PERF-005 | 4/7 | CLI | — | Count enqueued JS/CSS on home | No jQuery from theme; LPG block assets only where used | Not run |
| PERF-006 | 3A | INT | 200 products | Save a profile with filterable fields | Completes within PHP `max_execution_time` 30 s and 256M; does not resave all products on page load | Not run |
| PERF-007 | 7 | CLI | Production-like profile: `memory_limit=128M`, no object cache, `DISABLE_WP_CRON` + real cron | Browse key pages, place order, submit RFQ | No memory errors; emails sent inline | Not run |
| PERF-008 | 8 | MAN | Page cache plugin/LiteSpeed on host | Cart, checkout, my-account, REST excluded from cache; nonce pages not cached stale | Correct behaviour | Not run |

### 2.8 SEO

| ID | Phase | Level | Preconditions | Steps | Expected | Status |
|---|---|---|---|---|---|---|
| SEO-001 | 3A | INT | Product with specs (number with unit, select, boolean, text) | Apply `woocommerce_structured_data_product` filter (`WC_Structured_Data::generate_product_data`) | One `additionalProperty` `PropertyValue` per spec with `name` = label, `value`, `unitText` = unit when present | Passed (2026-11-01, composer test 104/369): StructuredDataTest::test_adds_property_values_and_keeps_existing_markup, StructuredDataTest::test_non_finite_number_falls_back_to_string; also CLI front end, AC-08 |
| SEO-002 | 3A | INT | Product without specs / no profile | Generate | No `additionalProperty` key added; rest of WC markup unchanged | Passed (2026-11-01, composer test 104/369): StructuredDataTest::test_no_specs_leaves_markup_untouched, StructuredDataTest::test_password_protected_product_adds_no_properties_until_password_is_entered |
| SEO-003 | 3A | INT | Product that already has other markup keys | Generate | Plugin adds nothing besides `additionalProperty` (offers, brand, etc. untouched) | Passed (2026-11-01, composer test 104/369): StructuredDataTest::test_adds_property_values_and_keeps_existing_markup |
| SEO-004 | 3A | INT | Spec values containing quotes, `</script>` | Generate + render JSON-LD | Valid JSON; no script break-out | Passed (2026-11-01, CLI on dev site, `"` JSON-escaped; `</script>` value injected through WC REST `meta_data` is stripped on read; JSON-LD parses. See AC-08 and DEF-002) |
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

### 2.11 Phase 3A acceptance run on the dev site (CLI), 2026-11-01

Run by lpg-qa against http://gas-retailer.test (`gas_retailer_db`) without `composer test`, because the test DB was in use. The writes used the admin save code paths through `wp eval` / `wp eval-file --user=1`: `ProfilePostType::save_fields()`, `ProductSpecsPanel::apply()` + `ProductFields::apply()` + `$product->save()` (so `AttributeSync` ran on `woocommerce_before_product_object_save`), `Compatibility::save()`, `Documents::apply()`/`sanitize_rows()`, `wp post term set`, `wp wc product update`, and `wp media import`. The front end and Store API were checked with curl.

**Fixtures** (all named `[QA TEST]`, test-only values): profile #20 with `qa_rating` (number, unit `qau`, filterable), `qa_kind` (select `QA-A`/`QA-B`, filterable), `qa_note` (text) and `qa_flag` (boolean, filterable). Also subcategory #30 under Regulators, brand #31, and four products: P1 #21 (Regulators, enquiry-only flag, 1000), P2 #22 (Regulators + override #20, 1000, specs), P3 #23 (subcategory, empty price) and P4 #24 (Regulators + override #20, price 0). There were also PDF #25 and PNG #26. Seeded profiles, categories and segments were not changed.

| ID | Covers | Evidence (key output) | Status |
|---|---|---|---|
| AC-01 | Profile resolution: CAT-030, CAT-032 | `ProfileResolver::for_product`: P1 (Regulators, no override) → 16; P2 (override 20) → 20; P3 (only in subcategory #30, no term meta) → 16 | Passed (2026-11-01) |
| AC-02 | Spec values per §11.3/C3/C4: CAT-040..045 | `2,5`→`2.5`; `1e3` and `1,000` gave 1 error each and kept `2.5`; `-1.5` stored; `QA-C`/`qa-a` gave errors and kept `QA-A`; `maybe` gave an error; `yes`/`no` stored; `''` deleted the meta row; text tags stripped | Passed (2026-11-01) |
| AC-03 | Attribute sync and layered nav: CAT-063, 066, 067, 068 | `pa_qa_rating`/`pa_qa_kind`/`pa_qa_flag` were created with the profile. P2 attributes: `2.5 qau`, `QA-A`, `Yes`, all `visible=false variation=false`. `_lpg_synced_attributes` lists the 3. `/shop/?filter_qa_kind=qa-a` → P2 only; `=qa-b` → P4 only; `filter_qa_flag=yes` → P2 only; `filter_qa_kind=qa-a,qa-b&query_type_qa_kind=or` → P2 and P4. WC 11.2 syntax `filter_<slug without pa_>=<term-slug>` (attribute lookup table off: `woocommerce_attribute_lookup_enabled=no`) | Passed (2026-11-01) |
| AC-04 | `lpg_segment`: CAT-004 (partial) | `wp post term set 22 lpg_segment household` → `/segment/household/` 200 lists P2; `/segment/commercial/` 200 and doesn't list it | Passed (2026-11-01) |
| AC-05 | `product_brand` | `wp post term set 22 product_brand qa-test-brand` → `/brand/qa-test-brand/` 200 lists P2 | Passed (2026-11-01) |
| AC-06 | Two-way compatibility: CAT-070, 071 | `save(21,[22,24])` → 21 `[22,24]`, 22 `[21]`, 24 `[21]`; `get_ids(22)` `[21]`; `save(21,[24])` → 22 `[]`; `save(21,[])` → all `[]` | Passed (2026-11-01) |
| AC-07 | Enquiry-only: CAT-090..093, 096 | `is_purchasable`: P1 flagged false, P3 empty price false, P4 price 0 true, P2 true; `_regular_price` of P1 still `1000`. Store API (Nonce header from GET `/cart`): add-item P1 and P3 → HTTP 400 `woocommerce_rest_product_not_purchasable`; P4 and P2 → 201. `?add-to-cart=21` → cart empty. Single-product pages of P1/P3 show no add-to-cart form; P2/P4 do | Passed (2026-11-01) |
| AC-08 | Structured data: SEO-001, SEO-004 | `/product/qa-test-p2/` has 1 `ld+json` block. Product `additionalProperty`: `[QA TEST] Rating` 2.5 `unitText` `qau`, `Kind` "QA-A", `Note` "qa \"quoted\"", `Flag` true. After `wp wc product update 22 --meta_data` wrote `QA x</script><script>alert(1)</script> & y` raw to `_lpg_spec_qa_note`: the page contains 0 `alert(1)`, the JSON-LD parses, and the value is `"QA x &amp; y"` (tags stripped by `ProductSpecs::get` read-time sanitising; WC also uses `JSON_HEX_TAG`). `&amp;` is DEF-002 (upstream). An external schema validator (SEO-005) was not run | Passed (2026-11-01) |
| AC-09 | Documents: CAT-077, 078, 079 | `sanitize_rows([PNG #26])` → `[]`. `apply` with [PNG, PDF #25 type `brochure` label `<b>[QA TEST]</b> Datasheet`, missing 999999] → stored and `get()` = `[{"attachment_id":25,"type":"other","label":"[QA TEST] Datasheet"}]`. `can_add=false` with a new PDF → `[]` | Passed for the PDF-only rule; type handling is DEF-001 |
| AC-10 | Seed idempotent: CAT-102 | `wp lpg seed` exit 0: "Profiles: 0 created, 4 already present. Categories: 0 created, 11 already present. Default profiles: 0 assigned, 4 left unchanged." Term meta 22→16, 21→17, 20→18, 19→19 unchanged | Passed (2026-11-01) |
| AC-11 | Performance spot check: PERF-003, plus PERF-001/002 baselines | Autoload: `lpg_commerce_version` off, `lpg_settings` off (0 autoloaded `lpg%`); site total 297 autoloaded options, 65,534 B. Queries (SAVEQUERIES via `wp --exec`, `$wp->main()` + template-loader render, 4 test products): `/shop/` 14 for the main query and 40 in total after bootstrap (21.2 ms SQL); `/product/qa-test-p2/` 55 after bootstrap (45.5 ms SQL). Bootstrap in WP-CLI is 44. TTFB ×5 (warm): `/` 0.452–0.561 s (median 0.478); `/shop/` 0.527–0.642 s (median 0.552); `/product/qa-test-p2/` 0.552–0.756 s (median 0.597) | Recorded (baseline; no threshold in 3A) |
| AC-12 | Single product page | P1–P4 `/product/qa-test-pN/` all 200 with 0 PHP notice/warning strings in the HTML. No new `logs/debug.log` entries from HTTP requests. The only 2 new lines are `Undefined array key "SERVER_NAME"` from the CLI render simulation in AC-11, not the site. LPG blocks are not placed yet (no `lpg-spec-table`/`wp-block-lpg` markup; only the theme's `lpg-blocks.css`), which is expected before 3B | Passed (2026-11-01) |

**Cleanup (verified 2026-11-01):** I force-deleted posts 20–26 and terms 30 and 31, deleted attributes `pa_qa_*` (11–13) with `wc_delete_attribute`, and deleted the guest WC session my Store API cart created (after emptying the cart). Afterwards: 0 products and 0 variations; 0 posts and 0 terms named `[QA TEST]`/`qa-test`; 0 `pa_qa_*` term taxonomies; 0 orphan postmeta, termmeta and term relationships; `wc_product_meta_lookup` 0 rows; attachments only #8 (placeholder); 4 seeded profiles, 12 categories, 0 brands, 10 attributes (pre-existing); no `qa-test` files left in uploads.

**Not run in this pass:** everything that needs INT (`composer test`) or MAN, plus CAT-031 (`0` override), CAT-033..038, CAT-047/048, CAT-064/065, CAT-072..076, CAT-080/081 (real user without `upload_files`), CAT-084 (`_lpg_manufacturer` absence check), CAT-094/095/097, SEC-*, SEO-002/003/005/006.


### 2.12 Phase 3B acceptance run on the dev site (rendered blocks, CLI + curl), 2026-11-01

Run by lpg-qa against http://gas-retailer.test with `build/blocks` present. There was no browser, so the checks are at HTML level with curl (logged out) and `wp eval-file --user=1`, and `composer test` was not run. The writes used the save code paths: `ProductSpecsPanel::apply()` + `ProductFields::apply()` + `$product->save()`, `Documents::apply()`, `Compatibility::save()`, `set_post_password()` + `save()` and `wp media import`. Raw (REST-like) data was written with `update_post_meta` for AB-07 only. The single-product template places `lpg/spec-table`, `woocommerce/product-details`, `lpg/product-documents` and `lpg/compatible-products`.

**Fixtures** (all named `[QA TEST]`, test-only values, under Regulators / seeded profile #16): S #33 "Spec Regulator" (1000; `regulator_type` Low pressure, `operating_pressure` "[QA TEST] op value", `outlet_pressure` `12,5`→12.5, `flow_rate` 0.75, inlet/outlet "[QA TEST] …"; datasheet PDF #27; linked to N, E and D). N #29 (500, image #28 PNG), E #30 (enquiry-only), D #31 (draft, with spec and document) and X #32 (no specs, docs or links). Also manual PDF #34 (AB-08). The filterable fields created 4 `pa_` terms (#38–41). Seeded profiles, categories and segments were not changed.

| ID | Covers | Evidence (key output) | Status |
|---|---|---|---|
| AB-01 | Spec table: BLK-002 | `/product/qa-test-spec/` 200. `<section class="lpg-spec-table wp-block-lpg-spec-table">` → `h2.lpg-spec-table__heading` "Technical specifications", `table.lpg-spec-table__table` with `caption.screen-reader-text` "Technical specifications", 6 rows `__row--{key}` in profile order (Regulator type, Operating pressure, Outlet pressure `12.5 mbar`, Flow rate `0.75 kg/h`, Inlet connection, Outlet connection), each `th[scope=row]`. Byte offsets: add-to-cart form 87753 < product-meta 88566 < **spec table 89020** < **product-details 90493** < documents 93738 < compatible 94373 | Passed (2026-11-01) |
| AB-02 | Documents: BLK-009 | S: `section.lpg-product-documents`, h2 "Product documents", `li.__item--datasheet` → `a[href=…/qa-test-datasheet.pdf][target=_blank][rel=noopener]`, label "[QA TEST] Datasheet", meta "(Datasheet, PDF, 329 B)", `span.screen-reader-text` " (opens in a new tab)". N, E and X (no documents): 0 `lpg-product-documents` and no "Product documents" heading | Passed (2026-11-01) |
| AB-03 | Compatible products: BLK-006, BLK-008 | S: `<section style="--lpg-columns:4" class="lpg-compatible-products …">`, 2 cards. N: `img` (#28, alt "[QA TEST] image alt"), name, price `KSh 500`. E: placeholder image, name, `span.__price--enquire` "Enquire" and no price. Draft D: 0 refs (`qa-test-draft`, name and its document all absent). X (no links): 0 `lpg-compatible-products` | Passed (2026-11-01) |
| AB-04 | Empty product: BLK-003 | X `/product/qa-test-empty/` 200: 0 `lpg-spec-table`/`lpg-product-documents`/`lpg-compatible-products`; 0 "Technical specifications"/"Product documents"/"Compatible products"; 0 empty `<h2>`. (N/E also have no spec section, as they have no spec values) | Passed (2026-11-01) |
| AB-05 | Password protection (C15 M1/SD1) | Password set on S. Logged-out curl 200: `form.post-password-form` present; 0 `lpg-spec-table`/`lpg-product-documents`/`lpg-compatible-products`, 0 spec values, 0 PDF URL. JSON-LD has only `BreadcrumbList` (no Product node, so no `additionalProperty`); unprotected baseline had 6 `PropertyValue`s. After POST to `wp-login.php?action=postpass` (cookie): all 3 sections and the spec JSON-LD return. Password removed afterwards (`post_password` `''`, no form). Observation: N's compatible card still lists protected S (name, price, link), like WooCommerce's own product grids; see §5 | Passed (2026-11-01) |
| AB-06 | Draft product (C15 H1) | Logged out: `/product/qa-test-draft/`, `/?p=31` and `/?post_type=product&p=31` → 404; 0 LPG block markup, 0 draft name/spec/document strings (only the theme's `lpg-storefront` stylesheet handle). CLI render of each block for #31 as user 0 → 0 bytes | Passed (2026-11-01) |
| AB-07 | Escaping on a rendered page: BLK-004 | `update_post_meta`: `_lpg_spec_inlet_connection` = `<script>alert(1)</script>"onmouseover=qa3b() x`; `_lpg_spec_outlet_connection` = `</td></tr><script>alert(1)</script><img src=x onerror=alert(1)>" onmouseover="qa3b()`; `_lpg_documents` label `<script>alert(1)</script>"onmouseover=qa3b() [QA TEST] doc`, type `datasheet" onmouseover="qa3b()`. Page: 0 `alert(1)`, 0 `onerror`, 0 `onmouseover` inside any tag, outside JSON-LD 0 raw `"onmouseover=`; cells and label show `&quot;onmouseover=…`; the type became `__item--other` (C13). The 2 `"onmouseover=` hits are JSON-escaped `\"` values inside the `ld+json` script (inert data) | Passed (2026-11-01) |
| AB-08 | CLI render checks: BLK-001, 005, 007, 009 | See the BLK rows. `heading: ""` → 0 `<h2>` | Passed (2026-11-01) |
| AB-09 | Assets: BLK-011 | Product page: block CSS is **inlined** by core as `<style id="lpg-spec-table-style-inline-css">` / `lpg-compatible-products-style-inline-css` (content identical to `build/blocks/*/style-index.css` + `sourceURL` comment), plus `<link id="lpg-storefront-blocks-css">` theme `lpg-blocks.css`. URLs: `build/blocks/spec-table/style-index.css` 200 (100 B), `compatible-products/style-index.css` 200 (373 B), `lpg-blocks.css` 200 (3176 B). `/` and `/shop/` (and X, where all blocks render empty): no block style, 0 `lpg-commerce-core` scripts; `lpg-blocks.css` loads on every page (C15 note). `lpg/product-documents` has no style handle | Passed (2026-11-01) |
| AB-10 | Health | 0 PHP notice/warning/fatal markup on all 10 fetched pages (the only "warning:" text is the CSS token `--wp--preset--color--warning`). `logs/debug.log` 56 lines before and after: 0 new lines, no `SERVER_NAME` warnings this run. TTFB `/product/qa-test-spec/` warm ×3: 0.536 / 0.552 / 0.565 s, median **0.552 s** (3A baseline 0.597 s) | Passed (2026-11-01) |
| AB-11 | Editor and viewport: BLK-010, BLK-012, 360 px layout, `--lpg-columns` grid rendering | Covered by the browser run in §2.13 (BLK-010 passed; viewport checked at 375/768/1280; BLK-012 deferred to Phase 7) | See §2.13 |

**Cleanup (verified 2026-11-01):** I force-deleted products 29–33 and attachments 27, 28 and 34, and deleted the `pa_` terms 38–41. Afterwards: 0 products and 0 variations; 0 posts and 0 terms named `[QA TEST]`/`qa-test`; 0 `pa_` terms; 10 attributes, none `qa*`; attachments only #8; 0 orphan postmeta, termmeta, term relationships and term taxonomy rows; `wc_product_meta_lookup` 0 rows; 0 `qa-test` files in uploads; 4 profiles, 12 categories and 3 segments unchanged, with the category profile meta still 16–19.

### 2.13 Phase 3 browser checks (Chrome via Claude in Chrome), 2026-11-01

Run by lpg-qa in the user's Chrome (logged in as admin, own tab, closed afterwards) against http://gas-retailer.test.

**Harness limits:**
- `resize_window` reported success 3 times but the viewport stayed at 1680 px, so I stopped retrying. Widths 375/768/1280 were tested by loading the product page in a same-origin `<iframe>` of exactly that width inside the tab. Media queries apply to the iframe's own viewport (`innerWidth` confirmed 375/768/1280). Native 1680 was also measured.
- `gif_creator` captured 0 frames on 2 attempts, so there is no `lpg-product-responsive.gif`. Screenshots are the evidence instead.
- Screenshots (25) were saved in this session's scratchpad `browser3c/` (temporary, not committed).

**Fixtures** (`[QA TEST]`, test-only values, Regulators / seeded profile #16): S #44 "Spec Regulator" (6 spec values as in §2.12, image #37 PNG, datasheet PDF #35 + manual PDF #36, compatible with #38–#43), Linked 1–6 #38–#43 (prices 100–600, odd ones with an image, #43 enquiry-only), and profile #45 "[QA TEST] Profile" (3 text/number fields). The Regulator filterable fields created `pa_` terms #42–44. The editor autosaved the test post as draft #46, and WP auto-created an empty `wp_global_styles` #47 ("Custom Styles", `{"version":3,"isGlobalStylesUserThemeJSON":true}`) while I was in the editors.

| ID | Check | Evidence (key output) | Status |
|---|---|---|---|
| BR-A1 | No horizontal page scroll at 375/768/1280 | `scrollWidth <= clientWidth` true at all three widths: 375/375, 768/768, 1280/1280 (native 1680/1680 too) | Passed (2026-11-01) |
| BR-A2 | Spec table layout | 375: `th` `display:block`, the value cell sits below the label (stacked), `th` 333/335 px. 768: table-cell, `th` 288/722 px = **39.9 %**. 1280: `th` 304/760 px = **39.9 %**. Odd rows `rgb(243,245,247)`, even transparent (striping); table border and row borders `1px solid rgb(203,210,217)` visible in screenshots at all widths | Passed (2026-11-01) |
| BR-A3 | Compatible-products grid | `--lpg-columns:4`, `display:grid`. 375: 2 tracks (159.5 px ×2), 2 cards in the first row. 768: 4 tracks. 1280: 4 tracks (178 px), 4 cards + 2 cards on the next row. All 6 cards are the same height per width (212/221/231 px). Images square (134/142/152 px), `object-fit:contain`, not distorted (the 64 px test PNG is scaled up). Enquiry-only #43 shows "Enquire" | Passed (2026-11-01) |
| BR-A4 | Documents | Each link has a `::before` "PDF" badge (screenshot: white text on a dark orange label). Meta "(Datasheet, PDF, 329 B)" is `rgb(74,85,98)` at 14 px, against the link's `rgb(154,52,18)`, so it is muted. Note: the meta sits inside the `<a>`, so it is underlined with the link; at 375 px the first row's meta wraps under the label | Passed (2026-11-01) |
| BR-A5 | Keyboard focus | Real Tab key presses: document link `lpg-product-documents__link` → `:focus-visible` true, outline `2px solid rgb(194,65,12)`, offset 2px. Two more Tabs → `lpg-compatible-products__link` (Linked 1), same outline around the card link. Visible in screenshots | Passed (2026-11-01) |
| BR-A6 | Console on the product page | After a reload with tracking on: 0 errors, 0 warnings, 0 deprecations. Only `JQMIGRATE: Migrate is installed` (log level) | Passed (2026-11-01) |
| BR-B7 | Block editor (new post) | Inserter search "LPG" → Compatible products, Product documents, Product specifications (+ theme pattern "Hero"). Inserted spec-table: inspector "Heading" TextControl (default "Technical specifications", help "Leave empty to hide the heading."); preview = empty-state placeholder (SSR empty for a post). Inserted compatible-products: Heading + Columns + Maximum number of products RangeControls; slider to max/min → `columns:6`, `limit:1`; typed `20` → `limit:12` (clamped). Both `isValid:true`. Console: 0 errors; 1 core warning `global-styles-css-custom-properties-inline-css was added to the iframe incorrectly` (WP core handle, `wp-includes/script-loader.php:3254`, not LPG). Not published; WP autosaved draft #46 (deleted with WP-CLI) | Passed (2026-11-01) |
| BR-B8 | Site Editor, single-product template | `site-editor.php?p=/wp_template/lpg-storefront//single-product`: 53 blocks, 0 `isValid:false`, 0 `core/missing`, 0 `.block-editor-warning`, no "unexpected or invalid content" / "doesn't include support" text. Order: add-to-cart-form → product-meta → **lpg/spec-table** → woocommerce/product-details → **lpg/product-documents** → **lpg/compatible-products**. Each LPG block shows its placeholder (no product context). 0 dirty entities; **not saved**; 0 `wp_template` / `wp_template_part` rows afterwards. Console: 2 deprecations, neither from LPG code (`wc.wcEntities.useProduct()` from WooCommerce 11.1; tooltip `position` prop from core/WC; no `Tooltip`/`position=` in LPG blocks or admin JS) | Passed (2026-11-01) |
| BR-C9 | Spec-profile admin JS (profile #45) | Add field → a 4th empty row. Remove on `qa_beta` → row gone. Up on `qa_gamma` → order gamma/alpha/…; Down → alpha/gamma. Type → Select reveals the "Options" textarea (hidden for text/number). Saved with `qa_gamma` options "QA One/QA Two" and a row key `Bad Key`. **Client side:** the key input has `pattern="[a-z][a-z0-9_]{0,27}"`, so the browser blocks submit ("Please match the requested format."). **Server side** (form set to `noValidate` to simulate a crafted POST): red notice "Some field rows were not saved: Row 3 (Bad Key): the key must start with a lowercase letter…" + "Post updated."; stored `_lpg_fields` = `qa_alpha` (text) and `qa_gamma` (select, `["QA One","QA Two"]`). Bad row dropped (C1). No `confirm()`/`alert()` in `assets/admin/*.js`. Console clean | Passed (2026-11-01) |
| BR-C10 | Product admin (S #44) | **LPG Specifications tab:** "Profile: Regulator" note + 7 fields (Regulator type select = Low pressure, Operating pressure, Outlet pressure (mbar) 12.5, Flow rate (kg/h) 0.75, Inlet/Outlet connection, Compatible applications). **Documents meta box:** 2 rows (Datasheet/Manual selects, labels, Remove links); "Add PDF document" opens the media modal "Choose PDF documents"; the Media Library tab lists only the 2 PDFs (library `type: application/pdf`; PNG #37 and placeholder #8 hidden); closed with ×, rows still 2. **Compatible products search:** the field is initialised (`wc-product-search … enhanced`, `action=woocommerce_json_search_products`, `data-exclude=44`, 6 existing selections shown as chips). Typing in the browser did not produce suggestions (select2 stayed on "Please enter 1 or more characters"), and **the native WooCommerce Cross-sells field behaved identically**, so this is a harness/synthetic-input limit, not an LPG defect. The same AJAX call with the page nonce (`term=QA`, `exclude=44`) → 200 with the 6 linked products. Product not saved (selection unchanged 38–43). Console clean | Passed (2026-11-01), partial: suggestion dropdown not observed in the UI (harness); backend and field config verified |

**Not checked:** BLK-012 (theme switch, deferred to Phase 7); the suggestion dropdown rendering in the compatible-products select2 (needs a manual check by a human or Playwright in Phase 7); the responsive check via a real window resize (iframe used instead); a GIF recording.

**Cleanup (verified 2026-11-01):** force-deleted products 38–44, profile 45, draft post 46, auto-created `wp_global_styles` 47 and attachments 35–37; deleted `pa_` terms 42–44. Afterwards: 0 products/variations; 0 posts with ID > 19; 0 posts or terms named `[QA TEST]`/`qa-test`/`qa*`; 0 `pa_` terms; 10 attributes, none `qa*`; attachments only #8; 0 orphan postmeta, termmeta and term relationships; `wc_product_meta_lookup` 0 rows; 0 `wp_template`/`wp_template_part`/`wp_global_styles` rows; 0 `qa-test` files in uploads; profiles 16–19 (unmodified, 12:57:08), 12 categories and 3 segments unchanged, category profile meta 16–19. `logs/debug.log` 56 lines before and after (0 new).


### 2.14 INT rows without automated coverage

Mapped 2026-11-01 by lpg-qa from the test files against `tests/integration/*Test.php` (composer test, run by the Lead: OK, 104 tests, 369 assertions). I updated 66 INT rows to "Passed (2026-11-01, composer test 104/369)" and named the covering test methods. 25 of those are marked **partial**, with the untested part named in the status cell. The INT/UNIT rows below have no PHPUnit test that asserts their expected result, so they stay as they were:

- **Phase 2–3, still open (Not run):** ENV-008, CAT-005, CAT-006, CAT-007, CAT-014, CAT-016, CAT-020, CAT-031, CAT-033, CAT-036, CAT-046, CAT-049, CAT-051, CAT-061, CAT-062, CAT-076, CAT-083, CAT-084, CAT-097, CAT-104, CAT-105, CAT-110, CAT-111, SEC-001, SEC-003, SEC-010, SEO-006, PERF-002, PERF-006.
- **CLI evidence only, no PHPUnit test:** CAT-004 (AC-04), SEO-004 (AC-08).
- **A test asserts the opposite of the plan's expectation:** CAT-079. `DocumentsTest::test_unknown_type_becomes_other` asserts the coercion to `other`, so the row stays Failed (DEF-001) until the Lead rules. **Resolved by C13: the plan row was updated to the accepted behaviour.**
- **Later phases (feature not built yet):** COM-009, ENQ-001, ENQ-002, ENQ-004, ENQ-006, RFQ-001 to RFQ-006, EXC-002, EXC-003, SEC-007, SEC-008, SEC-009, ADM-001.

---

## 3. Defect log

| ID | Date | Case | Severity | Summary | Steps to reproduce | Owner | Status |
|---|---|---|---|---|---|---|---|
| DEF-001 | 2026-11-01 | CAT-079 | Low | A document row with an invalid `type` is kept and stored as `other` instead of being rejected. §11.4 lists the allowed values but doesn't say whether to reject or normalise, so the Lead needs to rule on it (either fix the code or change the expectation) | `wp eval --user=1`: `$p=wc_get_product($id); Documents::apply($p, [["attachment_id"=>$pdf,"type"=>"brochure","label"=>"<b>[QA TEST]</b> Datasheet"]], true); $p->save();` **Expected (test plan):** the row is rejected. **Actual (observed):** stored `{"attachment_id":25,"type":"other","label":"[QA TEST] Datasheet"}` | Lead (ruling), then lpg-domain | Closed: accepted as designed (C13) |
| DEF-002 | 2026-11-01 | SEO-004 / AC-08 | Low | `&` in a spec value appears as `&amp;` inside the JSON-LD string. This is upstream WooCommerce behaviour: `WC_Structured_Data` outputs `wc_esc_json( wp_json_encode(...), true )`, which HTML-escapes with double-encoding. JSON-LD consumers will read the literal `&amp;`. The plugin is not the cause. Product names with `&` were not checked | Set a text spec to `QA x & y` and curl the product page. **Expected:** JSON-LD `"value":"QA x & y"`. **Actual:** `"value":"QA x &amp; y"` | Lead (accept upstream / note for Phase 4 SEO) | Deferred to Phase 4 SEO (C14) |

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
| Q12 | Compatible-products cards still list a password-protected product (name, price, link) on other products' pages. C15 M1 covers the protected product's own page, and WooCommerce's grids behave the same way. Should the cards also skip `post_password_required()` products? Observed in AB-05 | BLK-008, C15 | **Answered: C16, keep showing (matches WooCommerce grids).**
