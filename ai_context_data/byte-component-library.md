# Byte Theme SDC Component Library

> **Purpose:** Complete reference for every Single Directory Component (SDC) in the Byte theme (`byte_theme`). Use this when creating test cases, Content Control Center (CCC) items, or Canvas page structures that involve specific Byte components.
>
> **Component ID format:** `sdc.byte_theme.<component-name>` (e.g. `sdc.byte_theme.section`).

---

## Component Groups at a Glance

| Group | Components |
|-------|-----------|
| **Base** | `anchor`, `badge`, `blockquote`, `button`, `heading`, `icon`, `image`, `text` |
| **Card** | `card` (Image card), `card-icon` (Icon card), `card-logo` (Logo card), `card-pricing` (Pricing card), `card-testimonial` (Testimonial card) |
| **Hero** | `cta` (Hero CTA), `hero-billboard`, `hero-blog`, `hero-side-by-side` |
| **Layout** | `accordion`, `accordion-container`, `footer`, `group`, `navbar`, `section` |

---

## Hierarchy and Nesting Rules

### Components with Slots

Only the following components accept child components via named slots:

| Parent Component | Slot Name | Slot Title | Recommended Children |
|-----------------|-----------|------------|---------------------|
| `section` | `header_slot` | Header | `heading` (one only) |
| `section` | `main_slot` | Grid | `card`, `card-icon`, `card-logo`, `card-pricing`, `card-testimonial`, `group`, `accordion-container`, `image`, `blockquote`, `text`, blocks |
| `section` | `footer_slot` | Footer | `group`, `button`, `text` |
| `group` | `group_slot` | Group items | `heading`, `text`, `button`, `image`, `badge`, `icon` |
| `hero-billboard` | `hero_slot` | Hero content | `group` (containing `heading` + `text` + `button`) |
| `hero-side-by-side` | `hero_slot` | Content | `group` (containing `heading` + `text` + `button`) |
| `cta` | `actions` | Buttons | `button` |
| `accordion-container` | `accordion_content` | Accordion content | `accordion` (one or more) |
| `accordion` | `accordion_content` | Accordion content | Any inline content (`text`, `heading`, rich HTML) |
| `navbar` | `logo` | Site logo | `block.system_branding_block` |
| `navbar` | `navigation` | Navigation menu | `block.system_menu_block.main` |
| `navbar` | `links` | CTAs | `button` |
| `footer` | `footer_first` | Branding & Social | `block.system_branding_block`, `block.system_menu_block.social` |
| `footer` | `footer_last` | Call to action | `block.webform_block`, `text` |
| `footer` | `footer_utility_first` | Utility links | `block.system_menu_block.footer` |
| `footer` | `footer_utility_last` | Copyright text | `text` |

### Leaf Components (No Slots)

These accept only props and cannot contain children: `anchor`, `badge`, `blockquote`, `button`, `card`, `card-icon`, `card-logo`, `card-pricing`, `card-testimonial`, `heading`, `hero-blog`, `icon`, `image`, `text`.

### Top-Level Placement Rules

- All page content goes in the **content** region.
- Use `section` as the primary wrapper for every content block (heroes excluded).
- No component should sit directly in the content region without a section wrapper, except:
  - `hero-billboard` or `hero-side-by-side` at the top of the page.
  - A standalone `text` component for legal disclaimers at the very bottom.
- `navbar` belongs in the **header** page region.
- `footer` belongs in the **footer** page region.

### Key Constraints

- Never place more than one `heading` in a section's `header_slot`.
- `accordion` components must be placed inside an `accordion-container`.
- Card components belong in section grid slots (`main_slot`), not directly in the content region.
- Empty slots are forbidden -- if a component has slots, fill them with valid children.

---

## Shared: Image Object Schema

Multiple components accept a `media` prop with this object shape:

| Property | Type | Description |
|----------|------|-------------|
| `src` | string | Path or URL to the image file |
| `alt` | string | Alt text for accessibility |
| `width` | integer | Image width in pixels |
| `height` | integer | Image height in pixels |

Used by: `card`, `card-logo`, `card-testimonial`, `cta`, `hero-billboard`, `hero-blog`, `hero-side-by-side`, `image`, `section` (as `background_media`).

---

## Shared: Icon Reference

