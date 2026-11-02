# TravelEasy design system (DRAFT, round 1, in review)

Status: `in-review`. Brand direction not yet chosen by the owner. Previews: see [previews.md](previews.md).
Stories: TE-020 (tokens), TE-016 to TE-019 (shell). Component specs (TE-021 to TE-023) are not written yet.

## 1. Brand (proposal, owner decides)

| | A: Fjord (recommended) | B: Polder | C: Oranje |
|---|---|---|---|
| Idea | Deep teal on cool blue-grey neutrals; calm and trustworthy | Dutch green on soft green-grey; fresh, understated | Midnight navy with one orange diamond; most distinctive |
| Wordmark | "Travel" medium + "Easy" extra bold, route-arc mark | lowercase `traveleasy` bold, two dots joined by a dotted route | spaced capitals `TRAVELEASY`, orange diamond (decoration only) |
| Brand light / dark | `#0B6E7F` / `#5CC4D6` | `#1F6B3A` / `#7BD08F` | `#1B2B5E` / `#9DB2FF` |

Tone of voice (all directions): friendly, clear, short sentences; address the traveller as "je" (nl) / "you" (en); state prices
and times precisely; no exclamation marks, no travel clichés, no blame in errors. Error copy says what happened and how to fix
it. Direction B allows a slightly more playful register; C a dry touch in empty states.

Decision record: filled in when the owner answers (TE-020 AC9). Until then Direction A is used for the shell artifact.

## 2. Tokens

All colours are CSS custom properties (light on `:root`, dark under `[data-theme="dark"]` and `prefers-color-scheme: dark`
when no explicit choice). Values below are Direction A; B and C are in the artifact. Ratios are measured (WCAG 2.x relative
luminance) and also shown live in the artifact. Every pair passes: text pairs at least 4.5:1, UI/focus pairs at least 3:1.

| Token | Light | Dark | Purpose |
|---|---|---|---|
| `--bg` | `#F7F9FA` | `#0E1A20` | page background |
| `--surface` | `#FFFFFF` | `#15252D` | cards, header, inputs |
| `--surface-2` | `#E8EFF2` | `#1D313A` | selected rows, subtle panels |
| `--text` | `#14232B` | `#EAF2F5` | body text, headings |
| `--text-muted` | `#4A5F6A` | `#A9BDC6` | hints, secondary text (still 4.5:1 on bg, surface, surface-2) |
| `--border` | `#6F8590` | `#7F98A3` | input and control borders (3:1 on surface) |
| `--line` | `#D3DEE2` | `#2A424C` | decorative dividers only, never the sole boundary of a control |
| `--brand` | `#0B6E7F` | `#5CC4D6` | primary buttons, links, current-page marks |
| `--on-brand` | `#FFFFFF` | `#07262C` | text/icons on `--brand` (5.9:1 / 7.8:1) |
| `--focus` | `#0B6E7F` | `#7DD3E3` | focus ring (3:1 on bg, surface, surface-2) |
| `--success` | `#1B6B3A` | `#6FD08C` | status text, always with icon + words |
| `--warning` | `#8A5300` | `#F0B45A` | idem |
| `--danger` | `#B3261E` | `#FF8A80` | idem |
| `--info` | `#1F5FA8` | `#8DBBF2` | idem |

Allowed pairs (the Vitest contrast test in TE-020 iterates exactly this list, per theme): text, text-muted on bg/surface/surface-2;
brand as text on bg/surface; on-brand on brand; each status colour as text on surface; border on surface (3:1); focus on bg,
surface, surface-2 (3:1). Status colour is never the only signal: use an icon and a word (for example check + "Bevestigd").

Type: **Figtree** (variable, OFL), latin woff2 subset about 30 KB, `font-display: swap`, preload the primary face; latin-ext on
demand; fallback `system-ui, sans-serif`. Mono for flight numbers, IATA codes and invoice numbers: `ui-monospace, SFMono-Regular,
Menlo, Consolas, monospace` (no download). Sizes in `rem`.

| Token | Size | Line height | Weight |
|---|---|---|---|
| `--text-xs` | 0.875rem (14) | 1.45 | 500 |
| `--text-base` | 1rem (16) | 1.5 | 400 |
| `--text-lg` | 1.125rem (18) | 1.55 | 400 |
| `--text-xl` | 1.25rem (20) | 1.4 | 700 |
| `--text-2xl` | 1.5rem (24) | 1.3 | 700 |
| `--text-3xl` | 1.875rem (30) | 1.2 | 800 |
| `--text-4xl` | `clamp(2.25rem, 1.6rem + 3vw, 3.25rem)` | 1.1 | 800 |

