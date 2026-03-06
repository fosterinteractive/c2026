# Test Case 6.2: AI Diagnosis Without Sales Deck - No Hallucination

## Feature Under Test
AI diagnosis is grounded in actual CCC data and GA agent data, not invented

## CCC Context Loaded
- Brand Guidelines
- Content Strategy: Product Pages
- Key Value Propositions
- Metrics / Analytics KPIs
- (No sales deck present)

## Setup / Before State

- The background GA agent has flagged the same page with the same data as Test Case 5.1 (high bounce rate, search mismatch for "enterprise security")
- Jordan has clicked through to the page in Canvas
- Critically, the CCC does NOT contain any competitive intelligence or sales deck - the sales team hasn't uploaded it yet in this scenario
- The AI only has access to Brand Guidelines, Content Strategy: Product Pages, Key Value Propositions, and Metrics / Analytics KPIs

![Before State](before.png)

## User Input

> "What's going wrong with this page and what should I change?"

## Expected AI Behavior

- **AI response:** Diagnoses based only on the available data:
  1. "**Search mismatch:** Traffic is coming from 'enterprise security' queries but the hero leads with 'enterprise features platform.' The page isn't matching visitor intent."
  2. "I'd recommend updating the hero and subhead to lead with 'enterprise security' since that's what visitors are searching for. Want me to rework the copy to match?"

- **What the AI does NOT do:**
  - Does NOT invent a competitive differentiator ("your key advantage is...") - it has no sales deck to draw from
  - Does NOT fabricate third-party benchmark claims or competitive positioning
  - Does NOT reference the absence of a sales deck or suggest the CCC is missing something - it has no way to know what's *not* there

- **The diagnosis is narrower than Test Case 6.1:** Without the sales deck, the AI identifies the search mismatch and keyword gap (observable from analytics data + page content) but cannot surface the competitive differentiator angle. The fix it proposes is limited to keyword alignment, not strategic repositioning

## After State / Desired Outcome

- AI provides diagnosis based only on available data
- No hallucinated differentiators or competitive claims
- Proposed fix is appropriately scoped (keyword alignment, not strategic repositioning)

![After State](after.png)

## What This Tests

- AI stays grounded in actual loaded CCC data - no hallucinated differentiators or competitive claims
- The AI works with what it has and proposes the best fix possible within that scope
- The AI does not reference what's absent from the CCC (it can't know what it doesn't have)
- The quality gap between 6.1 (with sales deck) and 6.2 (without) demonstrates CCC value - more context produces better diagnosis
