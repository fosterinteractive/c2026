---
purpose: "Load when building or reviewing any FinDrop product page (Cards, Expense, Travel, or future products). Defines the required section order, component patterns, and layout rules for product pages. The author supplies the copy — this document supplies the structure. Skip for blog posts, landing pages, or non-product content types."

---

# Content Strategy: Product Pages v4

## How to Use This Document

This is the structural template for all FinDrop product pages. It defines the section order, which sections are required vs. optional, and the Byte theme component patterns for each section.

**Workflow:** The author provides a copy deck (headings, body text, CTAs, testimonials). The AI applies this template to assemble the page using the correct components, props, and layout patterns. The copy deck does not need to specify components — this document handles that.

**CCC documents to load alongside this one:**

- Brand Guidelines (+ Abbreviations, Tone & Voice, Visuals & Imagery)
- Key Facts & Value Propositions
- The relevant Persona sub-context for the page's target audience

---

## Background Color Rules

The Byte theme is a dark theme. Sections with no `background_color` set are transparent against the dark page background (`#0F172B`). Text defaults to white (`#FFFFFF`). This dark base is the visual norm — most of the page should be the default dark.

Background colors create visual rhythm by breaking up the dark base. Use them sparingly. Too many colored bands make the page feel cluttered.

**Color palette for sections:**

| Value        | Hex                      | Visual Effect         | Use For                             |
| ------------ | ------------------------ | --------------------- | ----------------------------------- |
| *(none set)* | transparent on `#0F172B` | Dark default          | Most sections — the baseline        |
| `muted`      | `#1D293D`                | Slightly lighter dark | Final CTA band only                 |
| `accent`     | `#DBEAFE`                | Light blue contrast   | Do not use on product page sections |
| `primary`    | `#155DFC`                | Bright blue           | Do not use on product page sections |
| `secondary`  | `#90A1B9`                | Grey                  | Do not use on product page sections |

**Rules:**

1. **Most sections have NO background color.** They sit on the dark default. This is correct — do not add color just because a section exists.
2. **`muted` — Testimonials and Final CTA only.** These are the only sections that use `muted`.
3. **`accent`, `primary`, and `secondary` — do not use** on product page sections.
4. **When any background color is applied,** set `padding_block_start` and `padding_block_end` to `64`. When no background color, padding is `0`.
5. **Card backgrounds on colored sections:** Do not apply `background_color` to `card-icon` components inside a section that already has a background color. Card backgrounds (`muted`) are for cards sitting on the default dark background only.

---

## Card Type Selection

The Byte theme has multiple card components. Each has a specific purpose. Do not substitute one for another.

| Card Component      | Use For                                                      | Never Use For                                             |
| ------------------- | ------------------------------------------------------------ | --------------------------------------------------------- |
| `card-icon`         | Short benefit statements (title + 1–2 sentences + icon). Scannable, at-a-glance value props. | Stats/numbers, testimonials, or anything needing an image |
| `card` (image card) | Stats/results with a heading and body text. Cross-product links with product illustrations. Any card that needs an image. | Short benefits that work better as icon cards             |
| `card-testimonial`  | Customer quotes with attribution and optional headshot.      | Anything other than testimonials. Never fabricate.        |
| `card-pricing`      | Pricing tiers only.                                          | Feature descriptions or general content                   |

**Key rule:** If the content is a **stat or number** (e.g., "80% less time on expenses"), always use `card` (image card) with `style: framed` — never `card-icon`. Icon cards are for benefits and features described in words, not metrics.

---

## Image Policy

All images except testimonial headshots must be illustration-style assets or branded photography from the media library. Search with descriptive queries related to the product and feature being described (e.g., "FinDrop travel booking illustration", "expense dashboard illustration", "virtual card controls illustration").

Testimonial headshots are the only place where portrait photographs are always used. Select professional headshots matching the testimonial attribution.

No image may appear more than once on the same page. See Visuals & Imagery guidelines.

**Do not set aspect ratio (`size`) on `image` components by default.** Leave unset and let the image display at its natural proportions.

---

## Section Order

All product pages follow this fixed order. Required sections must appear on every product page. Optional sections are included when the copy deck provides content for them.

