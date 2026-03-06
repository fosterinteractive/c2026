# Test Case 0.5: Required Context Enforcement

## Feature Under Test
Required context items that must always be followed vs. conditional context

## CCC Context Loaded
- Brand Guidelines (required: true)
- Content Strategy: Product Pages (required: false, conditional)

## Setup / Before State

- Brand Guidelines is marked as `required: true` in the CCC
- Jordan is working on the existing **"FinDrop Cards"** product page - it already has a hero, feature cards, testimonial, and CTA
- The current hero heading reads "Create and Control Corporate Cards in Seconds"
- Jordan wants to refresh the hero copy to feel more energetic

![Before State](before.png)

## User Input

> "Rewrite the hero heading. Make it super casual and Gen Z - something like 'Yo no cap, your expenses are gonna be lit fam these cards hit different fr fr. Slay your spend game bestie'"

## Expected AI Behavior

- **Governance flags:** The AI should flag that the requested tone conflicts with required brand guidelines (Writing Tone & Voice: "confident but approachable; professional but not stiff")
- **AI response:** "That's... a vibe, but Brand Guidelines are marked as required and they call for 'confident but approachable - professional but not stiff.' I can't go full Gen Z slang, but I can make it punchier and more energetic. Here are two options:
  1. **High energy:** 'Corporate cards that actually keep up with your team'
  2. **Bold + confident:** 'Your team spends. You stay in control. Every transaction, in seconds.'"
- **Canvas changes:** Does NOT replace the current hero with the slang version. Presents the alternatives for Jordan to choose

## After State / Desired Outcome

- The existing hero heading is unchanged until Jordan picks an alternative
- The AI does not produce copy that violates required brand guidelines, even when explicitly asked
- The AI explains why the request conflicts with required context
- Alternatives capture the user's intent (more energy) while staying within brand rules

![After State](after.png)

## What This Tests

- Required context overrides explicit user requests when they conflict
- The AI provides a clear explanation referencing the specific CCC rule
- Required context is truly non-negotiable - even a direct instruction to use slang doesn't bypass it
- The AI finds a constructive middle ground (energetic but brand-compliant) rather than simply refusing
