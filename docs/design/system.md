# TravelEasy design system

Status: tokens (TE-020) and app shell (TE-016 to TE-019) are **approved** by the owner on 2026-11-02. Base component specs
(TE-021, section 4) are **in-review**. Links: [previews.md](previews.md). Brand: Direction A, "Fjord".

## 1. Brand (approved)

- Name and wordmark: "TravelEasy", set as "Travel" (Figtree 500) + "Easy" (Figtree 800) with a small route-arc mark (an arc ending in a
  dot, `currentColor`, `aria-hidden`; the link carries `aria-label="TravelEasy"`). Brand colour is teal. Wordmark size 24 px in the header.
- Tone of voice: friendly and clear. Short sentences. "je" (nl) / "you" (en). Exact prices and times. No exclamation marks, no
  travel clichés, no blame. Errors say what happened and how to fix it. Buttons name the action ("Boeking annuleren", not "OK").
- Typeface: **Figtree** only (variable, SIL OFL), plus the system monospace stack for flight numbers, IATA codes and invoice numbers.
- Dropped directions (kept for the record): B "Polder" (green `#1F6B3A` / `#7BD08F`), C "Oranje" (navy `#1B2B5E` / `#9DB2FF`, orange diamond `#FF7A1A`).
- Owner decision 2026-11-02 (relayed by the orchestrator): Direction A, tone, Figtree, header labels "Nederlands / English" and
  "Thema" (Systeem / Licht / Donker) approved. The support-chat button is **not** shown until M8.

## 2. Tokens (exact values, TE-020)

Light on `:root`; dark on `[data-theme="dark"]` and under `@media (prefers-color-scheme: dark)` when no explicit `data-theme` is set
(`:root:not([data-theme="light"])`). Names below map 1:1 to CSS custom properties (`--bg`) and Tailwind theme colours (`bg-bg`, `text-muted`...).
No component may use a raw hex value.

### Colour

| Token | Light | Dark | Purpose |
|---|---|---|---|
| `--bg` | `#F7F9FA` | `#0E1A20` | page background |
| `--surface` | `#FFFFFF` | `#15252D` | header, cards, inputs, menus |
| `--surface-2` | `#E8EFF2` | `#1D313A` | selected rows, table header, subtle panels, disabled fill |
| `--text` | `#14232B` | `#EAF2F5` | body text, headings |
| `--text-muted` | `#4A5F6A` | `#A9BDC6` | hints, secondary text |
| `--border` | `#6F8590` | `#7F98A3` | borders of controls (3:1 on surface) |
| `--line` | `#D3DEE2` | `#2A424C` | decorative dividers only; never the sole boundary of a control |
| `--brand` | `#0B6E7F` | `#5CC4D6` | primary button fill, links, current-page mark |
| `--brand-hover` | `#095A69` | `#86D4E2` | primary hover, link hover |
| `--brand-active` | `#07474F` | `#A5E0EB` | primary pressed |
| `--on-brand` | `#FFFFFF` | `#07262C` | text/icons on brand fill |
| `--focus` | `#0B6E7F` | `#7DD3E3` | focus ring |
| `--success` | `#1B6B3A` | `#6FD08C` | status text/icon/border |
| `--warning` | `#8A5300` | `#F0B45A` | idem |
| `--danger` | `#B3261E` | `#FF8A80` | idem; danger button fill |
| `--danger-hover` | `#8F1C16` | `#FFA9A2` | danger button hover |
| `--danger-active` | `#70130F` | `#FFC2BC` | danger button pressed |
| `--on-danger` | `#FFFFFF` | `#2A0605` | text on danger fill |
| `--info` | `#1F5FA8` | `#8DBBF2` | idem |

Allowed pairs (TE-020 AC4 test iterates exactly this list in both themes): text and text-muted on bg, surface, surface-2 (4.5:1);
brand and brand-hover on bg, surface and surface-2 (4.5:1, links); brand-active on bg and surface; on-brand on brand, brand-hover, brand-active (4.5:1); on-danger on danger, danger-hover,
danger-active (4.5:1); success, warning, danger, info on bg, surface and surface-2 (4.5:1); border on surface, bg and surface-2 (3:1); focus on bg,
surface and surface-2 (3:1). Measured values (light/dark) are shown live in the tokens artifact. The test must also cover the hover and
active pairs; the designer verified them by calculation but they are not yet in the artifact, so the unit test is the source of truth.
Status colour is never the only signal: always icon plus words. Placeholder text uses `--text-muted`. Forced colours (`@media (forced-colors: active)`): selected rows, highlighted options, current page and today/selected days must remain distinguishable with system colours (`Highlight`/`HighlightText`, outline), never by fill alone.

