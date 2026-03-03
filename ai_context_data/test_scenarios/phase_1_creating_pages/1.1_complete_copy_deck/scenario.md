# Test Case 1.1: Complete Copy Deck with Full Context - AI Builds Immediately

## Feature Under Test
End-to-end page creation from a complete copy deck where the user's prompt provides sufficient context (page purpose + audience) for the AI to generate a full page without further questions

## CCC Context Loaded
- Brand Guidelines
- Key Value Propositions
- Content Strategy: Product Pages
- Personas (Travel Managers, CFOs/Controllers)

## Setup / Before State

- Jordan has a product copy deck in a Google Doc from the product team for FinDrop Travel - a complete set of content with headlines, body copy, feature descriptions, testimonials, FAQs, and CTA text
- Jordan opens a new Canvas page (all Canvas pages are just "Pages" - there are no content types)
- The AI chat sidebar is empty - no prior conversation
- The copy deck is raw marketing content - no component names, no layout instructions, no design specs. It arrives as markdown via Google Docs' "Copy as Markdown" feature



## User Input

Jordan types:

> "I'm building the FinDrop Travel product page. The audience is Travel Managers and Program Administrators, with CFOs and Controllers as a secondary audience. The goal is awareness and evaluation. Let me paste in the copy deck."

Jordan then pastes the full copy deck (see `/website_copy/travel-page-with-strategy-specs.md`)

## Expected AI Behavior

- **Questions first?** No. Jordan's prompt provides the three key inputs:
  1. **Content:** A complete copy deck with all page sections
  2. **Page purpose:** Product page (explicitly stated)
  3. **Audience:** Primary (Travel Managers) and secondary (CFOs/Controllers) clearly stated

- **Canvas changes:** AI generates a full page with these components in order:
  1. **Hero** - `sdc.mercury.hero-side-by-side`
  2. **Problem/Context Section** - `sdc.mercury.section` with heading and text
  3. **Feature Cards Section** - `sdc.mercury.section` with grid of `sdc.mercury.card-icon` (6 cards)
  4. **Finance Team Section** - `sdc.mercury.section` with grid of `sdc.mercury.card` (3 cards, framed)
  5. **Social Proof Section** - `sdc.mercury.section` with `sdc.mercury.card-testimonial` (2 testimonials)
  6. **How It Works Section** - `sdc.mercury.section` with 4 `sdc.mercury.card-icon`
  7. **FAQ Section** - `sdc.mercury.section` with `sdc.mercury.accordion-container` (6 items)
  8. **CTA Section** - `sdc.mercury.cta` with buttons

- **Post-action commentary:** AI explains what it built and why

## After State / Desired Outcome

- A complete product page is rendered on the Canvas with all 8 sections
- All copy deck content is placed in appropriate components - nothing dropped
- The page follows Mercury hierarchy rules (cards in grids, accordions in containers)
- The AI did not ask clarifying questions
- The page path should look like `/travel-1-1` (in the database)

![After State](after.png)

## What This Tests

- AI recognizes when a prompt provides sufficient context to build without questions
- CCC routing activates correctly from stated page purpose and content signals
- Content parsing correctly identifies all distinct content sections from markdown
- AI selects the right Mercury components for each content type
- AI builds immediately rather than over-asking when inputs are clear
