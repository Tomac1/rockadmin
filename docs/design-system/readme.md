# Rock Admin Design System

> A comprehensive design system for admin portals of all types — e-commerce, CMS, CRM, monitoring dashboards, and more. Built with React components, CSS custom properties, and high-fidelity UI kit templates.

**Namespace**: `window.RockAdminDesignSystem_fced52`  
**Entry CSS**: `styles.css` (imports all token files)  
**Bundle**: `_ds_bundle.js` (compiled components)

---

## Sources & Context

This design system was authored from scratch based on the Rock Admin specification:
- **Brand**: Rock Admin — a universal admin panel framework
- **Use case**: Any admin project (e-shop, CMS, CRM, monitoring portal)
- **No external codebase or Figma provided** — all visual decisions are original

---

## CONTENT FUNDAMENTALS

**Tone**: Professional, direct, functional. Admin UIs communicate status and enable action — copy is utilitarian.

**Casing**: Sentence case for labels, headings, messages. UPPERCASE only for table column headers and section labels (11px, tracked).

**Voice**: Second person ("Your product", "Save changes"). Never first person from the UI.

**Numbers**: Locale-formatted (1,284 not 1284). Currency shows symbol prefix (€149).

**Errors**: Specific and actionable. Not "Something went wrong" — "POST /api/export failed — timeout after 30s."

**Status labels**: Single word where possible: active, draft, archived, shipped, processing.

**Emoji**: Not used. Icons only.

---

## VISUAL FOUNDATIONS