Shadows: `--shadow` light `0 1px 2px rgba(20,35,43,.14), 0 8px 24px rgba(20,35,43,.10)`; dark `0 0 0 1px rgba(255,255,255,.04), 0 8px 24px rgba(0,0,0,.45)`.
Modal backdrop `rgba(7,20,26,.62)`.

### Type

Figtree variable woff2, latin subset (about 30 KB), `font-display: swap`, preload the primary face, latin-ext on demand, fallback
`system-ui, -apple-system, "Segoe UI", sans-serif`. Mono: `ui-monospace, SFMono-Regular, Menlo, Consolas, monospace`. Sizes in `rem`.

| Token | Size | Line height | Weight |
|---|---|---|---|
| `--text-xs` | 0.875rem | 1.45 | 500 |
| `--text-base` | 1rem | 1.5 | 400 |
| `--text-lg` | 1.125rem | 1.55 | 400 |
| `--text-xl` | 1.25rem | 1.4 | 700 |
| `--text-2xl` | 1.5rem | 1.3 | 700 |
| `--text-3xl` | 1.875rem | 1.2 | 800 |
| `--text-4xl` | `clamp(2.25rem, 1.6rem + 3vw, 3.25rem)` (2.25rem on mobile) | 1.1 | 800 |

Headings use `text-wrap: balance`; running text max width 65ch (lead 56ch). Letter-spacing: h1 -0.01em, uppercase labels +0.06em.

### Space, radius, elevation, motion, breakpoints

- Spacing `--space-N` = N x 4 px for N in 1, 2, 3, 4, 5, 6, 8, 10, 12, 16.
- Radius: `--radius-sm` 4, `--radius-md` 8 (controls), `--radius-lg` 12 (cards, dialogs), `--radius-full` 999.
- Elevation: 0 border only; 1 `--shadow` (cards, popovers); 2 dialog `0 2px 4px rgba(0,0,0,.2), 0 16px 40px rgba(0,0,0,.3)`.
- Motion: `--motion-fast` 100ms, `--motion-base` 200ms, `--motion-slow` 320ms, `--ease` `cubic-bezier(.2,0,0,1)`. One rule under
  `@media (prefers-reduced-motion: reduce)` sets the durations to `0.01ms`, `animation-iteration-count: 1` and `scroll-behavior: auto`.
  Motion never carries meaning on its own.
- Breakpoints (mobile first, `min-width`): sm 480, md 768, lg 1024, xl 1280. Design at 320. Content max width 1280 incl. 32 px gutters
  (16 px below md).
- Focus ring: `outline: 3px solid var(--focus); outline-offset: 2px`, on every interactive element, never clipped by `overflow`
  (use `outline-offset` inside scroll wrappers or padding). Sticky headers set `scroll-padding-top` (WCAG 2.4.11).
- Targets: at least 44 x 44 px for touch layouts; at least 24 x 24 px always (WCAG 2.5.8). The 36 px small button is for precise
  pointer, dense staff tables only.

Machine-readable file: `resources/css/tokens.css` (TE-020 AC3, implemented). It holds the CSS custom properties for both themes, the
Tailwind theme (`@theme inline`), the `@font-face` rules, the type scale, the reduced-motion rule and the global focus ring. Notes:
- Tailwind names: `bg-bg`, `bg-surface`, `bg-surface-2`, `text-text`, `text-text-muted`, `border-line`, `bg-brand`, `text-on-brand`, `text-danger`...
  `text-muted` is **not** the spec's muted text: the starter kit's `muted`/`muted-foreground` names stay as aliases (`bg-muted` = surface-2,
  `text-muted-foreground` = text-muted) so existing components keep working. `border-input` and `ring` alias `--border` and `--focus`.
- Dark theme applies on `.dark`, `[data-theme='dark']` and, with neither present (no JavaScript), under `prefers-color-scheme`. The theme script
  and `use-appearance` set both `.dark` and `data-theme`; TE-018 owns the switch UI.
- Font: Figtree variable, latin (20 KB) and latin-ext (10 KB) woff2 in `resources/fonts/`, taken from Fontsource (`@fontsource-variable/figtree`),
  licence SIL OFL 1.1, copy in `resources/fonts/OFL.txt` (copyright 2022 The Figtree Project Authors). The latin face is preloaded in `app.blade.php`.
