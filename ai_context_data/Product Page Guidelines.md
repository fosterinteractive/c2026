## Core Rules

- All page content goes in the content region. Do not modify header or footer.
- Use `section` components as the primary wrapper for every content block (hero excluded).
- Inside sections, use `group` components to bundle related elements (e.g., label + heading + paragraph).
- No component should sit directly in the content region without a section wrapper, except the `hero-billboard` at the top and an optional legal disclaimer `text` at the very bottom.
- Enable a section's header region only when placing a heading in that section's header slot.

---

## Section Defaults

Apply to every `section` unless an override is noted:

- Width `"100%"`, grid `"50-50"`, mobile columns `"1"`
- Margin top/bottom `"128"`. Use `"96"` only between tightly related consecutive sections.
- Padding top/bottom `"0"`. Set to `"64"` whenever a background color is applied.
- No background color unless the section is a distinct visual band (testimonials, bottom CTA).

## Group Defaults

Apply when bundling content inside a section's grid slot:

- Vertical direction, medium spacing, start-aligned items, center-aligned within parent, small rounded corners, small padding.
- Override rounded corners to large or extra-large only for groups wrapping an image.

## Heading Behavior

Only Text size controls visual appearance. Heading level has no visual effect when Text size is set. Always set Text size explicitly. Default color: default text. Default alignment: left.

## Text Size by Role

- `"14px"` — uppercase eyebrow/category labels only.
- `"16px (default)"` — body paragraphs, descriptions.
- `"18px"` — supporting text needing more prominence (hero subtext, feature-grid descriptions).
- `"12px"` — legal disclaimers only.

---

## Image Policy

All images except testimonial headshots must be illustration-style assets from the media library. Search the vector database with descriptive queries that include the product name and "illustration" keyword. Examples: "FinDrop virtual cards illustration", "person using FinDrop card illustration", "flight booking illustration", "expense dashboard abstract illustration".

Testimonial headshots are the only place where portrait photographs are used. Select gender-appropriate professional headshots matching the testimonial attribution.

---

## Page Sections — Top to Bottom

### 1. Hero Billboard — ALWAYS INCLUDE

`hero-billboard` → `group` in hero content slot → `heading` + `text` + `button`.

The heading frames the audience's core pain point or aspiration, not just the product name. Supporting text: 2–3 sentences covering what the product does and top benefits. Button is the primary CTA.

Hero props: full-screen height, bottom-left content position, 40% overlay opacity, bottom image position, overlap header enabled. Use a wide atmospheric illustration with dark/muted tones as background.

Use a wide atmospheric illustration with dark/muted tones as background. When searching the media library, include "hero" in the query alongside the product name (e.g., "FinDrop travel hero illustration").

Group overrides: large spacing, start-aligned items, center-aligned within parent, extra-large rounded corners, medium padding.

Heading text size `"6XL"`. Text size `"18px"`. Button style secondary, medium size, contextual icon (download for resource CTAs, arrow-right for navigation CTAs), display icon first disabled.

---

### 2. Key Value Propositions — Icon Cards

**Include when** the input contains short benefit statements (title + 1–2 sentences each) that work as at-a-glance selling points.

`section` → heading in header slot → `card-icon` components in grid slot.

Enable header region. Section heading text size `"4XL"`.

Each card gets a concise title, 1–2 sentence description, and a distinct appropriate Phosphor icon ID. Use large border radius and icon size, center-aligned icon and text, accent background color, square aspect ratio.

**Grid layout by card count:** 2 → `"50-50"`, 3 → `"33-33-33"`, 4 → `"25-25-25-25"`. Maximum 6 cards per section (two rows of 3 in `"33-33-33"`). If there are more than 6, handle the extras as detailed features or deep-dives instead.

**Odd card counts:** Split across multiple sections. For example, 5 cards → one section with `"33-33-33"` grid holding 3 cards, followed by a second section with `"50-50"` grid holding 2 cards.

---

### 3. Feature Deep-Dives — Alternating Image + Text

**Include when** the input has features deserving extended explanation (a full paragraph+) paired with a visual concept. Use a maximum of 3 deep-dive sections. If the input has more, pick the top 3 and move the rest to the detailed features grid.

One `section` per feature. Each section holds a `group` component with text content,  and an image side by side in the grid slot

