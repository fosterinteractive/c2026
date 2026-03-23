# FinDrop: Travel Expense Management Product Page — v2

---

## Section 1: Hero

**Component:** `sdc.byte_theme.hero-billboard`

| Prop              | Value                                                        |
| ----------------- | ------------------------------------------------------------ |
| `height`          | `full`                                                       |
| `flex_position`   | `bottom-left`                                                |
| `overlay_opacity` | `40%`                                                        |
| `object_position` | `bottom`                                                     |
| `media`           | Custom illustration from the Travel media library. Stylized globe with illuminated network lines. |
| `overlap_navbar`  | `true`                                                       |

**`hero_slot`** → `sdc.byte_theme.group` containing:

Group override: `flex_gap`: `lg` (large spacing).

| Child   | Component | Key Props                                                    |
| ------- | --------- | ------------------------------------------------------------ |
| H1      | `heading` | `heading_text`: "Integrated Business Travel Management", `level`: 1, `text_size`: `heading-responsive-7xl`, `text_color`: `inverted`, `align`: `left` |
| Subhead | `text`    | "Book flights, hotels, and rental cars through FinDrop. Enforce travel policies at booking, capture receipts automatically, and reimburse in any currency — all from one platform.", `text_color`: `inverted`, `text_size`: `text-lg` |
| CTA     | `button`  | `label`: "Download Travel Guide", `variant`: `primary`, `size`: `large`, `icon`: `download` |

**Media note:** Hero image sourced from the Travel media library illustrations. Look for assets tagged with the Travel product launch.

**Image direction:** Custom illustration. A stylized globe with illuminated network lines connecting travel destinations. Dark background with warm/gold accent lighting. Globe positioned toward the bottom of the frame to work with `object_position: bottom` and content overlaid at `bottom-left`.

> **Demo note:** This hero deliberately leads with features (what the product does) rather than buyer outcomes (what the buyer gets). When the Sales Pitch Deck is attached to the CCC, the AI should identify this as the primary improvement opportunity and propose outcome-first copy drawn from the deck's positioning statements.

---

## Section 2: Benefit Overview Cards

**Component:** `sdc.byte_theme.section`

| Prop                  | Value         |
| --------------------- | ------------- |
| `width`               | `100%`        |
| `columns`             | `25-25-25-25` |
| `mobile_columns`      | `1`           |
| `margin_block_start`  | `128`         |
| `margin_block_end`    | `128`         |
| `padding_block_start` | `0`           |
| `padding_block_end`   | `0`           |
| `section_header`      | `true`        |
| `section_footer`      | `false`       |

**`header_slot`** → `sdc.byte_theme.heading`

- `heading_text`: "Travel expenses, handled from booking to close"
- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → 4× `sdc.byte_theme.card-icon`

All cards share these props:

| Prop               | Value         |
| ------------------ | ------------- |
| `background_color` | `muted`       |
| `border_radius`    | `large`       |
| `icon_size`        | `extra-large` |
| `icon_align`       | `center`      |
| `text_align`       | `center`      |

| Card | `text` (heading)                     | `description`                                                | `icon`                                                       |
| ---- | ------------------------------------ | ------------------------------------------------------------ | ------------------------------------------------------------ |
| 1    | "Book anywhere, stay in policy"      | "Employees book through preferred platforms with DropPay. Policies are enforced at booking, not three weeks later." | Pick an appropriate Phosphor icon (e.g., `shield`, `shield-check`) |
| 2    | "Trip-specific virtual cards"        | "Every trip gets its own card with a pre-set limit. When the trip ends, the card freezes automatically." | Pick an appropriate Phosphor icon (e.g., `credit-card`)      |
| 3    | "Receipts captured on the go"        | "Snap a photo or forward an email. FinDrop matches receipts to transactions automatically. No shoebox after the trip." | Pick an appropriate Phosphor icon (e.g., `receipt`, `camera`) |
| 4    | "Reimburse employees anywhere, fast" | "Reimburse in local currencies with exchange rates locked at the time of transaction. No manual conversion." | Pick an appropriate Phosphor icon (e.g., `currency-circle-dollar`, `hand-coins`) |

---