- The pair list lives in `resources/js/design/tokens.test.ts` (includes the AC10 and AC11 pairs); that test also forbids raw hex outside `tokens.css`.

## 3. App shell (TE-016 to TE-019, approved)

Artifact: App Shell link in previews.md. Boards: desktop light nl, desktop dark en, skip link focused, theme menu open, mobile 320
light/dark, mobile menu open, 404 mobile dark, 404 desktop light.

### Structure and order
`skip link` > `<header>` > `<main id="main" tabindex="-1">` > `<footer>`. One `<h1>` per page. Tab order: skip link, brand, nav, language,
theme, login, main, footer links. The page background is `--bg`; header and footer are `--surface` with a 1 px `--line` edge.

### Skip link (TE-016)
First focusable element. Hidden by moving off-screen (`left:-9999px`, never `display:none`); on focus: `left:16px; top:8px`, brand
fill, `--on-brand` text, 12/16 px padding, radius 8, focus ring. Text "Naar de hoofdinhoud" / "Skip to main content". Activating moves focus to `<main>`.

### Header
- Container max 1280, min height 72 px, padding 8/32 px (8/16 px on mobile).
- Desktop (from md, 768): brand left; `<nav aria-label="Hoofdmenu">` with Zoeken, Aanbiedingen, Mijn boekingen, Help (links 44 px high,
  12 px padding; current has `aria-current="page"` and a 3 px bottom bar in `--brand`); right side: language group, theme button, "Inloggen" (primary button).
- Mobile (below md): brand left, "Menu" button right (icon + visible text, 44 px, `aria-expanded`, `aria-controls="mobile-menu"`, name stays
  "Menu"). The panel drops below the header (full width, `--surface`, `--shadow`, 1 px `--border` bottom): nav links stacked
  (44 px, current has 4 px left bar + `--surface-2`), "Taal" group, "Thema" radio group, "Inloggen". Escape closes it and returns focus to the button;
  focus is not trapped; activating a link closes it. At 320 px nothing scrolls horizontally.
- The header is not sticky in v1.

### Language switch (TE-017)
`<div role="group" aria-label="Taal|Language">` with two `<a lang hreflang>`: visible text "Nederlands" and "English" (accessible name equals
visible text). Segmented control, 2 px `--border`, 40 px high (44 on touch); the current language has `aria-current="true"`, a `--brand` fill
and a check icon. Choosing a language navigates to the same page in that locale, keeps the theme, focus goes to `<main>`; the locale is also
stored in a cookie (no personal data). Default nl.

**As built (TE-017):** a `<form method="post" action="/locale">` (CSRF `_token`) with a `<fieldset>`/sr-only `<legend>` and two submit buttons
(`name=locale`, `lang`), because switching posts to the server and must work without JavaScript; `hreflang` exists only on links, so it is not set.
With JavaScript the post is an Inertia visit that keeps the page state, so **focus stays on the pressed button** (story AC2, which overrides
"focus goes to main" above), `<html lang>` and the title change, and a polite live region says "Taal gewijzigd naar Nederlands".

### Theme switch (TE-018)
Desktop: button "Thema" (sun icon in light, moon in dark, caret) with `aria-expanded` and `aria-controls`, opening a popover (`--surface`, 2 px
`--border`, radius 12, `--shadow`, 260 px wide, right-aligned under the header) with a `role="radiogroup"` of three native radios (Systeem, Licht, Donker;
44 px rows, selected row `--surface-2`) and the note "Systeem volgt de instelling van je apparaat." Arrow keys move and select, Escape closes
and returns focus to the button, clicking outside closes. Mobile: the same radiogroup inline in the menu panel. Default Systeem. The choice is stored
client-side only (cookie or localStorage) and applied before first paint by an inline script with a CSP nonce, so there is no flash; Systeem listens to
`prefers-color-scheme`.

**As built (TE-018):** the choice lives in the `appearance` cookie only (no localStorage); the server renders the class and `color-scheme` meta
from it (TE-018 AC2), so the hook reads the same cookie. The popover is closed with Escape, a click outside or Tab out of it. Applied theme is set as `data-theme="light|dark"` on `<html>`; `color-scheme` follows.

### Footer
Two labelled `<nav>`: Service (Help en veelgestelde vragen, Annuleren en wijzigen) and Juridisch (Privacyverklaring, Toegankelijkheidsverklaring,
Algemene voorwaarden); links 44 px high. Fine print: "(c) 2026 TravelEasy. Prijzen zijn inclusief btw. Dit is een demo: alle gegevens zijn fictief."
(the demo sentence is shown only while `DEMO_MODE` is on). The support-chat button (M8) is **not** drawn; when M8 arrives it needs its own spec and
a reserved bottom-right area with extra footer padding.

