# Test Case 0.1: CCC Items Display Correctly in the Interface

## Feature Under Test
CCC interface - item listing, metadata display, sub-context visibility

## CCC Context Loaded
All configured FinDrop items (display only - no AI action)

## Setup / Before State

- Jordan opens the Context Control Center from the Drupal admin UI
- FinDrop's CCC is pre-configured with the following items:
  - Brand Guidelines (3 sub-contexts, 2 boundary exclusions)
  - Content Strategy: Product Pages (scoped to Canvas Pages)
  - Content Strategy: Landing Pages (3 sub-contexts: Top/Middle/Bottom of Funnel)
  - Content Strategy: Articles (2 sub-contexts)
  - Key Value Propositions
  - Sales Pitch Deck v7
  - Metrics / Analytics KPIs (2 sub-contexts with External Context)
  - Personas & Ideal Customer Profiles (3 sub-contexts)
- No Canvas page is open

![Before State](before.png)

## User Input

Jordan navigates to the CCC and visually inspects the item listing.

## Expected Behavior

- Each item row shows: name, use case tag(s), target scope, and sub-context count
- Brand Guidelines shows use cases "Writing Words" and "Reviews" with target "Global" and "3 sub-items"
- Content Strategy: Product Pages shows use case "Editing Canvas Blocks" with target "Canvas Pages" and "3 sub-items"
- Content Strategy: Articles is visible in the list but clearly distinct from Product Pages
- Metrics / Analytics KPIs shows "External Context" designation on its sub-items
- Boundaries column shows "2 Exclusions" for Brand Guidelines
- Sales Pitch Deck shows an attachment indicator (PPTX) and "1 Exclusion"

![After State](after.png)

## What This Tests

- CCC interface correctly displays all configured context items with metadata
- Sub-context counts are accurate
- Use case tags and target scopes render correctly
- External Context items are visually distinguishable
- Boundary/exclusion counts are surfaced
