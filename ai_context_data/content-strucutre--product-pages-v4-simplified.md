---
purpose: "Load when building or reviewing any FinDrop product page. Defines section order, component patterns, and layout rules. The author supplies copy — this document supplies the structure. Skip for blog posts, landing pages, or non-product content types."

---

# Product Page Guidelines v4

## Core Rules

- All page content goes in the content region. Do not modify header or footer.
- Use `section` components as the primary wrapper for every content block (hero excluded).
- Inside sections, use `group` components to bundle related elements (e.g., label + heading + paragraph).
- No component should sit directly in the content region without a section wrapper, except the `hero-billboard` at the top and an optional legal disclaimer `text` at the very bottom.
- Enable a section's header region only when placing a heading in that section's header slot.

---

## Section Defaults

Apply to every `section` unless an override is noted:

- Width `100%`, grid `50-50`, mobile columns `1`.
- Margin top/bottom `128`. Use `96` only between tightly related consecutive sections.
- Padding top/bottom `0`. Set to `64` whenever a background color is applied.
- No background color unless the section is on the whitelist below.

## Background Color Whitelist

The Byte theme is a dark theme. Sections with no `background_color` sit on the dark page background (`#0F172B`). Text defaults to white. This dark base is the norm — most of the page should be default dark with no background color set.

**Rules:**

- **Most sections: no background color.** This is correct. Do not add color just because a section exists.
- **`muted`: Testimonials and Final CTA only.** No other sections use `muted`.
- **`accent`, `primary`, and `secondary`: do not use** on product page sections.
- **When any background color is applied,** set padding to `64`. When no background, padding is `0`.

Do not apply `background_color` to `card-icon` components inside a section that already has a background color.

## Card Type Selection

| Card Component      | Use For                                                      | Never Use For                                      |
| ------------------- | ------------------------------------------------------------ | -------------------------------------------------- |
| `card-icon`         | Short benefits (title + 1–2 sentences + icon)                | Stats, testimonials, or anything needing an image  |
| `card` (image card) | Stats/results, cross-product links, anything needing an image | Short benefits that work better as icon cards      |
| `card-testimonial`  | Customer quotes with attribution                             | Anything other than testimonials. Never fabricate. |

## Group Defaults

Apply when bundling content inside a section's grid slot:

- Vertical direction, medium spacing, start-aligned items, center-aligned within parent, small rounded corners, small padding.
- Override rounded corners to large for groups wrapping an image.

## Heading Behavior

Only text size controls visual appearance. Heading level has no visual effect when text size is set. Always set text size explicitly. Default color: default text. Default alignment: left.

## Text Size by Role

- `text-sm` (14px) — uppercase eyebrow/category labels only.
- `normal` (16px) — body paragraphs, descriptions.
- `text-lg` (18px) — supporting text needing more prominence (hero subtext, feature-grid descriptions).
- `text-xs` (12px) — legal disclaimers only.

---

## Image Policy

All images except testimonial headshots must be illustration-style assets or branded photography from the media library. Search with descriptive queries related to the product and feature being described (e.g., "FinDrop travel booking illustration", "expense dashboard illustration", "virtual card controls illustration").

Testimonial headshots are the only place where portrait photographs are always used. Select professional headshots matching the testimonial attribution.

No image may appear more than once on the same page.

Do not set aspect ratio (`size`) on `image` components by default — leave unset and let the image display at its natural proportions.

---

## Page Sections — Top to Bottom

All product pages follow this fixed order. Required sections must appear on every page. Optional sections are included when the copy deck provides content for them.

| Order | Section                       | Required |
| ----- | ----------------------------- | -------- |
| 1     | Hero Billboard                | **Yes**  |
| 2     | Problem Statement             | No       |
| 3     | Benefit Overview (Icon Cards) | No       |
| 4     | Feature Value Props           | **Yes**  |
| 5     | Results / Numbers             | **Yes**  |
| 6     | Testimonials                  | **Yes**  |
| 7     | How It Works                  | No       |
| 8     | FAQ                           | No       |
| 9     | Platform Features Grid        | No       |
| 10    | Cross-Product Cards           | No       |
| 11    | Bottom CTA                    | **Yes**  |
| 12    | Legal Disclaimer              | No       |

---

### 1. Hero Billboard — ALWAYS INCLUDE

`hero-billboard` — **always.** Do not use `hero-side-by-side` for product pages.

`hero-billboard` → `group` in hero slot → `heading` + `text` + `button`.

The heading frames the buyer's outcome or aspiration, not just the product name. Supporting text: 2–3 sentences covering what the product does and the top benefit. Maximum two CTAs.