Other tokens: spacing `--space-N` = N x 4 px for N in 1,2,3,4,5,6,8,10,12,16; radius `sm 4`, `md 8`, `lg 12`, `full 999`; elevation
0 border only, 1 `0 1px 2px rgba(20,35,43,.16), 0 4px 12px rgba(20,35,43,.10)`, 2 overlay `0 2px 4px .., 0 12px 32px ..` (dark
theme: lighter surface + border instead of shadow); motion `--motion-fast 100ms`, `--motion-base 200ms`, `--motion-slow 320ms`,
`--ease cubic-bezier(.2,0,0,1)`; breakpoints mobile first `sm 480`, `md 768`, `lg 1024`, `xl 1280`, content max width 1200,
reading width 65ch. Design at 320 px.

Focus ring: `outline: 3px solid var(--focus); outline-offset: 2px` on every interactive element; sticky headers set
`scroll-padding-top` (WCAG 2.4.11). Targets at least 44 x 44 px on touch, never below 24 x 24 px.
Reduced motion: one rule under `@media (prefers-reduced-motion: reduce)` sets the three durations to `0.01ms` and
`scroll-behavior: auto`. Motion never carries meaning alone.

Machine-readable token file: to be exported to `resources/css/tokens.css` by the frontend-developer after the owner picks a direction (TE-020 AC3).

## 3. App shell (TE-016, TE-017, TE-018, TE-019)

Artifact: see previews.md. Boards: desktop light nl, desktop dark en, skip link focused, theme menu open, mobile 320 light, dark,
mobile menu open, 404 mobile dark, 404 desktop light.

### Landmarks and order
`skip link` > `<header>` (brand link, `<nav aria-label="Hoofdmenu|Main menu">`, controls) > `<main id="main" tabindex="-1">` >
`<footer>` (two labelled `<nav>`: Service, Juridisch). Tab order: skip link, brand, nav, language, theme, login, main content,
footer links, chat button. Exactly one `<h1>` per page, no skipped levels.

### Header
- Desktop (at least 768): brand left; nav (Zoeken, Aanbiedingen, Mijn boekingen, Help; current page has `aria-current="page"` and a
  3 px underline, not colour alone); right: language group, theme button, "Inloggen".
- Mobile (below 768): brand and a "Menu" button (`aria-expanded`, `aria-controls`; visible name stays "Menu" in both states). The
  panel holds nav, language, theme and login. Escape closes it and returns focus to the button; it never traps focus.
- Header is not sticky in v1 (avoids obscured-focus issues at 400 % zoom).

### Language switch (TE-017)
`<div role="group" aria-label="Taal|Language">` with two links: "Nederlands" and "English" (`lang` and `hreflang` set, visible
full words so the accessible name equals the visible text). Current language: `aria-current="true"`, filled background plus a check
icon. Switching reloads the page in the other locale at the matching URL and keeps scroll at top and focus on `<main>`.

### Theme switch (TE-018)
A button "Thema" (icon shows the applied theme) with `aria-expanded` opening a popover `role="radiogroup"` with three native radios:
Systeem (default), Licht, Donker. The choice is stored client-side only (cookie or localStorage, no personal data) and applied
before first paint by a nonce'd inline script to avoid a flash. "Systeem" follows `prefers-color-scheme`. Esc closes and returns
focus to the button. Mobile: the same radiogroup sits inline in the menu panel.

### Footer and chat
Service: Help en veelgestelde vragen, Annuleren en wijzigen, Chat met ons. Juridisch: Privacyverklaring, Toegankelijkheidsverklaring,
Algemene voorwaarden. Fine print: "(c) 2026 TravelEasy. Prijzen zijn inclusief btw. Dit is een demo: alle gegevens zijn fictief."
The support-chat button (plan M8) is reserved bottom-right on every public page; the footer has 96 px bottom padding on mobile so it
never covers content.

### Home placeholder page (replaces the starter-kit welcome page)
H1, lead, a search card (Van, Naar, Vertrek, Reizigers; "Alle velden zijn verplicht." stated up front, every field has a visible
label; no placeholder-as-label) and three value points (final price incl. VAT, book for a group, data minimisation). Desktop: two
columns; mobile: stacked.

### 404 page (TE-019)
Route illustration (decorative, `aria-hidden`), eyebrow "Foutcode 404", `<h1>` "Deze pagina bestaat niet", explanation, primary
"Naar de startpagina", secondary "Zoek reizen". Served with HTTP 404, same shell, localised by the active locale. Other error pages
(403, 419, 429, 500, 503) reuse the layout; copy keys below follow the pattern `error.<code>.title|body`.

### Copy keys (nl default / en)
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

Remaining shell strings are in the artifact. Images: none used (no licences to note); icons are inline stroke SVG, bundled.

### Open questions for the owner
1. Which direction: A Fjord (recommended), B Polder or C Oranje? Any colour or wordmark change?
2. Is a friendly "je" tone with no exclamation marks right, or do you want it more playful (B) or more brisk (C)?
3. Figtree as the one typeface OK?
4. Header controls: are the visible words "Nederlands / English" and a "Thema" popover with Systeem / Licht / Donker fine?
5. Should the support-chat button already show on the placeholder page?