### Home placeholder (replaces the starter-kit welcome page)
Hero: `<h1>` "Vind je volgende reis", lead, search card (Van, Naar, Vertrek, Reizigers, "Alle velden zijn verplicht.", primary "Zoek reizen"); desktop two
columns (card 440 px), mobile stacked. Below: three value points (final price incl. VAT; book for a group; data minimisation). No images in v1, icons are inline SVG.

### 404 (TE-019)
Same shell, HTTP 404. Decorative route illustration (`aria-hidden`), mono eyebrow "Foutcode 404", `<h1>` "Deze pagina bestaat niet", body, primary
"Naar de startpagina", secondary "Zoek reizen". 403/419/429/500/503 reuse the layout with `error.<code>.title|body` keys. The page is localised by the active locale.

**As built (TE-019):** one Inertia page `error` (props `status`, `requestId`, `retryAfter`) for all six statuses, in the public shell. Extra keys:
`error.code` ("Foutcode :code"), `error.search`, `error.reload` (419), `error.reference` ("Referentie: :id", selectable text, no copy button),
`error.retry_after` (429). Titles follow this table, not the story's AC1 wording "Pagina niet gevonden"; focus moves to the `<h1>`.

### Copy keys (nl / en)
| Key | nl | en |
|---|---|---|
| `shell.skip` | Naar de hoofdinhoud | Skip to main content |
| `shell.nav.label` | Hoofdmenu | Main menu |
| `shell.nav.search` / `offers` / `bookings` / `help` | Zoeken / Aanbiedingen / Mijn boekingen / Help | Search / Offers / My bookings / Help |
| `shell.menu` | Menu | Menu |
| `shell.login` | Inloggen | Log in |
| `shell.lang.label` | Taal | Language |
| `shell.theme.label` | Thema | Theme |
| `shell.theme.system` / `light` / `dark` | Systeem / Licht / Donker | System / Light / Dark |
| `shell.theme.note` | Systeem volgt de instelling van je apparaat. | System follows your device setting. |
| `home.title` | Vind je volgende reis | Find your next trip |
| `home.lead` | Vergelijk vluchten, boek voor je hele gezelschap en zie de eindprijs inclusief btw voordat je bevestigt. | Compare flights, book for your whole group and see the final price, VAT included, before you confirm. |
| `search.required` | Alle velden zijn verplicht. | All fields are required. |
| `search.submit` | Zoek reizen | Search trips |
| `error.404.code` | Foutcode 404 | Error code 404 |
| `error.404.title` | Deze pagina bestaat niet | This page does not exist |
| `error.404.body` | De link is verouderd of er zit een typefout in het adres. Ga terug naar de startpagina of zoek direct een reis. | The link is out of date or the address has a typo. Go back to the home page or search for a trip. |
| `error.home` | Naar de startpagina | Go to the home page |
| `footer.fine` | (c) 2026 TravelEasy. Prijzen zijn inclusief btw. Dit is een demo: alle gegevens zijn fictief. | (c) 2026 TravelEasy. Prices include VAT. This is a demo: all data is fictional. |

Remaining shell strings (value points, footer links) are in the artifact; the frontend-developer derives keys from them.

## 4. Base components (TE-021, in review)

Artifact: Base Components link in previews.md (two boards: forms and inputs; data and feedback). All components are built on React Aria
Components (RAC) unless noted, styled with the tokens above. Common rules for **every** control: focus ring per section 2; target at least 44 px
(36 px allowed for the small button only in dense staff tables with a precise pointer); text 4.5:1, UI parts 3:1; no meaning by colour alone;
hover never the only affordance; disabled controls use `--surface-2` fill, `--text-muted` text and a dashed `--line` border and are not
focusable unless a tooltip/explanation requires it (prefer explaining why in help text); respect `prefers-reduced-motion`.
Heights in this section are `min-height` (never fixed `height`) so text spacing (WCAG 1.4.12) and 400 % zoom never clip. Under `@media (pointer: coarse)` every control, dense table row (40 to 44) and small button (36 to 44) is raised to 44 px. Overlays (dialog, popovers) set `max-height: calc(100dvh - 32px)` and scroll internally. A highlighted option on a `--brand` fill uses `--on-brand` text. Fixed or sticky bottom UI (toast region, mobile action bars) never covers a focused control: set `scroll-padding-bottom` on `html` to the region height plus 16 px (or reserve the area in layout) (WCAG 2.4.11).
Each "Tests" line lists what TE-022/TE-023 must write (Vitest/RTL for behaviour and ARIA; Playwright + axe for focus, size, live regions).

