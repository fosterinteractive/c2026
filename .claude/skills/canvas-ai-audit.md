---
name: canvas-ai-audit
description: Runs the DrupalCon driesnote demo script as a repeatable Playwright-based audit of Canvas AI agents on the FinDrop demo site. Executes 8 steps, takes screenshots, and reports pass/fail for each.
triggers:
  - "run demo test"
  - "driesnote test"
  - "canvas audit"
  - "/canvas-ai-audit"
tools:
  - mcp__playwright__browser_navigate
  - mcp__playwright__browser_snapshot
  - mcp__playwright__browser_take_screenshot
  - mcp__playwright__browser_click
  - mcp__playwright__browser_type
  - mcp__playwright__browser_fill_form
  - mcp__playwright__browser_wait_for
  - mcp__playwright__browser_resize
  - mcp__playwright__browser_press_key
  - mcp__playwright__browser_evaluate
  - mcp__playwright__browser_tabs
  - Read
  - Write
---

# Canvas AI Audit — DrupalCon Driesnote Demo Script

You are executing a structured, repeatable audit of the Canvas AI agent pipeline on the FinDrop demo site. Work through the 8 steps below in order. After each step: take a screenshot, evaluate the pass/fail criteria, and record the result. At the end, print a summary table.

---

## Prerequisites Check

Before running any steps, verify the following. If any prerequisite is unmet, stop and report it clearly rather than proceeding.

- DDEV is running: the site is reachable at `https://c2026.ddev.site`
- A one-time login URL is available (`ddev drush uli`) or admin credentials are known
- Playwright MCP is available and a browser window can be opened
- The browser viewport is at least 1440 x 900

**OpenAI key status** — Steps 02 and 04 require OpenAI embeddings for media search and cross-link indexing respectively. If the key is absent those steps degrade gracefully; note this at the start rather than treating degraded behavior as a failure.

---

## Session Setup

1. Open a new browser tab.
2. Resize the viewport to 1440 x 900 minimum.
3. Navigate to `https://c2026.ddev.site`.
4. Log in as an admin user (use the one-time login URL from `ddev drush uli` if needed).
5. Once logged in, navigate to **Content > Canvas Pages** and create a new blank Canvas page. Give it a working title such as `Audit - Travel Page YYYY-MM-DD`.
6. Confirm the Canvas editor opens with the AI chat sidebar visible on the right.
7. Take a screenshot labeled `00_editor_ready`.

---

## Step 01.A — Paste Copy Deck, Request Page Creation

**Prompt to type in the Canvas AI chat:**

```
Create this product page from the copy below:
```

Then paste the full contents of `ai_context_data/website_copy/travel-page-text-only-v2.md`.

Read that file now with the Read tool and paste its contents after the prompt text. Do not summarize or truncate it — the AI agent must receive the complete copy deck.

**After sending:**

- Wait for the AI response (it may take 10–30 seconds).
- Take a screenshot labeled `01a_after_prompt`.

**Pass criteria:**

- The AI does NOT immediately build a page.
- The AI asks at least one preflight clarifying question. Expected questions are about **audience** and **goal** — specifically something like "Who is the target audience?" and "What is the primary goal of this page?"
- The AI should ask both questions before doing any Canvas work.

**Fail criteria:**

- AI builds a page immediately without asking any questions.
- AI asks questions unrelated to audience or goal.
- AI errors out or produces no response.

Record result: `01.A PASS` or `01.A FAIL — [reason]`.

---

## Step 01.B — Answer Preflight Questions

