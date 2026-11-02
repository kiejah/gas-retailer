# Design system v1: lpg-storefront

**Status:** v1 approved provisionally with changes (P-013, 2026-11-01). v1.1 (Phase 4 step 2, templates) applies those changes and adds the listing, homepage and product-tab components below. v1.2 (Phase 5C) adds the forms, WhatsApp link, business information, delivery strip and page patterns (§6.13–6.16).
**Source of truth:** `gas-retailer/wp-content/themes/lpg-storefront/theme.json`. This document explains it. If the two disagree, theme.json wins and this file must be corrected.
**Direction (P-004):** an industrial/commercial equipment catalogue. Charcoal and steel neutrals on white and light-grey surfaces, one orange accent for CTAs only, system fonts, 2–4 px radii, thin borders instead of shadows, tabular figures for prices and specs.

---

## 1. Colour tokens

Slugs are unchanged from the placeholder palette, so existing templates and content keep working. Changes: `border-strong` darkened from `#8A96A3` to `#7B8794` so form-control borders reach 3:1 (WCAG 1.4.11), and two new tokens were added: `focus` and `focus-inverse`.

| Slug | Hex | Role |
|---|---|---|
| `base` | `#FFFFFF` | Page background, card background, text on dark |
| `surface` | `#F3F5F7` | Image wells, spec-table label column, table headers, outline-button hover |
| `border` | `#CBD2D9` | Card, table and divider lines (decorative, 1 px) |
| `border-strong` | `#7B8794` | Input/select borders, card hover border, "Enquire" label border |
| `muted` | `#4A5562` | Secondary text: breadcrumbs, captions, document meta, struck-through prices |
| `steel` | `#2D3741` | Spec labels, PDF badge, link hover |
| `contrast` | `#1C232B` | Body text, headings, links, prices, secondary-button border, footer background |
| `accent` | `#C2410C` | **CTA fill only** (primary buttons) |
| `accent-dark` | `#9A3412` | Primary-button hover |
| `accent-light` | `#FDBA74` | Reserved. No longer used (footer links are now white). Kept so saved content doesn't break |
| `success` | `#15803D` | Status text and icons (in stock, notice borders) |
| `warning` | `#B45309` | Status text and icons |
| `error` | `#B91C1C` | Form errors, error notices |
| `focus` | `#1D4ED8` | Keyboard focus ring (functional, never decorative) |
| `focus-inverse` | `#93C5FD` | Focus ring on `contrast`/`steel` backgrounds (footer) |

### 1.1 Contrast ratios (WCAG 2.x, computed)

AA needs 4.5:1 for body text, 3:1 for large text (≥ 24 px, or ≥ 18.66 px bold), and 3:1 for UI component boundaries and focus indicators.

| Foreground | Background | Ratio | Use | Result |
|---|---|---|---|---|
| `contrast` #1C232B | `base` #FFFFFF | **15.85** | Body text, headings, links, prices | AAA |
| `contrast` | `surface` #F3F5F7 | **14.51** | Text in wells and table headers | AAA |
| `steel` #2D3741 | `base` | **12.11** | Spec labels, "Enquire" label | AAA |
| `steel` | `surface` | **11.08** | Spec label column | AAA |
| `muted` #4A5562 | `base` | **7.59** | Breadcrumbs, captions, meta | AAA |
| `muted` | `surface` | **6.94** | Meta on grey | AA (AAA ≥ 7 missed narrowly) |
| `base` #FFFFFF | `accent` #C2410C | **5.18** | Primary button text | AA |
| `base` | `accent-dark` #9A3412 | **7.31** | Primary button hover | AAA |
| `contrast` | `base` (transparent) | **15.85** | Secondary (outline) button text; 1 px `contrast` border | AAA |
| `contrast` | `surface` | **14.51** | Secondary button hover | AAA |
| `base` | `contrast` | **15.85** | Search button (icon), footer text, footer links | AAA |
| `base` | `steel` | **12.11** | Search button hover, PDF badge | AAA |
| `accent` | `base` | **5.18** | (Not used for text. Shown in case the owner wants accent text) | AA |
| `success` #15803D | `base` | **5.02** | Status text | AA |
| `warning` #B45309 | `base` | **5.02** | Status text | AA |
| `error` #B91C1C | `base` | **6.47** | Error text | AA |
| `border-strong` #7B8794 | `base` | **3.66** | Input/select border (UI boundary) | Passes 3:1 |
| `border-strong` | `surface` | **3.35** | Input on a grey panel | Passes 3:1 |
| `border` #CBD2D9 | `base` | 1.53 | Decorative card and table lines only, never the only cue for a control | n/a (decorative) |
| `focus` #1D4ED8 | `base` | **6.70** | Focus ring, 2 px, 2 px offset | Passes 3:1 |
| `focus` | `surface` | **6.13** | Focus ring on grey | Passes 3:1 |
| `focus-inverse` #93C5FD | `contrast` | **8.79** | Focus ring in the footer | Passes 3:1 |
| `accent-light` #FDBA74 | `contrast` | 9.40 | Reserved | — |

