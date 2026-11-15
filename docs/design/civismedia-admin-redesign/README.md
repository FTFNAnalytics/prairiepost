# Handoff: CivisMedia Admin Redesign — "Front page" direction (1b)

## Overview
A modernization of the newsroom admin (`/admin/`) in the **FTFNAnalytics/prairiepost** codebase (branch `claude/prairie-post-news-site-hiffgl`), rebranded **CivisMedia**. The chosen direction treats the admin like the front page of a paper: a thick–thin newspaper head with a dateline rail, a serif-only interface on paper white, cyan as the working accent and magenta as a rarer spot color. It replaces the current dense mono/serif "Prairie Dispatch" admin chrome (`assets/css/admin.css`, `admin/_layout.php`).

## About the Design Files
The files in this bundle are **design references created in HTML** — prototypes showing intended look and behavior, not production code to copy directly. The task is to **recreate this design inside the existing PHP admin**: rewrite `assets/css/admin.css` (or add `assets/css/civismedia-admin.css` and swap the link in `admin/_layout.php`), update the header/nav markup in `admin/_layout.php`, and make small per-page markup adjustments in `admin/*.php`. No framework change — it stays plain PHP + one stylesheet.

## Fidelity
**High-fidelity for the Dashboard** (`admin/index.php`): recreate it pixel-perfectly from the spec below and the `CivisMedia Admin Redesign.dc.html` mockup (option **1b**, the middle card). The remaining admin screens (Stories, Story editor, Ads, Newsletter, Sources, Subscribers, Accounts, Settings) were not individually mocked in this direction — apply the **system rules** below to them; the current-state recreation (`Current Admin (recreation).dc.html`) documents their existing structure, which stays the same.

## Design Tokens
From the Broadsheet design system (`styles.css` in this bundle is the canonical token sheet — port its `:root` block verbatim):

- Ground `--color-bg: #f3f2f2` · surface fill `--color-surface: #eae9e9` · ink `--color-text: #201e1d`
- Accent (cyan): `#0088b0`; ramp highlights: 100 `#e9f8ff`, 200 `#cbeeff`, 600 `#1186ac`, 700 `#006786`, 800 `#004961`, 900 `#0a303e`
- Accent 2 (magenta): `#d6006c`; 100 `#fff1f4`, 700 `#aa0b56`, 800 `#790e3d`
- Neutral ramp 100–900 (see styles.css); muted text = ink at 55%
- Divider: ink at 16% (`color-mix(in srgb, #201e1d 16%, transparent)`)
- Type: **Source Serif 4** for everything (headings 600, body 400, true italic) — `@import` from Google Fonts. No sans-serif, no monospace anywhere; the serif is the chrome.
- Spacing: 5 / 10 / 15 / 20 / 30 / 40 px (`--space-1/2/3/4/6/8`)
- Radius: 2px standard (`--radius-md`), 4px large. This replaces the old zero-radius rule.
- Shadows: sm `0 1px 2px rgba(45,43,43,.14)`, md `0 3px 10px rgba(45,43,43,.16)`
- Focus: `:focus-visible { outline: 2px solid #0088b0; outline-offset: 2px; }` — never the browser default.

## Screens / Views

### Admin chrome (every page — replaces admin_header/admin_footer in `admin/_layout.php`)
Page container: max-width 1264px, horizontal padding 48px, background `#f3f2f2`.

