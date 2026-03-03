# Test Case 3.2: AEO Schema & Final Checks

**DEMO SCENARIO: Part 3.6 — AEO Schema & Final Checks**

## Feature Under Test
AI generates structured data for AI search tools (Google AI summaries, ChatGPT, etc.) and performs accessibility checks

## CCC Context Loaded
SEO/AEO Guidelines

## Setup / Before State

- The page is nearly ready to publish
- Jordan wants the page to show up in AI search tools
- The page has an FAQ section (accordion) and product content

![Before State](before.png)

## User Input

> "Create an AEO schema for this page."

## Expected AI Behavior

- **Canvas changes:**
  - AI generates a Schema.org JSON-LD file
  - Schema is populated into a field on the right-hand side of the editor

- **AI response:**
  > "Done. I've generated FAQPage and Product schema based on the page content. It's in the structured data field."

## Optional: Accessibility Check

> "Check accessibility on the page."

- **AI checks heading levels and responds:**
  > "Heading structure looks good — all levels nest correctly. H1 > H2 > H3 throughout."

## After State / Desired Outcome

- FAQPage schema generated from accordion content
- Product schema generated from page content
- Schema appears in the structured data field in the editor
- (Optional) Accessibility check confirms heading structure is valid
- Page is ready to publish

![After State](after.png)

## What This Tests

- AI can generate Schema.org structured data for AEO (Answer Engine Optimization)
- Multiple schema types generated for one page (FAQPage + Product)
- Schema is placed in the appropriate field in the editor UI
- AI can perform basic accessibility checks (heading levels)
- Final pre-publish checks are supported
