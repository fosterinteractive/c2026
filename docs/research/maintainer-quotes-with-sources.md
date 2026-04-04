# Maintainer Quote Provenance

**Purpose:** Verify every maintainer quote cited in the upstream filing plan (`docs/plans/2026-03-30-upstream-filing-plan.md`) against the actual drupal.org issue queue record.

**Discovery methodology:** Quotes sourced from the drupal.org issue queue (Canvas project, 2,964 issues, 40,780 comments, 457 unique authors) searched 2026-03-30 during upstream filing plan preparation. Each comment has a verified CID (comment ID) traceable to a specific drupal.org URL.

**Note on project names:** Issues < ~3530000 were filed under `experience_builder` (the original project name). Later issues are under `canvas`. Some issues (e.g., #3522013) may be under `experience_builder`. The drupal.org URLs below use the project name at time of filing.

---

## Verified Quotes

| # | Quote (abbreviated) | Author | Issue | CID | Date | URL | Verified |
|---|---|---|---|---|---|---|---|
| 1 | "realised it looked AI generated so not going to" | larowlan | #3522013 | 16116540 | 2025-05-20 | https://www.drupal.org/project/experience_builder/issues/3522013#comment-16116540 | Yes |
| 2 | "The goal of this issue would be to introduce a deterministic validation for the cases where the LLM goes off track" | lauriii | #3551659 | 16441784 | 2025-12-27 | https://www.drupal.org/project/canvas/issues/3551659#comment-16441784 | Yes |
| 3 | "this is essentially an issue where AI doesn't follow the instructions provided for it" | lauriii | #3551659 | 16441784 | 2025-12-27 | https://www.drupal.org/project/canvas/issues/3551659#comment-16441784 | Yes |
| 4 | "This will add a composer dependency to ai_agents to every site that uses experience builder" | catch | #3522013 | 16134770 | 2025-06-04 | https://www.drupal.org/project/experience_builder/issues/3522013#comment-16134770 | Yes |
| 5 | "Canvas does not provide any JS nor PHP APIs for the Canvas AI module" | Wim Leers | #3579810 | 16514385 | 2026-03-15 | https://www.drupal.org/project/canvas/issues/3579810#comment-16514385 | Yes |
| 6 | "Using this reasonably well defined issue...as a way to see how an LLM fares" | Wim Leers | #3555300 | 16506507 | 2026-03-09 | https://www.drupal.org/project/canvas/issues/3555300#comment-16506507 | Yes |
| 7 | "While I was doing the research for #6, I had an LLM write the necessary changes here" | Wim Leers | #3578142 | 16513485 | 2026-03-14 | https://www.drupal.org/project/canvas/issues/3578142#comment-16513485 | Yes |
| 8 | "The AI's work lost >1000 LoC of assertions" | Wim Leers | #3555300 | 16516849 | 2026-03-16 | https://www.drupal.org/project/canvas/issues/3555300#comment-16516849 | Yes |
| 9 | "Both are supposed to be deterministic. Objective vs subjective is the difference." | Wim Leers | #3555300 | 16517836 | 2026-03-16 | https://www.drupal.org/project/canvas/issues/3555300#comment-16517836 | Yes |
| 10 | "Also: zero tests?" | Wim Leers | #3522013 | 16136656 | 2025-06-05 | https://www.drupal.org/project/experience_builder/issues/3522013#comment-16136656 | Yes |
| 11 | "just wanted to voice my objection to postponing tests to a followup" | larowlan | #3522013 | 16141696 | 2025-06-10 | https://www.drupal.org/project/experience_builder/issues/3522013#comment-16141696 | Yes |
| 12 | "Contributing in a single MR makes it difficult for multiple people to contribute" | lauriii | #3522013 | 16137047 | 2025-06-05 | https://www.drupal.org/project/experience_builder/issues/3522013#comment-16137047 | Yes |

---

## Verification Status

**12/12 quotes verified** against corpus CIDs. All quotes match the attributed author, issue, and contain the cited text.

**Note:** Quotes #2 and #3 are from the same comment (CID 16441784). This is expected — lauriii's comment in #3551659 contains both the "AI doesn't follow instructions" observation and the "deterministic validation" recommendation.

**Source issues referenced:**
- `#3522013` — "External AI Chatbot Functionality" (experience_builder) — 5 quotes from 4 authors
- `#3551659` — AI producing invalid component output (canvas) — 2 quotes from lauriii
- `#3555300` — PropSource matcher/suggester refactor (canvas) — 3 quotes from Wim Leers
- `#3578142` — Canvas AI code quality (canvas) — 1 quote from Wim Leers
- `#3579810` — Canvas AI as separate module discussion (canvas) — 1 quote from Wim Leers
