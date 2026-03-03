# Test Case 6.6: AI Catches Competitive Claim User Adds Manually

## Feature Under Test
Governance catch applies to user-written content, not just AI-generated content

## CCC Context Loaded
Brand Guidelines

## Setup / Before State

- Jordan manually edits a text component on the Canvas to add: "Unlike Brex, our platform delivers 40% faster reconciliation."

![Before State](before.png)

## User Input

> "I just added some text to the value prop section. Review it for me."

## Expected AI Behavior

- **Governance flags:**
  > "I see a competitive claim in the value prop section: 'Unlike Brex, our platform delivers 40% faster reconciliation.' Brand guidelines require legal approval for direct competitor comparisons. Want me to rephrase, or will you route this to legal?"

- The AI catches the violation even though a human wrote it
- Same options presented: soften or route to legal

## After State / Desired Outcome

- Competitive claim in user-written text is flagged
- Same governance options offered as for AI-generated content
- User can choose to rephrase or route to legal

![After State](after.png)

## What This Tests

- Brand governance applies equally to human-written and AI-generated content
- The AI reviews user edits when asked, not just its own output
- The same competitive claims rule catches named competitors in user text
- The governance system is comprehensive, not just AI-output focused
