# Test Case 2.5: Hero Reframe - Lead with Benefits, Not Features

## Feature Under Test
AI restructures hero copy from feature-led to benefit-led, using persona context already established in the conversation

## CCC Context Loaded
- Brand Guidelines (Writing Tone & Voice)
- Personas (Travel Managers)
- Key Value Propositions

## Setup / Before State

- The page was built in Phase 1 with Travel Managers as the primary audience - that persona context is already active in the conversation
- The current hero leads with a feature: "All-in-One Travel Booking with Built-in Policy Enforcement"
- The subhead lists more features: "Flights, hotels, and rental cars in one app. Policy applied at time of booking. Automatic reconciliation."

![Before State](before.png)

## User Input

> "You've led with features in the hero. Lead with the benefit to the buyer instead."

## Expected AI Behavior

- **Canvas changes:** Hero heading and body text are rewritten to lead with the outcome that matters to Travel Managers, not the product capability:
  - Before: "All-in-One Travel Booking with Built-in Policy Enforcement"
  - After: "Business Travel Your Employees Love and Your Finance Team Trusts"
  - Before subhead: "Flights, hotels, and rental cars in one app. Policy applied at time of booking. Automatic reconciliation."
  - After subhead: "90%+ adoption in the first month. Zero expense reports. Complete spend visibility. That's what happens when travel booking actually works."

- **CCC context used:** The AI already knows the audience is Travel Managers from the Phase 1 conversation - it uses the Personas sub-context to identify what Travel Managers care about (adoption rates, employee satisfaction, reducing administrative burden) rather than generic benefit language. Key Value Propositions provide the proof points (90%+ adoption, zero expense reports)

- **Post-action commentary:** "Reframed - the hero now leads with what Travel Managers actually care about: adoption and no more expense reports. The features are still on the page in the card sections below."

## After State / Desired Outcome

- Hero leads with benefits (outcomes), not features (capabilities)
- Benefits are persona-specific, not generic "save time and money"
- Features aren't deleted, just repositioned (still present in feature cards)

![After State](after.png)

## What This Tests

- AI can distinguish between features (what the product does) and benefits (what the buyer gets)
- Persona context from earlier in the conversation is used to pick the right benefits - not generic language
- The AI doesn't need to re-ask about audience - it uses context already established in the Phase 1 flow
- Features aren't deleted, just repositioned
- Key Value Propositions supply concrete proof points for the benefit-led copy
