---
purpose: "Load when building or reviewing any FinDrop product page (Cards, Expense, Travel, or future products). Defines the required section order, component patterns, and layout rules for product pages. The author supplies the copy — this document supplies the structure. Skip for blog posts, landing pages, or non-product content types."
---

# Content Strategy: Product Pages

## How to Use This Document

This is the structural template for all FinDrop product pages. It defines the section order, which sections are required vs. optional, and the Byte theme component patterns for each section.

**Workflow:** The author provides a copy deck (headings, body text, CTAs, testimonials). The AI applies this template to assemble the page using the correct components, props, and layout patterns. The copy deck does not need to specify components — this document handles that.

**CCC documents to load alongside this one:**
- Brand Guidelines (+ Abbreviations, Tone & Voice, Visuals & Imagery)
- Key Facts & Value Propositions
- The relevant Persona sub-context for the page's target audience

---

## Section Order

All product pages follow this fixed order. Required sections must appear on every product page. Optional sections are included when the copy deck provides content for them.

| Order | Section | Required | Notes |
|-------|---------|----------|-------|
| 1 | Hero | **Required** | Full-width billboard with H1, subhead, and primary CTA |
| 2 | Problem Statement | Optional | Text block framing the pain the product solves |
| 3 | Benefit Overview | Optional | High-level benefit cards (typically 3–4) |
| 4 | Feature Value Props | **Required** | Detailed feature cards or deep-dive sections |
| 5 | Results / Numbers | **Required** | Stat cards proving the claims with approved metrics |
| 6 | Testimonial(s) | **Required** | At least one customer quote |
| 7 | How It Works | Optional | Step-by-step onboarding or usage flow |
| 8 | FAQ | Optional | Common objections answered |
| 9 | Platform Features | Optional | Detailed feature explanations in a grid |
| 10 | Cross-Product Cards | Optional | Links to other FinDrop products |
| 11 | Final CTA | **Required** | Closing conversion section |
| 12 | Legal Disclaimer | Optional | Required when banking/fintech disclosures apply |

---

## Section 1: Hero (Required)

**Component:** `sdc.byte_theme.hero-billboard`

| Prop | Default Value | Notes |
|------|--------------|-------|
| `height` | `full` | Always full-screen for product pages |
| `flex_position` | `bottom-left` | Content anchored bottom-left |
| `overlay_opacity` | `40%` | Adjust per image — ensure text readability |
| `object_position` | `bottom` | Adjust to match image composition |
| `overlap_navbar` | `true` | Hero always sits behind the nav |

**`hero_slot`** → `sdc.byte_theme.group` containing:

| Child | Component | Props |
|-------|-----------|-------|
| H1 | `heading` | `level`: 1, `text_size`: `heading-responsive-7xl`, `text_color`: `inverted`, `align`: `left` |
| Subhead | `text` | `text_size`: `text-lg`, `text_color`: `inverted` |
| Primary CTA | `button` | `variant`: `primary`, `size`: `large`. Pick appropriate icon from: `download`, `arrow-right` |
| Secondary CTA (optional) | `button` | `variant`: `secondary-inverted`, `size`: `large` |

**Media:** Custom illustration or branded photography from the product's media library. No stock photography. See Visuals & Imagery guidelines.

**Copy rules:**
- H1 should lead with a buyer outcome or the product's primary value proposition from Key Facts.
- Subhead expands on the H1 with a supporting detail and the human impact.
- Maximum two CTAs. Primary is the conversion action; secondary is a lower-commitment alternative (e.g., "Watch Demo").

---

## Section 2: Problem Statement (Optional)

**Component:** `sdc.byte_theme.section`

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | `100` |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `0` |
| `padding_block_end` | `0` |
| `section_header` | `true` |
| `section_footer` | `false` |

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

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | See grid rules below |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `0` |
| `padding_block_end` | `0` |
| `section_header` | `true` |
| `section_footer` | `false` |

**`header_slot`** → `sdc.byte_theme.heading`
- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.card-icon` (one per benefit)

**Grid rules:** Match column layout to card count:
- 2 cards → `columns`: `50-50`
- 3 cards → `columns`: `33-33-33`
- 4 cards → `columns`: `25-25-25-25`

**Card-icon shared props:**

| Prop | Value |
|------|-------|
| `background_color` | `muted` |
| `border_radius` | `large` |
| `icon_size` | `extra-large` |
| `icon_align` | `center` |
| `text_align` | `center` |

- Pick an appropriate Phosphor icon for each card based on the benefit. Refer to Visuals & Imagery for recommended icons by use case.
- Each card has a short heading (`text` prop) and a 1–2 sentence description (`description` prop).

**Copy rules:**
- These are high-level benefits, not detailed features.
- Use this section when the page needs a quick scannable summary before diving deeper.

---

## Section 4: Feature Value Props (Required)

This section has two patterns. Choose based on the number of features.

### Pattern A: Icon Card Grid (2–4 features)

Use when the copy deck has 2–4 features that can each be summarized in a heading + short paragraph.

**Component:** `sdc.byte_theme.section`

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | Match to count: `50-50`, `33-33-33`, or `25-25-25-25` |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `0` |
| `padding_block_end` | `0` |
| `section_header` | `true` |
| `section_footer` | `false` |

**`header_slot`** → `sdc.byte_theme.heading`
- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.card-icon` (one per feature)

