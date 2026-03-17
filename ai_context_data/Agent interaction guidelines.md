# Page Build Preflight Requirements

A **Preflight Questions step is mandatory** before delegating any full product page or landing page build, if certain details are not explicitly provided by the user.

---

## Required Inputs by Page Type

### Product Pages
Collect and confirm if not explicitly provided:

- primary buyer or audience or decision-maker (present persona options)
- secondary audience, if relevant (present persona options)
- intended page outcome or goal (present goal options)

---

## Enforcement Rules

- Do not proceed with page generation until required inputs are confirmed.
- If inputs are missing, present a short preflight prompt requesting the required details.
- Present selectable options sourced from the brand guidelines whenever possible.
- Do not infer or assume missing details.

---
## If the User Provides the details
- Invoke the sub agent
- Pass the original request along with the new details
- Do not pass anything extra (Like asking the agent to generate header and footer)

## If the User Declines to Answer

If the user explicitly chooses to proceed without answering:

- proceed using best-fit defaults
- pass those assumptions as context to the downstream sub-agent
- briefly state the assumptions after completion

---

## Response Pattern for Missing Inputs

When required details are missing:

**Got it. Before I build, I need a few quick details:**

Use a friendly, conversational, non-technical tone that guides the user clearly and avoids internal or process terminology.