| Order | Section             | Required     | Notes                                                  |
| ----- | ------------------- | ------------ | ------------------------------------------------------ |
| 1     | Hero                | **Required** | Full-width billboard with H1, subhead, and primary CTA |
| 2     | Problem Statement   | Optional     | Text block framing the pain the product solves         |
| 3     | Benefit Overview    | Optional     | High-level benefit cards (typically 3–4)               |
| 4     | Feature Value Props | **Required** | Detailed feature cards or deep-dive sections           |
| 5     | Results / Numbers   | **Required** | Stat cards proving the claims with approved metrics    |
| 6     | Testimonial(s)      | **Required** | At least one customer quote                            |
| 7     | How It Works        | Optional     | Step-by-step onboarding or usage flow                  |
| 8     | FAQ                 | Optional     | Common objections answered                             |
| 9     | Platform Features   | Optional     | Detailed feature explanations in a grid                |
| 10    | Cross-Product Cards | Optional     | Links to other FinDrop products                        |
| 11    | Final CTA           | **Required** | Closing conversion section                             |
| 12    | Legal Disclaimer    | Optional     | Required when banking/fintech disclosures apply        |

---

## Section 1: Hero (Required)

**Component:** `sdc.byte_theme.hero-billboard` — **always.** Do not use `hero-side-by-side` or any other hero variant for product pages. Product page heroes are always full-width billboard with a background image.

| Prop              | Default Value | Notes                                      |
| ----------------- | ------------- | ------------------------------------------ |
| `height`          | `full`        | Always full-screen for product pages       |
| `flex_position`   | `bottom-left` | Content anchored bottom-left               |
| `overlay_opacity` | `40%`         | Adjust per image — ensure text readability |
| `object_position` | `bottom`      | Adjust to match image composition          |
| `overlap_navbar`  | `true`        | Hero always sits behind the nav            |

**`hero_slot`** → `sdc.byte_theme.group` containing:

Group override: `flex_gap`: `lg` (large spacing for text wrapping in heroes).

| Child                    | Component | Props                                                        |
| ------------------------ | --------- | ------------------------------------------------------------ |
| H1                       | `heading` | `level`: 1, `text_size`: `heading-responsive-7xl`, `text_color`: `inverted`, `align`: `left` |
| Subhead                  | `text`    | `text_size`: `text-lg`, `text_color`: `inverted`             |
| Primary CTA              | `button`  | `variant`: `primary`, `size`: `large`. Pick appropriate icon from: `download`, `arrow-right` |
| Secondary CTA (optional) | `button`  | `variant`: `secondary-inverted`, `size`: `large`             |

When two CTAs are used, wrap them in a horizontal `group` (`flex_direction`: `row`, `padding`: none) so they sit side by side.

**Media:** Custom illustration or branded photography related to the product being described. Search the media library with descriptive queries (e.g., "FinDrop travel hero illustration", "FinDrop cards hero illustration"). No stock photography. See Visuals & Imagery guidelines.

**Copy rules:**

- H1 should lead with a buyer outcome or the product's primary value proposition from Key Facts.
- Subhead expands on the H1 with a supporting detail and the human impact.
- Maximum two CTAs. Primary is the conversion action; secondary is a lower-commitment alternative (e.g., "Watch Demo").

---

## Section 2: Problem Statement (Optional)

**Component:** `sdc.byte_theme.section`

| Prop                  | Value   |
| --------------------- | ------- |
| `width`               | `100%`  |
| `columns`             | `100`   |
| `mobile_columns`      | `1`     |
| `margin_block_start`  | `128`   |
| `margin_block_end`    | `128`   |
| `padding_block_start` | `0`     |
| `padding_block_end`   | `0`     |
| `section_header`      | `true`  |
| `section_footer`      | `false` |

**`header_slot`** → `sdc.byte_theme.heading`

- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.text`

- `text_size`: `text-lg`, `text_color`: `default`

**Copy rules:**

- Frame the problem the buyer faces today — before FinDrop.
- End with a pivot sentence that introduces the product as the solution.
- Keep to 2–3 short paragraphs.

---

## Section 3: Benefit Overview (Optional)

**Component:** `sdc.byte_theme.section`

| Prop                  | Value                |
| --------------------- | -------------------- |
| `width`               | `100%`               |
| `columns`             | See grid rules below |
| `mobile_columns`      | `1`                  |
| `margin_block_start`  | `128`                |
| `margin_block_end`    | `128`                |
| `padding_block_start` | `0`                  |
| `padding_block_end`   | `0`                  |
| `section_header`      | `true`               |
| `section_footer`      | `false`              |

**`header_slot`** → `sdc.byte_theme.heading`

- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.card-icon` (one per benefit)

**Grid rules:** Match column layout to card count:

- 2 cards → `columns`: `50-50`
- 3 cards → `columns`: `33-33-33`
- 4 cards → `columns`: `25-25-25-25`

**Card-icon shared props:**

| Prop               | Value          | Notes                                                        |
| ------------------ | -------------- | ------------------------------------------------------------ |
| `background_color` | `muted`        | Only when the parent section has NO background color. Omit if section already has a background. |
| `border_radius`    | `large`        |                                                              |
| `icon_size`        | `extra-large`  |                                                              |
| `icon_align`       | `center`       |                                                              |
| `text_align`       | `center`       |                                                              |
| `tile_size`        | *(do not set)* | Never set aspect ratio on icon cards. Leave unset.           |

- Pick an appropriate Phosphor icon for each card based on the benefit. Refer to Visuals & Imagery for recommended icons by use case.
- Each card has a short heading (`text` prop) and a 1–2 sentence description (`description` prop).
- **Never set `tile_size` (aspect ratio) on icon cards.** Leave it unset and let the card size naturally.

**Copy rules:**

- These are high-level benefits, not detailed features.
- Use this section when the page needs a quick scannable summary before diving deeper.

---

## Section 4: Feature Value Props (Required)

This section has two patterns. Choose based on the number of features.

### Pattern A: Icon Card Grid (2–4 features)

Use when the copy deck has 2–4 features that can each be summarized in a heading + short paragraph.

**Component:** `sdc.byte_theme.section`

| Prop                  | Value                                                 |
| --------------------- | ----------------------------------------------------- |
| `width`               | `100%`                                                |
| `columns`             | Match to count: `50-50`, `33-33-33`, or `25-25-25-25` |
| `mobile_columns`      | `1`                                                   |
| `margin_block_start`  | `128`                                                 |
| `margin_block_end`    | `128`                                                 |
| `padding_block_start` | `0`                                                   |
| `padding_block_end`   | `0`                                                   |
| `section_header`      | `true`                                                |
| `section_footer`      | `false`                                               |

**`header_slot`** → `sdc.byte_theme.heading`

- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.card-icon` (one per feature)

Card-icon props: same as Benefit Overview cards above (`background_color`: `muted` only when section has no background, `border_radius`: `large`, `icon_size`: `extra-large`, `icon_align`: `center`, `text_align`: `center`). Pick an appropriate Phosphor icon for each.

### Pattern B: Side-by-Side Deep-Dives (5+ features)

Use when the copy deck has 5 or more features, or when individual features need a full paragraph of explanation plus an illustration.

Each feature becomes its own `sdc.byte_theme.section` with a `50-50` grid. The grid contains a `sdc.byte_theme.group` for the text content and a `sdc.byte_theme.image` placed directly in the grid slot (not wrapped in a group — there is a CSS bug that distorts images inside groups).

**Section props (per feature):**

| Prop                  | Value   |
| --------------------- | ------- |
| `width`               | `100%`  |
| `columns`             | `50-50` |
| `mobile_columns`      | `1`     |
| `margin_block_start`  | `128`   |
| `margin_block_end`    | `128`   |
| `padding_block_start` | `0`     |
| `padding_block_end`   | `0`     |
| `section_header`      | `false` |
| `section_footer`      | `false` |

**Image (direct in grid slot, no group wrapper):**

`sdc.byte_theme.image` — custom illustration related to the feature being described. Search the media library with a descriptive query for the feature. Set `radius`: `large`. Do not set `size` (aspect ratio). Do NOT wrap in a group component.

**Text content group:**

| Prop             | Value    |
| ---------------- | -------- |
| `flex_direction` | `column` |
| `flex_gap`       | `md`     |
| `items_align`    | `start`  |
| `flex_align`     | `center` |
| `radius`         | `sm`     |
| `padding`        | `sm`     |

Contains:

| Child           | Component | Props                                                        |
| --------------- | --------- | ------------------------------------------------------------ |
| Eyebrow         | `text`    | Feature category label (UPPERCASE), `text_size`: `text-sm`, `text_color`: `default` |
| H2              | `heading` | `level`: 2, `text_size`: `heading-responsive-4xl`, `text_color`: `default`, `align`: `left` |
| Body            | `text`    | `text_size`: `normal`, `text_color`: `default`               |
| Link (optional) | `button`  | `variant`: `secondary`, `size`: `medium`, `icon`: `arrow-right` |

**Alternating layout:** Alternate image position across consecutive sections — image-left/text-right, then text-left/image-right, and so on. This prevents visual monotony.

**Image rules:** Each deep-dive must have a unique illustration. Never repeat an image. See Visuals & Imagery for the no-repetition rule.

---

## Section 5: Results / Numbers (Required)

**Component:** `sdc.byte_theme.section`

| Prop                  | Value                                       |
| --------------------- | ------------------------------------------- |
| `width`               | `100%`                                      |
| `columns`             | `33-33-33` (for 3 stats) or `50-50` (for 2) |
| `mobile_columns`      | `1`                                         |
| `margin_block_start`  | `128`                                       |
| `margin_block_end`    | `128`                                       |
| `padding_block_start` | `0`                                         |
| `padding_block_end`   | `0`                                         |
| `section_header`      | `true`                                      |
| `section_footer`      | `false`                                     |

**`header_slot`** → `sdc.byte_theme.heading`

- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.card` (one per stat)

| Prop               | Value      |
| ------------------ | ---------- |
| `style`            | `framed`   |
| `orientation`      | `vertical` |
| `level`            | 3          |
| `background`       | `default`  |
| `is_text_centered` | `false`    |

**Copy rules:**

- Each card highlights one approved stat from Key Facts & Value Propositions.
- Heading is the stat itself (e.g., "80% less time on expenses").
- Body text explains the human impact (e.g., "That's 15 hours back every month...").
- All numbers must be pulled from Key Facts. Do not invent or estimate.
- Aim for 3 stat cards. Use 2 only if the product doesn't have a third distinct metric.

---

## Section 6: Testimonial(s) (Required)

**Component:** `sdc.byte_theme.section`

| Prop                  | Value                                             |
| --------------------- | ------------------------------------------------- |
| `width`               | `50%` (1 testimonial) or `100%` (2+ testimonials) |
| `columns`             | `100` (1 testimonial) or `50-50` (2 testimonials) |
| `mobile_columns`      | `1`                                               |
| `margin_block_start`  | `128`                                             |
| `margin_block_end`    | `128`                                             |
| `padding_block_start` | `64`                                              |
| `padding_block_end`   | `64`                                              |
| `background_color`    | `muted`                                           |
| `section_header`      | `false`                                           |
| `section_footer`      | `false`                                           |

**`main_slot`** → `sdc.byte_theme.card-testimonial`

| Prop    | Value                                      |
| ------- | ------------------------------------------ |
| `align` | `center`                                   |
| `style` | `default`                                  |
| `media` | Headshot from media library (if available) |

**Copy rules:**

- Use only approved testimonials from Key Facts & Value Propositions or explicitly flagged new quotes.
- If the testimonial is not yet approved in the CCC, flag it with a note recommending addition to Key Facts.
- At least one testimonial is required. Two is preferred when available.
- Attribution must include name, title, company, and employee count.

---

## Section 7: How It Works (Optional)

**Component:** `sdc.byte_theme.section`

