# Test Case 3.1: SEO - Cross-Linking with Semantic Search

**DEMO SCENARIO: Part 3.5 — Cross-Linking with Semantic Search**

## Feature Under Test
AI uses semantic search (vector search) to find and add cross-links to related content

## CCC Context Loaded
- SEO/AEO Guidelines (if configured)
- Site content index (vector embeddings)

## Setup / Before State

- Jordan's product page is nearly complete
- The benefit cards reference FinDrop's other products — Virtual Credit Cards and Expense Management
- These should link to the relevant product pages on the site

![Before State](before.png)

## User Input

Jordan selects the benefit cards, then types:

> "Add links on the cards to related pages"

## Expected AI Behavior

- **Process:** AI runs a semantic search across the site's content using vector search (not just keyword matching)

- **AI response:**
  > "Found 3 relevant pages across the site. I've added links to the matching cards."

- **Canvas changes:**
  - Links are added to the benefit cards pointing to relevant pages
  - Virtual Credit Cards card links to /products/virtual-cards
  - Expense Management card links to /products/expense-management
  - Links are contextually appropriate (not just keyword matches)

## After State / Desired Outcome

- Benefit cards now have links to related product pages
- Links were found via semantic search, not just keyword matching
- Cross-linking improves site navigation and SEO

![After State](after.png)

## What This Tests

- AI uses semantic/vector search to find relevant content
- Cross-links are added automatically to selected components
- Semantic matching finds conceptually related content (not just exact keyword matches)
- Links are contextually appropriate to each card's content