### Colors
- **Primary**: Electric Blue #2563eb (--color-primary-600). CTAs, active states, focus rings, nav indicators.
- **Page background**: #f0f2f7 — light gray-blue. Creates contrast against white surface cards.
- **Surfaces**: White (#ffffff) for cards, tables, modals, dropdowns.
- **Navy navbars**: Top nav #0d1117, Second nav #161b26. Deep contrast against light content.
- **Semantic**: Green (success/active), Yellow (warning/draft), Red (error/danger), Blue (info).
- **No gradients** in UI chrome.

### Typography
- **Font**: Inter (variable, Google Fonts CDN). Neutral, professional, highly legible.
- **Base size**: 14px (--text-md) for body and form fields.
- **Dense scale**: Down to 10px (--text-2xs) for table headers, badges, timestamps.
- **Weights**: Medium (500) labels, Semibold (600) headings/values, Bold (700) brand only.
- **Mono**: JetBrains Mono for SKUs, slugs, API endpoints, logs, code.

### Layout & Containers

| Token | Class | Width | Use |
|---|---|---|---|
| `--content-max-width` | `.ra-container` | 1600px | Dashboard, tables, wide layouts |
| `--content-medium-width` | `.ra-container--medium` | 1200px | Forms with sidebar, detail pages |
| `--content-narrow-width` | `.ra-container--narrow` | 860px | Prose, documents |
| `--content-form-width` | `.ra-container--form` | 640px | Single-column forms |
| `--content-login-width` | `.ra-container--login` | 420px | Login, wizard steps |

- **Full-width navbars** always span 100vw. Content uses max-width container.
- **Two sticky navbars**: Top nav (52px) + Project nav (44px) = 96px combined.
- **Page header**: Title left, action buttons right — always at top of content.
- **Footer**: White, 44px, border-top — copyright left, Useful Links dropdown right.

### Cards
White background. 1px #e2e8f0 border. 8px border-radius. Shadow-sm.
No colored left-border accent pattern. No gradient fills.

### Spacing
4px base unit. Common gaps: 8, 12, 16, 24px. Content padding: 24px.

### Shadows
Minimal. Cards: shadow-sm. Dropdowns/popovers: shadow-lg. Modals: shadow-2xl.
Focus rings: 3px blue box-shadow ring.

### Borders & Radii
Inputs, buttons: 6px. Cards: 8px. Modals: 12px. Badges/pills: 9999px.

### Animations
Duration: 100–150ms color transitions, 250ms entrances.
Easing: cubic-bezier(0.4, 0, 0.2, 1). Fade-in + translateY for modals/dropdowns.
Nav badges pulse gently (2s opacity cycle) for live alert indicators.

### Hover / Press
Buttons: background darkens. Table rows: neutral-50 on hover.
Nav items: rgba(white, 0.07) over dark backgrounds.

---

## NAVBAR SYSTEM

**Top navbar** (--nav-primary-bg: #0d1117):
Brand logo + name → project switcher buttons → notification bell with live badge → settings dropdown + user menu.

**Second navbar** (--nav-secondary-bg: #161b26):
Project-specific nav items. Active item: 2px bottom border in primary blue.
Pulsing count badges propagate from child items up to parent nav button.

---

## ICONOGRAPHY

- **Style**: Stroke-only SVG, 1.5px stroke, round linecap/join, 14–18px in UI
- **Library**: Lucide (CDN) for card HTML files and UI kits
- **Components**: Accept icon as ReactElement — pass any SVG
- **No icon fonts, no emoji, no PNG icons**

---

## FILE INDEX

```
/
├── styles.css                   Entry point — @imports all tokens
├── readme.md                    This file
├── SKILL.md                     Agent skill definition
├── tokens/
│   ├── colors.css               Primary, neutral, semantic, navy, surface
│   ├── typography.css           Inter + size/weight/spacing scale
│   ├── spacing.css              4px base + layout measurements
│   ├── shadows.css              Shadow scale + semantic aliases
│   └── borders.css              Radii, z-index, animation tokens
├── assets/
│   ├── logo.svg                 Rock Admin logo (dark backgrounds)
│   ├── logo-light.svg           Logo for dark backgrounds
│   └── favicon.svg              32×32 icon mark
├── guidelines/                  Foundation cards (DS tab: Colors, Type, Spacing)
│   ├── colors-primary.card.html
│   ├── colors-neutral.card.html
│   ├── colors-semantic.card.html
│   ├── colors-nav.card.html
│   ├── type-scale.card.html
│   ├── type-weights.card.html
│   ├── spacing.card.html
│   ├── shadows.card.html
│   └── borders.card.html
├── components/
│   ├── buttons/                 Button, IconButton
│   ├── forms/                   Input, Select, Textarea, Checkbox, Radio, Switch, FormField, SearchSelect
│   ├── navigation/              TopNavbar, SecondNavbar, Breadcrumb, Dropdown
│   ├── data-display/            Table, Badge, Avatar (+AvatarGroup), TreeView
│   ├── feedback/                Alert, Modal, ConfirmModal, Toast (+ToastProvider), Offcanvas
│   ├── layout/                  Card, PageHeader, InpageSidebar (+InpageLayout), Footer
│   └── charts/                  StatCard, LineChart, BarChart, DonutChart
└── ui_kits/
    └── rock_admin/
        ├── index.html           Interactive SPA demo (6 screens)
        └── screens/
            ├── DashboardEcom.jsx
            ├── ProductList.jsx
            ├── FormAdd.jsx
            ├── Settings.jsx
            ├── Monitoring.jsx
            └── Login.jsx
```

---

## COMPONENTS

All components: `const { X } = window.RockAdminDesignSystem_fced52`

| Component | Key props |
|---|---|
| **Button** | variant (primary/secondary/ghost/danger/outline/success/warning), size (sm/md/lg/xl), loading, leftIcon/rightIcon |
| **IconButton** | icon, size (xs/sm/md/lg), variant (default/bordered/danger/nav) |
| **Input** | type, leftIcon, rightIcon, error, success, size |
| **Select** | options (string[] or {value,label}[]), placeholder |
| **Textarea** | autoResize, placeholders (chips), maxLength |
| **Checkbox** | indeterminate supported |
| **Radio / RadioGroup** | RadioGroup horizontal prop |
| **Switch** | sm size variant |
| **SearchSelect** | multi-select, search filtering |
| **FormField** | label, required, hint, error, hintLinkLabel |
| **Dropdown** | trigger, items/sections, align (left/right) |
| **TopNavbar** | projects, notifications, user, settingsItems |
| **SecondNavbar** | items with children/sections + pulsing badges |
| **Breadcrumb** | items with onClick handlers |
| **Table** | columns (render fn), selectable, sortable, search, pagination, toolbar |
| **TreeView** | nodes (recursive), columns |
| **Badge** | 10 colors, dot, pulse, solid |
| **Avatar / AvatarGroup** | initials fallback, deterministic color |
| **Alert** | info/success/warning/error, dismissible |
| **Modal** | size (sm/md/lg/xl), portal, footer slot |
| **ConfirmModal** | variant (danger/warning), loading |
| **Toast / ToastProvider** | useToast() hook, 4 types |
| **Offcanvas** | side (right/left), size (sm/md/lg) |
| **Card** | title, subtitle, actions, footer, hoverable, flush |
| **PageHeader** | title, description, eyebrow, meta, actions |
| **InpageSidebar / InpageLayout** | sectioned sidebar for settings pages |
| **Footer** | copyright, year, usefulLinks |
| **StatCard** | sparkline, trend indicator |
| **LineChart** | multi-series, responsive SVG, tooltips |
| **BarChart** | grouped bars, responsive SVG |
| **DonutChart** | legend, center value, hover highlight |

---

## CAVEATS

- **Inter font**: Google Fonts CDN. For offline/production, self-host the Inter variable font woff2.
- **Charts**: Pure SVG — no chart library. For advanced analytics consider Recharts or Chart.js.
- **Toast**: Requires `<ToastProvider>` at app root; use `useToast()` hook to trigger.
- **Icons**: Card files use inline SVG; in production integrate `lucide-react` npm package.
- **Dropdown in Footer**: Footer internally accesses `window.RockAdminDesignSystem_fced52.Dropdown` — load the DS bundle before rendering Footer standalone.