### 4.1 Button
- Purpose/anatomy: runs an action. Optional icon (left), label, optional spinner. Icon-only buttons need `aria-label`.
- Variants: primary, secondary, danger, ghost. Sizes: sm 36, md 44 (default), lg 52. Radius 8, 2 px border, weight 700.
- Tokens: primary `--brand`/`--on-brand` (hover `--brand-hover`, active `--brand-active`); secondary `--surface`, `--text`, border `--border` (hover `--surface-2`
  + border `--text`; active border `--brand`); danger `--danger`/`--on-danger`; ghost transparent, `--brand` text (hover `--surface-2`, active underline).
- Keys: Enter and Space. RAC `Button`; `type` defaults to `button`.
- States: default, hover, focus, active, disabled, pending. Pending: `isPending`, width kept, label becomes "Bezig…"/"Working…" with a spinner (static
  under reduced motion), `aria-disabled`, announced politely once, extra activations ignored.
- Copy rule: the label names the result ("Boeking annuleren"). Dutch labels are about 30 % longer; labels wrap, buttons never truncate.
- Tests: variants/sizes render; pending ignores clicks and announces; 44 px target; focus ring visible; axe in both themes.

### 4.2 Link
- Always underlined (offset 3 px). Default `--brand`; hover `--brand-hover` and 3 px underline; visited same as default; focus ring.
- External: trailing icon + visually hidden "(opent in nieuw tabblad)"/"(opens in a new tab)", `target="_blank" rel="noopener noreferrer"`.
  Internal: Inertia `Link`. Nav current page: `aria-current="page"`. Link text is descriptive, never "klik hier".
- Keys: Enter. RAC `Link`. Tests: external has sr text and rel; internal uses Inertia navigation.

### 4.3 Field (TextField, password, textarea) and form error summary
- Anatomy: persistent visible label above; optional "(optioneel)" suffix; control (48 px high, 2 px `--border`, radius 8); help text; error text with icon.
  Placeholders are only examples, never labels. A form that has only required fields says "Alle velden zijn verplicht." at the top.
- States: default, hover (border `--text-muted`), focus (border `--brand` + ring), filled, disabled, read-only (dashed border, `--bg` fill), invalid
  (border `--danger`, icon + text, `aria-invalid="true"`), success (border `--success`, icon + text; only after a real async check, such as a valid offer code).
- Wiring: label via `for`/RAC `Label`; help and error ids in `aria-describedby`; errors appear on blur or submit, not on first keystroke, and clear when fixed. Announcement: an error that appears on blur is also written into one persistent, visually hidden polite live region (`role="status"`, present on page load, text replaced with "E-mailadres: Vul een geldig e-mailadres in...") so a screen reader that has already moved to the next field still hears it (4.1.3); on submit only the summary announces (focus move). The `aria-describedby` link stays for when the user returns to the field. Format and limits are visible before any error: the 12-character minimum in the password help, the date format in the date help (3.3.2). Placeholder text, when used for an example, is `--text-muted`.
  `autocomplete` is a typed prop (given-name, family-name, email, tel, bday, new-password, current-password, one-time-code). Paste is never blocked (WCAG 3.3.8).
  Sensitive fields (passport, date of birth) say why they are asked, in help text. Password: toggle button with a fixed accessible name "Toon wachtwoord" / "Show password" and `aria-pressed` (never a name that flips), min 12 characters stated in the help text.
- Error copy: what happened and how to fix, e.g. "Vul een geldig e-mailadres in, bijvoorbeeld naam@voorbeeld.nl."
- Error summary (on submit): a `section` with `role="group"`, heading "Er zijn N problemen met dit formulier", list of links "Veld: probleem"; border 2 px
  `--danger`. It receives focus (`tabindex="-1"`); each link focuses its field. Not a live region (focus move announces it). Redundant entry (WCAG 3.3.7): do not ask again for data already given in the session.
- RAC `TextField`, `Input`, `TextArea`, `FieldError`. Tests: label association, describedby chain, aria-invalid, summary focus and link-to-field, 320 px and text-spacing overrides.

