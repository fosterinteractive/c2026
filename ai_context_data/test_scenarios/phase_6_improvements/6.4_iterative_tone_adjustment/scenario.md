# Test Case 6.4: Iterative Tone Adjustment on the Fix

## Feature Under Test
Multi-turn tone refinement during a fix

## CCC Context Loaded
Brand Guidelines (Writing Tone & Voice)

## Setup / Before State

- AI generated the updated hero (Test Case 6.3) but the tone is too aggressive

![Before State](before.png)

## User Input

> "Too aggressive. Soften it - confident, not pushy."

## Expected AI Behavior

- Updates the hero copy. New version is softer but still data-backed
- "Enterprise security" keyword remains in the subhead - the tone changes, not the keyword strategy
- "Toned it down - still leads with security, but reads as confident authority rather than hard sell. Keywords preserved."

## After State / Desired Outcome

- Tone is softer, more confident authority
- "Enterprise security" keyword preserved
- Security focus maintained
- Previous fixes not undone

![After State](after.png)

## What This Tests

- Tone adjustment preserves keyword alignment from the previous turn
- Brand guidelines anchor the tone consistently
- AI remembers the full conversation context (diagnosis -> fix -> keyword alignment -> tone refinement)
- Incremental changes don't undo previous fixes
