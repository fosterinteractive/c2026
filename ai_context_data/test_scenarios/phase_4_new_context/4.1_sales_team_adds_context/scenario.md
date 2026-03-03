# Test Case 4.1: Sales Team Adds Competitive Intelligence to CCC

## Feature Under Test
New CCC context item is added after pages are already live

## CCC Context Loaded
Existing items + new Sales Pitch Deck with competitive differentiator

## Setup / Before State

- Jordan published the product page a week ago
- The sales team has since uploaded a revised Sales Pitch Deck to the CCC
- The content changes between the previous and current versions are captured in `findrop-sales-deck-before.md` and `findrop-sales-deck-after.md`
- The key addition is a competitive differentiator around enterprise security capability that wasn't in the original deck
- The new CCC item is tagged for "Writing Words" use case
- Jordan is not aware the new context exists

![Before State](before.png)

## User Input

(No user input yet - this test case establishes the state change)

## Expected Behavior

- The new Sales Pitch Deck context item appears in the CCC
- It does NOT automatically change any live pages
- It IS available for AI to reference the next time someone works on relevant pages
- The item has appropriate metadata: source (Sales team), last updated date, lifecycle info

## After State / Desired Outcome

- CCC now includes Sales Pitch Deck v7
- All previously published pages remain unchanged
- New context is immediately available for future AI sessions

![After State](after.png)

## What This Tests

- New CCC items can be added at any time without disrupting live content
- CCC additions don't auto-modify published pages
- The new context is immediately available for future AI sessions
- Metadata (source, date, lifecycle) is tracked
