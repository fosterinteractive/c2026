# Test Case 0.3: Sub-Context Activation - Landing Page Funnel Stage Routing

## Feature Under Test
Sub-context selection within a parent context item (funnel stages apply to PPC landing pages)

## CCC Context Loaded
Content Strategy: Landing Pages -> Bottom of Funnel sub-context

## Setup / Before State

- Jordan is on a Canvas page titled "Book a Demo - LinkedIn Campaign Landing Page"
- Content Strategy: Landing Pages has three sub-contexts: Top of Funnel, Middle of Funnel, Bottom of Funnel
- No content exists on the canvas yet beyond the title

![Before State](before.png)

## User Input

> "Create a landing page for the Book a Demo landing page based on our LinkedIn Ad. Follow our standard bottom funnel landing page structure. The goal is demo requests from Finance Teams and Financial Controllers.
>
> Here's the LinkedIn ad copy driving traffic:
>
> Finance teams are drowning in expense reports The average company wastes $12,000 per employee annually on manual expense processing. See how virtual corporate cards can eliminate 90% of your expense admin
>
> Headline: Stop Drowning in Manual Expense Processing
> Description: Live walkthrough with instant ROI calculation
> Button: Request Demo"

## Expected AI Behavior

- **Questions first?** No - Jordan provided the funnel stage explicitly ("bottom funnel"), the goal ("demo requests"), the audience ("Finance Teams and Financial Controllers"), and the source content (LinkedIn ad copy). All key inputs are present
- **CCC context used:** The AI activates the Bottom of Funnel sub-context, which prioritizes conversion-focused copy, short-form persuasion, demo CTAs, and proof points. The Personas sub-context for Controllers & Finance Managers also loads
- **Canvas changes:** AI generates a landing page that:
  - Maintains message match with the LinkedIn ad (the visitor clicked "Stop Drowning in Manual Expense Processing" - the landing page must echo that framing, not introduce new messaging)
  - Leads with the $12,000 stat and expense processing pain point from the ad
  - Features a prominent demo request form or CTA as the primary conversion action
  - Includes the "instant ROI calculation" promise from the ad description
  - Uses landing page structure (not full product page structure - no extensive FAQ, no multi-section feature exploration)
- **AI response:** Acknowledges the bottom-funnel approach and the LinkedIn ad source. May briefly note how it's maintaining message match between the ad and the landing page
- **What stays unloaded:** Top of Funnel (awareness/education) and Middle of Funnel (evaluation/comparison) sub-contexts do not activate

## After State / Desired Outcome

- A focused, conversion-optimized landing page appears on the Canvas
- The messaging clearly connects to the LinkedIn ad that drove the visitor
- The page is structurally different from a product page - shorter, more focused, single conversion goal
- Key Value Propositions and Brand Guidelines are reflected but subordinate to the conversion goal

![After State](after.png)

## What This Tests

- Sub-context activation based on explicit user intent ("bottom funnel")
- Bottom of Funnel rules shape the page toward conversion (not education or exploration)
- Top and Middle of Funnel rules remain dormant
- AI maintains message match between the ad source and the landing page
- AI uses the pasted ad copy as source material alongside CCC context
- Persona sub-context (Finance Teams / Controllers) activates from the stated audience
- Funnel stage routing works for landing pages (identified from title and user intent, not a content type)