### 4.4 Select
- Trigger looks like a field with a caret; listbox popover (`--surface`, 2 px `--border`, `--shadow`, options 44 px); selected option bold + check; highlighted option `--brand` fill.
- Keys: Enter/Space/ArrowDown open; arrows move; Home/End; type-ahead; Enter selects; Escape closes and returns focus. Tab leaves.
  On touch with 5 options or fewer use the native `<select>`.
- ARIA: button `aria-haspopup="listbox"` + `aria-expanded`; `role="listbox"`/`option` with `aria-selected`. RAC `Select`. Error and help same as 4.3 ("Kies een reisklasse om door te gaan.").
- Tests: keyboard model, type-ahead, selection announced, focus return.

### 4.5 Combobox
- Anatomy: input with `role="combobox"`, listbox popover under it, option label + mono code (airport "Málaga AGP").
- Keys: typing filters (debounce about 300 ms for announcements); ArrowDown/Up move the highlight while DOM focus stays in the input (`aria-activedescendant`); Enter picks; Escape closes, second Escape clears; Tab does not auto-pick.
- ARIA: `aria-expanded`, `aria-controls`, `aria-autocomplete="list"`. Result counts are announced by the RAC built-in announcer only; do not add a custom live region (it would duplicate). The dashed "live region" caption in the artifact shows the text RAC must produce, via localised strings: "3 resultaten beschikbaar" / "Geen resultaten"; the empty text is also rendered visibly in the popover as a non-option message. Add a toggle button (chevron, 44 px, `aria-label` "Toon luchthavens" / "Show airports", `tabindex="-1"`, `aria-expanded`) so touch screen-reader users can open the list without typing. 
- Unmatched text on blur: error "Kies een luchthaven uit de lijst, bijvoorbeeld Malta (MLA)." RAC `ComboBox`. Tests: announcer text, no duplicate live region, activedescendant, toggle button opens the list, no-result message.

### 4.6 Date picker
- Anatomy: single text input with free typing in the locale format (nl `dd-mm-jjjj`, en `dd/mm/yyyy`; RAC DateField is segment-only, so it is not used), format shown under the label, calendar button (44 px, `aria-label` "Kalender openen"), calendar popover dialog.
- Calendar: month header with previous/next buttons; 7-column grid of `1fr` cells (fits 320 px; never 7 fixed 44 px columns), Monday first; day cells min-height 40 px (44 on `pointer: coarse`); today outlined, selected filled + bold + inner border + underline bar (visible in forced colours), unavailable days dimmed + struck through and exposed as unavailable (`aria-disabled`).
- Keys in grid: arrows (day/week), PageUp/PageDown (month), Shift+PageUp/PageDown (year), Home/End (week), Enter selects, Escape closes and returns focus to the button. Month change announced politely.
- Parse errors: "Die datum bestaat niet. Gebruik dd-mm-jjjj, bijvoorbeeld 12-06-2027." / "Vul een datum in als dd-mm-jjjj." Out-of-range: "Kies een datum vanaf 7 juni 2027." No drag-only interaction anywhere (WCAG 2.5.7); a date range uses two fields.
- RAC `Calendar` + `Popover` + `Dialog`, input parsed by the date library. Tests: typing parse in both locales, error copy, keyboard model, unavailable days.

### 4.7 Number field
- Anatomy: minus button, input (72 px wide, centred, tabular numbers), plus button; buttons 48 px; label above; help below.
- Keys: ArrowUp/Down step, PageUp/Down step x10, Home/End min/max; typing accepts the locale format (nl `23,5`, en `23.5`). Display always uses the active locale.
- Limit: stepper button disabled at min/max and the help text explains ("Minstens 1 volwassene per boeking."). Invalid input keeps the typed text and shows "Kies een getal van 1 tot 9."
- ARIA: `role="spinbutton"`, `aria-valuemin/max/now`, buttons named "Minder volwassenen"/"Meer volwassenen". RAC `NumberField`. Tests: locale parsing, min/max, step keys.

