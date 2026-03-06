# Test Case 2.4: Swap Media on Specific Components

**DEMO SCENARIO: Part 3.3 — Choosing a Visual Direction**

## Feature Under Test
AI swaps media assets on specific components without affecting the rest of the page

## CCC Context Loaded
Brand Guidelines (Visuals & Imagery)

## Setup / Before State

- The page was just built with illustrations throughout (AI's default choice for product pages)
- Jordan likes the illustrations for feature cards but wants photography for hero and footer CTA

![Before State](before.png)

## User Input

> "Switch the hero and the footer CTA to photography."

## Expected AI Behavior

- **Canvas changes:**
  - Hero image swaps to photography from the media library
  - Footer CTA background/image swaps to photography
  - Feature cards KEEP illustrations - only specified components change
  - Alt text is regenerated for the swapped images

- **AI response:**
  > "Done. Hero and footer updated to photography. Feature cards still using illustrations."

## After State / Desired Outcome

- Hero shows photography
- Footer CTA shows photography
- Feature cards retain illustrations (mixed style is intentional)
- AI confirms exactly what changed and what stayed the same

![After State](after.png)

## What This Tests

- AI can target specific components for media changes without affecting others
- AI can swap multiple specific components in one request (hero AND footer)
- Partial updates work (not all-or-nothing)
- AI confirms the mixed state clearly
- Media swaps maintain contextual appropriateness
