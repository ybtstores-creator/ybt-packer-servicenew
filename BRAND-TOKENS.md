# YBT Brand Tokens — Web ⇄ Android Contract

> **The web site is the source of truth.** The Android app is a port of the
> web's palette — never the other way around, and never an independent
> palette of its own.
>
> **Rule of use:** Android must consume brand colors **only through the named
> resources listed below** (`@color/brand_*`, `@color/chip_*`) or the M3
> theme roles. Layouts and code must not introduce new hex values for brand
> colors; new colors enter via a new named resource + a row in this table,
> same as a new CSS token would.

Sources on the web side:

| Web file | Role |
|---|---|
| `assets/css/ybt-consignment.css` | Shared design system (homepage, user dashboard, auth, ledger, modals). Canonical token block, `:root`-scoped at lines ~29–47. |
| `assets/style.css` | Storefront/navbar legacy tokens (`--ybt-green*`, `--ybt-gold*`), lines 2–10. |
| `assets/css/admin-modern.css` | Admin palette — **reference contract** (§5): governs future admin-scope app screens; the app's semantic subtle/text resources already come from it. |
| `.ybd-chip-*` classes in `ybt-consignment.css` | Status-chip scheme, lines ~329–343. |

Sources on the Android side: `app/src/main/res/values/colors.xml`,
`themes.xml`, `styles.xml`, and
`app/src/main/java/com/ybt/packer/ui/common/StatusChipHelper.kt`.

## How to keep them in sync

1. Change the web token first.
2. Update the matching Android resource and the row in this file in the same
   commit; the comment in `colors.xml` names the web token it mirrors.
3. **Run the drift checker (must exit 0):**

```bash
python tools/check_brand_tokens.py
```

   It parses this doc and verifies every §1 mapping against the **live**
   sources (`assets/style.css`, `assets/css/ybt-consignment.css`,
   `colors.xml` with alias resolution), verifies the §2 chip bindings against
   `StatusChipHelper.kt`, flags tokens/resources that vanished, and enforces
   near-white tint tolerance (≤8/channel) only for tints. Exit 1 = drift or
   missing doc row.
4. Rebuild the app (`gradlew assembleDebug`) and re-verify the emulator walk
   (TESTING.md §4) — chip colors are pixel-verified there.
5. Manual spot checks, if needed:

```bash
# web tokens
sed -n '2,10p' assets/style.css
grep -n "emerald\|gold" assets/css/ybt-consignment.css | head
# android resources
grep -n "brand_green\|brand_gold\|chip_neutral" android-app/app/src/main/res/values/colors.xml
```

## 1. Core brand colors

| Web token (source) | Web value | Android resource | Android value | Notes |
|---|---|---|---|---|
| `--ybt-green` (`style.css`) | `#10b981` | `@color/brand_green` | `#10B981` | Primary. Navbar gradient on web; M3 `colorPrimary` in app. |
| `--emerald` (`ybt-consignment.css`) | `#10b981` | *(same resource)* | `#10B981` | Same value as `--ybt-green`; keep all equal. |
| `--ybt-green-hover` (`style.css`) | `#059669` | `@color/brand_green_hover` | `#059669` | Pressed/hover state of primary. |
| `--emerald-deep` (`ybt-consignment.css`) / `--ybt-dark-green` (`style.css`) | `#047857` | `@color/brand_green_dark` | `#047857` | "Completed" chip; deep pressed state. |
| `--emerald-tint` (`ybt-consignment.css`) | `#e7f6ef` | `@color/brand_green_tint` | `#ECFDF5` | Shared-system light green (chips, dashboard notes). **Not the same as `--ybt-green-light`.** |
| `--ybt-green-light` (`style.css`) | `#d1fae5` | `@color/brand_green_light` | `#D1FAE5` | Navbar-era light green (M3 `colorPrimaryContainer`, `chip_selected`). |
| `--ybt-gold` (`style.css`) | `#d4af37` | `@color/brand_gold` | `#D4AF37` | Secondary accent (site borders/links; app M3 secondary, Users FAB). |
| `--ybt-gold-hover` (`style.css`) | `#b59021` | `@color/brand_gold_dark` | `#B59021` | Gold hover/pressed. |
| `--gold-deep` (`ybt-consignment.css`) | `#8a6d1f` | `@color/brand_gold_deep` | `#8A6D1F` | Gold-tint chip text. |
| `--gold-tint` (`ybt-consignment.css`) | `#faf3dc` | `@color/brand_gold_light` | `#FAF3DC` | Gold-tint chip background. |
| `--ybt-gold-light` (`style.css`) | `#fff8e1` | `@color/brand_gold_pale` | `#FFF8E1` | Navbar-era pale gold. |
| `--ink` (`ybt-consignment.css`) | `#0f1f17` | `@color/bg_dark_header` + `@color/text_primary` | `#0F1F17` | App screen headers use the site's ink as the header band color. |
| `--kraft` (`ybt-consignment.css`) | `#f1ead9` | `@color/kraft` | `#F1EAD9` | Kraft paper canvas on the web; the app's screen-header bands + status bar use it (with a generated `bg_kraft_tile` texture tile reproducing the web's 26px vertical hairline). |
| `--kraft-line` (`ybt-consignment.css`) | `#d9ccb0` | `@color/kraft_line` | `#D9CCB0` | Hairline edge under the app's header bands; web uses it for kraft borders. |
| `--kraft-deep` (`ybt-consignment.css`) | `#b9a67f` | `@color/kraft_deep` | `#B9A67F` | Border of the not_received chip on the web; reserved in app. |