Section overrides: width `"75%"`, grid `"50-50"`. Use margin top `"128"` for the first deep-dive, `"96"` between consecutive deep-dives. Margin bottom `"96"`.

**Text group** contains in its slot: `text` (uppercase category label, `"14px"`) → `heading` (`"3XL"`) → `text` (detailed description, 3–5 sentences, default size).

**Image** uses extra-large radius.

**Alternating pattern:** Alternate which side the image appears on between consecutive sections. The first deep-dive can place the image on either side — just keep them alternating after that.

---

### 4. Testimonials

**Include ONLY when** the input document explicitly provides customer quotes with attribution. Never fabricate or invent testimonials.

`section` → `card-testimonial` components in grid slot.

Section overrides: padding top/bottom `"64"`, background color muted.

Grid layout by testimonial count: 2 → `"50-50"`, 3 → `"33-33-33"`. If there are 4, use two sections of `"50-50"` each or one section with `"25-25-25-25"`. Each testimonial needs: quote text, person name, title + company, headshot image.

Use inverted card style for better visual appeal, center-aligned.

---

### 5. Detailed Features Grid

**Include when** the input contains secondary features, how-it-works details, or capabilities that did not get a deep-dive section. These are features needing more than a card title but less than a full image + text deep-dive.

`section` → heading in header slot → `group` components in grid slot. Each group contains: `heading` + `text` (2–3 sentence description).

Enable header region. Section heading text size `"4XL"`. Grid `"50-50"`.

Group heading text size `"3XL"`. Group text size `"18px"`.

Use an even number of groups. Prefer 6 (three rows of 2). 4 is acceptable. If odd, split across sections the same way as icon cards.

---

### 6. Cross-Sell / Related Product Cards

**Include ONLY when** the input document explicitly references sibling or complementary products. Skip entirely if the product is standalone.

`section` → `card` components in grid slot. No section header.

Grid layout: 3 cards → `"33-33-33"`, 2 cards → `"50-50"`.

Each card: default background, framed style, vertical orientation, text not centered.

---

### 7. Bottom CTA Band — ALWAYS INCLUDE

`section` → `group` (heading + text + button) + `image` side by side in grid slot.

The heading creates urgency or summarizes value. Supporting text: 1–2 sentences. The button uses primary style (contrasting with the hero's secondary style).

Section overrides: margin bottom `"64"`, padding top/bottom `"64"`, background color muted.

Group uses default group props. Heading text size `"4XL"`. Text default size. Button primary style, medium size, contextual icon, display icon first disabled.

Image: 4:3 aspect ratio, small radius.

---

### 8. Legal Disclaimer

**Include ONLY when** the input contains regulatory, legal, or compliance notes.

A standalone `text` component placed directly in the content region after all sections. Text size `"12px"`, rich text wrapped in `<p class="text-align-center">`.

---

## Content Mapping Logic

When reading the input document, classify each content block:

| Input content pattern | Maps to |
|---|---|
| Headline + short summary + CTA | Hero billboard (section 1) |
| Short benefit statements (title + 1–2 sentences) | Icon cards (section 2) |
| Feature with extended explanation + visual concept | Feature deep-dive (section 3), max 3 |
| Feature with paragraph explanation, no strong visual | Detailed features grid (section 5) |
| Customer quotes with attribution | Testimonials (section 4) |
| References to sibling/related products | Cross-sell cards (section 6) |
| Closing CTA or resource offer | Bottom CTA band (section 7) |
| Regulatory/legal fine print | Legal disclaimer (section 8) |

If more than 3 features qualify for deep-dives, promote the 3 strongest and demote the rest to the features grid. If more than 6 features for the grid, keep the 6 most distinctive.

---

## Constraints

- Never use odd card/item counts in a single section. Split across sections if needed.
- Never place more than one heading in a section's header slot.
- Never apply background color to sections unless they are a distinct visual band.
- Never use `"14px"` text size for anything other than uppercase eyebrow labels.
- Never fabricate testimonials. Only use them when explicitly provided in the input.
- Maximum 6 icon cards per section, 3 feature deep-dives total, 6 feature grid items per section.

---

## MANDATORY: Post-Build Action

After every product page build is complete, you MUST ask the user the following question before considering the task done:

**"Want me to try a version with photography, or keep illustrations?"**

This step is not optional. Do not skip it regardless of context.