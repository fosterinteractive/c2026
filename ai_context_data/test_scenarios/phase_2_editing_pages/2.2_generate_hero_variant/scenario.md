# Test Case 2.2: Generate a New Hero Variant

## Feature Under Test
AI generates a new variant of an existing section for comparison

## CCC Context Loaded
- Brand Guidelines
- Content Strategy: Product Pages

## Setup / Before State

- The page has been generated with a `hero-side-by-side` component
- Jordan wants a bolder, higher-impact hero

![Before State](before.png)

## User Input

> "Generate a new hero variant - high impact, bolder, main value prop front and center."

## Expected AI Behavior

- **Canvas changes:** AI generates a NEW hero component (likely `sdc.mercury.hero-billboard`) below or adjacent to the existing one. The new variant has:
  - A punchier, more prominent headline
  - The core value proposition ("90%+ adoption") as the lead message
  - Larger, bolder visual treatment
- **The original hero remains** - Jordan can compare both and choose
- **Post-action commentary:** "Here's a Billboard Hero variant - bolder, with the adoption stat front and center. The original Side-by-Side is still there. Want to keep one and remove the other?"

## After State / Desired Outcome

- Two hero components visible on the Canvas - the original and the new variant
- Jordan can visually compare and delete the one they don't want
- The new variant is genuinely different in approach, not just a minor tweak

![After State](after.png)

## What This Tests

- AI generates new variants rather than destructively editing existing components
- The "generate, compare, keep the winner" workflow is supported
- AI can shift between hero types (side-by-side -> billboard) when asked for "bolder"
- The original component is preserved for comparison