Hero group override: `flex_gap: lg` (large spacing). When two CTAs are used, wrap them in a horizontal `group` (`flex_direction: row`, `padding: none`).

Hero props: full-screen height, bottom-left content position, 40% overlay opacity, bottom image position, overlap header enabled.

Heading text size `7XL`. Text size `text-lg`. Primary button large, secondary button (if used) secondary-inverted large.

Use a wide atmospheric illustration with dark/muted tones as background. Search the media library for images related to the product (e.g., "FinDrop travel hero illustration").

---

### 2. Problem Statement (Optional)

**Include when** the copy deck opens with a pain-point narrative before diving into features.

`section` (columns `100`) → heading in header slot → `text` in grid slot.

Section heading text size `5XL`, center-aligned. Body text `text-lg`. Keep to 2–3 short paragraphs. End with a pivot sentence introducing the product.

---

### 3. Benefit Overview — Icon Cards (Optional)

**Include when** the copy has short benefit statements (title + 1–2 sentences) that work as at-a-glance selling points, separate from the detailed features.

`section` → heading in header slot → `card-icon` components in grid slot.

Section heading text size `5XL`, center-aligned.

Each card: muted background, large border radius, extra-large icon size, center-aligned icon and text. Pick an appropriate Phosphor icon for each card. Never set aspect ratio (`tile_size`) on icon cards — leave unset.

**Grid layout by card count:** 2 → `50-50`, 3 → `33-33-33`, 4 → `25-25-25-25`.

---

### 4. Feature Value Props — ALWAYS INCLUDE

Choose pattern based on feature count:

**Pattern A — Icon Card Grid (2–4 features):**

`section` → heading in header slot → `card-icon` components in grid slot. Same card props as Benefit Overview. Grid matches card count.

**Pattern B — Side-by-Side Deep-Dives (5+ features):**

One `section` per feature, each with `50-50` grid containing a `group` for text content and an `image` placed directly in the grid slot. Do NOT wrap the image in a group — there is a CSS bug that distorts images inside groups.

Text group contains: `text` (uppercase eyebrow, `text-sm`, default color) → `heading` (`4XL`) → `text` (body, `normal`) → optional `button` link (secondary, medium, arrow-right icon).

Image: `image` component with an illustration related to the feature being described. Set `radius: large`. Do not set `size` (aspect ratio). No group wrapper.

**Alternating pattern:** Alternate image position (left/right) between consecutive sections.

**Maximum 3 deep-dive sections.** If more than 3 features qualify, pick the top 3 and move the rest to the Platform Features Grid (section 9).

---

### 5. Results / Numbers — ALWAYS INCLUDE

`section` → heading in header slot → `card` components in grid slot.

Section heading text size `5XL`, center-aligned. Grid: `33-33-33` for 3 stats, `50-50` for 2.

Each card: framed style, vertical orientation, default background, text not centered. Heading is the stat, body explains the human impact.

All numbers must come from Key Facts & Value Propositions. Do not invent stats. Aim for 3 stat cards.

---

### 6. Testimonials — ALWAYS INCLUDE

**Include ONLY approved testimonials.** Never fabricate quotes. If a testimonial is not in Key Facts, flag it for addition.

`section` → `card-testimonial` components in grid slot.

Section overrides: `background_color: muted`, padding `64`. Width `50%` for 1 testimonial, `100%` for 2+.

Grid: `100` for 1 testimonial, `50-50` for 2. Card style: `default`. Center-aligned. Include headshot from media library when available.

At least one testimonial required. Two preferred.

---

### 7. How It Works (Optional)

**Include when** the onboarding flow is a selling point (e.g., "10-day implementation").

`section` → heading in header slot → `card-icon` components in grid slot.

Same card props as Benefit Overview. 3–4 steps maximum. Each step has a numbered heading and 1–2 sentence description. Grid matches step count.

---

### 8. FAQ (Optional)

`section` → heading in header slot → `accordion-container` → `accordion` items in grid slot.

Section heading text size `5XL`, center-aligned. Columns `100`.

Each accordion: heading level 3, closed by default. 4–6 questions. Answers should be 2–4 sentences. Security and integration questions are almost always relevant.

---

### 9. Platform Features Grid (Optional)

**Include when** the copy has secondary features that need more than a card title but less than a deep-dive.

`section` → heading in header slot → `group` components in grid slot (`50-50`).

Section heading text size `5XL`, center-aligned. Each group: `heading` (`3XL`, left-aligned) + `text` (`text-lg`). Prefer 4–6 features, even numbers.