### Typography

| Web | Android | Notes |
|---|---|---|
| `--display` = 'Archivo Black' | `@font/archivo_black` (bundled TTF, OFL license in `android-app/FONT-LICENSE.txt`) | Screen/section titles: `DisplayTitle` + `ToolbarTitle` styles, in ink — mirrors `.ybd-pagehead h2` / `.ybt-ledgerhead h1`. |
| `--sans` = 'IBM Plex Sans' | system Roboto | Intentional: platform default stays legible; only display headings port. |
| `--mono` = 'IBM Plex Mono' | system monospace | Not ported yet. |

### Header anatomy (the "kraft band")

Every screen top — dashboard header, injected detail headers, toolbar
activities — follows the web's kraft-paper surface block:

- `bg_header_dark.xml` drawable: `bg_kraft_tile` texture + 1dp bottom hairline `kraft_line`
- title: Archivo Black (`DisplayTitle` / `ToolbarTitle` style), ink `#0F1F17`
- subtitle: `#3D4A42` (the ledger-head subtitle tone, readable on kraft)
- status bar: `@color/kraft`, light icons

All layouts must reference `@drawable/bg_header_dark` / these styles rather
than re-declaring colors, so the band can only evolve in one place.

## 2. Status chips (`.ybd-chip-*` ⇄ `StatusChipHelper`)

| Status | Web (ybt-consignment.css) | Android bg → text | Android border | Match |
|---|---|---|---|---|
| `not_received` | bg `#fbf8f1`, text `#5c5543`, border `var(--kraft-deep)` | `chip_neutral_bg` `#FBF8F1` → `chip_neutral_fg` `#5C5543` | `@color/border` `#E2E8F0` | bg+text exact; border approximates kraft — add a `chip_neutral_border` resource if it ever matters visually |
| `received` | bg `var(--emerald-tint)` `#e7f6ef`, text `var(--emerald-deep)`, border `var(--emerald)` | `brand_green_tint` `#ECFDF5` → `brand_green_dark` | `brand_green` | text/border exact; bg near-match (see §1 note) |
| `packing_in_progress` | bg `var(--gold-tint)` `#faf3dc`, text `var(--gold-deep)`, border `var(--gold)` | `brand_gold_light` `#FAF3DC` → `brand_gold_deep` | `brand_gold` | exact |
| `packed` | bg `var(--emerald)`, white text, border `var(--emerald)` | `brand_green` → `white` | `brand_green` | exact |
| `completed` | bg `var(--emerald-deep)`, white text, border `var(--emerald-deep)` | `brand_green_dark` → `white` | `brand_green_dark` | exact |

Fallback for unknown statuses: neutral chip (same as `not_received`), both sides.