Not themed: input placeholder text uses the browser default grey. Placeholders are examples ("e.g. regulator"), never the only label (the search has an accessible label).

## 2. When to use the accent

1. **Only primary CTAs:** Add to cart, Browse products, Place order, Submit (RFQ/exchange in Phase 5). At most one accent button per card or section.
2. **Never** for links, prices, headings, icons, badges, borders, backgrounds of sections, nav items or hover states of non-CTA elements.
3. Secondary actions (Request a quote next to Browse products, Read more and Select options on cards) use the **outline** button: transparent fill, 1 px `contrast` border, `contrast` text.
4. Status uses `success`/`warning`/`error`, not the accent. Focus uses `focus`, not the accent.
5. If the owner supplies brand colours (W-001), swap `accent`/`accent-dark` in theme.json and re-check the white-on-accent ratio (must stay ≥ 4.5:1).

## 3. Typography

System stack only, no web-font requests: `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif`. Mono: `ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace`.

| Slug | Size (min → max, fluid) | Use |
|---|---|---|
| `x-small` | 0.75rem (12 px) | Labels only: "Enquire", PDF badge, stacked spec labels (uppercase, tracked). Never paragraphs |
| `small` | 0.875rem (14 px) | Card body, spec table, breadcrumbs, captions, meta |
| `medium` | 1rem (16 px) | Body, inputs (16 px stops iOS zoom), buttons, card names and prices |
| `large` | 1.125 → 1.25rem | Wordmark, lead text, single-product price |
| `x-large` | 1.25 → 1.5rem | h3, LPG block headings |
| `xx-large` | 1.5 → 2rem | h2 |
| `xxx-large` | 1.875 → 2.5rem | h1 |

Body line-height 1.6; headings 700, line-height 1.2, letter-spacing −0.01em; h6 uppercase small. Nav 0.9375rem/600. Wordmark 800, −0.02em. Prices and spec values use `font-variant-numeric: tabular-nums`.

## 4. Spacing, radii, shadows, layout

| Spacing slug | Value | | Radius slug | Value |
|---|---|---|---|---|
| `10` | 0.25rem | | `small` | 2 px: inputs, selects, badges, image wells |
| `20` | 0.5rem | | `medium` | 4 px: buttons, cards, dropdowns |
| `30` | 0.75rem | | | |
| `40` | 1rem (default block gap) | | **Shadow** | |
| `50` | clamp(1.25rem, 3vw, 1.75rem) (root side padding) | | `raised` | `0 1px 2px rgba(28,35,43,.08)` (rare) |
| `60` | clamp(1.75rem, 5vw, 3rem) | | `overlay` | `0 6px 16px rgba(28,35,43,.14)`: dropdowns and drawers only |
| `70` | clamp(2.5rem, 7vw, 4.5rem) | | | |
| `80` | clamp(3.5rem, 10vw, 6.5rem) | | | |

