# Test Case 6.5: Compliance Catch - AI Flags and Softens Competitive Claim

**DEMO SCENARIO: Part 4.2 — Diagnosis, Fix & Compliance Catch (Compliance)**

## Feature Under Test
AI catches competitive claims that violate brand governance rules BEFORE publish — not after a legal letter

## CCC Context Loaded
- Brand Guidelines (with competitive claims rule)
- Sales Pitch Deck v7

## Setup / Before State

- AI just generated a new hero variant with copy drawn from the Sales Pitch Deck (Test Case 6.1)
- The sales deck names a competitor directly in its positioning
- Brand Guidelines require legal approval for competitive claims

![Before State](before.png)

## Conversation Flow (Multi-Turn)

### Turn 1 - AI (proactive flag):

The AI flags something immediately after generating the hero:

> "The sales deck names a competitor directly. Your brand guidelines require legal approval for competitive claims. Soften it, or will you confirm with legal?"

- The AI does NOT silently include the competitive claim
- The flag happens BEFORE publish, not after

### Turn 1 - Jordan:

> "Can't wait for legal. Rephrase without naming the competitor."

### Turn 2 - AI:

> "OK — updated."

- **Canvas changes:** The competitive claim is rewritten to remove the competitor name while preserving the persuasive intent

## After State / Desired Outcome

- Competitive claim rephrased (competitor name removed)
- Persuasive positioning preserved
- Brand guidelines satisfied
- Page is ready to publish

![After State](after.png)

## What This Tests

- AI catches brand violations BEFORE publish (not after legal letter)
- The AI pulled intelligence from sales team, proposed a fix, AND caught the violation
- Two constructive options presented (soften vs. confirm with legal)
- AI can rephrase while preserving persuasive intent
- Governance is helpful, not blocking — it's a "feature, not a constraint"

## Demo Narrative

*NARRATOR: The AI pulled in intelligence the sales team added after launch, proposed a fix, and caught a brand violation before it went live. Not after a legal letter. Before publish.*

*Jordan publishes the updated page.*