Card-icon props: same as Benefit Overview cards above (`background_color`: `muted`, `border_radius`: `large`, `icon_size`: `extra-large`, `icon_align`: `center`, `text_align`: `center`). Pick an appropriate Phosphor icon for each.

### Pattern B: Side-by-Side Deep-Dives (5+ features)

Use when the copy deck has 5 or more features, or when individual features need a full paragraph of explanation plus an illustration.

Each feature becomes its own `sdc.byte_theme.section` with a `50-50` grid containing two `sdc.byte_theme.group` components.

**Section props (per feature):**

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | `50-50` |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `0` |
| `padding_block_end` | `0` |
| `section_header` | `false` |
| `section_footer` | `false` |

**Image group:**

| Prop | Value |
|------|-------|
| `flex_direction` | `column` |
| `flex_gap` | `md` |
| `items_align` | `start` |
| `flex_align` | `center` |
| `radius` | `lg` |

Contains: `sdc.byte_theme.image` — custom illustration depicting the feature.

**Text content group:**

| Prop | Value |
|------|-------|
| `flex_direction` | `column` |
| `flex_gap` | `md` |
| `items_align` | `start` |
| `flex_align` | `center` |
| `radius` | `sm` |
| `padding` | `sm` |

Contains:

| Child | Component | Props |
|-------|-----------|-------|
| Eyebrow | `text` | Feature category label (UPPERCASE), `text_size`: `text-sm`, `text_color`: `primary` |
| H2 | `heading` | `level`: 2, `text_size`: `heading-responsive-4xl`, `text_color`: `default`, `align`: `left` |
| Body | `text` | `text_size`: `normal`, `text_color`: `default` |
| Link (optional) | `button` | `variant`: `secondary`, `size`: `medium`, `icon`: `arrow-right` |

**Alternating layout:** Alternate image position across consecutive sections — image-left/text-right, then text-left/image-right, and so on. This prevents visual monotony.

**Background variation:** One deep-dive section may use `background_color`: `accent` with `padding_block_start`: `64` and `padding_block_end`: `64` to break up the rhythm. Use this for the most distinctive or differentiating feature.

**Image rules:** Each deep-dive must have a unique illustration. Never repeat an image. See Visuals & Imagery for the no-repetition rule.

---

## Section 5: Results / Numbers (Required)

**Component:** `sdc.byte_theme.section`

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | `33-33-33` (for 3 stats) or `50-50` (for 2) |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `0` |
| `padding_block_end` | `0` |
| `section_header` | `true` |
| `section_footer` | `false` |