The `large` (8 px) radius was removed: nothing used it, and it was outside the 2–4 px rule. Layout: content 760 px, wide 1280 px. Custom properties: `--wp--custom--focus--{width,offset}` (2 px/2 px), `--wp--custom--control--height` (2.75rem = 44 px), `--wp--custom--card--{padding,gap,radius}`.

## 5. Breakpoints

| Width | What changes | Why |
|---|---|---|
| < 600 px | Header: menu button, wordmark, account and cart in row 1, full-width search in row 2. Nav in an overlay. Spec table rows stack. Card grids show 2 columns | 600 px is core Navigation's fixed overlay breakpoint, so the header and spec table follow it |
| ≥ 600 px | Header: wordmark, search (max 40rem) and tools in row 1, the full nav in row 2. Spec table shows two columns | |
| ≥ 782 px | Compatible-products grid uses its `columns` attribute (plugin CSS) | WordPress/plugin column breakpoint |
| 1280 px | Wide max width | `wideSize` |

Between 600 and about 1000 px the nav wraps to a second line (checked at 768: two rows, no overflow).

## 6. Components

### 6.1 Buttons (`styles.elements.button`, `core/button` outline variation)
- **Primary:** `accent` fill, white 16 px/600 text, 1 px `accent` border, 4 px radius, padding 0.625rem × 1.25rem. Hover `accent-dark`. Focus: 2 px `focus` outline, 2 px offset.
- **Secondary:** the `is-style-outline` variation: transparent fill, `contrast` text and 1 px border. Hover `surface` fill.
- **Card buttons:** full width, at least 44 px tall, pinned to the bottom of the card. Add to cart is primary. Read more and Select options (any non-add-to-cart action) are outline.
- **Search submit:** `contrast` fill with a white icon (it is a utility, not a CTA, so it is not orange).

### 6.2 Links
`contrast` text, 1 px underline at a 0.18em offset. On hover the underline thickens to 2 px and the colour becomes `steel`. Nav and card titles have no underline until hover. Breadcrumb links are `muted`.

### 6.3 Forms (`elements.textInput`, `elements.select`)
White fill, `contrast` text, 1 px `border-strong` border (3.66:1), 2 px radius, padding 0.5rem × 0.75rem, 16 px text, focus ring flush (offset 0). The element selectors have low specificity (`:root :where()`), so WooCommerce block checkout inputs keep their own floating-label layout and only inherit what they don't set. Error text uses `error`.

### 6.4 Tables
`core/table` and the WooCommerce classic `.shop_table`/`.woocommerce-table` share one look: 1 px `border` cells, padding 0.75rem × 1rem, `surface` header row, text at `small`.