### 4.8 Table
- Native `<table>` with `<caption>`, `<th scope="col">`; header `--surface-2` with 2 px `--text` bottom rule; rows min-height 44 px (40 px dense for staff, 44 under `pointer: coarse`), 1 px `--line` separators; numbers right-aligned, tabular figures; status as badge = icon + words.
- Sortable column: real button in the th; `aria-sort` ascending/descending/none; icon shows direction; announcement "Gesorteerd op Vertrek, oplopend" (polite). Only one sorted column.
- Model: **native `<table>` for all read-only and sortable tables** (sort buttons are real buttons in the th; Tab order; row actions are buttons/links in cells). RAC `Table` is a grid with arrow-key cell navigation, so it is used **only** for tables with row selection or bulk actions; there, sortable headers use RAC `Column` with `allowsSorting` (no extra button), row actions live in one actions cell with a menu, and the grid keyboard model (arrows, Space selects) is documented on the page. Never mix a native-button th with RAC `Table`.
- Responsive: wide staff tables scroll inside `role="region" tabindex="0" aria-label` wrapper; customer tables reflow below md to stacked cards with `dt/dd` pairs (every cell keeps its header text). At 320 px no page-level horizontal scroll.
- Empty and loading messages are announced through one persistent polite live region (`role="status"`, present before results change, text replaced with "Geen resultaten gevonden" / "Vluchten laden…"); the visible empty-state block is plain content with the cause and a "Filters wissen" button (not itself a status element injected together with its content). Skeleton rows are static.
- Tests: aria-sort toggling, caption, reflow at 320, empty and loading announcements.

### 4.9 Dialog
- Anatomy: backdrop (`rgba(7,20,26,.62)`), panel (`--surface`, radius 12, max 440 px, padding 24), title (h2), body, actions right-aligned (stacked, full width at 320 px), close button (44 px, `aria-label` "Sluiten").
- Behaviour: focus trapped; initial focus on the safest action ("Terug"), never on a destructive button; Escape closes (unless an action is pending); focus returns to the opener; page behind inert, scroll locked. Panel `max-height: calc(100dvh - 32px)` with internal scroll so 400 % zoom and text spacing never hide the actions; focused elements scroll into view inside the panel.
- ARIA: `role="dialog"` or `alertdialog` for destructive confirmation, `aria-modal`, `aria-labelledby`, `aria-describedby`. RAC `Modal` + `Dialog` (`ModalOverlay`). Destructive confirm names the action and states the consequence, with money including VAT.
- Tests: trap, Escape, focus restore, initial focus, axe with dialog open.

### 4.10 Alert and toast
- Alert (inline): 2 px border in the status colour on `--surface`, icon + bold title + text. Info/success/warning `role="status"`; danger `role="alert"` (sparingly; one per event). Dismissible only when informational.
- Toast: card (`--surface`, 1 px `--border`, radius 12, `--shadow`, max 420 px), icon + title + text + dismiss button (36 px visual, 44 px hit area). Bottom-left desktop, full width bottom on mobile, max 3, newest on top.
  Role: the toast region container is a persistent `role="status"` polite live region (plus a separate persistent `role="alert"` region for errors); individual toasts have no role of their own (the RAC default `alertdialog` is overridden, because a toast is not a dialog and must not steal focus). Auto-dismiss only for non-critical confirmations: 8 s minimum (+1 s per 15 words), pausing on hover and focus; errors and anything important stay until dismissed (WCAG 2.2.1). Dismiss is a real button; Escape dismisses the focused toast; F6 jumps to the region. Never the only place for something that must not be missed (use an alert or banner). No sensitive data. Placement (bottom-left desktop, full-width bottom mobile) is the single variable `--toast-region`, so it can change after the owner answers; 2.4.11 is handled with `scroll-padding-bottom` (section above).
- RAC `ToastRegion` / toast queue. Tests: region roles, no role on the toast itself, timer pause on hover and focus, no auto-dismiss for errors, dismiss, no auto-dismiss under 8 s; Playwright: focus a control at the bottom of a scrolled page with a toast open and assert it is not covered (2.4.11).

### 4.11 Banner
- Page-level strip above the header: `--surface-2`, 3 px bottom border in the status colour, icon, text, optional action/dismiss. A `section` with `aria-label`; no live region on load.
- Dismissible only when informational (maintenance notice): dismissal remembered for the session; focus moves to the next focusable element. Session-expiry (warning): shown at least 2 minutes before expiry (well over the 20 s WCAG 2.2.1 minimum), has the action "Ingelogd blijven" which extends the session, is not dismissible, and is announced by being injected into a live region that already exists (an empty `role="alert"` container in the banner slot, present on page load; a banner inserted together with its own role does not reliably announce, 4.1.3). Banners sit after the skip link in DOM and tab order, so the skip link stays first (2.4.1). The demo banner is permanent while `DEMO_MODE` is on.
- Tests: label present, dismiss removes and moves focus, non-dismissible variants have no dismiss button, the session banner is announced via the pre-existing region, "Ingelogd blijven" extends the session, the skip link is first in tab order with a banner present.