## Section 3: Feature Deep-Dive — Policy Enforcement

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
| `section_header`      | `false` |
| `section_footer`      | `false` |

**`main_slot`** → `sdc.byte_theme.image` (left, direct in grid slot) + `sdc.byte_theme.group` (text content, right)

**Image (left, no group wrapper):**

`sdc.byte_theme.image` — Custom illustration. A booking flow showing a hotel selection screen with a policy check overlay. One option flagged as over-limit, another marked compliant. Set `radius`: `large`.

**Group (text content, right):**

| Prop             | Value    |
| ---------------- | -------- |
| `flex_direction` | `column` |
| `flex_gap`       | `md`     |
| `items_align`    | `start`  |
| `flex_align`     | `center` |
| `radius`         | `sm`     |
| `padding`        | `sm`     |
| `background`     | — (none) |

Contains:

| Child   | Component | Key Props                                                    |
| ------- | --------- | ------------------------------------------------------------ |
| Eyebrow | `text`    | "POLICY ENFORCEMENT", `text_size`: `text-sm`, `text_color`: `default` |
| H2      | `heading` | `heading_text`: "Enforce policies at booking, not after the trip", `level`: 2, `text_size`: `heading-responsive-4xl`, `text_color`: `default`, `align`: `left` |
| Body    | `text`    | (see copy below), `text_size`: `normal`, `text_color`: `default` |

**Body copy:**
"When an employee books through a DropPay-connected platform, FinDrop checks the booking against your travel policy in real time. Hotel rate exceeds the per-night cap? Flagged before it's confirmed. Policies are configurable by destination, department, seniority, or trip purpose, so a sales team closing a deal has different allowances than an engineering team at a conference."

**Image direction:** Custom illustration. A booking flow showing a hotel selection screen with a policy check overlay. One option is flagged as over-limit, another is marked as compliant.

---

## Section 4: Feature Deep-Dive — Virtual Cards

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
| `section_header`      | `false` |
| `section_footer`      | `false` |

**`main_slot`** → `sdc.byte_theme.group` (text content, left) + `sdc.byte_theme.image` (right, direct in grid slot)

**Group (text content, left):**

| Prop             | Value    |
| ---------------- | -------- |
| `flex_direction` | `column` |
| `flex_gap`       | `md`     |
| `items_align`    | `start`  |
| `flex_align`     | `center` |
| `radius`         | `sm`     |
| `padding`        | `sm`     |
| `background`     | — (none) |

Contains:

| Child   | Component | Key Props                                                    |
| ------- | --------- | ------------------------------------------------------------ |
| Eyebrow | `text`    | "VIRTUAL CARDS", `text_size`: `text-sm`, `text_color`: `default` |
| H2      | `heading` | `heading_text`: "One card per trip. Zero reconciliation headaches.", `level`: 2, `text_size`: `heading-responsive-4xl`, `text_color`: `default`, `align`: `left` |
| Body    | `text`    | (see copy below), `text_size`: `normal`, `text_color`: `default` |
| Link    | `button`  | `label`: "Virtual credit cards", `variant`: `secondary`, `size`: `medium`, `icon`: `arrow-right` |

**Body copy:**
"FinDrop issues trip-specific virtual cards with pre-set spending limits. Every charge is automatically tagged to the right trip. When the trip is over, the card freezes. No lingering authorizations, no mystery charges weeks later. Reconciliation is straightforward because every charge on the card belongs to one trip."

**Image (right, no group wrapper):**

`sdc.byte_theme.image` — Custom illustration. A virtual card labeled with a trip name and destination, showing a spending limit bar and a list of tagged transactions. Set `radius`: `large`.

**Image direction:** Custom illustration. A virtual card labeled with a trip name and destination, showing a spending limit bar and a list of tagged transactions.

---

## Section 5: Feature Deep-Dive — Copilot

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
| `section_header`      | `false` |
| `section_footer`      | `false` |

**`main_slot`** → `sdc.byte_theme.image` (left, direct in grid slot) + `sdc.byte_theme.group` (text content, right)

**Image (left, no group wrapper):**