Components that accept icons use the [Phosphor Icons](https://phosphoricons.com) library. Pass the icon's Phosphor ID string (e.g. `"rocket"`, `"airplane"`, `"credit-card"`).

- **`button`** -- restricted to 6 fixed icons: `arrow-right`, `arrow-left`, `caret-right`, `caret-left`, `download`, `user-plus`.
- **`badge`**, **`card-icon`**, **`icon`** -- accept any valid Phosphor icon ID.

---

## Component Reference

### `sdc.byte_theme.accordion`

**Display Name:** Accordion | **Group:** Layout

A single section of content that can be collapsed and expanded.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `title` | string | no | -- | -- |
| `heading_level` | number | **yes** | `2`, `3`, `4`, `5`, `6` | -- |
| `open_by_default` | boolean | no | -- | `false` |

#### Slots

| Slot | Title | Description |
|------|-------|-------------|
| `accordion_content` | Accordion content | Content hidden when collapsed. |

#### Usage Notes

- Must always be placed inside an `accordion-container`.
- Set `heading_level` to maintain proper document outline (typically `3` when inside a section).

---

### `sdc.byte_theme.accordion-container`

**Display Name:** Accordion container | **Group:** Layout

A container for accordion items. Only one accordion may be open at a time.

#### Props

None.

#### Slots

| Slot | Title | Description |
|------|-------|-------------|
| `accordion_content` | Accordion content | Place `accordion` components here. Only one can be open at a time. |

#### Usage Notes

- Always place inside a section's `main_slot`.
- Slot should contain one or more `accordion` components.

---

### `sdc.byte_theme.anchor`

**Display Name:** Anchor | **Group:** Base

An invisible element with an ID you can link to with a URL fragment (e.g. `#intro`).

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `id` | string | **yes** | -- | -- |

#### Slots

None.

#### Usage Notes

- The `id` value should begin with a letter and contain only lowercase letters, numerals, and hyphens.
- Use to create in-page anchor links.

---

### `sdc.byte_theme.badge`

**Display Name:** Badge | **Group:** Base

A small label element with optional icon and link.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `label` | string | **yes** | -- | -- |
| `url` | string | no | -- | -- |
| `style` | string | **yes** | `primary` (bg `#155DFC`), `secondary` (bg `#90A1B9`) | -- |
| `icon` | string | no | Any Phosphor icon ID | `"rocket"` |
| `icon_first` | boolean | no | -- | `true` |

#### Slots

None.

---

### `sdc.byte_theme.blockquote`

**Display Name:** Blockquote | **Group:** Base

A styled quotation block with optional citation.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `text` | string | no | -- | -- |
| `cite_name` | string | no | -- | -- |
| `cite_text` | string | no | -- | `"Engineer, Technical Services"` |
| `cite_url` | string | no | -- | -- |

#### Slots

None.

---

### `sdc.byte_theme.button`

**Display Name:** Button | **Group:** Base

A clickable button or link styled as a button.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `variant` | string | **yes** | `primary` (bg `#155DFC`), `secondary` (bg `#DBEAFE`), `primary-inverted` (bg `#FFFFFF`), `secondary-inverted` (bg `#020618`) | -- |
| `label` | string | no | -- | `"Button label"` |
| `href` | string | no | -- | -- |
| `size` | string | **yes** | `small`, `medium`, `large` | -- |
| `icon` | string | no | `arrow-right`, `arrow-left`, `caret-right`, `caret-left`, `download`, `user-plus` | -- |
| `mobile_width` | boolean | no | -- | `false` |
| `icon_first` | boolean | no | -- | `false` |
| `disabled` | boolean | no | -- | `false` |

#### Slots

None.

#### Usage Notes

- Icon options are limited to the 6 values listed above (not free-form Phosphor IDs).
- Commonly placed inside `group` slots, `cta` action slots, or `navbar` link slots.

---

### `sdc.byte_theme.card`

**Display Name:** Image card | **Group:** Card

A card component with an image, heading, description, and optional link.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `background` | string | no | `default` (`#0F172B`), `accent` (`#DBEAFE`), `primary` (`#155DFC`), `inverted` (`#FFFFFF`) | -- |
| `style` | string | **yes** | `framed`, `full` | -- |
| `orientation` | string | **yes** | `vertical`, `horizontal` | -- |
| `heading_text` | string | **yes** | -- | -- |
| `level` | number | no | `2`, `3`, `4` | `3` |
| `url` | string | no | -- | -- |
| `media` | object | no | Image object | -- |
| `text` | string | no | -- | -- |
| `is_text_centered` | boolean | no | -- | `false` |

#### Slots

None.

#### Usage Notes

- Place inside a section's `main_slot`.
- When `url` is set, the entire card becomes clickable.
- `framed` style adds padding and border; `full` style has edge-to-edge image.

---

### `sdc.byte_theme.card-icon`

**Display Name:** Icon card | **Group:** Card

A card featuring an icon, heading, and description text.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `tile_size` | string | no | `square`, `4:3`, `16:9` | -- |
| `background_color` | string | no | `primary` (`#155DFC`), `secondary` (`#90A1B9`), `accent` (`#DBEAFE`), `muted` (`#1D293D`) | -- |
| `border_radius` | string | no | `small`, `medium`, `large` | `"medium"` |
| `icon` | string | no | Any Phosphor icon ID | `"rocket"` |
| `icon_size` | string | no | `small`, `medium`, `large`, `extra-large` | -- |
| `icon_align` | string | **yes** | `left`, `center`, `right` | `"center"` |
| `url` | string | no | -- | -- |
| `text` | string | no | -- | -- |
| `description` | string (HTML) | no | -- | -- |
| `text_align` | string | **yes** | `left`, `center`, `right` | `"center"` |

#### Slots

None.

#### Usage Notes

- Place inside a section's `main_slot`.
- `description` accepts rich HTML content (e.g. `<p>` tags).
- Maximum 6 icon cards per section. Use grid layouts: 2 = `"50-50"`, 3 = `"33-33-33"`, 4 = `"25-25-25-25"`.

---

### `sdc.byte_theme.card-logo`

**Display Name:** Logo card | **Group:** Card

A component with a logo image and optional background color and link.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `media` | object | no | Image object | -- |
| `background_color` | string | no | `primary` (`#155DFC`), `secondary` (`#90A1B9`), `accent` (`#DBEAFE`), `muted` (`#1D293D`) | -- |
| `border_radius` | string | no | `small`, `medium`, `large` | `"small"` |
| `url` | string | no | -- | -- |

#### Slots

None.

#### Usage Notes

- Typically used for partner/client logo grids inside a section's `main_slot`.

---

### `sdc.byte_theme.card-pricing`

**Display Name:** Pricing card | **Group:** Card

A pricing tier card with heading, price, feature list, and CTA button.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `heading_text` | string | no | -- | -- |
| `description` | string | no | -- | -- |
| `price` | string | no | -- | -- |
| `currency_code` | string | no | -- | -- |
| `currency_symbol` | string | no | -- | -- |
| `symbol_position` | string | **yes** | `before`, `after` | `"before"` |
| `text` | string (HTML) | no | -- | -- |
| `button_url` | string | no | -- | -- |
| `button_label` | string | no | -- | -- |
| `promote` | boolean | no | -- | `false` |

#### Slots

None.

#### Usage Notes

- `text` prop accepts rich HTML; typically a `<ul>` list of plan features.
- Set `promote` to `true` to visually highlight a recommended tier.
- Place inside a section's `main_slot`.

---

### `sdc.byte_theme.card-testimonial`

**Display Name:** Testimonial card | **Group:** Card

A card displaying a customer quote with citation and optional headshot.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `text` | string | no | -- | -- |
| `cite_name` | string | no | -- | -- |
| `cite_text` | string | no | -- | -- |
| `cite_url` | string | no | -- | -- |
| `align` | string | **yes** | `center`, `left` | `"center"` |
| `style` | string | **yes** | `default` (bg `#0F172B`), `inverted` (bg `#FFFFFF`) | `"default"` |
| `media` | object | no | Image object | -- |

#### Slots

None.

#### Usage Notes

- Place inside a section's `main_slot`.
- `media` is used for the testimonial headshot image.
- Never fabricate testimonials -- only use when quotes are explicitly provided.

---

### `sdc.byte_theme.cta`

**Display Name:** Hero CTA | **Group:** Hero

A call-to-action banner with heading, description, optional background, and a slot for buttons.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `heading_text` | string | no | -- | -- |
| `level` | number | **yes** | `1`, `2`, `3`, `4`, `5`, `6` | -- |
| `text` | string | no | -- | -- |
| `text_align` | string | **yes** | `center`, `left`, `right` | `"center"` |
| `background_color` | string | no | `primary` (`#155DFC`), `secondary` (`#90A1B9`), `accent` (`#DBEAFE`), `muted` (`#1D293D`), `inverted` (`#FFFFFF`) | -- |
| `media` | object | no | Image object (background image) | -- |

#### Slots

| Slot | Title | Description |
|------|-------|-------------|
| `actions` | Buttons | Place `button` components here. |

#### Usage Notes

- The heading is rendered internally at size `7XL`.
- When `media` is set, a dark overlay is automatically applied over the background image.
- Commonly used as the bottom CTA band on a page, placed inside a section's `main_slot`.

---

### `sdc.byte_theme.footer`

**Display Name:** Footer | **Group:** Layout

The site footer with slots for branding, CTAs, utility links, and copyright.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `align` | boolean | no | -- | `true` |

#### Slots

| Slot | Title | Description |
|------|-------|-------------|
| `footer_first` | Branding & Social | Site branding and social media links. |
| `footer_last` | Call to action | Newsletter signup or other CTA. |
| `footer_utility_first` | Utility links | Footer navigation menu. |
| `footer_utility_last` | Copyright text | Copyright notice. |

#### Usage Notes

- Placed in the **footer** page region, not in content.
- The `align` prop controls whether links display horizontally.

---

### `sdc.byte_theme.group`

**Display Name:** Group | **Group:** Layout

A flex container for bundling related child components together.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `flex_direction` | string | **yes** | `column` (Vertical), `row` (Horizontal) | `"column"` |
| `flex_gap` | string | no | `sm` (Small), `md` (Medium), `lg` (Large), `xl` (Extra-Large) | -- |
| `items_align` | string | **yes** | `start`, `center`, `end` | `"start"` |
| `flex_align` | string | **yes** | `start`, `center`, `end` | `"center"` |
| `radius` | string | no | `sm` (Small), `md` (Medium), `lg` (Large), `xl` (Extra-Large) | -- |
| `padding` | string | no | `sm` (Small), `md` (Medium), `lg` (Large), `xl` (Extra-Large) | -- |
| `background` | string | no | `primary` (`#155DFC`), `secondary` (`#90A1B9`), `accent` (`#DBEAFE`) | -- |

#### Slots

| Slot | Title | Description |
|------|-------|-------------|
| `group_slot` | Group items | Place child components here (heading, text, button, image, etc.). |

#### Usage Notes

- The primary way to bundle related elements (e.g. label + heading + description).
- `items_align` controls alignment of children within the group.
- `flex_align` controls alignment of the group itself within its parent.
- Default composition: vertical direction, medium spacing, start-aligned items, center-aligned within parent, small rounded corners, small padding.

---

### `sdc.byte_theme.heading`

**Display Name:** Heading | **Group:** Base

A heading element with configurable level, size, color, and alignment.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `heading_text` | string | **yes** | -- | `"Enter title"` |
| `level` | number | **yes** | `1`, `2`, `3`, `4`, `5`, `6` | -- |
| `text_size` | string | **yes** | `default`, `heading-responsive-8xl` (8XL), `heading-responsive-7xl` (7XL), `heading-responsive-6xl` (6XL), `heading-responsive-5xl` (5XL), `heading-responsive-4xl` (4XL), `heading-responsive-3xl` (3XL), `heading-responsive-2xl` (2XL), `heading-responsive-xl` (XL) | -- |
| `text_color` | string | **yes** | `default` (Default text `#FFFFFF`), `inverted` (Inverted text `#020618`), `primary` (`#155DFC`) | -- |
| `align` | string | **yes** | `left`, `center`, `right` | -- |
| `url` | string | no | -- | -- |

#### Slots

None.

#### Usage Notes

- **`text_size` controls visual appearance, not `level`.** The heading level has no visual effect when text_size is set. Always set text_size explicitly.
- When placed in a section's `header_slot`, only one heading is allowed.
- `url` wraps the heading text in a link.

---

### `sdc.byte_theme.hero-billboard`

**Display Name:** Hero billboard | **Group:** Hero

A full-width hero banner with background image, overlay, and a content slot.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `height` | string | **yes** | `full` (Full screen), `large`, `ribbon` | `"full"` |
| `flex_position` | string | **yes** | `top-left`, `center-left`, `bottom-left`, `hero-center` (Center) | `"center-left"` |
| `overlay_opacity` | string | **yes** | `0%`, `20%`, `40%`, `60%`, `75%` | `"0%"` |
| `object_position` | string | **yes** | `top`, `center`, `bottom` | `"center"` |
| `media` | object | no | Image object (background image) | -- |
| `overlap_navbar` | boolean | no | -- | `false` |

#### Slots

| Slot | Title | Description |
|------|-------|-------------|
| `hero_slot` | Hero content | Place a `group` containing `heading` + `text` + `button`. |

#### Usage Notes

- Placed directly in the content region (does not need a section wrapper).
- Typically the first component on a page.
- Set `overlap_navbar` to `true` to position the hero behind the header navigation.
- Never use inverted-style text or image components inside the hero slot.

---

### `sdc.byte_theme.hero-blog`

**Display Name:** Hero blog | **Group:** Hero

A hero component designed specifically for blog content type pages.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `heading_text` | string | no | -- | `"Enter the title"` |
| `level` | integer | no | `1`, `2`, `3`, `4`, `5`, `6` | `1` |
| `date` | integer | no | -- (Unix timestamp) | `1770856866` |
| `author` | string | no | -- | -- |
| `author_url` | string | no | -- | `""` |
| `media` | object | no | Image object | -- |

#### Slots

None.

#### Usage Notes

- Self-contained hero: heading, date, author, and image are all props (no slots).
- The `date` prop expects a Unix timestamp integer.

---

### `sdc.byte_theme.hero-side-by-side`

**Display Name:** Hero side-by-side | **Group:** Hero

A hero with an image on one side and a content slot on the other.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `hero_flex_gap` | string | no | `large` (Normal), `extra-large` | `"large"` |
| `hero_flex_direction_mobile` | string | no | `vertical`, `vertical-reverse` (Reverse vertical) | `"vertical"` |
| `background` | string | no | `primary` (`#155DFC`), `secondary` (`#90A1B9`), `accent` (`#DBEAFE`) | -- |
| `padding_block_start` | string | **yes** | `0`, `16`, `32`, `64` | -- |
| `padding_block_end` | string | **yes** | `0`, `16`, `32`, `64` | -- |
| `image_size` | string | no | `2:1`, `16:9`, `3:2`, `4:3`, `1:1` (Square) | `"4:3"` |
| `image_position` | string | no | `left`, `right` | `"left"` |
| `image_radius` | string | no | `small`, `large`, `extra-large` | `"small"` |
| `media` | object | no | Image object | -- |

#### Slots

| Slot | Title | Description |
|------|-------|-------------|
| `hero_slot` | Content | Place a `group` containing `heading` + `text` + `button`. |

#### Usage Notes

- Placed directly in the content region (does not need a section wrapper).
- Heading/subheading in the content slot should have a max character count under 40.
- Use `image_position` to alternate image placement across page sections.

---

### `sdc.byte_theme.icon`

**Display Name:** Icon | **Group:** Base

A standalone icon element from the Phosphor Icons library.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `icon` | string | **yes** | Any Phosphor icon ID | `"rocket"` |
| `icon_size` | string | **yes** | `extra-small`, `small`, `medium`, `large`, `extra-large` | `"small"` |

#### Slots

None.

---

### `sdc.byte_theme.image`

**Display Name:** Image | **Group:** Base

A responsive image component with configurable aspect ratio, corner radius, caption, and link.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `media` | object | no | Image object | -- |
| `size` | string | no | `2:1`, `16:9`, `3:2`, `4:3`, `1:1` (Square) | `"4:3"` |
| `radius` | string | no | `small`, `large`, `extra-large` | -- |
| `caption` | string | no | -- | -- |
| `url` | string | no | -- | -- |

#### Slots

None.

#### Usage Notes

- Place inside a section's `main_slot` or inside a `group`.
- When `url` is set, the image becomes a clickable link.

---

### `sdc.byte_theme.navbar`

**Display Name:** Navbar | **Group:** Layout

The site navigation bar with logo, menu, and CTA slots.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `menu_align` | string | no | `left`, `center`, `right` | `"center"` |

#### Slots

| Slot | Title | Description |
|------|-------|-------------|
| `logo` | Site logo | Site branding block. |
| `navigation` | Navigation menu | Main navigation menu block. |
| `links` | CTAs | `button` components for header actions. |

#### Usage Notes

- Placed in the **header** page region, not in content.
- Includes responsive hamburger menu for mobile automatically.

---

### `sdc.byte_theme.section`

**Display Name:** Section | **Group:** Layout

The primary layout wrapper for page content. Provides a CSS grid with header, grid, and footer slots.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `width` | string | **yes** | `100%`, `90%`, `80%`, `75%`, `50%` | `"100%"` |
| `columns` | string | **yes** | `100`, `50-50`, `33-33-33`, `75-25`, `25-75`, `67-33`, `33-67`, `50-25-25`, `25-25-50`, `25-25-25-25` | `"50-50"` |
| `views_columns` | string | no | `50-50`, `33-33-33`, `25-25-25-25` | -- |
| `mobile_columns` | string | **yes** | `1`, `2`, `3` | -- |
| `margin_block_start` | string | **yes** | `0`, `8`, `20`, `32`, `48`, `64`, `96`, `128` | -- |
| `margin_block_end` | string | **yes** | `0`, `8`, `20`, `32`, `48`, `64`, `96`, `128` | -- |
| `padding_block_start` | string | **yes** | `0`, `16`, `32`, `64` | -- |
| `padding_block_end` | string | **yes** | `0`, `16`, `32`, `64` | -- |
| `background_color` | string | no | `primary` (`#155DFC`), `secondary` (`#90A1B9`), `accent` (`#DBEAFE`), `muted` (`#1D293D`) | -- |
| `background_media` | object | no | Image object (background image) | -- |
| `section_header` | boolean | no | -- | `true` |
| `section_footer` | boolean | no | -- | `true` |

#### Slots

| Slot | Title | Description |
|------|-------|-------------|
| `header_slot` | Header | Section heading (one `heading` component max). Only renders when `section_header` is `true` and content is present. |
| `main_slot` | Grid | Main content area laid out according to `columns` grid. |
| `footer_slot` | Footer | Section footer content. Only renders when `section_footer` is `true` and content is present. |

#### Usage Notes

- The most-used layout component. Wraps nearly all page content below the hero.
- **Standard defaults:** width `"100%"`, grid `"50-50"`, mobile columns `"1"`, margin top/bottom `"128"`, padding `"0"`.
- Set padding to `"64"` whenever a background color is applied.
- Use `"96"` margin only between tightly related consecutive sections.
- Enable `section_header` only when placing a heading in the header slot.
- `views_columns` is used only when a Drupal Views block is placed in the grid.
- The `columns` prop defines the desktop/tablet grid; `mobile_columns` overrides on small screens.

---

### `sdc.byte_theme.text`

**Display Name:** Text | **Group:** Base

A rich text component for paragraphs, lists, and inline formatting.

#### Props

| Prop | Type | Required | Allowed Values | Default |
|------|------|----------|---------------|---------|
| `text` | string (HTML) | no | -- | -- |
| `text_size` | string | **yes** | `text-xs` (12px), `text-sm` (14px), `normal` (16px default), `text-lg` (18px), `text-xl` (20px), `text-2xl` (24px), `text-3xl` (32px) | -- |
| `text_color` | string | **yes** | `default` (Default text `#FFFFFF`), `inverted` (Inverted text `#020618`), `primary` (`#155DFC`) | -- |

#### Slots

None.

#### Usage Notes

- Wrap content in HTML tags: `<p>` for paragraphs, `<ul>`/`<ol>` for lists, `<strong>`/`<em>` for emphasis, `<a>` for links.
- **Text size by role:**
  - `text-xs` (12px) -- legal disclaimers only.
  - `text-sm` (14px) -- uppercase eyebrow/category labels only.
  - `normal` (16px) -- body paragraphs, descriptions.
  - `text-lg` (18px) -- supporting text needing more prominence.
- Can be placed standalone in the content region only for legal disclaimers at the bottom of a page.
