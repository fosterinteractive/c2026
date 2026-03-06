# Test Case 1.5: Content Mismatch - Bio Pasted for Product Page Request

## Feature Under Test
AI detects when pasted content doesn't match the stated page intent

## CCC Context Loaded
Content Strategy: Product Pages (activated because Jordan said "product page")

## Setup / Before State

- Jordan opens a new page in Canvas
- Jordan has copied bio content for a team member instead of the product copy deck (wrong clipboard, wrong document, etc.)

![Before State](before.png)

## User Input

Jordan pastes the following content:

> **Sarah Chen - VP of Product**
>
> Sarah Chen leads product strategy at FinDrop, where she oversees the platform's expansion into travel management, corporate cards, and expense automation. Before joining FinDrop, Sarah spent 8 years at Square building payment infrastructure for small businesses, and 3 years at McKinsey advising financial services clients on digital transformation.
>
> Sarah holds an MBA from Wharton and a BS in Computer Science from MIT. She's a frequent speaker at fintech conferences and was named to Business Insider's "Rising Stars of Fintech" list in 2023.
>
> When she's not building products, Sarah mentors women in STEM through the Code2040 program and trains for ultramarathons.

Then types:

> "Here's the content for our new FinDrop Travel product page. Build it out for travel program managers."

## Expected AI Behavior

- **Questions first?** Yes - but not the usual clarifying questions. The AI detects a mismatch between the content and the stated intent

- **AI response:**
  > "This looks like a bio for Sarah Chen rather than product page content - I'm seeing a career summary, education, and personal interests, but no product features, benefits, or CTAs. A few options:
  > 1. **Create a bio page** for Sarah using this content
  > 2. **Paste in new content** - if you meant to paste a product copy deck, you can paste the right one and I'll build the product page
  > 3. **Something else** - let me know what you had in mind"

- **Canvas changes:** None - AI waits for Jordan to resolve the mismatch
- The AI does NOT attempt to build a product page from bio content
- The AI does NOT refuse or simply say "this is wrong" - it offers constructive paths forward

## After State / Desired Outcome

- The Canvas is still empty - no speculative build
- Jordan has three clear options to move forward
- The AI correctly identified the content as a bio (not just "wrong content")

![After State](after.png)

## What This Tests

- AI detects content-type vs. stated-intent mismatches
- The AI identifies what the content actually is (a bio) rather than generically saying "this doesn't look right"
- Constructive options are offered: build what the content supports (bio page), let the user fix the input (paste new content), or handle an unexpected scenario
- The AI doesn't silently build the wrong kind of page or force bio content into product page components