---

### 10. Cross-Product Cards (Optional)

**Include when** the page should link to sibling FinDrop products.

`section` (no header) → `card` components in grid slot.

Grid: `33-33-33` for 3, `50-50` for 2. Each card: framed, vertical, default background, text not centered. Use official product one-liners from Brand Guidelines. Search the media library for an illustration related to each product.

---

### 11. Bottom CTA — ALWAYS INCLUDE

`section` → `group` (heading + text + button) + `image` directly in grid slot (no group wrapper), side by side in `50-50` grid.

Section overrides: margin bottom `64`, padding top/bottom `64`, background color `muted`.

Text group override: `flex_gap: lg` (large spacing). Heading text size `4XL`. Body text `text-lg`. Button primary, medium size, contextual icon. Optional secondary button. When two CTAs are used, wrap them in a horizontal `group` (`flex_direction: row`, `padding: none`).

The heading restates the primary value proposition. CTA label should come from the approved CTA library in Key Facts.

---

### 12. Legal Disclaimer (Optional)

**Include when** banking or fintech regulatory disclosures apply.

Standalone `text` in content region after all sections. Text size `text-xs`, center-aligned.

---

## Content Mapping Logic

When reading a copy deck, classify each block:

| Copy deck pattern                                         | Maps to                                              |
| --------------------------------------------------------- | ---------------------------------------------------- |
| Headline + summary + CTA                                  | Hero billboard (1)                                   |
| Pain-point narrative                                      | Problem statement (2)                                |
| Short benefits (title + 1–2 sentences), 2–4 items         | Benefit overview icon cards (3)                      |
| Features with short descriptions, 2–4 items               | Feature icon card grid (4A)                          |
| Features with full paragraphs + visual concepts, 5+ items | Feature deep-dives (4B), max 3, overflow to grid (9) |
| Stats with human impact framing                           | Results / numbers (5)                                |
| Customer quotes with attribution                          | Testimonials (6)                                     |
| Numbered steps, onboarding flow                           | How it works (7)                                     |
| Questions and answers                                     | FAQ (8)                                              |
| Secondary capability descriptions                         | Platform features grid (9)                           |
| References to other FinDrop products                      | Cross-product cards (10)                             |
| Closing CTA or resource offer                             | Bottom CTA (11)                                      |
| Regulatory/legal fine print                               | Legal disclaimer (12)                                |

---

## Constraints

- Never use odd card counts in a single section. Split across sections if needed.
- Never place more than one heading in a section's header slot.
- Never apply background color without setting padding to `64`.
- Never apply background color to sections other than Testimonials (`muted`) and Final CTA (`muted`).
- Never use `accent`, `primary`, or `secondary` as section background colors on product pages.
- Never apply `card-icon` backgrounds inside sections that already have a background color.
- Never use `text-sm` for anything other than uppercase eyebrow labels.
- Never fabricate testimonials. Only use them when provided in the copy deck.
- Never use `hero-side-by-side` for product page heroes. Always `hero-billboard`.
- Always use `card` (image card) for stats/numbers, never `card-icon`.
- Never set aspect ratio (`tile_size`) on icon cards. Leave unset.
- Never set aspect ratio (`size`) on `image` components by default. Leave unset.
- Never wrap images in a group for feature deep-dives. Place `image` directly in the grid slot (CSS bug distorts images in groups).
- `muted` only on Testimonials and Final CTA.
- Maximum 6 icon cards per section, 3 deep-dive sections total, 6 grid items per section.
- All stats must come from Key Facts. All CTAs should come from the approved CTA library.

---

## Checklist

- [ ] All sections in correct order?
- [ ] All required sections present (Hero, Features, Results, Testimonial, CTA)?
- [ ] Hero uses `hero-billboard` (not `hero-side-by-side`)?
- [ ] Most sections have no background colour (dark default)?
- [ ] `muted` only on Testimonials and Final CTA, no other section backgrounds?
- [ ] Text color correct for background (`default`/white on dark)?
- [ ] Correct card types (stats → `card`, benefits → `card-icon`, quotes → `card-testimonial`)?
- [ ] No aspect ratio set on icon cards?
- [ ] No aspect ratio set on image components?
- [ ] Feature deep-dive images in grid slot directly (not in a group)?
- [ ] Stats from Key Facts only?
- [ ] Mandatory phrasing rules followed?
- [ ] No competitor names in external content?
- [ ] No image repeated on the page?
- [ ] Testimonials approved or flagged?
- [ ] Hero leads with buyer outcome, not feature list?