`sdc.byte_theme.image` — Custom illustration. FinDrop Copilot mobile chat interface showing a traveler asking about a hotel rate in Tokyo, with Copilot responding with the per-night cap and a "book within policy" button. Set `radius`: `large`.

**Group (text content, right):**

| Prop             | Value    |
| ---------------- | -------- |
| `flex_direction` | `column` |
| `flex_gap`       | `md`     |
| `items_align`    | `start`  |
| `flex_align`     | `center` |
| `radius`         | `sm`     |
| `padding`        | `sm`     |
| `background`     | — (none) |

Contains:

| Child   | Component | Key Props                                                    |
| ------- | --------- | ------------------------------------------------------------ |
| Eyebrow | `text`    | "COPILOT", `text_size`: `text-sm`, `text_color`: `default`   |
| H2      | `heading` | `heading_text`: "Your travelers' policy questions, answered instantly", `level`: 2, `text_size`: `heading-responsive-4xl`, `text_color`: `default`, `align`: `left` |
| Body    | `text`    | (see copy below), `text_size`: `normal`, `text_color`: `default` |

**Body copy:**
"FinDrop Copilot handles the busywork so employees don't have to. \"What's our hotel budget for Chicago?\" \"Can I upgrade to business class?\" Copilot answers in natural language based on your actual policies, not a help article from 2019. Especially valuable for international travel, where per diems and rules vary by destination."

**Image direction:** Custom illustration. FinDrop Copilot mobile chat interface showing a traveler asking about a hotel rate, with Copilot responding with the specific per-night cap and a "book within policy" action.

---

## Section 6: Testimonials

**Component:** `sdc.byte_theme.section`

| Prop                  | Value   |
| --------------------- | ------- |
| `width`               | `100%`  |
| `columns`             | `50-50` |
| `mobile_columns`      | `1`     |
| `margin_block_start`  | `128`   |
| `margin_block_end`    | `128`   |
| `padding_block_start` | `64`    |
| `padding_block_end`   | `64`    |
| `background_color`    | `muted` |
| `section_header`      | `false` |
| `section_footer`      | `false` |

**`main_slot`** → 2× `sdc.byte_theme.card-testimonial`

Both cards share: `align`: `center`, `style`: `default`

| Testimonial | `text`                                                       | `cite_name`      | `cite_text`                                  | `media`                              |
| ----------- | ------------------------------------------------------------ | ---------------- | -------------------------------------------- | ------------------------------------ |
| 1           | "We used to find out about out-of-policy bookings when the expense report showed up, weeks after the trip. With FinDrop, the policy is enforced at booking. My team reviews exceptions, not every transaction." | "Kathryn Smith"  | "Director of Travel Operations, Ridgeline"   | Headshot — select from media library |
| 2           | "Our team travels across 14 countries. Before FinDrop, international reimbursements took two to three weeks and someone had to manually convert currencies. Now it's automatic. Employees get paid in their local currency within days." | "Nolan Ericsson" | "VP of Global Operations, Westmark Dynamics" | Headshot — select from media library |

> **Note:** These testimonials are NOT in the approved Customer References in Key Facts. They appear to be new quotes for the travel launch. Recommend adding to CCC as approved references before publication.

---

## Section 7: Platform Features Grid

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

- `heading_text`: "Learn How We Make Travel Expense Management Easy"
- `level`: 2, `text_size`: `heading-responsive-5xl`, `text_color`: `default`, `align`: `center`

**`main_slot`** → 6× `sdc.byte_theme.group` (each containing a `heading` + `text`)

All groups share these props:

| Prop             | Value    |
| ---------------- | -------- |
| `flex_direction` | `column` |
| `flex_gap`       | `md`     |
| `items_align`    | `start`  |
| `flex_align`     | `center` |
| `radius`         | `sm`     |
| `padding`        | `sm`     |
| `background`     | — (none) |

Each group contains a `heading` and a `text`:

- **Heading:** `level`: 3, `text_size`: `heading-responsive-3xl`, `text_color`: `default`, `align`: `left`
- **Text:** `text_size`: `text-lg`, `text_color`: `default`

