# Test Case 2.3: Restructure Content - Move Sections to FAQ

**DEMO SCENARIO: Part 3.4 — Iterative Refinement**

## Feature Under Test
AI restructures page content by generating new components while preserving originals for comparison

## CCC Context Loaded
Content Strategy: Product Pages

## Setup / Before State

- The page has a long content block in the middle with detailed information
- Jordan wants to convert this into a FAQ block at the bottom

![Before State](before.png)

## User Input

Jordan selects a longer content block in the middle of the page, then types:

> "Too much copy in this section. Turn this into a FAQ block at the bottom."

## Expected AI Behavior

- **Canvas changes:**
  - **NOTE: Canvas AI can't delete content** - it generates a new FAQ component at the bottom
  - A new `sdc.mercury.section` is created at the bottom with an `sdc.mercury.accordion-container` containing `sdc.mercury.accordion` items
  - The selected content is restructured as questions and answers
  - **The original block stays in place** for Jordan to compare

- **AI response:** Creates the FAQ section. Does NOT delete the original content.

- **Post-generation:** Jordan reviews the new FAQ section, likes it, and deletes the original content manually.

## After State / Desired Outcome

- New FAQ section exists at the bottom with content reformatted as Q&A pairs
- Original content block remains until Jordan manually deletes it
- Jordan can compare both versions before deciding
- Accordion items are inside an accordion-container (hierarchy respected)

![After State](after.png)

## What This Tests

- AI generates new components rather than destructively editing
- User retains control over deletion decisions
- Content is reformatted appropriately (prose -> FAQ Q&A format)
- Mercury component hierarchy is maintained
- The "generate, compare, keep the winner" workflow is supported