### 4.12 Pagination
- `nav aria-label="Paginering"` with a list of links to real URLs (`?page=3`). Elements: Vorige, page numbers (current + neighbours + first/last, ellipses `aria-hidden`), Volgende; 44 px; current page filled `--brand`, bold, `aria-current="page"`.
  First/last page: Vorige/Volgende rendered as dimmed dashed non-links. Page links carry a visually hidden "Pagina" prefix where the number alone is ambiguous.
- Mobile (below md): compact "‹ Pagina 3 van 12 ›" with `aria-label` on the arrow links.
- After navigation focus moves to the results heading (`tabindex="-1"`, text "Vluchten, pagina 3 van 12"); that focus move is the only announcement, with no extra live message (it would be read twice). Result count shown above.
- Tests: aria-current, URL-based navigation, focus lands on the heading with no live-region duplicate, compact layout at 320.

### 4.13 Copy keys for the components (nl / en)
`ui.pending` Bezig… / Working…; `ui.close` Sluiten / Close; `ui.optional` (optioneel) / (optional); `ui.show` Toon / Show; `ui.hide` Verberg / Hide;
`ui.external` (opent in nieuw tabblad) / (opens in a new tab); `table.empty` Geen resultaten gevonden / No results found; `table.reset` Filters wissen / Clear filters;
`table.loading` Laden… / Loading…; `pagination.label` Paginering / Pagination; `pagination.prev` Vorige / Previous; `pagination.next` Volgende / Next;
`pagination.of` Pagina {page} van {total} / Page {page} of {total}; `form.summary.title` Er zijn {count} problemen met dit formulier / There are {count} problems with this form;
`form.required` Alle velden zijn verplicht. / All fields are required.; `date.open` Kalender openen / Open calendar; `date.error.invalid` Die datum bestaat niet. Gebruik dd-mm-jjjj, bijvoorbeeld 12-06-2027. /
That date does not exist. Use dd/mm/yyyy, for example 12/06/2027.; `combobox.count` {n} resultaten beschikbaar / {n} results available; `combobox.none` Geen luchthavens gevonden voor "{q}" / No airports found for "{q}".
Full sample copy is in the artifact.

### 4.14 Open questions for the owner (TE-021)
1. Toast placement bottom-left on desktop (so it never covers the future chat button at bottom-right): OK?
2. Staff tables use a 40 px dense row and a 36 px small button (precise pointer only); customer layouts stay at 44 px. OK?
3. The destructive confirm shows the refund amount including VAT in the dialog text. OK?

### 4.15 Tests every component must have (TE-022, TE-023)
Beyond the per-component lines above, each component's tests include:
1. **State-pair contrast from tokens:** for every state (default, hover, active, selected, highlighted, invalid, success; disabled text excluded) compute contrast from the token file in both themes and assert 4.5:1 for text and 3:1 for UI parts.
2. **Focus not obscured (2.4.11):** Playwright scrolls a focused control to the bottom of the viewport with a toast, sticky element or dialog footer present and asserts its box is not covered (`elementFromPoint` on its corners).
3. **Live regions:** assert the region exists before the change, the exact nl and en text, one announcement per event, and the right role (toast container `status`, errors `alert`, no role on the toast itself).
4. **Text spacing and zoom:** apply the WCAG 1.4.12 overrides (line height 1.5, paragraph spacing 2x, letter spacing 0.12em, word spacing 0.16em) and 400 % zoom (320 CSS px wide) and assert no clipped text, no overlap and no horizontal page scroll; dialog actions stay reachable.
5. **Forced colours:** Playwright `forcedColors: 'active'`; selected rows, highlighted options, current page, today and selected days differ from their neighbours without relying on fill.
6. **Targets:** every interactive element at least 24 x 24 px, and 44 x 44 px under `pointer: coarse` emulation.
7. **axe** in light and dark, nl and en, with the component open and in each state.
8. **Manual screen-reader pass** (NVDA with Firefox or Chrome, VoiceOver with Safari) per component, recorded in the story handoff: name, role, state, error and live announcements, and nothing announced twice.

### 4.16 Changes after the first accessibility review (2026-11-02)
Banner (2.2.1, 4.1.3, 2.4.1), toast and dialog (2.4.11, 2.2.1, role), field (4.1.3, 3.3.1, 3.3.2, password toggle, placeholder), date picker (cell width, input model, selected day),
table, pagination and combobox (live regions, grid model, toggle button), and the cross-cutting rules (min-heights, dialog max-height, highlight text colour, forced colours,
extra contrast pairs, coarse-pointer sizes, test list) are addressed in the sections above. Toast placement stays a variable until the owner answers.
