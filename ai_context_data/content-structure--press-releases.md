---
purpose: "Load when building or reviewing FinDrop press releases or company announcements. Defines section order, component patterns, and layout rules. Press releases are formal, structured, and follow a standard B2B format. Skip for blog articles, product pages, or landing pages."
---

# Content Strategy: Press Releases

> ⚠️ **NOT TESTED.** This guideline has not been validated against live press release builds. Treat as a starting point and refine based on output quality.

## Core Rules

- All page content goes in the content region. Do not modify header or footer.
- Use `section` components as the primary wrapper for every content block (hero excluded).
- Press releases use `hero-blog` as the hero component — same as articles.
- Press releases are text-heavy and highly structured. Most sections are single-column `text` blocks.
- Tone is formal and factual. Follow Brand Guidelines > Tone & Voice but shift toward the professional end — no casual language, no humor. Data-confident voice still applies.

## Section Defaults

- Width `100%`, grid `100` (single column), mobile columns `1`.
- Margin top/bottom `128`. Use `96` between tightly related consecutive sections.
- Padding top/bottom `0`. Set to `64` when a background color is applied.

## Background Color Rules

Press releases are almost entirely default dark with no background color. They are formal documents, not marketing landing pages.

- **Most sections: no background color.**
- **`accent`: do not use.** Press releases should not have visual highlight bands.
- **`muted`: bottom CTA only** (if included).
- **`primary` and `secondary`: do not use.**

---

## Page Sections — Top to Bottom

| Order | Section | Required |
|-------|---------|----------|
| 1 | Hero Blog | **Yes** |
| 2 | Dateline & Summary | **Yes** |
| 3 | Body | **Yes** |
| 4 | Quote | **Yes** |
| 5 | Supporting Details | No |
| 6 | Boilerplate | **Yes** |
| 7 | Media Contact | **Yes** |
| 8 | Bottom CTA | No |

---

### 1. Hero Blog — ALWAYS INCLUDE

`hero-blog` — same as articles. Do not use `hero-billboard`.

Props: `heading_text` (press release headline), `level`: 1, `date` (publication date as Unix timestamp), `author` (omit or set to "FinDrop Newsroom"), `media` (featured image if available).

Headlines should be factual and specific: "FinDrop Launches Integrated Business Travel Management Platform" not "FinDrop Changes Everything About Travel."

---

### 2. Dateline & Summary — ALWAYS INCLUDE

`section` (columns `100`) → `text` in grid slot.

Body text `text-lg` for visual weight. Format as:

**[CITY, STATE — Date]** — One to two sentence summary of the announcement. This is the lead paragraph and should answer who, what, when, and why in the most concise form possible.

Follow AP style for the dateline. Use the city where the company is headquartered or where the announcement originates.

---

### 3. Body — ALWAYS INCLUDE

One or more `section` (columns `100`) → `text` in grid slot.

Body text `normal` (16px). Each section covers one topic: the problem being solved, the product details, market context, or customer impact.

Structure follows the inverted pyramid — most important information first, supporting details later. Keep paragraphs short (2–4 sentences).

If citing FinDrop metrics, all stats must come from Key Facts & Value Propositions. Follow mandatory phrasing rules exactly.

---

### 4. Quote — ALWAYS INCLUDE

At least one executive quote is required. Use `section` (columns `100`) → `blockquote` in grid slot.

Quote should come from a named FinDrop executive (e.g., CEO, VP of Product). Attribution format: "First Last, Title, FinDrop."

If the press release includes a customer quote, add a second `blockquote` in a separate section. Customer quotes must be from approved references in Key Facts or explicitly flagged as new.

Do not fabricate quotes.

---

### 5. Supporting Details (Optional)

**Include when** the announcement has additional context: product availability, pricing, integration details, or event information.

`section` (columns `100`) → heading in header slot → `text` in grid slot.

Section heading: `level`: 2, `text_size`: `heading-responsive-4xl`, `align`: `left`.

For lists of features or availability details, use `<ul>` or `<ol>` inside the `text` component.

---

### 6. Boilerplate — ALWAYS INCLUDE

`section` (columns `100`) → heading in header slot → `text` in grid slot.

Section heading: "About FinDrop", `level`: 2, `text_size`: `heading-responsive-3xl`, `align`: `left`.

Standard boilerplate text (keep consistent across all press releases):

"FinDrop is a financial operations platform that combines instant corporate cards, automated expense management, and integrated business travel on one platform. FinDrop reduces expense processing time by 80% and implements in 10 business days. For more information, visit findrop.com."

Update the boilerplate only when product lines change. Do not customize per release.

---

### 7. Media Contact — ALWAYS INCLUDE

`section` (columns `100`) → `text` in grid slot.

Body text `normal`. Format as:

**Media Contact**
[Name]
[Title], FinDrop
[email]
[phone]

---

### 8. Bottom CTA (Optional)

**Include when** the press release should drive to a product page, demo request, or resource download.

`section` (columns `50-50`, `background_color: muted`, padding `64`, margin bottom `64`) → `group` (heading + text + button) + image.

Same pattern as product pages and articles. CTA should relate to the announcement topic.

---

## Content Mapping Logic

| Press release content pattern | Maps to |
|---|---|
| Headline, date | Hero blog (1) |
| Dateline, lead paragraph | Dateline & summary (2) |
| Announcement details, market context | Body (3) |
| Executive or customer quotes | Quote (4) |
| Availability, pricing, feature lists | Supporting details (5) |
| Company description | Boilerplate (6) |
| PR contact information | Media contact (7) |
| Conversion action | Bottom CTA (8) |

---

## Constraints

- Never use `hero-billboard` for press releases. Always `hero-blog`.
- Never apply background color except on the optional `muted` bottom CTA.
- Never fabricate quotes — executive or customer.
- Never customize the boilerplate per release without approval.
- All FinDrop stats must come from Key Facts. Follow mandatory phrasing rules.
- No competitor names without Legal approval per Brand Guidelines.
- Headlines must be factual and specific — no hyperbole, no superlatives without Legal review.

---

## Checklist

- [ ] Hero uses `hero-blog`?
- [ ] Dateline follows AP style?
- [ ] Lead paragraph answers who, what, when, why?
- [ ] At least one executive quote included?
- [ ] Customer quotes approved or flagged?
- [ ] All stats from Key Facts with mandatory phrasing?
- [ ] Boilerplate present and standard?
- [ ] Media contact present?
- [ ] No competitor names without Legal approval?
- [ ] No "first/only/best" claims without Legal review?
