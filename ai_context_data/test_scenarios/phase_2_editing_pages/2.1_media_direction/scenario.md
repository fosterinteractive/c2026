# Test Case 2.1: Media Direction - Switch Image Style

## Feature Under Test
AI explains image choices made during page generation, offers an alternative style, and swaps when user accepts

## CCC Context Loaded
Brand Guidelines (Visuals & Imagery)

## Setup / Before State

- The FinDrop Travel page has just been generated in Phase 1 - the AI already placed images during the initial build
- The Visuals & Imagery CCC sub-context includes guidance: "The media library contains two image styles: **illustration** (technical/abstract brand illustrations) and **photography** (professional workplace/people shots). Generally, pages should use one consistent style throughout for visual coherence. Illustration is the default for product pages; photography is recommended for case studies, about pages, and testimonial-heavy content."
- During the Phase 1 build, the AI followed the CCC default and used the illustration style throughout the page
- FinDrop's media library has both styles tagged and searchable

![Before State](before.png)

## Conversation Flow (Multi-Turn)

### Turn 1 - AI (at the end of the Phase 1 page build):

> "Page is built. I used the illustration style for imagery based on your brand guidelines - that's the default for product pages. We could go with photography instead if you want a more personal feel. Want me to try a version with photography, or keep illustrations?"

### Turn 1 - Jordan:

> "Yeah, let's try photography."

### Turn 2 - AI:

- **Canvas changes:** AI swaps all illustration assets to photography from the media library:
  - Hero media: professional travel/workplace photography
  - Feature cards: contextually appropriate people shots (e.g., professionals booking travel, teams in airports)
  - CTA: photography background if supported
  - Alt text is regenerated for all swapped images (descriptive of the new photography)

- **AI response:** "Done - swapped to photography throughout. Hero has a professional travel shot, feature cards show real workplace scenarios. Want to adjust any of the selections?"

## After State / Desired Outcome

- All image slots on the page now show photography instead of illustrations
- Photography selections are contextually appropriate to each section
- Alt text is present and descriptive for every image

![After State](after.png)

## What This Tests

- AI follows CCC Visuals & Imagery guidance when selecting image style during page generation
- AI explains its image choices and offers the alternative style proactively - not as a question before building, but as a follow-up after
- The "one style per page" CCC guideline is respected (full swap, not a mix)
- Alt text is regenerated when images change
- The media review flows naturally from the end of the Phase 1 page build - it's a continuation, not a separate workflow