## 3. Android-only aliases (no direct web token — app plumbing)

| Android resource | Value | Purpose |
|---|---|---|
| `primary`, `primary_dark`, `accent` | aliases of brand colors | Legacy layout references; don't restyle individually. |
| `background` `#F8FAFC`, `surface` `#FFFFFF`, `bg_input` `#F8FAFC` | — | App surfaces; web user pages are white with kraft accents, app is light grey + white cards. |
| `text_secondary` `#475467`, `text_muted` `#94A3B8`, `border` `#E2E8F0`, `bg_hover` `#F1F5F9` | — | Slate text/border tiers from the M3 baseline. |
| `info_subtle` `#CFFAFE` / `info_dark` `#0E7490` | — | Admin `--ybt-info` family (non-chip info UI). |
| `warning_subtle` `#FFFBEB` / `warning_text` `#B45309` | — | Admin warning family. |
| `danger` `#D93636`, `danger_subtle` `#FFF1F2` / `danger_text` `#BE123C` | — | Danger family (app Log out button, error states). |
| `success` / `success_subtle` `#ECFDF5` / `success_text` `#047857` | — | Admin success family. |

## 4. Semantic mapping (meaning, not just color)

| Meaning | Web | App |
|---|---|---|
| Primary action | emerald fill, white text | `colorPrimary` = emerald, `YbtButton` fill |
| Secondary accent | gold | `colorSecondary` / `accent` = gold |
| Success | emerald family | emerald family |
| In-progress | gold family | gold family |
| Neutral/pending | kraft/neutral | `chip_neutral_*` |
| Error/danger | rose/red | `danger` + `danger_subtle`/`danger_text` |

## 5. Web admin palette (`admin-modern.css`) — reference contract

The **web admin** runs a separate indigo/slate palette. The app does not
consume it today — this section is the contract for any **future admin-scope
screens** in the app (admin dashboards, cleanup tools, etc.), so they match
the web admin instead of borrowing the storefront emerald/kraft brand.

The admin's status colors were already the source of the app's semantic
subtle/text resources (see §3) — those are conformant. What's missing is the
indigo primary family and the admin chrome tones.

### 5.1 Primary (indigo) family — not yet in the app

| Web token | Value | Planned app resource | Notes |
|---|---|---|---|
| `--ybt-primary` | `#6366F1` | `admin_primary` | Admin accent — NEVER mix with storefront emerald. |
| `--ybt-primary-dark` | `#4F46E5` | `admin_primary_dark` | Badge/pressed text. |
| `--ybt-primary-light` | `#818CF8` | `admin_primary_light` | Hover tone. |
| `--ybt-primary-subtle` | `#EEF2FF` | `admin_primary_subtle` | Badge/selected backgrounds. |
| `--ybt-border-focus` | `#6366F1` | (same as `admin_primary`) | Focus rings. |

### 5.2 Semantic triples (light/subtle already in the app)

| Meaning | main | light | subtle | App resources today |
|---|---|---|---|---|
| Success | `#10B981` | `#D1FAE5` | `#ECFDF5` | `success_text` `#047857` (web hardcodes this on badges), `success_subtle` `#ECFDF5`; main = shared `brand_green` |
| Warning | `#F59E0B` | `#FEF3C7` | `#FFFBEB` | `warning_subtle` + `warning_text` `#B45309`; main not yet in app |
| Danger | `#F43F5E` | `#FFE4E6` | `#FFF1F2` | `danger_subtle` + `danger_text` `#BE123C`; **app `danger` is `#D93636`, its own red — see watchlist** |
| Info | `#06B6D4` | `#CFFAFE` | `#ECFEFF` | `info_subtle` + `info_dark` `#0E7490`; main not yet in app |
| Secondary/neutral | `#64748B` | `#94A3B8` | — | `slate_400` = `text_muted` `#94A3B8`; `#64748B` not yet in app |

### 5.3 Surfaces, text, borders