**`header_slot`** → `sdc.byte_theme.heading`
- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.card` (one per stat)

| Prop | Value |
|------|-------|
| `style` | `framed` |
| `orientation` | `vertical` |
| `level` | 3 |
| `background` | `default` |
| `is_text_centered` | `false` |

**Copy rules:**
- Each card highlights one approved stat from Key Facts & Value Propositions.
- Heading is the stat itself (e.g., "80% less time on expenses").
- Body text explains the human impact (e.g., "That's 15 hours back every month...").
- All numbers must be pulled from Key Facts. Do not invent or estimate.
- Aim for 3 stat cards. Use 2 only if the product doesn't have a third distinct metric.

---

## Section 6: Testimonial(s) (Required)

**Component:** `sdc.byte_theme.section`

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | `100` (1 testimonial) or `50-50` (2 testimonials) |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `64` |
| `padding_block_end` | `64` |
| `background_color` | `muted` |
| `section_header` | `false` |
| `section_footer` | `false` |

**`main_slot`** → `sdc.byte_theme.card-testimonial`

| Prop | Value |
|------|-------|
| `align` | `center` |
| `style` | `inverted` |
| `media` | Headshot from media library (if available) |

**Copy rules:**
- Use only approved testimonials from Key Facts & Value Propositions or explicitly flagged new quotes.
- If the testimonial is not yet approved in the CCC, flag it with a note recommending addition to Key Facts.
- At least one testimonial is required. Two is preferred when available.
- Attribution must include name, title, company, and employee count.

---

## Section 7: How It Works (Optional)

**Component:** `sdc.byte_theme.section`

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | Match to step count: `25-25-25-25` (4 steps), `33-33-33` (3 steps) |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `0` |
| `padding_block_end` | `0` |
| `section_header` | `true` |
| `section_footer` | `false` |

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

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | `100` |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `0` |
| `padding_block_end` | `0` |
| `section_header` | `true` |
| `section_footer` | `false` |

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

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | `50-50` |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `0` |
| `padding_block_end` | `0` |
| `section_header` | `true` |
| `section_footer` | `false` |

**`header_slot`** → `sdc.byte_theme.heading`
- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → `sdc.byte_theme.group` (one per feature, arranged in 50-50 grid)

Each group:

| Prop | Value |
|------|-------|
| `flex_direction` | `column` |
| `flex_gap` | `md` |
| `items_align` | `start` |
| `flex_align` | `center` |
| `radius` | `sm` |
| `padding` | `sm` |

Contains: `heading` (`level`: 3, `text_size`: `heading-responsive-3xl`, `align`: `left`) + `text` (`text_size`: `text-lg`)

**Copy rules:**
- 4–6 features in the grid.
- Each feature gets a short heading and a concise paragraph.
- Use this section for secondary features that support the main value props — integration details, international support, admin capabilities, etc.

---

## Section 10: Cross-Product Cards (Optional)

**Component:** `sdc.byte_theme.section`

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | `33-33-33` |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `128` |
| `padding_block_start` | `0` |
| `padding_block_end` | `0` |
| `section_header` | `false` |
| `section_footer` | `false` |

**`main_slot`** → `sdc.byte_theme.card` (one per product)

| Prop | Value |
|------|-------|
| `style` | `framed` |
| `orientation` | `vertical` |
| `level` | 3 |
| `background` | `default` |
| `is_text_centered` | `false` |

Each card links to another FinDrop product page. Use the product one-liners from Brand Guidelines > Product Portfolio. Image should be a custom illustration from the product's media library.

**Copy rules:**
- Include cards for the other FinDrop products that are NOT the current page's product.
- Use the official product one-liner from Brand Guidelines as the card description.
- Link each card to the relevant product page.

---

## Section 11: Final CTA (Required)

**Component:** `sdc.byte_theme.section`

| Prop | Value |
|------|-------|
| `width` | `100%` |
| `columns` | `50-50` |
| `mobile_columns` | `1` |
| `margin_block_start` | `128` |
| `margin_block_end` | `64` |
| `padding_block_start` | `64` |
| `padding_block_end` | `64` |
| `background_color` | `muted` |
| `section_header` | `false` |
| `section_footer` | `false` |

**`main_slot`** → `sdc.byte_theme.group` (text, left) + illustration or image (right)

**Text group:**

| Prop | Value |
|------|-------|
| `flex_direction` | `column` |
| `flex_gap` | `md` |
| `items_align` | `start` |
| `flex_align` | `center` |
| `radius` | `sm` |
| `padding` | `sm` |

Contains:

| Child | Component | Props |
|-------|-----------|-------|
| H2 | `heading` | `level`: 2, `text_size`: `heading-responsive-4xl`, `text_color`: `default`, `align`: `left` |
| Body | `text` | `text_size`: `text-lg`, `text_color`: `default` |
| Primary CTA | `button` | `variant`: `primary`, `size`: `medium`, `icon`: `download` or `arrow-right` |
| Secondary CTA (optional) | `button` | `variant`: `secondary`, `size`: `medium` |

**Right column:** Custom illustration or branded photography from the product media library.

**Copy rules:**
- H2 should restate the primary value proposition or echo the hero message.
- Body text is 1–2 sentences reinforcing the CTA.
- CTA label should use an approved CTA from Key Facts > CTA Library.

---

## Section 12: Legal Disclaimer (Optional)

**Component:** `sdc.byte_theme.text` (standalone in content region)

| Prop | Value |
|------|-------|
| `text_size` | `text-xs` |
| `text_color` | `default` |

Include when the page requires banking or fintech regulatory disclosures. Standard text: "FinDrop is a financial technology company, not a bank. Banking services provided by Copperbell National Bank, N.A., Member FDIC."

---

## Checklist

Before publishing any product page, verify:

- [ ] All sections appear in the correct order per this template?
- [ ] All required sections are present (Hero, Feature Value Props, Results, Testimonial, Final CTA)?
- [ ] All stats pulled from Key Facts & Value Propositions (none invented)?
- [ ] Mandatory phrasing rules followed (see Key Facts)?
- [ ] Vocabulary rules followed (see Abbreviations, Spelling, Dates & Formatting)?
- [ ] No competitor names in external-facing content (see Brand Guidelines > Competitive Positioning)?
- [ ] No image repeated on the page (see Visuals & Imagery)?
- [ ] All testimonials are approved references or flagged for addition to Key Facts?
- [ ] CTAs use labels from the approved CTA library?
- [ ] Hero leads with buyer outcome, not feature list?
