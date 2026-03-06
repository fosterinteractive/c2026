# Test Case 0.2: Intelligent Context Routing - Product Page Loads Product Context Only

## Feature Under Test
Selective context loading based on page content and user intent

## CCC Context Loaded
- Brand Guidelines (Global)
- Key Value Propositions (Global)
- Content Strategy: Product Pages (scoped to Canvas Pages)

## Setup / Before State

- Jordan opens a new Canvas page (all Canvas pages are the same "Page" type - there are no content types)
- Jordan sets the page title to "FinDrop Travel - Product Page" and has added a few initial elements:
  - A hero component with the heading "Business Travel Your Employees Love"
  - A section with some placeholder feature card text about booking and policy enforcement
- The CCC contains content strategy items for Product Pages, Articles, Bio Pages, and Landing Pages - each with a Purpose field that describes when it should activate
- The AI chat sidebar is open

![Before State](before.png)

## User Input

> "What context do you have loaded for this page?"

## Expected AI Behavior

- **Questions first?** No - this is an informational query
- **AI response:** Lists the active context items: Brand Guidelines (with sub-contexts), Key Value Propositions, and Content Strategy: Product Pages
- **Critically absent:** Content Strategy: Articles, Bio Page Strategy, Landing Page Strategy - these must NOT be mentioned as loaded
- **Post-action commentary:** AI may briefly explain that context was loaded based on the page's title and content (product-focused heading, feature card structure)

## After State / Desired Outcome

- AI confirms exactly which CCC items are active
- No irrelevant context items are loaded or referenced
- The response demonstrates that the CCC routing is selective, not a bulk dump

![After State](after.png)

## What This Tests

- Intelligent context routing selects the correct items based on page title and content signals - not a "content type" dropdown
- Irrelevant context items (Articles, Bio Pages, Landing Pages) stay out of scope
- Global items (Brand Guidelines, Key Value Propositions) load alongside content-specific items
- The AI can introspect and report on its active context