| Web token | Value | App resource | Match |
|---|---|---|---|
| `--ybt-bg-main` / `--ybt-bg-input` | `#F8FAFC` | `background` / `bg_input` | exact |
| `--ybt-bg-card` | `#FFFFFF` | `surface` | exact |
| `--ybt-bg-hover` | `#F1F5F9` | `bg_hover` | exact |
| `--ybt-bg-sidebar` | `#0F172A` | not in app | — future admin chrome |
| `--ybt-text-primary` | `#0F172A` | not in app | app's `text_primary` is the site ink `#0F1F17` — do NOT merge (see watchlist) |
| `--ybt-text-secondary` | `#475569` | `text_secondary` = `#475467` | **1-digit drift — see watchlist** |
| `--ybt-text-muted` | `#94A3B8` | `text_muted` | exact |
| `--ybt-text-inverse` | `#FFFFFF` | `white` | exact |
| `--ybt-border` | `#E2E8F0` | `border` | exact |
| `--ybt-border-light` | `#F1F5F9` | `border_light` | exact |

Geometry: radius 8/10/14/18dp (`sm`/`base`/`lg`/`xl`), spacing scale 4→32,
shadow ladder `sm`→`xl` plus the indigo glow `rgba(99,102,241,.25)` — port
per-screen as needed.

### 5.4 Admin badge pattern (`.ybt-badge-*`, line ~474)

Subtle background + dark text, no border — the admin counterpart of the
storefront chips in §2:

| Badge | bg | text |
|---|---|---|
| `ybt-badge-primary` | `#EEF2FF` | `#4F46E5` |
| `ybt-badge-success` | `#ECFDF5` | `#047857` (hardcoded on web) |
| `ybt-badge-warning` | `#FFFBEB` | `#B45309` |
| `ybt-badge-danger` | `#FFF1F2` | `#BE123C` |
| `ybt-badge-info` | `#ECFEFF` | `#0E7490` |
| `ybt-badge-secondary` | `#F1F5F9` | `#475569` |

Admin status → badge mapping (`ybt_get_status_badge_color()` in
`includes/tracking.php`): `not_received`=secondary, `received`=info,
`packing_in_progress`=warning, `packed`=primary, `completed`=success.
Note this differs from the storefront chip scheme in §2 (packed = solid
emerald there, subtle indigo here) — each side of the app must follow its
own scope.

### 5.5 Admin-scope rules for the app

1. Future admin screens use `admin_*`-prefixed resources, never restyled
   storefront tokens.
2. Admin chrome (headers/sidebars) is `#0F172A`, not the kraft band — keep
   the two visually separated so scope is obvious.
3. Badges follow §5.4 (subtle+text); storefront screens keep §2 chips.
4. White text on indigo for primary fills (`--ybt-text-inverse`).

### 5.6 Divergence watchlist

| Item | App today | Web admin | Resolution |
|---|---|---|---|
| Secondary text | `#475467` | `#475569` | Converge the app to `#475569` when admin screens land (harmless either way, but one value is the goal). |
| Danger red | `#D93636` (Log out) | `#F43F5E` | Storefront choice; admin-scope screens use `#F43F5E`. |
| Near-black text | `#0F1F17` (site ink) | `#0F172A` (admin slate-black) | Intentional per-scope blacks — never unify; scope decides. |

## 6. Change log

| Date | Change | Web | App |
|---|---|---|---|
| 2026-09-25 | Contract created; `received` chip fixed to `#ECFDF5` (was `#D1FAE5`) so it matches the web's `--emerald-tint` | — | `colors.xml`, `StatusChipHelper.kt` |
| 2026-09-25 | Kraft + display-font header pass: kraft band/status bar, Archivo Black headings, kraft tokens now mapped (was "not used in app") | — | `colors.xml`, `themes.xml`, `styles.xml`, `bg_header_dark.xml`, `bg_kraft_tile.png`, 13 layouts, font resource |
| 2026-09-25 | Admin indigo palette documented as reference contract (§5): badge pattern, status mapping, admin-scope rules, divergence watchlist | `admin-modern.css` (documented only) | — |
| 2026-09-25 | Added `tools/check_brand_tokens.py` drift checker (§1 values vs live CSS/colors.xml, §2 chip bindings vs StatusChipHelper.kt); wired into sync step 3 | — | — |