**Head, top to bottom:**
1. **Top rule**: `border-top: 3px solid #201e1d` on the dateline strip.
2. **Dateline strip**: flex, space-between, padding 7px 0, `border-bottom: 1px solid` divider. Three spans, 11px, uppercase, letter-spacing .08em, color `#006786`: left "The newsroom edition", center full date ("Monday, August 11, 2026"), right live status ("Wire last fetched 6:02 a.m.").
3. **Brand row**: flex, align-end, space-between, padding 26px 0 18px. Left: wordmark 40px / 600 / letter-spacing −0.015em / line-height 1 — "CivisMedia" in ink + " Newsroom" in `#1186ac`. Right: page-level actions (see per-page), e.g. "Fetch all feeds" (secondary button) + "New story" (primary button).
4. **Nav band**: `border-top: 4px solid #201e1d` (the thick of the thick–thin pair; the dateline's 1px hairline is the thin). Background `#e9f8ff` (accent-100), padding 10px 14px, flex gap 22px, links 14px serif, ink, no underline, hover → cyan. Items: Dashboard · Stories · Post a link · Desks · Sources · Advertising · The 6 a.m. · Subscribers · Accounts · Settings. **Active page = solid pill**: background `#0088b0`, text `#f3f2f2`, padding 4px 10px, radius 2px. Right-aligned: "Margaret Olesen · Sign out" 13px, neutral-600. Role gating stays exactly as in `_layout.php` today (authors see fewer items).
5. Below the band, a 1px hairline (divider color).

Footer: keep the current two-span footer line, restyled 11px uppercase serif, neutral-600, no top border (whitespace instead).

### Dashboard (`admin/index.php`) — fully specced
Two-column grid under the chrome: `1fr 320px`, gap 56px, margin-top 18px.

**Left — The morning pull**
- h2 "The morning pull", 30px/600, margin 0 0 4px. Sub-line 14px muted: "Start a draft from a headline, or tick it off once it's covered."
- **Region control**: segmented control (replaces the old tab strip). 1px divider border, radius 2px, options 13px padding 7px 12px, selected option = cyan fill with paper text. Options come from the `regions` setting JSON.
- **Wire items** as a numbered index (replaces `.newsitem` rows): grid `34px 1fr`, gap 14px, padding 14px 0. Index numeral 20px/600 cyan `#0088b0`. Headline 19px/600 serif, line-height 1.25, ink (links to the source, unstyled color). Meta 12px neutral-600: "Source · time". Action row (flex, gap 8px, 13px):
  - **Start draft** — primary button (form POST, unchanged behavior)
  - **Post link** — secondary button with cyan border `#0088b0` and text `#006786` (editors only)
  - **Mark used** — ghost button, magenta text `#aa0b56`
  - Used items: whole row opacity .5, numeral loses cyan, headline weight 400, meta gains "· used", only a magenta "Unmark" ghost remains.

**Right rail — three surface panels** (background `#eae9e9`, padding 20px, radius 2px, stacked with 34px gaps):
1. **The paper today** — the six counts from `pp_counts()` as rows (flex space-between, 14px, padding 6px 0): label + value 600. Values in `#006786`; **In review in magenta `#aa0b56`** (it wants attention).
2. **Review queue** (editors only) — per story: headline 14.5px/600 ink, then 12px neutral-600 "Author · Desk · [Review]" where Review is a small ghost button (12px, padding 1px 6px, cyan).
3. **The front page, as set** (editors only) — one line per slot with a cyan tag (see Tags) for the slot name ("Hero", "Featured", "Desk leads") followed by 13.5px story titles.

Panel headers everywhere: h6 style — 13px, uppercase, letter-spacing .08em, 600, color `#006786`, margin 0 0 10px.

Open drafts: keep as a list or fold into the rail; each row gets a small ghost "Open" button.

### Remaining screens — apply the system
Structure per screen stays as in the repo (and as documented in `Current Admin (recreation).dc.html`); restyle with this mapping:

| Current (admin.css) | New treatment |
| --- | --- |
| `.adminbar` dark green top bar | The full newspaper head above |
| `.pagetitle` (condensed caps) | h2, 30px/600 serif, sentence case |
| `.pagesub` | 14px muted serif |
| `.panel` (white, 1px border) | Surface panel: `#eae9e9`, padding 20px, radius 2px, **no border**; h6 header in cyan-700 |
| `.tbl` | `.table`: 14px; th 11px uppercase .08em, 60% ink, 1px divider under header; td padding 10px, 8%-ink row rules; row hover 4% ink wash |
| `.btn` (dark block) | Primary: cyan fill, paper text, radius 2px, padding 10px 18px, 14px/600; hover `#1186ac`, active `#006786` |
| `.btn--sky` | Primary (cyan) |
| `.btn--ghost` | Secondary: transparent, 1px divider border; or cyan-outlined secondary for promoted secondary actions (Post link pattern) |
| `.btn--danger` | Ghost with magenta text `#aa0b56` for destructive row actions (Delete, Remove); confirm dialogs unchanged |
| `.chip--published` | Tag, cyan tint: bg `#e9f8ff`, text `#004961`, 11px, padding 3px 10px, radius 1.5px |
| `.chip--in_review` | Tag, magenta tint: bg `#fff1f4`, text `#790e3d` |
| `.chip--draft` / `--used` | Tag, neutral tint: bg `#f8f4f4`, text `#444141` |
| `.chip--scheduled` | Tag, cyan outline: 1px `#0088b0` border, cyan text |
| `.chip--ok` / `--error` | Cyan tint / magenta tint |
| `.tabs` region tabs | Segmented control |
| Form `label` (mono caps) | 12px serif, 70% ink, 5px below-gap |
| `input`/`select`/`textarea` | `.input`: surface fill `#eae9e9`, 1px divider border, radius 2px, 14px serif, min-height 36px; focus border cyan |
| `.edtoolbar` buttons | Secondary buttons in a flex row, gap 4px |
| `.editor` | Paper-white writing surface (`#f3f2f2` on the surface panel or vice versa), 17px serif, 1.6 line-height, cyan focus border |
| `.flash` | Surface panel with a 3px top rule: cyan for success, magenta for error |
| `.stats` stat tiles | Open (unboxed) figures: value 26–44px/600 serif, label 10px uppercase cyan-700 or neutral-600 |
| `.deskdot` | Keep — 11px square in the desk's own color (desk colors are user data) |

## Interactions & Behavior
- All existing PHP behavior, forms, CSRF fields, confirms and role gating are unchanged — this is a reskin plus the small dashboard re-layout above.
- Hovers: primary `#1186ac`; secondary/ghost: 7–10% ink or accent wash. Pressed: one ramp step deeper. Links: cyan, underline offset 3px.
- Region segmented control keeps the existing `?region=` link navigation (each option is an `<a>`; the selected one takes the cyan fill).
- Table row hover: 4% ink wash. No JS required beyond what exists (`editor.js` untouched).

## State Management
None new. All state is server-rendered PHP as today.

## Assets
- **Fonts**: Source Serif 4 via Google Fonts (`@import` in the stylesheet), weights 400/600 + italic 400. Self-hosting optional as with the current fonts.
- **Logo**: the current `logo-reversed.svg` is Prairie Dispatch-branded and dark-bar specific; the new head uses a **text wordmark** ("CivisMedia Newsroom") — no image asset needed. If a CivisMedia logo exists, it replaces the wordmark at ~28px height.
- No icons required (the system is deliberately icon-free serif chrome). If icons are added later: Phosphor, duotone weight.

## Files
- `CivisMedia Admin Redesign.dc.html` — the mockup source; **option 1b (middle card)** is the approved direction. (1a and 1c are alternates; ignore.) Open in the design project for a live render.
- `Current Admin (recreation).dc.html` — faithful recreation of the current admin, screen by screen, for before/after and structural reference.
- `styles.css` — the Broadsheet token sheet + component classes the mockup uses; port tokens and component recipes from here.
- Repo files to change: `assets/css/admin.css`, `admin/_layout.php`; light markup touches in `admin/index.php` (dashboard re-layout) and the tag/button class names across `admin/*.php`.
