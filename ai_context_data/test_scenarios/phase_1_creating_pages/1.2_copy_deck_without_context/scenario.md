# Test Case 1.2: Same Copy Deck WITHOUT Context - AI Asks, User Answers, AI Builds

**DEMO SCENARIO: Part 3.1-3.2 — Start from Content & AI Asks, Then Builds**

## Feature Under Test
AI correctly identifies missing context, asks targeted questions, then builds once the user answers

## CCC Context Loaded
- Brand Guidelines
- Key Value Propositions
- Content Strategy: Product Pages
- Personas (activated after audience is confirmed)

## Setup / Before State

- Jordan opens a Google Doc with the FinDrop Travel product copy
- Jordan selects all and copies as markdown
- Jordan switches to Drupal, opens Canvas AI chat
- Jordan pastes the markdown text with a minimal prompt

![Before State](before.png)

## Conversation Flow (Multi-Turn)

### Turn 1 - Jordan:

> "Here's our copy deck for the new FinDrop Travel product page."

Jordan pastes the copy deck from Google Docs (markdown format).

### Turn 1 - AI:

- **Questions first?** Yes. The AI recognized this as a product page and loaded the matching strategy from the CCC. But it needs two things from Jordan.

- **AI thought chain visible in sidebar:**
  - Loaded: Content Strategy: Product Pages
  - Loaded: Brand Guidelines > Visuals & Imagery
  - Loaded: Personas & Ideal Customer Profiles
  - Loaded: Key Value Propositions

- **AI response:** "Who's the target audience? I see personas for Controllers, CFOs, Travel Managers, and Office Managers. And what's the primary goal?"

- **Canvas changes:** None yet. AI waits for answers before building

![Turn 1 AI Response](turn_1_ai_response.png)

### Turn 2 - Jordan:

> "Travel Managers. And the goal is whitepaper downloads for the new product."

### Turn 2 - AI:

- **CCC context used:** Persona sub-context for Travel Managers activates
- **Canvas changes:** AI generates a full page using FinDrop's component library:
  - Hero
  - Value prop section
  - Feature cards (with illustrations - matching technical content)
  - Testimonial area
  - CTA (whitepaper download as primary)

- **AI follow-up (referencing visual identity rule from CCC):**
  > "Done. I went with illustrations - they match the technical content. Your visual identity guidelines also include photography. Want me to swap?"

## After State / Desired Outcome

- A complete product page is rendered with all sections
- Illustrations used throughout (default for product pages per CCC)
- AI proactively offers to swap image style
- The 2-turn flow feels natural

![After State](after.png)

## What This Tests

- AI distinguishes between "enough context to build" and "need more info"
- Questions reference actual CCC personas by name
- AI shows thought chain of loaded CCC context
- AI selects image style based on CCC guidelines and content type
- AI proactively offers alternative after building (not before)
- Whitepaper download goal correctly shapes CTA structure
