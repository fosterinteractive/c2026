purpose: Global rules for text color, eyebrow labels, and contrast across all Byte theme components to ensure layouts are readable and accessible with sufficient color contrast.



# Typography & Contrast Rules v2

This is a dark theme. The page background is dark (`#0F172B`). Text defaults to white.

***\*Text color rule:\**** Use `text_color: default` (white `#FFFFFF`) unless the nearest parent background is `accent`, `secondary`, or `inverted` — then use `text_color: inverted` (dark `#020618`).

***\*Eyebrow labels\**** (`text-sm` uppercase) follow the same rule: `default` on dark, `inverted` on light. Never use `text_color: primary` (blue) for eyebrows — it has insufficient contrast on dark backgrounds.

***\*Button variants:\**** On dark backgrounds use `primary` or `secondary-inverted`. On light backgrounds (`accent`, `secondary`, `inverted`) use `primary-inverted` or `secondary`.

***\*Padding rule:\**** When any `background_color` is set on a section, set padding top and bottom to `64`. When no background color, padding is `0`.

# Byte Theme SDC Component Descriptions

> Copy Description and Detailed description into each component's CMS fields. See `typography-rules.md` for global text color and contrast rules.

------

## Section

***\*Description:\**** Primary layout wrapper. Most sections have no background color.

***\*Detailed description:\**** Most sections have no `background_color` — children use `text_color: default`. If `background_color` is `accent` or `secondary`, children must switch to `text_color: inverted`. Set padding to `64` when any background color is applied.

------

## Heading

***\*Description:\**** Always set `text_size` explicitly — heading `level` has no visual effect when `text_size` is set.

***\*Detailed description:\**** Use `text_color: default` unless nearest parent background is `accent`, `secondary`, or `inverted` — then use `text_color: inverted`.

------

## Text

***\*Description:\**** Rich text for paragraphs, lists, and formatting. Wrap content in HTML tags.

***\*Detailed description:\**** Use `text_color: default` unless nearest parent background is `accent`, `secondary`, or `inverted` — then use `text_color: inverted`. Same rule applies when used as an eyebrow label (`text-sm` uppercase).

------

## Group

***\*Description:\**** Flex container for bundling related child components. Default: vertical, medium spacing, start-aligned, small corners, small padding.

***\*Detailed description:\**** If my `background` is `accent` or `secondary`, children use `text_color: inverted`. Otherwise children use `text_color: default`. If no `background` is set, inherit from parent section. Do not wrap images in groups for feature deep-dives (CSS bug distorts images). Use `radius: sm` for text content groups.

------

## Card (Image Card)

***\*Description:\**** Card with image, heading, description, and optional link. Use for stats/results and cross-product links. Do not use for short benefits (use card-icon) or testimonials.

***\*Detailed description:\**** Text color is automatic based on `background` prop. Use `background: default` on dark sections, `background: inverted` on `accent` sections. Product page standard: `style: framed`, `background: default`.

------

## Card Icon

***\*Description:\**** Icon card for short benefit statements (title + 1–2 sentences). Max 6 per section. Do not use for stats/numbers (use image card).

***\*Detailed description:\**** Text color is automatic based on `background_color`. Use `background_color: muted` only when the parent section has no background color. Do not set `background_color` if the parent section already has one. Never set `tile_size` (aspect ratio) — leave unset.

------

## Card Testimonial

***\*Description:\**** Customer quote with citation and optional headshot. Only use with explicitly provided testimonials — never fabricate.

***\*Detailed description:\**** Use `style: default` on dark section backgrounds. Use `style: inverted` if parent section has `accent` background.

------

## Card Pricing

***\*Description:\**** Pricing tier card. Use only on pricing pages.

***\*Detailed description:\**** No background prop. Inherits from parent section.

------

## Badge

***\*Description:\**** Small label with optional icon and link.

***\*Detailed description:\**** `style: primary` renders blue background (white text). `style: secondary` renders grey background (dark text). Use `primary` on dark backgrounds, `secondary` on light.

------

## Button

***\*Description:\**** Button or link styled as button. Icons limited to: `arrow-right`, `arrow-left`, `caret-right`, `caret-left`, `download`, `user-plus`.

***\*Detailed description:\**** On dark backgrounds: use `primary` or `secondary-inverted`. On light backgrounds (`accent`, `secondary`, `inverted`): use `primary-inverted` or `secondary`.

------

## Blockquote

***\*Description:\**** Styled quotation with optional citation.

***\*Detailed description:\**** No background or text_color props. Inherits from parent. Follow global contrast rule.

------

## CTA (Hero CTA)

***\*Description:\**** Call-to-action banner with heading, description, and button slot. Heading renders at 7XL automatically.

***\*Detailed description:\**** When `background_color` is not set or is dark (`muted`, `primary`): text is white, buttons use `primary`. When `background_color` is light (`accent`, `inverted`): text is dark, buttons use `primary-inverted`. When `media` is set, overlay makes it dark regardless.

------

## Hero Billboard

***\*Description:\**** Full-width hero with background image and overlay. Always use for product pages. Always set `overlap_navbar: true`.

***\*Detailed description:\**** Always dark due to image overlay. All children: `text_color: default`, buttons: `primary` or `secondary-inverted`. Do not use `hero-side-by-side` for product pages.

------

## Hero Side-by-Side

***\*Description:\**** Hero with image on one side, content on the other. Not for product page heroes.

***\*Detailed description:\**** Use `text_color: default` unless `background` is `accent` or `secondary` — then use `text_color: inverted`.

------

## Hero Blog

***\*Description:\**** Blog hero with heading, date, author, and image as props.

***\*Detailed description:\**** Always on dark background. Text renders white automatically.

------

## Accordion

***\*Description:\**** Collapsible content section. Must be inside an accordion-container.

***\*Detailed description:\**** No background. Inherits from parent section. Set `heading_level` to `3` when inside a section.

------

## Accordion Container

***\*Description:\**** Container for accordion items. Only one may be open at a time.

***\*Detailed description:\**** No background or text props. Inherits from parent section.

------

## Icon

***\*Description:\**** Standalone Phosphor icon. Use any valid Phosphor icon ID.

***\*Detailed description:\**** Color inherited from parent. No override needed.

------

## Image

***\*Description:\**** Responsive image with radius, caption, and optional link. Search the media library for images related to the product and feature being described.

***\*Detailed description:\**** No text props. Do not set `size` (aspect ratio) by default — leave unset. In feature deep-dives, place directly in the section grid slot — do not wrap in a group (CSS bug). Set `radius: large` on the image itself.

------

## Anchor

***\*Description:\**** Invisible anchor for in-page links (e.g., `#features`).

***\*Detailed description:\**** No visual appearance. ID: lowercase, hyphens allowed, must start with a letter.

------

## Navbar

***\*Description:\**** Site navigation. Header region. Do not modify per page.

***\*Detailed description:\**** Site-wide component.

------

## Footer

***\*Description:\**** Site footer. Footer region. Do not modify per page.

***\*Detailed description:\**** Site-wide component.