# Test Case 6.3: AI Generates Updated Hero with Keyword Alignment

## Feature Under Test
AI generates new content drawn from CCC knowledge to fix the diagnosed issues, including natural keyword integration

## CCC Context Loaded
- Sales Pitch Deck v7
- Brand Guidelines
- Content Strategy: Product Pages

## Setup / Before State

- AI has diagnosed the search mismatch, missing differentiator, and keyword gap (Test Case 6.1)
- Jordan has accepted the diagnosis

![Before State](before.png)

## User Input

> "Yes, fix all three. Lead with the security differentiator. Make sure 'enterprise security' is in the subhead - that's what people are searching for. Outcomes first, not features."

## Expected AI Behavior

- **Canvas changes:** AI generates a new hero variant with:
  - Headline focused on security outcomes (matching search intent)
  - Subhead incorporating "enterprise security" naturally - not keyword-stuffed
  - Key differentiator from Sales Pitch Deck woven into the copy
  - Existing CTA buttons preserved (demo request)

- **CCC context used:**
  - Sales Pitch Deck v7 provides the differentiator messaging
  - Brand Guidelines ensure the tone is right
  - Content Strategy: Product Pages keeps it structured for the right audience

- **Post-action commentary:** "New hero leads with enterprise security - matching what visitors are searching for. The differentiator from your sales deck is in the subhead. 'Enterprise security' reads naturally, not stuffed."

## After State / Desired Outcome

- Hero leads with security outcomes
- "Enterprise security" appears naturally in subhead
- Differentiator from sales deck is incorporated
- Brand voice is maintained

![After State](after.png)

## What This Tests

- AI generates new copy from CCC context (sales deck), not from scratch
- Content addresses all three diagnosed issues (search mismatch + missing differentiator + keyword alignment)
- Keyword integration is natural, not forced
- Brand voice is maintained
- The fix is traceable to specific CCC sources