| #    | Heading (H3)                                | Body                                                         |
| ---- | ------------------------------------------- | ------------------------------------------------------------ |
| 1    | "Booking flexibility across platforms"      | "FinDrop works with DropPay-connected booking platforms, so employees can keep using familiar tools. Travel policies are enforced at the point of booking — no matter which connected platform they choose." |
| 2    | "Real-time policy enforcement"              | "Travel rules apply automatically during booking and throughout the trip. Hotel caps, flight class limits, meal per diems, and destination-specific requirements are checked instantly, with out-of-policy spend flagged before anything is confirmed." |
| 3    | "Compatibility with existing booking tools" | "FinDrop layers on top of your current travel booking workflow through DropPay integrations. You don't need to replace tools — FinDrop adds policy controls, receipt capture, and automated expense reporting." |
| 4    | "International travel readiness"            | "Global trips are supported with destination-based policies, local currency reimbursements, and per diem management. Exchange rates lock at the time of purchase to avoid reimbursement discrepancies." |
| 5    | "Automatic trip card closure after travel"  | "Trip-specific virtual cards automatically freeze when the trip end date passes. Leftover authorizations are declined, and charges remain tagged and coded to the trip for clean closeout." |
| 6    | "Unified reporting across FinDrop products" | "Travel spend rolls into the same SmartBudgets™ dashboards and reporting used for virtual cards and expense management. Everything shows up in one view, without a separate travel system to reconcile." |

---

## Section 8: Platform Product Cards

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

**`main_slot`** → 3× `sdc.byte_theme.card`

All cards share: `style`: `framed`, `orientation`: `vertical`, `level`: 3, `background`: `default`, `is_text_centered`: `false`

| Card | `heading_text`         | `text`                                                       | `url`                     | `media`                                        |
| ---- | ---------------------- | ------------------------------------------------------------ | ------------------------- | ---------------------------------------------- |
| 1    | "Virtual credit cards" | "Create virtual cards in seconds with custom spending limits for every vendor, project, or subscription. Built-in controls at the card level." | `/products/virtual-cards` | Custom illustration from product media library |
| 2    | "Expense management"   | "Automatic receipt capture, policy enforcement, and GL coding. Expense reports do themselves." | `/products/expense`       | Custom illustration from product media library |
| 3    | "Integrations"         | "Two-way sync with QuickBooks, NetSuite, and 50+ ERPs. Travel data flows into the same SmartBudgets™ dashboards as all other FinDrop spend." | `/integrations`           | Custom illustration from product media library |

---

## Section 9: Final CTA

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

**`main_slot`** → `sdc.byte_theme.group` (text content, left) + `sdc.byte_theme.image` directly in grid slot (right, no group wrapper)

**Group (text content, left):**

| Prop             | Value    |
| ---------------- | -------- |
| `flex_direction` | `column` |
| `flex_gap`       | `lg`     |
| `items_align`    | `start`  |
| `flex_align`     | `center` |
| `radius`         | `sm`     |
| `padding`        | `sm`     |
| `background`     | — (none) |

Contains:

| Child | Component | Key Props                                                    |
| ----- | --------- | ------------------------------------------------------------ |
| H2    | `heading` | `heading_text`: "Take control of travel spend before the next trip", `level`: 2, `text_size`: `heading-responsive-4xl`, `text_color`: `default`, `align`: `left` |
| Body  | `text`    | "See how FinDrop automates travel expense management, from booking to reimbursement, in our latest whitepaper.", `text_size`: `text-lg`, `text_color`: `default` |
| CTA   | `button`  | `label`: "Download the whitepaper", `variant`: `primary`, `size`: `medium`, `icon`: `download`, `mobile_width`: `false`, `icon_first`: `false` |

**Right column:** `sdc.byte_theme.image` placed directly in grid slot (no group wrapper). Custom illustration from the Travel media library — travel/finance imagery conveying the platform story.

---

## Section 10: Legal Disclaimer

**Component:** `sdc.byte_theme.text` (standalone in content region)

| Prop         | Value                                                        |
| ------------ | ------------------------------------------------------------ |
| `text`       | "FinDrop is a financial technology company, not a bank. Banking services provided by Copperbell National Bank, N.A., Member FDIC." |
| `text_size`  | `text-xs`                                                    |
| `text_color` | `default`                                                    |