### 6.5 Header (`parts/header.html` → `patterns/header.php`)
```
< 600 px   [☰] Gas-Plug ....... [wa] [account] [cart]
           [ Search products, e.g. regulator     (⌕) ]
≥ 600 px   Gas-Plug   [ Search products …  (⌕) ]   [wa WhatsApp us] [account] [cart]
           ───────────────────────────────────────────────────────
           Products ▾  Household  Commercial  Industrial  LPG Accessories
           Safety Equipment  Refills & Exchange  Request a quote
```
- Wordmark: the core Site Title ("Gas-Plug", from Settings > General), **placeholder** pending W-001. No logo image.
- Search: core Search with the WooCommerce product-search variation (searches products only). It sits outside the nav overlay, so it is visible at every width (P-005/P-006). Core removes the input's outline, so the theme draws the 2 px `focus` ring (offset 2 px) around the whole field (`.wp-block-search__inside-wrapper`) when the input has keyboard focus, and on the search button when it has focus (DEF-003). Applies to every core Search in the theme, including the empty-state search.
- Tools: the plugin's `lpg/whatsapp-link` (§6.14), WooCommerce Customer account (icon) and Mini-cart (icon, price hidden). All are at least 44 × 44 px (the account rule targets `.wc-block-customer-account__link`, DEF-005).
- **Mini-cart drawer:** WooCommerce 11.2.1 hides the closed drawer with opacity only, so its controls stayed in the Tab order. The theme adds `visibility: hidden` to `.wc-block-components-drawer__screen-overlay--is-hidden`, delayed by the 0.3 s fade-out, so the closed drawer is skipped by Tab and the open/close animation is unchanged (DEF-004, CSS only).
- **Desktop/tablet nav (≥ 600 px):** top-level items are at least 44 px tall (flex, centred); wrapped rows at 768 have no extra row gap (DEF-005).
- Nav: core/navigation with inner link/submenu blocks and **no `ref`**, so it needs no `wp_navigation` post and core creates no fallback. The order follows P-006. Items come from `inc/nav-items.php`, shared with the mobile menu, and are built from the terms that exist, so a missing term drops its item. Segment items go to `/segment/<slug>/`. LPG Accessories, Safety Equipment and Refills & Exchange go to their category archives.
- **Products ▾ opens on click/tap** (`submenuVisibility: "click"`, P-014). "Products" is a `<button>` with `aria-expanded` (false → true on click, Enter or Space; Escape closes and returns focus). The dropdown (4 px radius, thin border, overlay shadow) lists **All products first**, then the 11 top-level categories.
- **Request a quote** appears only when a published page with the slug `request-a-quote` exists, checked when the header renders (§12.2, P-013 #1). No page is created by the theme.
- **Mobile menu (< 600 px):** the nav uses a custom overlay template part, `parts/mobile-menu.html` (area `navigation-overlay`, WP 7.0+) → `patterns/mobile-menu.php`. Reason: core's default overlay always reports every submenu as open (`isSubmenuOpen` is true while the overlay is open), so the 12 Products items pushed the segments off-screen at 375. The custom overlay has a close button (core `navigation-overlay-close`), then **Products as a native `<details>/<summary>` disclosure, collapsed when the menu opens**, then Household, Commercial, Industrial, LPG Accessories, Safety Equipment, Refills & Exchange (and Request a quote when the page exists). All six are visible without scrolling at 375 (last one ends at 464 px of a 697 px viewport). The summary toggles on tap, Enter or Space, and the browser exposes its expanded/collapsed state; no JavaScript is added. Rows are at least 44 px tall. White full-screen panel, charcoal text, a chevron that flips when open.
- WooCommerce block hooks would otherwise inject a second account icon after the nav. The nav sets `metadata.ignoredHookedBlocks` to prevent that.

### 6.6 Product card (`assets/css/product-card.css`)
One look for WooCommerce product-template cards (shop, archives, upsells, related products, cart cross-sells) and `lpg/compatible-products` cards:
- White card, 1 px `border`, 4 px radius, 0.75rem padding, 0.5rem gap, left-aligned. The border goes to `border-strong` on hover (no shadow, no motion).
- **Image well:** square, `surface` background, `object-fit: contain`, so the product sits on a plain background and is never cropped.
- **Name:** 16 px/600 `contrast`, wraps in full (technical names are not truncated). Underlined on hover.
- **Price:** 16 px/700 `contrast`, tabular figures. A sale shows the struck-through old price in `muted` before the new one.
- **Brand and SKU** (P-004, WooCommerce cards): `small`, `muted`, under the name. Each renders nothing when empty.
- **Sale badge:** small `contrast` tag with white uppercase `x-small` text, top right of the image. Never the accent.
- **Enquire** (enquiry-only products): a neutral label, not a button. Uppercase `x-small`, `steel` text, 1 px `border-strong` border, 2 px radius. Used for `.lpg-compatible-products__price--enquire` and for the plugin's `.lpg-enquire-label` (§12.1), which replaces the price in WooCommerce cards and on the single product page (that rule is global in theme.json because it appears outside cards).
- **Button** (WooCommerce cards only, see 6.1). For enquiry-only products the plugin replaces it with `.lpg-enquire-link` "View details": same place, full width, 44 px tall, outline look as "Read more". The plugin's compatible-products markup has no button.
- **Grid:** WooCommerce Product Collection with `shrinkColumns: true`, so every grid (shop, archives, homepage, upsells, related, cart cross-sells) is 2 columns at 375 and up to its column count on wide screens (P-013 #2).
- Loaded only when `woocommerce/product-template` or `lpg/compatible-products` renders.

### 6.7 Spec table (`assets/css/lpg-blocks.css`)
- **≥ 600 px:** two columns in a 1 px `border` frame. The label column (38%) is `surface`/`steel`/600 with a vertical rule. Values have tabular figures. Rows are separated by 1 px rules (no zebra striping).
- **< 600 px:** each row stacks: a `surface` label strip (x-small, uppercase, tracked) above the value. Long values wrap with `overflow-wrap: anywhere`, with no horizontal scroll (checked at 375).
- Block heading is `x-large`.

### 6.8 Documents (`assets/css/lpg-blocks.css`)
Rows are separated by 1 px rules and are at least 44 px tall. Each starts with a `steel` "PDF" badge. Only `__label` is underlined. `__meta` (type and size) is plain `muted` small text that never wraps internally, and drops to its own line at narrow widths (QA design note).

### 6.9 Catalogue listings (`assets/css/catalog.css`)
Templates `archive-product` (shop), `taxonomy-product_cat`, `taxonomy-lpg_segment`, `taxonomy-product_brand`, `product-search-results` share one layout: breadcrumbs, H1 (term name, no prefix), term description (`muted`, max 760 px), category links (shop: top-level categories with products; category: its subcategories with products), then a wide two-column area.
- **Filters column** (16 rem; stacks above results below 782 px): native WooCommerce Product Filters only, pattern `lpg-storefront/product-filters`: active-filter chips with "Clear filters" (outline), Price (slider + inputs), Category, Brand, then one attribute filter per global `pa_` attribute (the filterable LPG specs, read when the pattern renders). Each filter hides itself when the current results have no options. Groups are separated by 1 px rules; headings `medium`/700; checkbox borders `border-strong` (3:1) via WooCommerce's variable; focus ring on checkboxes. Below 600 px WooCommerce collapses the filters behind an outline "Filter products" button (44 px) that opens its dialog. When no filter has options (e.g. an empty category) the whole column is hidden.
- **Toolbar:** results count (`muted`, small) and sorting (labelled select, 44 px) above the grid.
- **Pagination:** bordered 44 × 44 px numbers, current page with a `contrast` border and bold.
- **Empty state** (patterns `no-products`, `no-search-results`, inside the Product Collection "No results" block): `surface` panel with a thin border, an H2, a short explanation, and "Browse all products" (outline). Search adds a product search field.

### 6.10 Homepage sections (`templates/front-page.html`, P-005 order)
Hero (`surface` band, catalogue-led H1, primary "Browse products", outline "Request a quote" only when that page exists) → segment tiles (3-column grid of bordered tiles; the whole tile is the link) → category links (chips; the section is left out while no category has products) → Featured products → RFQ band (left 4 px `contrast` rule; button only when the page exists, otherwise "Contact us for bulk and project orders." with no contact details) → New products → safety strip (`surface` band) → delivery strip (§6.15) with a small Refills & Exchange note tile → trust strip (three placeholder tiles, visually hidden H2). Product grids keep their heading inside the Product Collection block, so a grid with zero products renders nothing at all. All copy is placeholder and marked `[Placeholder]`, `[Placeholder copy]` or `[PLACEHOLDER: …]` (italic `muted`). Sections are spaced with `spacing-70`. These small rules live in theme.json global CSS.

### 6.11 Single product sections (`assets/css/single-product.css`)
Everything under the gallery/summary columns sits in one wide group, `.lpg-product-sections`, so the spec table, WooCommerce tabs, documents, compatible products, upsells and related products share the columns' width (P-013 #3; measured 28–1252 px at 1280 and 20–355 px at 375). Order: spec table → tabs → documents → compatible products → upsells → related products (§11.9, P-009 #4).
- **Tabs** (WooCommerce "minimal" style, restyled): a row of tab links over a 1 px `border` rule; inactive `muted`, active `contrast` with a 2 px `contrast` underline; 44 px tall. Panel headings `x-large`; text limited to 75 ch.
- **Reviews** (WooCommerce default behaviour, W-008): title `x-large`, "no reviews" in `muted`, the form in a bordered panel up to 40 rem wide inside the shared column (not full-width), labels above fields, inputs full width at 44 px, required marks in `error`, primary "Submit".
- **Meta** (SKU, category, tags, brand): `small` `muted`, wrapping row.
- **Enquiry button** (`lpg/enquiry-button`, §13.6): in the summary directly after the add-to-cart form. By default it renders only for enquiry-only products, which have no add-to-cart form, so it takes the cart button's place. Styled in §6.13.
- **Warranty** (`lpg/product-warranty`, §12.4): directly under the meta row in the summary. Same `small` `muted` text as the meta; `__label` ("Warranty:") is 600 weight; `__value` keeps the plugin's line breaks (`nl2br`) and wraps long words. The block renders nothing without a warranty value.
- **Upsells** (pattern `lpg-storefront/upsell-products`, heading "You may also need"): WooCommerce Product Collection in its native `woocommerce/product-collection/upsells` collection (the product is taken from the single-product location), up to 4 items, product cards as in 6.6, 2-up at 375 and up to 4 columns at 1280. It renders nothing when the product has no upsells. WooCommerce 11.2 would also print its classic `woocommerce_upsell_display()` ("You may also like…") after the tabs in block templates, so `functions.php` opts that one hook out through WooCommerce's documented `woocommerce_blocks_hook_compatibility_additional_data` filter (block templates only; no data changes).

### 6.12 Cart cross-sells
The cart page's WooCommerce Cart block already contains WooCommerce's default cross-sells inner block (a Product Collection in the `woocommerce/product-collection/cross-sells` collection, heading "You may be interested in…"). It lives in the cart page content, not in a theme template, so the theme only styles it: product cards from 6.6, 2-up at 375. It renders nothing when the cart items have no cross-sells. The cart's quantity input has its side padding reset (theme.json global CSS) so the theme's text-input padding doesn't widen WooCommerce's fixed 132 px mobile cart column, which had caused 7 px of horizontal scroll at 375.

### 6.12 Checkout page (`templates/page-checkout.html`)
Theme override of WooCommerce 11.2.1's checkout template: WooCommerce's minimal checkout header part, then the page title as the only H1 ("Checkout"), then the page content with WooCommerce's Checkout block unchanged (DEF-006). The cart uses WooCommerce's template, which already has one H1.

### 6.13 Forms and the enquiry button (`assets/css/lpg-forms.css`)
Applies to `lpg/enquiry-button` (`.lpg-enquiry`), `lpg/rfq-form` (`.lpg-rfq`) and `lpg/exchange-form` (`.lpg-exchange`), using lpg-domain's fixed classes (`.lpg-field*`, `.lpg-form-*`, `.lpg-notice*`). The plugin ships small layout-only stylesheets per block (field stacking, widths, `[hidden]`, the 600 px item grid); the theme adds the design system on top and doesn't repeat them.
- **Layout:** mobile-first single column; forms are a grid with `spacing-40` gaps. Main actions (`__toggle`, `__open`, `__submit`) are full width below 600 px and auto width from 600 px.
- **Labels** 600 weight. `.lpg-field__help`, `.lpg-rfq__required-note` and `.lpg-notice` are `small` `muted`. `.lpg-field__required` is `error`.
- **Controls:** the §6.3 look (white, 1 px `border-strong`, 2 px radius, 16 px text in the body font), full width, at least 44 px tall. Textareas start at 7.5 rem. Selects get a charcoal chevron. A **disabled** control (the exchange size select until a brand is chosen) is `surface` with `muted` text and a not-allowed cursor.
- **Draft flag** (`.lpg-notice__draft`, W-007): a small dashed `border-strong` tag on `surface` with `steel` 600 text. It's distinct but neutral (no warning colour).
- **No-JS fallback** (`.lpg-form-fallback`): `surface` panel with a thin border.
- **Fieldsets:** borderless. RFQ groups have a `large` 700 legend, floated so it sits below a 1 px divider between groups.
- **Radios** (`.lpg-field--radio`): 20 px controls in `contrast`, label rows at least 44 px (52 px measured), regular weight. **Consent** (`.lpg-field--checkbox`): the checkbox beside a multi-line label, which is the large target; its error spans both columns.
- **Item rows** (`.lpg-rfq__item`): `surface` panels with a thin border in a numbered list. They stack below 600 px; from 600 px the plugin's grid puts description, qty and Remove on one line. `.lpg-rfq__add-item` and `.lpg-rfq__remove-item` are outline buttons, root-prefixed because theme.json's `:root :where(.wp-element-button)` has the same specificity as a single class. Submit stays the one accent button.
- **Errors (not colour alone):** a control with `aria-invalid="true"` gets an `error` 2 px border (a checkbox gets a red box). `.lpg-field-error` is `error` `small` 600 with a round "!" badge. `.lpg-form-status.is-error` is a panel with a 4 px `error` left rule and the same badge. An empty status is hidden.
- **Success** (`.lpg-form-success`, focused by the plugin): a panel with a `success` border and 4 px left rule, plus a ✓.
- **Sending:** the plugin disables Submit and sets `aria-busy` on the form, which shows 60 % opacity, a not-allowed cursor on the button and a progress cursor on the form.
- **Enquiry button:** `.lpg-enquiry__toggle` is a primary button. `.lpg-enquiry__panel` is a `surface` panel with a thin border. When it's open (`aria-expanded="true"`, set by the plugin's JS), the toggle switches to the outline look, so "Open WhatsApp" is the one accent button (§2).

### 6.14 WhatsApp link and business information (`assets/css/lpg-contact.css`)
Markup: `div.lpg-contact-links` > `a.lpg-whatsapp-link` (`svg.lpg-icon` + `span.lpg-whatsapp-link__text`) and optionally `a.lpg-phone-link`; `address.lpg-business-info` > `.lpg-business-info__item--address|--hours|--phone` > `__label` + `__value`.
- **Header (P-017/P-023):** first in the tools row, with `showPhone: false` (the block's default is true). A quiet "WhatsApp us" text link with its icon, 600 weight, underlined on hover, 44 px tall (129 × 44 at 1280). **Never a floating bubble, never green.** Below 600 px only the 24 px icon shows, in a 44 × 44 target. The text is visually hidden (clip method) and stays the accessible name ("WhatsApp us"). Below 360 px the header gaps tighten and the wordmark is kept on one line, so "Gas-Plug" doesn't wrap at its hyphen at 320.
- **Footer:** `lpg/business-info` and `lpg/whatsapp-link` (`showPhone: false`, because the phone is in the business info) in a wrapping row (`.lpg-footer__contact`). White text and links on the dark band, `small`, `focus-inverse` focus ring.
- **Business information:** upright `small` text, labels on their own line in 600 weight, `<br>` line breaks kept, long values wrap. Address and hours print the plugin's placeholders as-is (W-002). Phone links are 44 px tall with tabular figures.
- **Contact page** (`.lpg-contact`, pattern `page-contact`): the same blocks in a bordered panel at body size, with `showPhone: true` as the pattern brief asked, so the phone appears twice (in the business info and next to WhatsApp).

### 6.15 Homepage delivery strip (theme.json global CSS, P-023)
In `patterns/home-delivery.php`: a `[Placeholder copy]` tag over a core List named **"Delivery info strip (editable)"** in List View (class `lpg-delivery-strip`): "Delivery within Nairobi · Free delivery on orders over KSh 10,000 · Pickup available". Text is 600 weight, no bullets; the items stack below 600 px and sit on one line from 600 px with `muted` middle-dot separators (CSS, hidden from screen readers). It matches the WooCommerce shipping zone (§13.9). **No delivery times, no tracking language.** To change it, edit that list in the Site Editor (or the pattern file) and keep it in line with the shipping settings. The Refills & Exchange tile beside it links to the `refills-exchange` page when it is published, otherwise to the product category.

### 6.16 Page patterns (for the Lead's Phase 5 pages)
Offered for new pages (`Block Types: core/post-content`, `Post Types: page`). The page title (H1) comes from the template, so the patterns start below it. All copy is `[Placeholder copy]`.
| Pattern | Content |
|---|---|
| `lpg-storefront/page-request-a-quote` | Lead paragraph and a short instruction, then `lpg/rfq-form`. No claims about prices, stock or response times |
| `lpg-storefront/page-refills-exchange` | Explainer: arranged on WhatsApp, separate from equipment orders; delivery within Nairobi only; no times. Then `lpg/exchange-form` and a link to the Refills & Exchange category |
| `lpg-storefront/page-contact` | `.lpg-contact` panel with `lpg/business-info` and `lpg/whatsapp-link {showPhone:true}`, then a note linking to Request a quote (the page's permalink, or `/request-a-quote/` if it isn't published yet) |

The Lead created the pages from these patterns in Phase 5C: `request-a-quote` (#102), `refills-exchange` (#103), `contact` (#104).

## 7. Asset loading (C15 performance note)

| File | Loaded when | How |
|---|---|---|
| theme.json global styles | Every page (inline) | Core |
| `assets/css/lpg-blocks.css` | `lpg/spec-table`, `lpg/product-documents` or `lpg/compatible-products` renders | `wp_enqueue_block_style()` per block, handle `lpg-storefront-blocks` |
| `assets/css/product-card.css` | `woocommerce/product-template` or `lpg/compatible-products` renders | `wp_enqueue_block_style()`, handle `lpg-storefront-product-card` |
| `assets/css/catalog.css` | `woocommerce/product-collection`, `woocommerce/product-filters` or `woocommerce/product-categories` renders | `wp_enqueue_block_style()`, handle `lpg-storefront-catalog` |
| `assets/css/single-product.css` | `woocommerce/product-details` or `lpg/product-warranty` renders | `wp_enqueue_block_style()`, handle `lpg-storefront-single` |
| `assets/css/lpg-forms.css` | `lpg/enquiry-button`, `lpg/rfq-form` or `lpg/exchange-form` renders | `wp_enqueue_block_style()`, handle `lpg-storefront-forms` (inlined, or a `<link>` when the page passes core's 40 KB inline budget) |
| `assets/css/lpg-contact.css` | `lpg/whatsapp-link` or `lpg/business-info` renders (header and footer, so on most pages; small, inlined) | `wp_enqueue_block_style()`, handle `lpg-storefront-contact` |

On WP 7.1 with a block theme, `wp_should_load_block_assets_on_demand()` is true. `wp_enqueue_block_style()` then hooks `render_block` for that block name only. Block templates render before `wp_head`, so the style still prints in the head. Because `path` is passed, core inlines these small files (`<style id="…-inline-css">`) instead of adding a request. In the editor (`is_admin`), the styles load through `enqueue_block_assets`. No JavaScript is added, and there are no web fonts or CDN requests.

## 8. Still placeholder, pending the owner

| Item | Placeholder now | Owner item |
|---|---|---|
| Business name and wordmark | "Gas-Plug" site title as text | W-001 |
| Logo | None (no image) | W-001 |
| Brand colours | Orange `#C2410C` accent and charcoal/steel neutrals | W-001 |
| Product photography | WooCommerce placeholder or neutral grey | W-006 |
| WhatsApp header link (P-006 utility) | Placed (P-023): plugin block, renders only when the number is set in plugin settings | W-002 (number provided) |
| Address and opening hours | Plugin-settings placeholders shown as-is in the footer and contact page | W-002 |
| Request a quote page | Nav item, hero button and RFQ button hidden until a published `request-a-quote` page exists; RFQ band shows a neutral line meanwhile | Phase 5 |
| Homepage copy, safety, delivery and trust content | Marked placeholders, no claims. Delivery strip matches the shipping settings and may be revised by the client | W-004, W-005, W-003, W-006 |
