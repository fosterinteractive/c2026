# Test Case 5.1: Background Agent Detects Underperformance and Notifies Jordan

**DEMO SCENARIO: Part 4.1 — The Setup & Alert**

## Feature Under Test
Background agent detects underperforming content and provides actionable suggestions based on CCC analysis

## CCC Context Loaded
- Metrics / Analytics KPIs (benchmarks)
- Sales Pitch Deck v7 (updated since page was published)
- Brand Guidelines
- Content Strategy: Product Pages

## Setup / Before State

- Jordan launched the FinDrop Travel page a couple of weeks ago
- Life moved on — other projects, other deadlines
- After launch, Jordan marked the Travel page as important in the agent interface and set performance thresholds:
  - Bounce rate threshold
  - Engaged sessions benchmark
  - Whitepaper downloads target

![Before State](before.png)

## Trigger (Automated - Two Weeks Later)

- Background agent flags a problem: "Underperforming content detected"
- Agent interface shows:
  - Current bounce rate vs. threshold
  - Engaged sessions below benchmark
  - Whitepaper downloads falling short

## Expected AI Behavior

The agent hasn't just flagged the problem — it's analyzed the page against the current CCC and prepared specific recommendations:

**AI (in agent dashboard):**
> "Three suggestions:
> 1. Lead with buyer outcomes instead of features in the hero.
> 2. Update competitive positioning using the revised sales deck.
> 3. Strengthen the whitepaper CTA with a specific benefit statement.
>
> Which would you like to start with?"

**Jordan:** "Start with 1 and 2."

Jordan clicks "Work on it" to open the page in Canvas AI. The Canvas sidebar opens with a pre-loaded prompt summarizing the performance issues and selected suggestions.

## After State / Desired Outcome

- Jordan is on the underperforming page in Canvas
- AI chat sidebar is pre-loaded with:
  - Performance data (bounce rate, sessions, downloads)
  - Selected suggestions (1 and 2)
- All relevant CCC context is loaded (including Sales Pitch Deck v7)
- Jordan is ready to proceed with the fixes

![After State](after.png)

## What This Tests

- Background agent correctly identifies underperforming content
- Agent provides specific, actionable suggestions (not just "page is failing")
- Suggestions reference CCC content (sales deck, brand guidelines)
- "Work on it" flow seamlessly opens Canvas with pre-loaded context
- Performance data is available without manual copy-paste
- Agent interface allows Jordan to select which suggestions to address