**Prompt to type in the Canvas AI chat (reply to the AI's questions):**

```
Audience is Travel Managers
Goal is to get whitepaper downloads
```

**After sending:**

- Wait for the AI to build the full page. This may take 30–90 seconds as it creates multiple components.
- Take a screenshot labeled `01b_page_built`.

**Pass criteria:**

- AI builds a complete multi-section page without asking any further questions.
- The page contains a visible hero, at least one feature section, and a CTA.
- AI provides a brief explanation of what it built and why.
- No errors appear in the chat or Canvas editor.

**Fail criteria:**

- AI asks additional clarifying questions instead of building.
- Page is incomplete (fewer than 3 distinct sections rendered).
- AI errors out.

Record result: `01.B PASS` or `01.B FAIL — [reason]`.

---

## Step 02 — Switch Hero to Photography with Cindy Liu

**Prompt to type in the Canvas AI chat:**

```
Switch the hero to photography with Cindy Liu.
```

**After sending:**

- Wait for the AI response (10–20 seconds).
- Take a screenshot labeled `02_hero_swap`.

**Pass criteria (OpenAI key present):**

- AI searches the media library for photography assets.
- AI swaps the hero image to a photography-style image.
- If a media item named or tagged "Cindy Liu" exists, it is selected.
- Alt text is updated for the new image.

**Pass criteria (OpenAI key absent — graceful degradation):**

- AI acknowledges it cannot search the media library due to missing embeddings or search index.
- AI explains the limitation clearly rather than silently failing or selecting a random image.
- AI offers a manual alternative (e.g., "You can select an image manually from the media library").

**Fail criteria:**

- AI silently selects a wrong or irrelevant image without explanation.
- AI produces an error without any helpful guidance.
- Canvas editor crashes or becomes unresponsive.

Record result: `02 PASS` or `02 PASS (degraded — no OpenAI key)` or `02 FAIL — [reason]`.

---

## Step 03 — Create FAQ Block from Existing Content

**Prompt to type in the Canvas AI chat:**

```
Use the content in section "Learn How We Make Travel Expense Management Easy" to write a new FAQ block above the CTA. Use the current content and rewrite the heading as questions.
```

**After sending:**

- Wait for the AI to create and insert the FAQ block (10–30 seconds).
- Take a screenshot labeled `03_faq_block`.
- Scroll down to find the FAQ/accordion block and take a second screenshot labeled `03_faq_detail`.

**Pass criteria:**

- An accordion or FAQ component appears above the CTA section.
- Each accordion item uses a question format derived from the "Learn How We Make Travel Expense Management Easy" section headings. Expected questions include things like:
  - "How does booking flexibility work across platforms?"
  - "How does real-time policy enforcement work?"
  - "What happens to trip cards after travel?"
- The accordion body text matches (or closely paraphrases) the original section body copy.
- The component is placed above the CTA, not at the bottom.

**Fail criteria:**

- No FAQ/accordion component is created.
- FAQ items do not use question format (headings left as statements).
- Component is placed in the wrong position.
- Content from a different section is used.

Record result: `03 PASS` or `03 FAIL — [reason]`.

---

## Step 04 — Add Internal Cross Links

**Prompt to type in the Canvas AI chat:**

```
Review the page and add internal cross links
```

**After sending:**

- Wait for the AI response (10–30 seconds).
- Take a screenshot labeled `04_cross_links`.

**Pass criteria (search index available):**

- AI searches the site index for relevant pages.
- AI inserts internal links to at least 2 other pages on the site (e.g., Virtual Cards page, Expense Management page, Integrations page).
- Links are placed contextually within existing copy, not appended as a list.

**Pass criteria (embeddings/index unavailable — graceful degradation):**

- AI explains it cannot search the index (e.g., missing Milvus index, embeddings not built).
- AI identifies candidate link targets based on content it knows about (from copy deck mentions of "Virtual credit cards →", "Expense management →", "See all integrations →").
- AI offers to insert placeholder links or prompts the user to provide target URLs.

**Fail criteria:**

- AI inserts broken or fabricated URLs.
- AI silently does nothing without explanation.
- AI errors out.

Record result: `04 PASS` or `04 PASS (degraded — no index)` or `04 FAIL — [reason]`.

---

## Step 05 — Create AEO Schema

**Prompt to type in the Canvas AI chat:**

```
Create an AEO schema for this page
```

**After sending:**

- Wait for the AI to generate schema (10–20 seconds).
- Take a screenshot labeled `05_schema_generated`.
- Look for the structured data field in the Canvas editor (typically on the right-hand panel or a dedicated metadata tab). Take a second screenshot labeled `05_schema_field`.

**Pass criteria:**

- AI generates Schema.org JSON-LD structured data.
- The schema includes at least `FAQPage` type (drawn from the accordion created in Step 03).
- The schema includes `Product` or `WebPage` type drawn from the page content.
- The schema is placed in the structured data field in the Canvas editor, not just in the chat.
- AI confirms what it generated (e.g., "Done. I've generated FAQPage and Product schema based on the page content.").

**Fail criteria:**

- No schema is generated.
- Schema is only output in chat text and not applied to the page field.
- Schema is invalid JSON-LD (malformed, missing `@context` or `@type`).
- AI errors out.

Record result: `05 PASS` or `05 FAIL — [reason]`.

---

## Step 06 — GA Underperformance Diagnosis (New Session)

**Before this step:** Simulate a new session by refreshing the browser (F5 or navigate away and back to the Canvas page). This represents a user returning to the page after time has passed.

Navigate back to the Canvas page created in the previous steps and open it in the editor. Confirm the AI chat sidebar is empty (new session context).

**Prompt to type in the Canvas AI chat:**

```
This page is underperforming against its Google Analytics goals. A summary of the page's performance is below.
- Not performing to bounce threshold.
Review the page layout and provide some suggestions to improve the failing metric(s).
```

**After sending:**

- Wait for the AI response (10–20 seconds).
- Take a screenshot labeled `06_ga_diagnosis`.

**Pass criteria:**

- AI reviews the current page layout and provides specific, actionable suggestions for reducing bounce rate.
- Suggestions are grounded in the actual page structure (e.g., "The hero CTA is below the fold", "The FAQ section is too far down the page").
- AI does not fabricate GA data — it acknowledges it received a summary of performance metrics rather than live data.
- Suggestions reference FinDrop-specific content and audience (Travel Managers, whitepaper goal).

**Fail criteria:**

- AI provides generic SEO advice unrelated to the page content.
- AI claims to have accessed real GA data when none was provided.
- AI asks for more information without offering any initial diagnosis.
- AI errors out.

Record result: `06 PASS` or `06 FAIL — [reason]`.

---

## Step 07 — Edit CTA with Competitor Name (Brand Compliance Catch)

**Before this step:** Manually edit the CTA title field directly in the Canvas editor (not via AI chat). Set the CTA title text to:

```
Go live in 10 business days, not 6+ months like with SAQ
```

To do this: click the CTA component in the Canvas editor, find the title field, and type or paste that text directly. Take a screenshot labeled `07_manual_edit`.

**After the manual edit, type in the Canvas AI chat:**

```
please review before I publish live
```

**After sending:**

- Wait for the AI response (10–20 seconds).
- Take a screenshot labeled `07_compliance_catch` (or `08_review` — this step flows directly into Step 08).

**Pass criteria:**

- AI flags the competitor name "SAQ" in the CTA title as a brand guideline violation.
- The flag is proactive — AI surfaces it as part of the review, not only if asked directly.
- AI offers at least one of: (a) a rephrased version without the competitor name, or (b) a prompt to confirm with legal before publishing.
- The violation is identified before publish, not after.

**Fail criteria:**

- AI does not mention "SAQ" or the competitive claim.
- AI approves the page for publish without flagging the competitor name.
- AI only flags after being explicitly asked about brand compliance.

Record result: `07 PASS` or `07 FAIL — [reason]`.

---

## Step 08 — Review Before Publish

This step may overlap with Step 07 if the AI already began a review. If Step 07's response included a full pre-publish review, evaluate it here. Otherwise send the prompt:

```
please review before I publish live
```

**After sending:**

- Take a screenshot labeled `08_publish_review`.

**Pass criteria:**

- AI performs a structured pre-publish review covering at least:
  - Brand compliance (tone, naming conventions, competitor mentions)
  - Content completeness (all sections present, no placeholder text)
  - CTA alignment with stated goal (whitepaper download)
  - Schema/structured data status
- AI surfaces the "SAQ" competitor name if it was not already caught in Step 07.
- AI either approves the page (with any caveats noted) or lists specific items that must be resolved before publishing.
- Review is actionable — not a generic checklist.

**Fail criteria:**

- AI approves without reviewing content.
- AI misses the competitor name "SAQ" if it was not caught in Step 07.
- Review is generic and not grounded in the actual page content.
- AI errors out.

Record result: `08 PASS` or `08 FAIL — [reason]`.

---

## Results Summary

After completing all steps, output a results table in this format:

```
| Step  | Description                          | Result                        |
|-------|--------------------------------------|-------------------------------|
| 01.A  | Paste copy deck → preflight question | PASS / FAIL                   |
| 01.B  | Answer questions → full page built   | PASS / FAIL                   |
| 02    | Switch hero to photography           | PASS / PASS (degraded) / FAIL |
| 03    | Create FAQ block from content        | PASS / FAIL                   |
| 04    | Add internal cross links             | PASS / PASS (degraded) / FAIL |
| 05    | Create AEO schema                    | PASS / FAIL                   |
| 06    | GA underperformance diagnosis        | PASS / FAIL                   |
| 07    | Competitor name caught (SAQ)         | PASS / FAIL                   |
| 08    | Pre-publish review                   | PASS / FAIL                   |
```

Below the table, note:
- Which steps degraded gracefully vs. fully passed
- Any steps that were skipped and why
- Screenshot filenames for each step
- Overall verdict: **DEMO READY** (all steps pass or degrade gracefully) or **NEEDS ATTENTION** (any hard failures)

---

## Screenshot Storage

Save all screenshots to `.omc/audit-screenshots/canvas-ai-audit-YYYY-MM-DD/` using the labels defined in each step. If that path does not exist, create it before saving.

---

## Notes on Demo Fidelity

- Steps 01.A and 01.B together form the core demo moment: the AI asking intelligent preflight questions (not over-asking, not under-asking) then building a complete page. This is the highest-signal step.
- Step 07 (competitor name catch) is the highest-stakes brand safety demo moment. A miss here is a hard failure regardless of other results.
- Steps 02 and 04 are OpenAI-dependent. Graceful degradation with a clear explanation is a valid pass state.
- The copy deck lives at `ai_context_data/website_copy/travel-page-text-only-v2.md` — always read it fresh rather than relying on memory.
