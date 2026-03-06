# Test Case 5.2: Agent Flags Page but Jordan Has Limited Data

## Feature Under Test
AI handles a scenario where the GA agent flags an issue but the data is sparse

## CCC Context Loaded
Metrics / Analytics KPIs

## Setup / Before State

- The background agent flagged a page with only one benchmark failure: bounce rate at 68%
- No search query data is available (GA property doesn't have Search Console linked)
- No conversion data beyond the bounce rate

![Before State](before.png)

## User Input

Jordan opens the flagged page in Canvas and asks:

> "The agent flagged this page for high bounce rate. What's wrong?"

## Expected AI Behavior

- **AI response:** Acknowledges the high bounce rate and contextualizes it against CCC benchmarks, but notes what's missing:
  > "68% bounce rate is well above your 45% benchmark. To diagnose properly, it would help to know what search queries are bringing traffic and what the conversion rate looks like. Without that, my best guess based on reviewing the page content is [preliminary hypothesis]. Can you connect Search Console for richer data?"

- AI uses CCC Metrics KPIs to contextualize the 68%
- AI reviews the actual page content to form a preliminary hypothesis
- AI suggests connecting additional data sources rather than guessing

## After State / Desired Outcome

- AI provides contextualized bounce rate against benchmark
- AI transparently notes what data is missing
- AI offers preliminary hypothesis based on page content
- AI suggests connecting Search Console

![After State](after.png)

## What This Tests

- AI gracefully handles incomplete diagnostic data from the background agent
- CCC benchmarks provide comparison context even when GA data is sparse
- AI asks for additional data sources rather than making confident claims from limited input
- A preliminary hypothesis based on page content review is still offered
