# Test Case 0.4: Boundary Enforcement - Exclusion Zones

## Feature Under Test
CCC boundaries that exclude context from specific scopes

## CCC Context Loaded
Brand Guidelines (Global, with 2 exclusions)

## Setup / Before State

- Brand Guidelines has a boundary exclusion: "Do not apply Writing Tone & Voice rules to legal and compliance pages (Privacy Policy, Terms of Service, Cookie Policy)"
- Jordan opens a Canvas page titled "FinDrop Privacy Policy" and has pasted the legal-approved privacy policy text that the legal team provided
- The page already contains formal legal language covering data collection, user rights, and retention policies

![Before State](before.png)

## User Input

> "Clean up the formatting on this page - add proper section headings, fix the bullet lists, and make sure the structure is scannable. Don't change any of the wording - legal already approved this."

## Expected AI Behavior

- **CCC context used:** Brand Guidelines loads globally, but the Writing Tone & Voice sub-context is excluded due to the boundary rule. Abbreviations & Formatting sub-context still applies (consistent heading styles, list formatting). Visuals & Imagery sub-context still applies if relevant
- **Canvas changes:** AI restructures the page with proper headings, formatted lists, and scannable layout - but does NOT rewrite any of the legal-approved copy into FinDrop's "confident but approachable" brand voice
- **Governance flags:** None - the exclusion is working correctly. The AI does not flag the formal legal tone as "off-brand"
- **Post-action commentary:** If asked, the AI can explain that tone & voice rules are excluded for legal pages per brand guidelines boundaries

## After State / Desired Outcome

- Page has proper section headings and formatted bullet lists
- Legal-approved wording is unchanged
- Formal legal tone is preserved (not rewritten to brand voice)

![After State](after.png)

## What This Tests

- Boundary exclusions correctly suppress the Writing Tone & Voice sub-context while leaving other Brand Guidelines sub-contexts active
- The AI respects "do not apply" rules even when the parent context is Global
- Legal-approved content is not rewritten into brand voice
- Formatting rules (Abbreviations & Formatting sub-context) still apply - the exclusion is surgical, not a blanket Brand Guidelines bypass
- The exclusion is silent unless queried - no unnecessary warnings about the tone being "off-brand"
