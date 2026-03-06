# Test Case 6.1: AI Diagnoses Performance Issues from GA Data and CCC Context

**DEMO SCENARIO: Part 4.2 — Diagnosis, Fix & Compliance Catch (Diagnosis)**

## Feature Under Test
AI cross-references performance data with CCC context and discovers new content added since page was published

## CCC Context Loaded
- Key Value Propositions > Sales Pitch Deck v7 (updated 1 day ago)
- Brand Guidelines

## Setup / Before State

- Jordan clicked "Work on it" from the agent dashboard (Test Case 5.1)
- Canvas AI sidebar shows pre-loaded context:
  - Bounce rate data
  - Session metrics
  - Download numbers
- Jordan selected suggestions 1 and 2:
  1. Lead with buyer outcomes instead of features in the hero
  2. Update competitive positioning using the revised sales deck

![Before State](before.png)

## AI Analysis

The AI's thought chain appears in sidebar:
- Loaded: Key Value Propositions > Sales Pitch Deck v7 (updated 1 day ago)
- Loaded: Brand Guidelines

**AI discovers something important:**
> "I found a new positioning statement focused on outcomes to the buyer. Want me to update the hero and value prop to lead with this?"

**Key insight:** Since Jordan built this page, the sales team had updated the Sales Pitch Deck with new competitive positioning. That context didn't exist when the page went live. It does now.

## User Input

> "Yes. Outcomes first, not features."

## Expected AI Behavior

- **Canvas changes:** AI generates a new hero variant with updated copy drawn from the revised sales deck in the CCC
- Hero now leads with buyer outcomes instead of product features
- Value prop section updated with new competitive positioning

## After State / Desired Outcome

- New hero variant generated leading with outcomes
- Copy drawn from Sales Pitch Deck v7 (the recently updated CCC item)
- AI demonstrates cross-team knowledge connection (sales team's updates surface to marketing)

![After State](after.png)

## What This Tests

- AI cross-references performance data with CCC context
- AI discovers CCC content that was added after the page was published
- Sales team's updates automatically surface to marketing team
- "Outcomes first" instruction is followed
- Cross-team knowledge connection ("expertise multiplication") works as designed
