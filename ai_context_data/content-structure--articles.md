---
purpose: "Load when building or reviewing FinDrop blog posts, thought leadership articles, or editorial content. Defines section order, component patterns, and layout rules for article pages. Skip for product pages, landing pages, or non-editorial content types."
---

# Content Strategy: Articles

> ⚠️ **NOT TESTED.** This guideline has not been validated against live article builds. Treat as a starting point and refine based on output quality.

## Core Rules

- All page content goes in the content region. Do not modify header or footer.
- Use `section` components as the primary wrapper for every content block (hero excluded).
- Article pages use `hero-blog` as the hero component (not `hero-billboard`).
- Body content is primarily `text` components inside `section` wrappers — articles are text-heavy, not card-heavy.
- No component should sit directly in the content region without a section wrapper, except `hero-blog` at the top.

## Section Defaults

Same as product pages:

- Width `100%`, grid `100` (single column for body text), mobile columns `1`.
- Margin top/bottom `128`. Use `96` between tightly related consecutive sections.
- Padding top/bottom `0`. Set to `64` when a background color is applied.
- No background color unless the section is a distinct visual band (pullquote, CTA).

## Background Color Rules

Articles are mostly default dark with no background color. Use color bands even more sparingly than product pages.

- **Most sections: no background color.** Body text, headings, images all sit on default dark.
- **`accent`: maximum one per article.** Use for a pullquote or key stat callout — the single moment you want the reader to pause.
- **`muted`: bottom CTA only.** Same rule as product pages.
- **`primary` and `secondary`: do not use** on article sections.

---

## Page Sections — Top to Bottom

| Order | Section | Required |
|-------|---------|----------|
| 1 | Hero Blog | **Yes** |
| 2 | Introduction | **Yes** |
| 3 | Body Sections | **Yes** |
| 4 | Pullquote / Stat Callout | No |
| 5 | Inline Image | No |
| 6 | Testimonial | No |
| 7 | Related Articles | No |
| 8 | Bottom CTA | **Yes** |

---

### 1. Hero Blog — ALWAYS INCLUDE

`hero-blog` — always for articles. Do not use `hero-billboard`.

Props: `heading_text` (article title), `level`: 1, `date` (Unix timestamp), `author`, `author_url` (optional), `media` (featured image).

Always on dark background. Text renders white automatically.

---

### 2. Introduction — ALWAYS INCLUDE

`section` (columns `100`) → `text` in grid slot.

No section header. Body text `text-lg` for the opening paragraph(s) to give them more visual weight. Use `text_color: default`.

Keep to 2–3 paragraphs. Frame the problem or question the article addresses. End with a clear thesis or preview of what the reader will learn.

---

### 3. Body Sections — ALWAYS INCLUDE

Each major section of the article becomes its own `section` → heading in header slot → `text` in grid slot.

Section heading: `level`: 2, `text_size`: `heading-responsive-4xl`, `align`: `left`. Body text: `normal` (16px).

Columns `100` for text-only sections. Use `75-25` or `67-33` when pairing text with a sidebar element (image, callout).

Subheadings within body text: use `level`: 3, `text_size`: `heading-responsive-3xl` inside the `text` component as `<h3>` tags, or as separate `heading` components if they need their own section break.

---

### 4. Pullquote / Stat Callout (Optional)

**Include when** the article has a key quote or stat that deserves visual emphasis.

Two options:

**Option A — Blockquote:** `section` (columns `100`) → `blockquote` in grid slot. No background color. Simple inline emphasis.

**Option B — Accent band:** `section` (columns `100`, `background_color: accent`, padding `64`) → `text` or `blockquote` in grid slot. Use `text_color: inverted`. This is the article's one `accent` section — do not use accent anywhere else.

Use for customer quotes, key statistics, or a summary statement you want the reader to remember.

---

### 5. Inline Image (Optional)

**Include when** the article references a diagram, chart, screenshot, or photograph.

`section` (columns `100`) → `image` in grid slot.

Image props: appropriate aspect ratio (typically `16:9` for wide images, `4:3` for standard), `radius: small`, `caption` describing the image content.

For side-by-side image + text: use `section` with `50-50` grid, image in one slot, `text` in the other.

---

### 6. Testimonial (Optional)

**Include ONLY when** the article includes an explicitly provided customer quote with attribution. Never fabricate.

`section` (columns `100`) → `card-testimonial` in grid slot.

Card style: `default` (dark background). Use `inverted` only if inside an `accent` section.

---

### 7. Related Articles (Optional)

**Include when** there are 2–3 related articles to cross-link.

`section` → heading in header slot → `card` components in grid slot.

Section heading: "Related Reading" or similar, `level`: 2, `text_size`: `heading-responsive-4xl`, `align`: `left`.

Grid: `50-50` for 2 articles, `33-33-33` for 3. Each card: `style: framed`, `orientation: vertical`, `background: default`. Card heading is the article title, text is a 1–2 sentence summary, link goes to the article, media is the article's featured image.

---

### 8. Bottom CTA — ALWAYS INCLUDE

`section` (columns `50-50`, `background_color: muted`, padding `64`, margin bottom `64`) → `group` (text content) + image or illustration.

Group contains: `heading` (`level`: 2, `text_size`: `heading-responsive-4xl`) + `text` (`text-lg`) + `button` (`primary`, `medium`).

CTA should relate to the article topic — link to a relevant product page, whitepaper download, or demo request. Use approved CTAs from Key Facts > CTA Library where possible.

---

## Content Mapping Logic

| Article content pattern | Maps to |
|---|---|
| Title, date, author, featured image | Hero blog (1) |
| Opening paragraphs, thesis statement | Introduction (2) |
| Headed sections with body text | Body sections (3) |
| Key quote or standout statistic | Pullquote / stat callout (4) |
| Diagrams, charts, screenshots, photos | Inline image (5) |
| Customer quote with attribution | Testimonial (6) |
| Links to related content | Related articles (7) |
| Conversion action, resource offer | Bottom CTA (8) |

---

## Constraints

- Never use `hero-billboard` for articles. Always `hero-blog`.
- Never apply background color except on one optional `accent` section and the `muted` bottom CTA.
- Never fabricate testimonials or quotes.
- Never use more than `100` column width for body text sections — articles should not use multi-column card grids for main content.
- All stats must come from Key Facts when citing FinDrop metrics.
- Follow all vocabulary and phrasing rules from Brand Guidelines > Abbreviations.

---

## Checklist

- [ ] Hero uses `hero-blog` (not `hero-billboard`)?
- [ ] Introduction leads with the problem or question?
- [ ] Body sections use `level: 2` headings with proper hierarchy?
- [ ] Maximum one `accent` section, `muted` only on bottom CTA?
- [ ] Text color correct for background (default on dark, inverted on accent)?
- [ ] All FinDrop stats from Key Facts?
- [ ] Testimonials approved or flagged?
- [ ] Bottom CTA present with relevant conversion action?
- [ ] No competitor names in external content?
