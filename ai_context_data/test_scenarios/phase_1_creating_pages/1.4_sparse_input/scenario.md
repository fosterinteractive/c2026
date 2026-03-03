# Test Case 1.4: Sparse Input - AI Identifies Content Gaps

## Feature Under Test
Graceful handling of incomplete or sparse content input

## CCC Context Loaded
- Brand Guidelines
- Key Value Propositions
- Content Strategy: Product Pages

## Setup / Before State

- Jordan has only bullet points from the product team - not a full copy deck
- A new Canvas page is open

![Before State](before.png)

## User Input

> "Here are some notes from the product team for our Travel product page:
>
> - Books business travel in under 3 minutes
> - Policy enforcement at time of booking
> - Zero expense reports
> - 90%+ adoption rate
> - Integrates with existing accounting software
>
> The audience is travel program managers. Goal is evaluation."

## Expected AI Behavior

- **Questions first?** Yes - even though the audience and goal are stated, the content is too sparse. The AI has feature bullets but is missing:
  - Testimonials
  - FAQ content
  - CTA copy
  - A problem narrative
  - The finance-team story

- **AI response:** Acknowledges the bullet points and what's present vs. missing:
  > "I have your key features and an adoption stat - strong starting points. I'm missing a few things to build a full product page:
  > - Do you have customer quotes or testimonials?
  > - Any FAQs from prospects?
  > - A specific CTA you want (demo request, free trial, contact sales)?
  >
  > I can draft those from your value propositions if you'd like, or you can add more content."

- **CCC context used:** Key Value Propositions may fill in messaging gaps; Brand Guidelines inform tone. But the AI should be transparent about what it's generating vs. what came from the copy deck

## After State / Desired Outcome

- No page generated yet - AI waits for more input or permission to draft
- Jordan has clear understanding of what's missing
- AI offers constructive path forward (supplement from CCC or add more content)

![After State](after.png)

## What This Tests

- AI distinguishes between "has audience/goal but insufficient content" and "ready to build"
- AI identifies specific content gaps (testimonials, FAQ, CTA) rather than generically asking for "more"
- AI offers to supplement sparse input using CCC context (value props) with transparency
- The conversation remains collaborative - AI doesn't refuse, but also doesn't produce a thin page from sparse input