| Prop                  | Value                                                        |
| --------------------- | ------------------------------------------------------------ |
| `width`               | `100%`                                                       |
| `columns`             | Match to step count: `25-25-25-25` (4 steps), `33-33-33` (3 steps) |
| `mobile_columns`      | `1`                                                          |
| `margin_block_start`  | `128`                                                        |
| `margin_block_end`    | `128`                                                        |
| `padding_block_start` | `0`                                                          |
| `padding_block_end`   | `0`                                                          |
| `section_header`      | `true`                                                       |
| `section_footer`      | `false`                                                      |

**`header_slot`** → `sdc.byte_theme.heading`

- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.card-icon` (one per step)

Same shared props as Benefit Overview cards. Pick an appropriate Phosphor icon for each step.

**Copy rules:**

- 3–4 steps maximum. Keep it simple.
- Each step has a numbered heading (e.g., "1. Sign up") and a 1–2 sentence description.
- This section is best for products where the onboarding flow is a selling point (e.g., "10-day implementation").

---

## Section 8: FAQ (Optional)

**Component:** `sdc.byte_theme.section` containing `sdc.byte_theme.accordion-container` with `sdc.byte_theme.accordion` items

**Section props:**

| Prop                  | Value   |
| --------------------- | ------- |
| `width`               | `100%`  |
| `columns`             | `100`   |
| `mobile_columns`      | `1`     |
| `margin_block_start`  | `128`   |
| `margin_block_end`    | `128`   |
| `padding_block_start` | `0`     |
| `padding_block_end`   | `0`     |
| `section_header`      | `true`  |
| `section_footer`      | `false` |

**`header_slot`** → `sdc.byte_theme.heading`

- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.accordion-container` → multiple `sdc.byte_theme.accordion`

Each accordion: `heading_level`: 3, `open_by_default`: `false` (except optionally the first one).

**Copy rules:**

- 4–6 questions maximum.
- Address the most common objections from the relevant persona.
- Answers should be concise (2–4 sentences) and factual. Pull stats from Key Facts where relevant.
- Security and integration questions are almost always relevant — include them.

---

## Section 9: Platform Features Grid (Optional)

**Component:** `sdc.byte_theme.section`

| Prop                  | Value   |
| --------------------- | ------- |
| `width`               | `100%`  |
| `columns`             | `50-50` |
| `mobile_columns`      | `1`     |
| `margin_block_start`  | `128`   |
| `margin_block_end`    | `128`   |
| `padding_block_start` | `0`     |
| `padding_block_end`   | `0`     |
| `section_header`      | `true`  |
| `section_footer`      | `false` |

**`header_slot`** → `sdc.byte_theme.heading`

- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.group` (one per feature, arranged in 50-50 grid)

Each group:

| Prop             | Value    |
| ---------------- | -------- |
| `flex_direction` | `column` |
| `flex_gap`       | `md`     |
| `items_align`    | `start`  |
| `flex_align`     | `center` |
| `radius`         | `sm`     |
| `padding`        | `sm`     |

Contains: `heading` (`level`: 3, `text_size`: `heading-responsive-3xl`, `align`: `left`) + `text` (`text_size`: `text-lg`)

**Copy rules:**

- 4–6 features in the grid.
- Each feature gets a short heading and a concise paragraph.
- Use this section for secondary features that support the main value props — integration details, international support, admin capabilities, etc.

---

## Section 10: Cross-Product Cards (Optional)

**Component:** `sdc.byte_theme.section`

| Prop                  | Value      |
| --------------------- | ---------- |
| `width`               | `100%`     |
| `columns`             | `33-33-33` |
| `mobile_columns`      | `1`        |
| `margin_block_start`  | `128`      |
| `margin_block_end`    | `128`      |
| `padding_block_start` | `0`        |
| `padding_block_end`   | `0`        |
| `section_header`      | `false`    |
| `section_footer`      | `false`    |

**`main_slot`** → `sdc.byte_theme.card` (one per product)

| Prop               | Value      |
| ------------------ | ---------- |
| `style`            | `framed`   |
| `orientation`      | `vertical` |
| `level`            | 3          |
| `background`       | `default`  |
| `is_text_centered` | `false`    |

Each card links to another FinDrop product page. Use the product one-liners from Brand Guidelines > Product Portfolio. Search the media library for an illustration related to each product (e.g., "FinDrop cards illustration", "FinDrop expense illustration").

**Copy rules:**

- Include cards for the other FinDrop products that are NOT the current page's product.
- Use the official product one-liner from Brand Guidelines as the card description.
- Link each card to the relevant product page.

---

## Section 11: Final CTA (Required)

**Component:** `sdc.byte_theme.section`

| Prop                  | Value   |
| --------------------- | ------- |
| `width`               | `100%`  |
| `columns`             | `50-50` |
| `mobile_columns`      | `1`     |
| `margin_block_start`  | `128`   |
| `margin_block_end`    | `64`    |
| `padding_block_start` | `64`    |
| `padding_block_end`   | `64`    |
| `background_color`    | `muted` |
| `section_header`      | `false` |
| `section_footer`      | `false` |

**`main_slot`** → `sdc.byte_theme.group` (text, left) + `sdc.byte_theme.image` (right, direct in grid slot — no group wrapper)

**Text group:**

| Prop             | Value    |
| ---------------- | -------- |
| `flex_direction` | `column` |
| `flex_gap`       | `lg`     |
| `items_align`    | `start`  |
| `flex_align`     | `center` |
| `radius`         | `sm`     |
| `padding`        | `sm`     |

Contains:

| Child                    | Component | Props                                                        |
| ------------------------ | --------- | ------------------------------------------------------------ |
| H2                       | `heading` | `level`: 2, `text_size`: `heading-responsive-4xl`, `text_color`: `default`, `align`: `left` |
| Body                     | `text`    | `text_size`: `text-lg`, `text_color`: `default`              |
| Primary CTA              | `button`  | `variant`: `primary`, `size`: `medium`, `icon`: `download` or `arrow-right` |
| Secondary CTA (optional) | `button`  | `variant`: `secondary`, `size`: `medium`                     |

When two CTAs are used, wrap them in a horizontal `group` (`flex_direction`: `row`, `padding`: none) so they sit side by side.

**Right column:** `sdc.byte_theme.image` placed directly in the grid slot. Do not wrap in a group. Do not set `size` (aspect ratio). Search the media library for an illustration related to the product.

**Copy rules:**

- H2 should restate the primary value proposition or echo the hero message.
- Body text is 1–2 sentences reinforcing the CTA.
- CTA label should use an approved CTA from Key Facts > CTA Library.

---

## Section 12: Legal Disclaimer (Optional)

**Component:** `sdc.byte_theme.text` (standalone in content region)

| Prop         | Value     |
| ------------ | --------- |
| `text_size`  | `text-xs` |
| `text_color` | `default` |

Include when the page requires banking or fintech regulatory disclosures. Standard text: "FinDrop is a financial technology company, not a bank. Banking services provided by Copperbell National Bank, N.A., Member FDIC."

---

## Checklist

Before publishing any product page, verify:

- [ ] All sections appear in the correct order per this template?
- [ ] All required sections are present (Hero, Feature Value Props, Results, Testimonial, Final CTA)?
- [ ] Hero uses `hero-billboard` (not `hero-side-by-side` or other variants)?
- [ ] `muted` only on Testimonials and Final CTA, no other section backgrounds?
- [ ] Text color correct for background (default/white on dark)?
- [ ] Correct card types used (stats → `card`, benefits → `card-icon`, quotes → `card-testimonial`)?
- [ ] No aspect ratio (`tile_size`) set on icon cards?
- [ ] No aspect ratio (`size`) set on image components?
- [ ] Feature deep-dive images placed directly in grid slot (not wrapped in a group)?
- [ ] All stats pulled from Key Facts & Value Propositions (none invented)?
- [ ] Mandatory phrasing rules followed (see Key Facts)?
- [ ] Vocabulary rules followed (see Abbreviations, Spelling, Dates & Formatting)?
- [ ] No competitor names in external-facing content (see Brand Guidelines > Competitive Positioning)?
- [ ] No image repeated on the page (see Visuals & Imagery)?
- [ ] All testimonials are approved references or flagged for addition to Key Facts?
- [ ] CTAs use labels from the approved CTA library?
- [ ] Hero leads with buyer outcome, not feature list?