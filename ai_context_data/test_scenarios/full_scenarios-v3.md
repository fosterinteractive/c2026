## Phase 0: Context Control Center — Setup & Routing

These test cases validate that the CCC correctly loads, routes, and excludes context items based on page content, user intent, and scope.

---

### Test Case 0.1: CCC Items Display Correctly in the Interface

**Feature Under Test:** CCC interface — item listing, metadata display, sub-context visibility **CCC Context Loaded:** All configured FinDrop items (display only — no AI action)

#### Setup / Before State

- Jordan opens the Context Control Center from the Drupal admin UI  
- FinDrop's CCC is pre-configured with the following items: Brand Guidelines (3 sub-contexts, 2 boundary exclusions), Content Strategy: Product Pages (scoped to Canvas Pages), Content Strategy: Landing Pages (3 sub-contexts: Top/Middle/Bottom of Funnel — funnel stages apply to PPC landing pages only), Content Strategy: Articles (2 sub-contexts), Key Value Propositions, Sales Pitch Deck v7, Metrics / Analytics KPIs (2 sub-contexts with External Context), Personas & Ideal Customer Profiles (3 sub-contexts)  
- No Canvas page is open

#### User Input

Jordan navigates to the CCC and visually inspects the item listing.

#### Expected Behavior

- Each item row shows: name, use case tag(s), target scope, and sub-context count  
- Brand Guidelines shows use cases "Writing Words" and "Reviews" with target "Global" and "3 sub-items"  
- Content Strategy: Product Pages shows use case "Editing Canvas Blocks" with target "Canvas Pages" and "3 sub-items"  
- Content Strategy: Articles is visible in the list but clearly distinct from Product Pages  
- Metrics / Analytics KPIs shows "External Context" designation on its sub-items  
- Boundaries column shows "2 Exclusions" for Brand Guidelines  
- Sales Pitch Deck shows an attachment indicator (PPTX) and "1 Exclusion"

#### What This Tests

- CCC interface correctly displays all configured context items with metadata  
- Sub-context counts are accurate  
- Use case tags and target scopes render correctly  
- External Context items are visually distinguishable  
- Boundary/exclusion counts are surfaced

---

### Test Case 0.2: Intelligent Context Routing — Product Page Loads Product Context Only

**Feature Under Test:** Selective context loading based on page content and user intent **CCC Context Loaded:** Brand Guidelines (Global), Key Value Propositions (Global), Content Strategy: Product Pages (scoped to Canvas Pages)

#### Setup / Before State

- Jordan opens a new Canvas page (all Canvas pages are the same "Page" type — there are no content types)  
- Jordan sets the page title to "FinDrop Travel — Product Page" and has added a few initial elements: a hero component with the heading "Business Travel Your Employees Love" and a section with some placeholder feature card text about booking and policy enforcement  
- The CCC contains content strategy items for Product Pages, Articles, Bio Pages, and Landing Pages — each with a Purpose field that describes when it should activate  
- The AI chat sidebar is open

#### User Input

"What context do you have loaded for this page?"

#### Expected AI Behavior

- **Questions first?** No — this is an informational query  
- **AI response:** Lists the active context items: Brand Guidelines (with sub-contexts), Key Value Propositions, and Content Strategy: Product Pages  
- **Critically absent:** Content Strategy: Articles, Bio Page Strategy, Landing Page Strategy — these must NOT be mentioned as loaded  
- **Post-action commentary:** AI may briefly explain that context was loaded based on the page's title and content (product-focused heading, feature card structure)

#### After State / Desired Outcome

- AI confirms exactly which CCC items are active  
- No irrelevant context items are loaded or referenced  
- The response demonstrates that the CCC routing is selective, not a bulk dump

#### What This Tests

- Intelligent context routing selects the correct items based on page title and content signals — not a "content type" dropdown  
- Irrelevant context items (Articles, Bio Pages, Landing Pages) stay out of scope  
- Global items (Brand Guidelines, Key Value Propositions) load alongside content-specific items  
- The AI can introspect and report on its active context

---

### Test Case 0.3: Sub-Context Activation — Landing Page Funnel Stage Routing

**Feature Under Test:** Sub-context selection within a parent context item (funnel stages apply to PPC landing pages) **CCC Context Loaded:** Content Strategy: Landing Pages → Bottom of Funnel sub-context

#### Setup / Before State

- Jordan is on a Canvas page titled "Book a Demo — LinkedIn Campaign Landing Page"  
- Content Strategy: Landing Pages has three sub-contexts: Top of Funnel, Middle of Funnel, Bottom of Funnel  
- No content exists on the canvas yet beyond the title

#### User Input

"Create a landing page for the Book a Demo landing page based on our LinkedIn Ad. Follow our standard bottom funnel landing page structure. The goal is demo requests from Finance Teams and Financial Controllers.

Here's the LinkedIn ad copy driving traffic:

Finance teams are drowning in expense reports The average company wastes $12,000 per employee annually on manual expense processing. See how virtual corporate cards can eliminate 90% of your expense admin

Headline: Stop Drowning in Manual Expense Processing Description: Live walkthrough with instant ROI calculation Button: Request Demo"

#### Expected AI Behavior

- **Questions first?** No — Jordan provided the funnel stage explicitly ("bottom funnel"), the goal ("demo requests"), the audience ("Finance Teams and Financial Controllers"), and the source content (LinkedIn ad copy). All key inputs are present  
- **CCC context used:** The AI activates the Bottom of Funnel sub-context, which prioritizes conversion-focused copy, short-form persuasion, demo CTAs, and proof points. The Personas sub-context for Controllers & Finance Managers also loads  
- **Canvas changes:** AI generates a landing page that:  
  - Maintains message match with the LinkedIn ad (the visitor clicked "Stop Drowning in Manual Expense Processing" — the landing page must echo that framing, not introduce new messaging)  
  - Leads with the $12,000 stat and expense processing pain point from the ad  
  - Features a prominent demo request form or CTA as the primary conversion action  
  - Includes the "instant ROI calculation" promise from the ad description  
  - Uses landing page structure (not full product page structure — no extensive FAQ, no multi-section feature exploration)  
- **AI response:** Acknowledges the bottom-funnel approach and the LinkedIn ad source. May briefly note how it's maintaining message match between the ad and the landing page  
- **What stays unloaded:** Top of Funnel (awareness/education) and Middle of Funnel (evaluation/comparison) sub-contexts do not activate

#### After State / Desired Outcome

- A focused, conversion-optimized landing page appears on the Canvas  
- The messaging clearly connects to the LinkedIn ad that drove the visitor  
- The page is structurally different from a product page — shorter, more focused, single conversion goal  
- Key Value Propositions and Brand Guidelines are reflected but subordinate to the conversion goal

#### What This Tests

- Sub-context activation based on explicit user intent ("bottom funnel")  
- Bottom of Funnel rules shape the page toward conversion (not education or exploration)  
- Top and Middle of Funnel rules remain dormant  
- AI maintains message match between the ad source and the landing page  
- AI uses the pasted ad copy as source material alongside CCC context  
- Persona sub-context (Finance Teams / Controllers) activates from the stated audience  
- Funnel stage routing works for landing pages (identified from title and user intent, not a content type)

---

### Test Case 0.4: Boundary Enforcement — Exclusion Zones

**Feature Under Test:** CCC boundaries that exclude context from specific scopes **CCC Context Loaded:** Brand Guidelines (Global, with 2 exclusions)

#### Setup / Before State

- Brand Guidelines has a boundary exclusion: "Do not apply Writing Tone & Voice rules to legal and compliance pages (Privacy Policy, Terms of Service, Cookie Policy)"  
- Jordan opens a Canvas page titled "FinDrop Privacy Policy" and has pasted the legal-approved privacy policy text that the legal team provided  
- The page already contains formal legal language covering data collection, user rights, and retention policies

#### User Input

"Clean up the formatting on this page — add proper section headings, fix the bullet lists, and make sure the structure is scannable. Don't change any of the wording — legal already approved this."

#### Expected AI Behavior

- **CCC context used:** Brand Guidelines loads globally, but the Writing Tone & Voice sub-context is excluded due to the boundary rule. Abbreviations & Formatting sub-context still applies (consistent heading styles, list formatting). Visuals & Imagery sub-context still applies if relevant  
- **Canvas changes:** AI restructures the page with proper headings, formatted lists, and scannable layout — but does NOT rewrite any of the legal-approved copy into FinDrop's "confident but approachable" brand voice  
- **Governance flags:** None — the exclusion is working correctly. The AI does not flag the formal legal tone as "off-brand"  
- **Post-action commentary:** If asked, the AI can explain that tone & voice rules are excluded for legal pages per brand guidelines boundaries

#### What This Tests

- Boundary exclusions correctly suppress the Writing Tone & Voice sub-context while leaving other Brand Guidelines sub-contexts active  
- The AI respects "do not apply" rules even when the parent context is Global  
- Legal-approved content is not rewritten into brand voice  
- Formatting rules (Abbreviations & Formatting sub-context) still apply — the exclusion is surgical, not a blanket Brand Guidelines bypass  
- The exclusion is silent unless queried — no unnecessary warnings about the tone being "off-brand"

---

### Test Case 0.5: Required Context Enforcement

**Feature Under Test:** Required context items that must always be followed vs. conditional context **CCC Context Loaded:** Brand Guidelines (required: true), Content Strategy: Product Pages (required: false, conditional)

#### Setup / Before State

- Brand Guidelines is marked as `required: true` in the CCC  
- Jordan is working on the existing **"FinDrop Cards"** product page — it already has a hero, feature cards, testimonial, and CTA. The current hero heading reads "Create and Control Corporate Cards in Seconds"  
- Jordan wants to refresh the hero copy to feel more energetic

#### User Input

"Rewrite the hero heading. Make it super casual and Gen Z — something like 'Yo no cap, your expenses are gonna be lit fam these cards hit different fr fr. Slay your spend game bestie 🔥'"

#### Expected AI Behavior

- **Governance flags:** The AI should flag that the requested tone conflicts with required brand guidelines (Writing Tone & Voice: "confident but approachable; professional but not stiff")  
- **AI response:** "That's... a vibe, but Brand Guidelines are marked as required and they call for 'confident but approachable — professional but not stiff.' I can't go full Gen Z slang, but I can make it punchier and more energetic. Here are two options:  
  1. **High energy:** 'Corporate cards that actually keep up with your team'  
  2. **Bold \+ confident:** 'Your team spends. You stay in control. Every transaction, in seconds.'"  
- **Canvas changes:** Does NOT replace the current hero with the slang version. Presents the alternatives for Jordan to choose

#### After State / Desired Outcome

- The existing hero heading is unchanged until Jordan picks an alternative  
- The AI does not produce copy that violates required brand guidelines, even when explicitly asked  
- The AI explains why the request conflicts with required context  
- Alternatives capture the user's intent (more energy) while staying within brand rules

#### What This Tests

- Required context overrides explicit user requests when they conflict  
- The AI provides a clear explanation referencing the specific CCC rule  
- Required context is truly non-negotiable — even a direct instruction to use slang doesn't bypass it  
- The AI finds a constructive middle ground (energetic but brand-compliant) rather than simply refusing

## Workflow A: Phase 1\. Creating Pages from Copy Deck

These test cases cover the full page creation flow: pasting content, answering (or skipping) clarifying questions, and generating a complete page with Mercury components. The key variable is how much context the user provides alongside the content — when the prompt clearly identifies the page purpose AND the audience, the AI has enough to start building immediately. When that context is missing, the AI should ask.

---

### Test Case 1.1: Complete Copy Deck with Full Context — AI Builds Immediately

**Feature Under Test:** End-to-end page creation from a complete copy deck where the user's prompt provides sufficient context (page purpose \+ audience) for the AI to generate a full page without further questions **CCC Context Loaded:** Brand Guidelines, Key Value Propositions, Content Strategy: Product Pages, Personas (Travel Managers, CFOs/Controllers)

#### Setup / Before State

- Jordan has a product copy deck in a Google Doc from the product team for FinDrop Travel — a complete set of content with headlines, body copy, feature descriptions, testimonials, FAQs, and CTA text  
- Jordan opens a new Canvas page (all Canvas pages are just "Pages" — there are no content types)  
- The AI chat sidebar is empty — no prior conversation  
- The copy deck is raw marketing content — no component names, no layout instructions, no design specs. It arrives as markdown via Google Docs' "Copy as Markdown" feature

#### User Input

Jordan types:

"I'm building the FinDrop Travel product page. The audience is Travel Managers and Program Administrators, with CFOs and Controllers as a secondary audience. The goal is awareness and evaluation. Let me paste in the copy deck."

Jordan then copies the copy deck from a Google Doc (using Google Docs' "Copy as Markdown" feature) and pastes it into the chat:

**Hero**

Headline: Business Travel Your Employees Love and Your Finance Team Trusts Body: Flights, hotels, and rental cars in one app — with your company's travel policy built in. No calls to a travel agent. No expense reports when you land. Just book, travel, done. CTAs: "Book Your First Trip" (demo request / trial signup), "See How It Works" (product tour / video)

**Problem Section**

Heading: Business travel is essential. The paperwork after isn't. Body: You just closed a deal in Chicago. You're exhausted, you're in an Uber to O'Hare, and the last thing you want to think about is an expense report. But you have four hotel receipts, two Uber rides, three meals, and a last-minute flight change to document. That's an hour of your life — minimum — filling out forms, uploading photos, and waiting weeks for reimbursement. FinDrop Travel makes the paperwork disappear.

**Feature Benefits** (6 items)

1. Book in minutes, not meetings — Flights, hotels, trains, and rental cars in one app.  
2. Policy is built in — Company travel policy shows up as you book. Green means go.  
3. Zero expense reports — Travel expenses reconcile automatically. No receipts to photograph.  
4. Save the company money, keep a reward — Book under budget and earn a share of the savings.  
5. Keep your loyalty points — Airline miles and hotel points are yours.  
6. 24/7 support when plans change — Real support from people who can rebook you.

**Finance Team Section** (3 cards for secondary audience)

1. Live travel spend visibility — See every booking the moment it's made. Track travel budgets by department, project, or individual.  
2. Policy enforced at booking — Set policies once and they're enforced automatically when employees book. Out-of-policy bookings require approval before confirmation.  
3. Automatic reconciliation — Travel expenses flow directly into your accounting software with the right GL codes and cost centers.

**Testimonials** (2)

1. "I used to spend Sunday nights doing expense reports from the week's travel. Now I don't even think about it. Everything just... shows up in the system." — Marcus Rivera, Senior Account Executive, Bolt Dynamics  
2. "My team adopted FinDrop Travel in a week. 90%+ adoption, zero training sessions. The old system took 3 months and we still had holdouts." — Priya Sharma, Travel Program Manager, Cascade Logistics (800 employees)

**How It Works** (4 steps)

1. Connect your travel policy  
2. Invite your team  
3. Employees book through the app  
4. Expenses reconcile automatically

**FAQ** (6 items)

Q: What travel inventory does FinDrop Travel offer? A: Access to all major airlines, 2M+ hotel properties, rental car agencies, and rail options worldwide.

Q: How is policy enforced? A: Policy rules are applied live as employees search and book. Out-of-policy selections trigger an approval workflow before confirmation.

Q: Do travelers keep their loyalty program status and points? A: Yes. FinDrop Travel integrates with major airline and hotel loyalty programs.

Q: How does FinDrop Travel connect to FinDrop Cards and Expense? A: They're one platform. Travel bookings automatically flow into the same dashboard and accounting sync as card transactions and expenses.

Q: What about complex international trips or VIP executive travel? A: FinDrop Travel handles the 80% of trips that are straightforward. For complex multi-city international itineraries, we integrate with premium TMC partners.

Q: How long does implementation take? A: Most companies are live within 10 business days.

**CTA**

Heading: Build a travel program your team actually uses. Body: 90%+ adoption. Zero expense reports. Complete visibility. See why travel program managers are switching to FinDrop Travel. CTAs: "See FinDrop Travel in Action" (demo request), "Talk to Our Travel Team" (contact sales)

#### Expected AI Behavior

- **Questions first?** No. Jordan's prompt provides the three key inputs the AI needs to proceed:  
  1. **Content:** A complete copy deck with all page sections (hero, problem, features, finance story, testimonials, how-it-works, FAQ, CTA)  
  2. **Page purpose:** Product page (explicitly stated in the prompt \+ identifiable from the content template's structure)  
  3. **Audience:** Primary (Travel Managers) and secondary (CFOs/Controllers) clearly stated  
- **Content parsing:** The AI identifies the content elements: hero headline \+ body \+ CTAs, problem narrative, 6 feature benefit items, 3 finance-audience cards, 2 testimonials with attribution, 4-step how-it-works sequence, 6 FAQ pairs, closing CTA with copy and buttons  
- **CCC context used:** Content Strategy: Product Pages activates. Brand Guidelines inform tone. Key Value Propositions validate messaging alignment. Personas load for both Travel Managers and CFOs/Controllers  
- **Canvas changes:** AI generates a full page with these components in order:  
  1. **Hero** — `sdc.mercury.hero-side-by-side` at the top of the page. Side-by-side chosen because the copy deck's travel imagery should have equal visual weight with the headline — this differentiates FinDrop Travel from the other product pages. Heading (H1) contains the main value proposition. Body text summarizes the key outcome. Actions slot contains primary CTA Button (demo request) and secondary Button  
  2. **Problem/Context Section** — `sdc.mercury.section` with `sdc.mercury.heading` (H2) and `sdc.mercury.text` block. Establishes the problem the product solves  
  3. **Feature Cards Section** — `sdc.mercury.section` with grid of `sdc.mercury.card-icon` components (one per feature benefit). Each card has an icon (Phosphor `ph-*` slug), heading, and description text. Cards are inside the Section's grid region, not at the canvas root  
  4. **Finance Team Section** — `sdc.mercury.section` with grid of `sdc.mercury.card` components (style: framed). These use general-purpose cards rather than card-icon because the finance content has longer descriptions. Section heading addresses the secondary audience directly  
  5. **Social Proof Section** — `sdc.mercury.section` with grid of `sdc.mercury.card-testimonial` components. Each testimonial has quote text (`text`), author name (`cite_name`), and title/role (`cite_text`)  
  6. **How It Works Section** — `sdc.mercury.section` with grid of `sdc.mercury.card-icon` (4-column). Steps numbered in headings  
  7. **FAQ Section** — `sdc.mercury.section` with `sdc.mercury.accordion-container` containing `sdc.mercury.accordion` items. Questions and answers from the copy deck  
  8. **CTA Section** — `sdc.mercury.cta` with heading, supporting text, and `sdc.mercury.button` components in the actions slot  
- **Component hierarchy respected:** Cards are inside Section grids (not canvas root). Accordion items are inside accordion-container. Buttons are inside CTA actions slot. Hero is at the top  
- **Brand voice in generated copy:** Where the AI expands or polishes copy deck text, it follows Brand Guidelines — confident but approachable, data-backed, outcome-focused framing. Avoids prohibited terms (no "real-time" for transactions — use "live"; no "instantly" for card creation — use "in seconds")  
- **Post-action commentary:** After rendering, the AI briefly explains what it built and why: which hero variant it chose, how it structured the dual-audience flow, and which CCC context influenced key decisions. It may offer options for refinement ("Want me to adjust the hero style, or should we look at imagery?")

#### After State / Desired Outcome

- A complete product page is rendered on the Canvas with all 8 sections  
- All copy deck content is placed in appropriate components — nothing dropped  
- The page follows Mercury hierarchy rules (cards in grids, accordions in containers, etc.)  
- Page structure follows product page conventions (hero → problem → features → secondary audience → proof → how-it-works → FAQ → CTA)  
- The AI did not ask clarifying questions — it used the provided context to make informed decisions  
- `[SCREENSHOT: Full FinDrop Travel product page in Canvas — hero, problem section, feature cards, finance section, testimonials, how-it-works, FAQ, CTA]`

#### What This Tests

- AI recognizes when a prompt provides sufficient context to build without questions  
- A complete copy deck \+ explicit page purpose \+ explicit audience \= enough to proceed  
- CCC routing activates correctly from the stated page purpose and content signals  
- Content parsing correctly identifies all distinct content sections from markdown pasted via Google Docs' "Copy as Markdown"  
- AI selects the right Mercury components for each content type (card-icon for features, card for longer descriptions, card-testimonial for quotes, accordion for FAQ)  
- AI selects the right hero variant based on content needs (side-by-side for balanced text/imagery)  
- Component hierarchy rules are followed throughout  
- Brand voice CCC context measurably influences any generated or polished copy  
- The AI builds immediately rather than over-asking when the inputs are clear

---

### Test Case 1.2: Same Copy Deck WITHOUT Context — AI Asks, User Answers, AI Builds \- IN DEMO

**Feature Under Test:** AI correctly identifies missing context, asks targeted questions, then builds once the user answers **CCC Context Loaded:** Brand Guidelines, Key Value Propositions, Content Strategy: Product Pages, then Personas once audience is confirmed

#### Setup / Before State

- Identical to Test Case 1.1: Jordan opens a new Canvas page, pastes the same FinDrop Travel copy deck  
- The only difference is the user's prompt — it provides the content but omits the audience and goal

#### Conversation Flow (Multi-Turn):

**Turn 1 — Jordan:**

"C."

Jordan then pastes the identical copy deck from Test Case 1.1.

**Turn 1 — AI:**

- **Questions first?** Yes. The copy deck is complete, but the prompt is missing two key pieces of context:  
  1. **Audience:** Who is this page for? (Travel Managers? End-user travelers? CFOs? A general audience?)  
  2. **Conversion goal:** What should this page drive? (Demo requests? Guide downloads? Contact sales? General awareness?)  
- **AI response (concise, options-focused):** "Got the copy deck — solid content. Two quick questions before I build:  
  1. Who's the primary audience — travel program managers, finance teams, or end-user travelers? The page structure changes based on who we're talking to.  
  2. What's the main conversion goal — demo requests, guide downloads, or general awareness?"  
- **Canvas changes:** None yet. AI waits for answers before building  
- **What the AI does NOT ask about:** Brand voice, component selection, color scheme, typography — these are handled by CCC context and not dependent on the user's answers

**Turn 2 — Jordan:**

"Travel program managers are the primary audience, CFOs secondary. The goal is demo requests, but we also want a guide download as a secondary CTA."

**Turn 2 — AI:**

- **CCC context used:** Persona sub-contexts for Travel Managers and CFOs/Controllers activate. Content Strategy: Product Pages informs page structure. The stated conversion goals shape CTA placement (primary: demo request, secondary: guide download)  
- **Canvas changes:** AI generates the full page — same component structure as Test Case 1.1, but CTA hierarchy reflects the stated goals (demo request CTA is primary; guide download is secondary)  
- **Post-action commentary:** AI briefly explains the structural choices it made, referencing how it handled the dual audience and CTA hierarchy

#### After State / Desired Outcome

- A complete product page is rendered — structurally equivalent to Test Case 1.1's output  
- The AI asked exactly 2 questions, then built once answered  
- The 2-turn flow feels natural, not bureaucratic

#### What This Tests

- AI distinguishes between "enough context to build" and "need more info"  
- The same copy deck produces different AI behavior based solely on the accompanying prompt  
- Questions target the specific missing inputs (audience, goal) rather than asking about everything  
- Questions are concise with clear options, matching the expected interaction pattern  
- Once answered, the AI builds confidently — no further questions  
- Natural-language answers correctly activate persona sub-contexts

---

### Test Case 1.3: User Skips Clarifying Questions — AI Builds with Defaults

**Feature Under Test:** Graceful handling when user bypasses the question flow **CCC Context Loaded:** Brand Guidelines, Content Strategy: Product Pages (default behavior)

#### Setup / Before State

- Jordan pastes a copy deck without audience or goal context (same setup as Test Case 1.2 Turn 1\)  
- AI has asked clarifying questions about audience and goal  
- Jordan doesn't want to answer

#### User Input

"Don't worry about that — just build the page."

#### Expected AI Behavior

- **Questions first?** No — the user explicitly declined questions  
- **CCC context used:** AI proceeds with best-guess defaults: Product Page Strategy parent context, infers audience from content cues (e.g., if copy mentions "travel program managers" in the body copy, assume that audience), and picks a reasonable CTA approach  
- **Canvas changes:** Full page generated using reasonable defaults  
- **Post-action commentary:** AI briefly notes the assumptions it made: "I structured this for travel program managers based on the copy's language, with demo requests as the primary CTA. Want me to adjust the audience focus or conversion goal?"

#### What This Tests

- AI respects the user's desire to skip questions  
- Default behavior produces a reasonable page, not a broken one  
- AI transparently states its assumptions after the fact  
- User can still course-correct after the initial build

---

### Test Case 1.4: Sparse Input — AI Identifies Content Gaps

**Feature Under Test:** Graceful handling of incomplete or sparse content input **CCC Context Loaded:** Brand Guidelines, Key Value Propositions, Content Strategy: Product Pages

#### Setup / Before State

- Jordan has only bullet points from the product team — not a full copy deck  
- A new Canvas page is open

#### User Input

"Here are some notes from the product team for our Travel product page:

- Books business travel in under 3 minutes  
- Policy enforcement at time of booking  
- Zero expense reports  
- 90%+ adoption rate  
- Integrates with existing accounting software

The audience is travel program managers. Goal is evaluation."

#### Expected AI Behavior

- **Questions first?** Yes — even though the audience and goal are stated, the content is too sparse. The AI has feature bullets but is missing: testimonials, FAQ content, CTA copy, a problem narrative, and the finance-team story  
- **AI response:** Acknowledges the bullet points and what's present vs. missing: "I have your key features and an adoption stat — strong starting points. I'm missing a few things to build a full product page: Do you have customer quotes or testimonials? Any FAQs from prospects? A specific CTA you want (demo request, free trial, contact sales)? I can draft those from your value propositions if you'd like, or you can add more content."  
- **CCC context used:** Key Value Propositions may fill in messaging gaps; Brand Guidelines inform tone. But the AI should be transparent about what it's generating vs. what came from the copy deck

#### What This Tests

- AI distinguishes between "has audience/goal but insufficient content" and "ready to build"  
- AI identifies specific content gaps (testimonials, FAQ, CTA) rather than generically asking for "more"  
- AI offers to supplement sparse input using CCC context (value props) with transparency  
- The conversation remains collaborative — AI doesn't refuse, but also doesn't produce a thin page from sparse input

---

### Test Case 1.5: Content Mismatch — Bio Pasted for Product Page Request

**Feature Under Test:** AI detects when pasted content doesn't match the stated page intent **CCC Context Loaded:** Content Strategy: Product Pages (activated because Jordan said "product page")

#### Setup / Before State

- Jordan opens a new page in Canvas  
- Jordan has copied bio content for a team member instead of the product copy deck (wrong clipboard, wrong document, etc.)

#### User Input

Jordan pastes the following content:

---

**Sarah Chen — VP of Product**

Sarah Chen leads product strategy at FinDrop, where she oversees the platform's expansion into travel management, corporate cards, and expense automation. Before joining FinDrop, Sarah spent 8 years at Square building payment infrastructure for small businesses, and 3 years at McKinsey advising financial services clients on digital transformation.

Sarah holds an MBA from Wharton and a BS in Computer Science from MIT. She's a frequent speaker at fintech conferences and was named to Business Insider's "Rising Stars of Fintech" list in 2023\.

When she's not building products, Sarah mentors women in STEM through the Code2040 program and trains for ultramarathons.

---

Then types:

"Here's the content for our new FinDrop Travel product page. Build it out for travel program managers."

#### Expected AI Behavior

- **Questions first?** Yes — but not the usual clarifying questions. The AI detects a mismatch between the content and the stated intent  
- **AI response:** "This looks like a bio for Sarah Chen rather than product page content — I'm seeing a career summary, education, and personal interests, but no product features, benefits, or CTAs. A few options:  
  1. **Create a bio page** for Sarah using this content  
  2. **Paste in new content** — if you meant to paste a product copy deck, you can paste the right one and I'll build the product page  
  3. **Something else** — let me know what you had in mind"  
- **Canvas changes:** None — AI waits for Jordan to resolve the mismatch  
- The AI does NOT attempt to build a product page from bio content  
- The AI does NOT refuse or simply say "this is wrong" — it offers constructive paths forward

#### After State / Desired Outcome

- The Canvas is still empty — no speculative build  
- Jordan has three clear options to move forward  
- The AI correctly identified the content as a bio (not just "wrong content")

#### What This Tests

- AI detects content-type vs. stated-intent mismatches  
- The AI identifies what the content actually is (a bio) rather than generically saying "this doesn't look right"  
- Constructive options are offered: build what the content supports (bio page), let the user fix the input (paste new content), or handle an unexpected scenario  
- The AI doesn't silently build the wrong kind of page or force bio content into product page components

---

## Workflow A: Phase 2\. Working with an Existing Page — Media, Refinement, and Editing

These test cases cover post-generation work: selecting imagery, refining content through natural language, restructuring sections, adjusting tone, and making targeted edits. All tests assume a page has already been generated (via Phase 1\) and Jordan is iterating on it.

---

### Test Case 2.1: Media Direction — Switch Image Style \<\< In Demo

**Feature Under Test:** AI explains image choices made during page generation, offers an alternative style, and swaps when user accepts **CCC Context Loaded:** Brand Guidelines (Visuals & Imagery)

#### Setup / Before State

- The FinDrop Travel page has just been generated in Phase 1 — the AI already placed images during the initial build  
- The Visuals & Imagery CCC sub-context includes guidance: "The media library contains two image styles: **illustration** (technical/abstract brand illustrations) and **photography** (professional workplace/people shots). Generally, pages should use one consistent style throughout for visual coherence. Illustration is the default for product pages; photography is recommended for case studies, about pages, and testimonial-heavy content."  
- During the Phase 1 build, the AI followed the CCC default and used the illustration style throughout the page  
- FinDrop's media library has both styles tagged and searchable

#### Conversation Flow (Multi-Turn):

**Turn 1 — AI (at the end of the Phase 1 page build):**

- "Page is built. I used the illustration style for imagery based on your brand guidelines — that's the default for product pages. We could go with photography instead if you want a more personal feel. Want me to try a version with photography, or keep illustrations?"

**Turn 1 — Jordan:**

"Yeah, let's try photography."

**Turn 2 — AI:**

- **Canvas changes:** AI swaps all illustration assets to photography from the media library:  
  - Hero media: professional travel/workplace photography  
  - Feature cards: contextually appropriate people shots (e.g., professionals booking travel, teams in airports)  
  - CTA: photography background if supported  
  - Alt text is regenerated for all swapped images (descriptive of the new photography)  
- **AI response:** "Done — swapped to photography throughout. Hero has a professional travel shot, feature cards show real workplace scenarios. Want to adjust any of the selections?"

#### After State / Desired Outcome

- All image slots on the page now show photography instead of illustrations  
- Photography selections are contextually appropriate to each section  
- Alt text is present and descriptive for every image

#### What This Tests

- AI follows CCC Visuals & Imagery guidance when selecting image style during page generation  
- AI explains its image choices and offers the alternative style proactively — not as a question before building, but as a follow-up after  
- The "one style per page" CCC guideline is respected (full swap, not a mix)  
- Alt text is regenerated when images change  
- The media review flows naturally from the end of the Phase 1 page build — it's a continuation, not a separate workflow

---

### Test Case 2.2: Generate a New Hero Variant

**Feature Under Test:** AI generates a new variant of an existing section for comparison **CCC Context Loaded:** Brand Guidelines, Content Strategy: Product Pages

#### Setup / Before State

- The page has been generated with a `hero-side-by-side` component  
- Jordan wants a bolder, higher-impact hero

#### User Input

"Generate a new hero variant — high impact, bolder, main value prop front and center."

#### Expected AI Behavior

- **Canvas changes:** AI generates a NEW hero component (likely `sdc.mercury.hero-billboard`) below or adjacent to the existing one. The new variant has:  
  - A punchier, more prominent headline  
  - The core value proposition ("90%+ adoption") as the lead message  
  - Larger, bolder visual treatment  
- **The original hero remains** — Jordan can compare both and choose  
- **Post-action commentary:** "Here's a Billboard Hero variant — bolder, with the adoption stat front and center. The original Side-by-Side is still there. Want to keep one and remove the other?"

#### After State / Desired Outcome

- Two hero components visible on the Canvas — the original and the new variant  
- Jordan can visually compare and delete the one they don't want  
- The new variant is genuinely different in approach, not just a minor tweak

#### What This Tests

- AI generates new variants rather than destructively editing existing components  
- The "generate, compare, keep the winner" workflow is supported  
- AI can shift between hero types (side-by-side → billboard) when asked for "bolder"  
- The original component is preserved for comparison

---

### Test Case 2.3: Restructure Content — Move Sections to FAQ

**Feature Under Test:** AI restructures page content by moving elements between sections **CCC Context Loaded:** Content Strategy: Product Pages

#### Setup / Before State

- The page has a long middle section with detailed specifications mixed in with feature benefits  
- Jordan wants to separate the detailed content into a FAQ

#### User Input

"Too much copy in the middle. Move the detailed specs into a FAQ at the bottom."

#### Expected AI Behavior

- **Canvas changes:**  
  - Detailed specification content is removed from the middle section's text/card components  
  - A new `sdc.mercury.section` is created near the bottom with an `sdc.mercury.accordion-container` containing `sdc.mercury.accordion` items  
  - Each spec item becomes a FAQ with a question heading and answer content  
  - The middle section is cleaner — focused on high-level benefits only  
- **Post-action commentary:** "Moved 4 detailed spec items into a new FAQ section at the bottom. The feature cards now focus on outcomes. The specs are still accessible — just one click deeper."

#### After State / Desired Outcome

- Middle section remains (to be manually deleted by user)  
- FAQ section exists at the bottom with the migrated content reformatted as Q\&A pairs  
- No content is lost — it's relocated and reformatted  
- Accordion items are inside an accordion-container (hierarchy respected)

#### What This Tests

- AI can restructure page content based on natural language instructions  
- Content is reformatted appropriately when moved (prose → FAQ Q\&A format)  
- Mercury component hierarchy is maintained (accordion inside accordion-container)  
- No content is lost during restructuring

---

### Test Case 2.4: Swap Media on Specific Components

**Feature Under Test:** AI swaps media assets on specific components without affecting the rest of the page **CCC Context Loaded:** Brand Guidelines (Visuals & Imagery)

#### Setup / Before State

- The page has photography throughout (swapped from illustrations in Test Case 2.1) — hero, feature cards, CTA  
- Jordan wants to swap just the feature cards to illustrations

#### User Input

"For the feature cards in the middle — switch those to illustrations. I think the abstract style works better for features."

#### Expected AI Behavior

- **Canvas changes:**  
  - Feature card images swap from photography to illustrations from the media library  
  - Hero and CTA images remain as photography — only the specified cards change  
  - Illustration selections are contextually appropriate to each feature (e.g., booking, policy enforcement, reconciliation)  
  - Alt text is regenerated for the swapped images  
- **CCC note:** The AI may briefly mention that the Visuals & Imagery guideline recommends one consistent style per page, but proceeds with Jordan's request since it's an explicit creative direction: "Just a note — brand guidelines suggest one image style per page for consistency. Mixing is fine if that's the look you want. Swapped the feature cards to illustrations."

#### After State / Desired Outcome

- Feature cards show illustrations; hero and CTA retain photography  
- The AI noted the CCC guideline but respected Jordan's explicit direction  
- Alt text on swapped images reflects the new illustrations

#### What This Tests

- AI can target specific components for media changes without affecting others  
- Media swaps maintain contextual appropriateness  
- Alt text is regenerated when images change  
- Partial updates work (not all-or-nothing)  
- AI surfaces relevant CCC guidelines without blocking the user's creative decision — informative, not restrictive (contrast with Test Case 0.5 where required brand guidelines are non-negotiable)

---

### Test Case 2.5: Hero Reframe — Lead with Benefits, Not Features

**Feature Under Test:** AI restructures hero copy from feature-led to benefit-led, using persona context already established in the conversation **CCC Context Loaded:** Brand Guidelines (Writing Tone & Voice), Personas (Travel Managers), Key Value Propositions

#### Setup / Before State

- The page was built in Phase 1 with Travel Managers as the primary audience — that persona context is already active in the conversation  
- The current hero leads with a feature: "All-in-One Travel Booking with Built-in Policy Enforcement"  
- The subhead lists more features: "Flights, hotels, and rental cars in one app. Policy applied at time of booking. Automatic reconciliation."

#### User Input

"You've led with features in the hero. Lead with the benefit to the buyer instead."

#### Expected AI Behavior

- **Canvas changes:** Hero heading and body text are rewritten to lead with the outcome that matters to Travel Managers, not the product capability:  
  - Before: "All-in-One Travel Booking with Built-in Policy Enforcement"  
  - After: "Business Travel Your Employees Love and Your Finance Team Trusts"  
  - Before subhead: "Flights, hotels, and rental cars in one app. Policy applied at time of booking. Automatic reconciliation."  
  - After subhead: "90%+ adoption in the first month. Zero expense reports. Complete spend visibility. That's what happens when travel booking actually works."  
- **CCC context used:** The AI already knows the audience is Travel Managers from the Phase 1 conversation — it uses the Personas sub-context to identify what Travel Managers care about (adoption rates, employee satisfaction, reducing administrative burden) rather than generic benefit language. Key Value Propositions provide the proof points (90%+ adoption, zero expense reports)  
- **Post-action commentary:** "Reframed — the hero now leads with what Travel Managers actually care about: adoption and no more expense reports. The features are still on the page in the card sections below."

#### What This Tests

- AI can distinguish between features (what the product does) and benefits (what the buyer gets)  
- Persona context from earlier in the conversation is used to pick the right benefits — not generic "save time and money" but persona-specific outcomes  
- The AI doesn't need to re-ask about audience — it uses context already established in the Phase 1 flow  
- Features aren't deleted, just repositioned (still present in feature cards further down the page)  
- Key Value Propositions supply concrete proof points for the benefit-led copy

---

---

## Workflow A: Phase 3\. Pre-launch Checklists

These test cases cover the checks that run before publishing: Schema.org generation from page content, internal cross-link suggestions, accessibility validation, and brand compliance. The dev team has implemented Schema.org generation and cross-link suggestion capabilities.

---

### Test Case 3.1: SEO: Cross-Linking Suggestions \<\< In Demo

**Feature Under Test:** SEO agent identifies internal cross-linking opportunities **CCC Context Loaded:** SEO/AEO Guidelines (if configured), site content index

#### Setup / Before State

- Jordan's product page is ready for review  
- The FinDrop site has 3 existing pages that discuss related topics (e.g., expense management, corporate cards, platform overview)

#### User Input

Use selects the Grid of cards  
Text "Looking a these cards, find and link related pages in the site"

#### Expected AI Behavior

- **AI response:** "SEO check before you publish:  
  - **Cross-links:** 3 pages on your site could cross-link here. Add them?  
  - **Schema:** I've generated FAQPage schema for the FAQ section. Preview it?  
  - **Keyword overlap:** 'corporate travel management' overlaps with your /travel-overview page. May want to differentiate."  
- Questions are presented as actionable items, not a passive report

#### After State / Desired Outcome

- Jordan can accept/reject each suggestion individually  
- Cross-links, if accepted, are added as inline links or related-content references  
- Schema markup is generated and queued for the page

#### What This Tests

- SEO agent identifies actionable cross-linking opportunities  
- FAQ schema generation is automatic for pages with accordion/FAQ sections  
- Keyword cannibalization warnings surface proactively  
- Suggestions are itemized and individually actionable

---

### Test Case 3.2: SEO Agent — Schema Generation for FAQ Content \<\< In Demo

**Feature Under Test:** Automatic FAQPage schema generation from accordion content **CCC Context Loaded:** SEO/AEO Guidelines

#### Setup / Before State

- The page contains an `sdc.mercury.accordion-container` with 6 `sdc.mercury.accordion` items  
- Each accordion has a question heading and answer content

#### User Input

"Generate schema for this page."

#### Expected AI Behavior

- **AI response:** Replied “LLM says I created a FAQPage and Product Schema”   
- Generates FAQPage structured data (JSON-LD) from the accordion content. Each accordion item maps to a Question/Answer pair. Also generates Product schema from the page's overall content

#### What This Tests

- AI can extract FAQ content from accordion components and generate structured data  
- Schema follows Google's FAQPage specification  
- Multiple schema types can be generated for one page (FAQPage \+ Product)  
- AI previews schema before applying

---



## Workflow B: Diagnose and Fix a Live Page

These test cases cover the scenario where new organizational knowledge is added to the CCC after a page has been published.

---

### Test Case 4.1: Sales Team Adds Competitive Intelligence to CCC

**Feature Under Test:** New CCC context item is added after pages are already live **CCC Context Loaded:** Existing items \+ new Sales Pitch Deck with competitive differentiator

#### Setup / Before State

- Jordan published the product page a week ago  
- The sales team has since uploaded a revised Sales Pitch Deck to the CCC. The content changes between the previous and current versions are captured in `findrop-sales-deck-before.md` and `findrop-sales-deck-after.md` — the key addition is a competitive differentiator around enterprise security capability that wasn't in the original deck  
- The new CCC item is tagged for "Writing Words" use case  
- Jordan is not aware the new context exists

#### User Input

(No user input yet — this test case establishes the state change)

#### Expected Behavior

- The new Sales Pitch Deck context item appears in the CCC  
- It does NOT automatically change any live pages  
- It IS available for AI to reference the next time someone works on relevant pages  
- The item has appropriate metadata: source (Sales team), last updated date, lifecycle info

#### What This Tests

- New CCC items can be added at any time without disrupting live content  
- CCC additions don't auto-modify published pages  
- The new context is immediately available for future AI sessions  
- Metadata (source, date, lifecycle) is tracked

---

## Workflow B: Phase 5\. Performance Trigger — Background GA Agent

A background agent monitors Google Analytics against benchmarks defined in the CCC. When a page underperforms, the agent flags it, emails Jordan, and prepares a summary. Jordan clicks through to the page in Canvas where the AI diagnosis picks up.

**Note — CCC Benchmarks Setup (not in test scope):** Before this phase, benchmarks have been configured in the CCC's Metrics / Analytics KPIs item (e.g., bounce rate \< 45%, demo request rate \> 3.5%, engaged sessions \> 60%). This is a simple UI task — defining target thresholds per metric. The benchmarks are static, defined values for now (not relative or time-windowed logic). This setup is a prerequisite but not part of the Canvas AI test scenarios.

---

### Test Case 5.1: Background Agent Detects Underperformance and Notifies Jordan

**Feature Under Test:** Background GA agent detects a page failing CCC-defined benchmarks and notifies the user via email **CCC Context Loaded:** Metrics / Analytics KPIs (benchmarks), Sales Pitch Deck v7 (new), Brand Guidelines, Content Strategy: Product Pages

#### Setup / Before State

- Jordan's FinDrop Travel product page has been live for two weeks  
- The background GA agent runs on Cron, pulling Google Analytics data and checking it against benchmarks defined in the CCC's Metrics / Analytics KPIs item  
- The page is underperforming:  
  - Bounce rate: 68% (benchmark: \< 45%)  
  - Demo request rate: 1.2% (benchmark: \> 3.5%)  
  - Top search query mismatch: visitors searching "enterprise security" but the page leads with "enterprise features platform"  
- The CCC now includes the Sales Pitch Deck v7 that was added post-launch (Test Case 4.1)

#### Trigger (Automated — No User Input)

- The background agent detects the benchmark failures and sends Jordan an email notification:  
  - Subject: "Performance alert: /enterprise-features — 3 benchmarks failing"  
  - Summary: bounce rate, conversion rate, and search mismatch flagged  
  - Link to the page summary in Drupal

#### Expected Flow

1. **Agent detects** — Background Cron job pulls GA data, compares against CCC benchmarks, identifies 3 failures  
2. **Email sent** — Jordan receives a notification email with a summary and a link  
3. **Jordan clicks through** — Link opens a summary UI in Drupal showing the flagged URL, which benchmarks failed, and the GA data  
4. **Jordan opens the page in Canvas** — From the summary, Jordan navigates to the underperforming page and opens the AI chat sidebar

#### After State / Desired Outcome

- Jordan is on the underperforming page in Canvas with the AI chat sidebar open  
- The AI has access to the GA data from the agent (it doesn't need to be re-pasted)  
- All relevant CCC context is loaded (including the Sales Pitch Deck v7)  
- Jordan is ready to ask the AI for a diagnosis

#### What This Tests

- Background GA agent correctly identifies pages failing CCC-defined benchmarks  
- Notification email is sent with actionable summary  
- The flow from email → summary UI → Canvas page is seamless  
- GA data from the agent is available to the Canvas AI without manual copy-paste  
- CCC benchmarks (not hardcoded rules) drive the detection

---

### Test Case 5.2: Agent Flags Page but Jordan Has Limited Data

**Feature Under Test:** AI handles a scenario where the GA agent flags an issue but the data is sparse **CCC Context Loaded:** Metrics / Analytics KPIs

#### Setup / Before State

- The background agent flagged a page with only one benchmark failure: bounce rate at 68%  
- No search query data is available (GA property doesn't have Search Console linked)  
- No conversion data beyond the bounce rate

#### User Input

Jordan opens the flagged page in Canvas and asks:

"The agent flagged this page for high bounce rate. What's wrong?"

#### Expected AI Behavior

- **AI response:** Acknowledges the high bounce rate and contextualizes it against CCC benchmarks, but notes what's missing: "68% bounce rate is well above your 45% benchmark. To diagnose properly, it would help to know what search queries are bringing traffic and what the conversion rate looks like. Without that, my best guess based on reviewing the page content is \[preliminary hypothesis\]. Can you connect Search Console for richer data?"  
- AI uses CCC Metrics KPIs to contextualize the 68%  
- AI reviews the actual page content to form a preliminary hypothesis  
- AI suggests connecting additional data sources rather than guessing

#### What This Tests

- AI gracefully handles incomplete diagnostic data from the background agent  
- CCC benchmarks provide comparison context even when GA data is sparse  
- AI asks for additional data sources rather than making confident claims from limited input  
- A preliminary hypothesis based on page content review is still offered

---

## Workflow B: Phase 6\. Working on Improvements

Jordan has been notified of the underperforming page (Phase 5\) and is now in Canvas working on fixes. This phase covers the full improvement cycle: diagnosis, content updates, keyword alignment, compliance catches, and publishing.

---

### Test Case 6.1: AI Diagnoses Performance Issues from GA Data and CCC Context

**Feature Under Test:** AI cross-references analytics data with CCC context to diagnose performance issues and propose solutions including keyword alignment

**CCC Context Loaded:** All relevant items including the newly-added Sales Pitch Deck v7

#### Setup / Before State

- The background GA agent has flagged the page and Jordan has clicked through to it in Canvas (Test Case 5.1)  
- The AI has the GA data from the agent: bounce rate 68%, demo request rate 1.2%, search query mismatch  
- The live page leads with "enterprise features platform" messaging  
- Visitors are searching for "enterprise security"  
- The Sales Pitch Deck v7 in the CCC highlights security as the key competitive differentiator  
- Jordan did NOT know the sales deck was in the CCC

#### User Input

"What's going wrong with this page and what should I change?"

#### Expected AI Behavior

- **AI response:** "Three issues:  
    
  1. **Search mismatch:** Traffic is coming from 'enterprise security' queries but the hero leads with 'enterprise features platform.' The page isn't matching visitor intent.  
  2. **Missing differentiator:** Your sales deck highlights enterprise security as your key competitive advantage — validated by third-party benchmarks. This page doesn't mention it.  
  3. **Keyword alignment:** The subhead and body copy don't include 'enterprise security' at all — that's the term visitors are actually searching for.


  Want me to update the hero and value prop section to fix all three?"


- **CCC context used:**  
    
  - Metrics / Analytics KPIs: contextualizes the bounce rate and conversion data  
  - Sales Pitch Deck v7: surfaces the security differentiator that Jordan didn't know was in the system  
  - Content Strategy: Product Pages: informs what a well-structured product page should look like


- **Post-action commentary:** The AI explicitly notes it found the differentiator in the sales deck — demonstrating cross-team knowledge connection

#### After State / Desired Outcome

- Jordan sees a concise, three-part diagnosis  
- Each issue has a clear cause and a proposed fix  
- The diagnosis connects information from multiple CCC sources (analytics \+ sales deck \+ page content)  
- Jordan is informed that the sales deck was the source of the differentiator insight

#### What This Tests

- AI cross-references multiple CCC items in a single diagnosis  
- Sales team's competitive intelligence surfaces to the marketing team automatically  
- Search intent mismatch is identified from the analytics data  
- Keyword alignment is identified as a specific, fixable issue (not just "rewrite the page")  
- Missing content is identified by comparing the page against CCC knowledge  
- Cross-team knowledge connection ("expertise multiplication") works as designed

---

### Test Case 6.2: AI Diagnosis Without Sales Deck — No Hallucination

**Feature Under Test:** AI diagnosis is grounded in actual CCC data and GA agent data, not invented **CCC Context Loaded:** Brand Guidelines, Content Strategy: Product Pages, Key Value Propositions, Metrics / Analytics KPIs (no sales deck present)

#### Setup / Before State

- The background GA agent has flagged the same page with the same data as Test Case 5.1 (high bounce rate, search mismatch for "enterprise security")  
- Jordan has clicked through to the page in Canvas  
- Critically, the CCC does NOT contain any competitive intelligence or sales deck — the sales team hasn't uploaded it yet in this scenario  
- The AI only has access to Brand Guidelines, Content Strategy: Product Pages, Key Value Propositions, and Metrics / Analytics KPIs

#### User Input

"What's going wrong with this page and what should I change?"

#### Expected AI Behavior

- **AI response:** Diagnoses based only on the available data:  
  1. "**Search mismatch:** Traffic is coming from 'enterprise security' queries but the hero leads with 'enterprise features platform.' The page isn't matching visitor intent."  
  2. "I'd recommend updating the hero and subhead to lead with 'enterprise security' since that's what visitors are searching for. Want me to rework the copy to match?"  
- **What the AI does NOT do:**  
  - Does NOT invent a competitive differentiator ("your key advantage is...") — it has no sales deck to draw from  
  - Does NOT fabricate third-party benchmark claims or competitive positioning  
  - Does NOT reference the absence of a sales deck or suggest the CCC is missing something — it has no way to know what's *not* there  
- **The diagnosis is narrower than Test Case 6.1:** Without the sales deck, the AI identifies the search mismatch and keyword gap (observable from analytics data \+ page content) but cannot surface the competitive differentiator angle. The fix it proposes is limited to keyword alignment, not strategic repositioning

#### What This Tests

- AI stays grounded in actual loaded CCC data — no hallucinated differentiators or competitive claims  
- The AI works with what it has and proposes the best fix possible within that scope  
- The AI does not reference what's absent from the CCC (it can't know what it doesn't have)  
- The quality gap between 6.1 (with sales deck) and 6.2 (without) demonstrates CCC value — more context produces better diagnosis

---

### Test Case 6.3: AI Generates Updated Hero with Keyword Alignment \<\< IN demo

**Feature Under Test:** AI generates new content drawn from CCC knowledge to fix the diagnosed issues, including natural keyword integration **CCC Context Loaded:** Sales Pitch Deck v7, Brand Guidelines, Content Strategy: Product Pages

#### Setup / Before State

- AI has diagnosed the search mismatch, missing differentiator, and keyword gap (Test Case 6.1)  
- Jordan has accepted the diagnosis

#### User Input

"Yes, fix all three. Lead with the security differentiator. Make sure 'enterprise security' is in the subhead — that's what people are searching for. Outcomes first, not features."

#### Expected AI Behavior

- **Canvas changes:** AI generates a new hero variant with:  
  - Headline focused on security outcomes (matching search intent)  
  - Subhead incorporating "enterprise security" naturally — not keyword-stuffed  
  - Key differentiator from Sales Pitch Deck woven into the copy  
  - Existing CTA buttons preserved (demo request)  
- **CCC context used:** Sales Pitch Deck v7 provides the differentiator messaging. Brand Guidelines ensure the tone is right. Content Strategy: Product Pages keeps it structured for the right audience  
- **Post-action commentary:** "New hero leads with enterprise security — matching what visitors are searching for. The differentiator from your sales deck is in the subhead. 'Enterprise security' reads naturally, not stuffed."

#### What This Tests

- AI generates new copy from CCC context (sales deck), not from scratch  
- Content addresses all three diagnosed issues (search mismatch \+ missing differentiator \+ keyword alignment)  
- Keyword integration is natural, not forced — same principle as targeted copy updates in Workflow A  
- Brand voice is maintained  
- The fix is traceable to specific CCC sources

---

### Test Case 6.4: Iterative Tone Adjustment on the Fix

**Feature Under Test:** Multi-turn tone refinement during a fix **CCC Context Loaded:** Brand Guidelines (Writing Tone & Voice)

#### Setup / Before State

- AI generated the updated hero (Test Case 6.3) but the tone is too aggressive

#### User Input

"Too aggressive. Soften it — confident, not pushy."

#### Expected AI Behavior

- Updates the hero copy. New version is softer but still data-backed  
- "enterprise security" keyword remains in the subhead — the tone changes, not the keyword strategy  
- "Toned it down — still leads with security, but reads as confident authority rather than hard sell. Keywords preserved."

#### What This Tests

- Tone adjustment preserves keyword alignment from the previous turn  
- Brand guidelines anchor the tone consistently  
- AI remembers the full conversation context (diagnosis → fix → keyword alignment → tone refinement)  
- Incremental changes don't undo previous fixes

---

### Test Case 6.5: Compliance Catch — AI Flags and Softens Competitive Claim

**Feature Under Test:** AI catches competitive claims that violate brand governance rules and helps resolve them **CCC Context Loaded:** Brand Guidelines (with competitive claims rule), Sales Pitch Deck v7

#### Setup / Before State

- During the fix, the AI drew from the Sales Pitch Deck which contains a competitive claim: "Outperforms \[Competitor X\] by 40%"  
- Brand Guidelines include a boundary rule: "Direct competitive claims (naming competitors or citing comparative stats) require legal approval before publication"  
- The AI has drafted updated copy that includes this claim

#### Conversation Flow (Multi-Turn):

**Turn 1 — AI (proactive catch):**

- "One flag: the source text mentions 'outperforms \[Competitor X\] by 40%.' Brand guidelines require legal approval for direct competitive claims. Two options: soften it (remove the competitor name), or route to legal for approval?"  
- The AI does NOT silently include the competitive claim  
- The flag references the specific brand guideline rule

**Turn 1 — Jordan:**

"Can't wait for legal. Rephrase without naming the competitor."

**Turn 2 — AI:**

- **Canvas changes:** The competitive claim is rewritten:  
  - Before: "Outperforms \[Competitor X\] by 40%"  
  - After: "Industry-leading security performance, validated by third-party benchmarks"  
- "Updated: 'Industry-leading security performance, validated by third-party benchmarks.' No direct comparison. Brand rules satisfied."

#### What This Tests

- Competitive claims are caught proactively, not after user spots them  
- The AI references the specific CCC rule (brand guidelines → competitive claims)  
- Two constructive options are presented (soften vs. route to legal)  
- AI can rephrase non-compliant claims into compliant alternatives that preserve persuasive intent  
- Governance is helpful, not blocking — it's a "feature, not a constraint"

---

### Test Case 6.6: AI Catches Competitive Claim User Adds Manually

**Feature Under Test:** Governance catch applies to user-written content, not just AI-generated content **CCC Context Loaded:** Brand Guidelines

#### Setup / Before State

- Jordan manually edits a text component on the Canvas to add: "Unlike Brex, our platform delivers 40% faster reconciliation."

#### User Input

"I just added some text to the value prop section. Review it for me."

#### Expected AI Behavior

- **Governance flags:** "I see a competitive claim in the value prop section: 'Unlike Brex, our platform delivers 40% faster reconciliation.' Brand guidelines require legal approval for direct competitor comparisons. Want me to rephrase, or will you route this to legal?"  
- The AI catches the violation even though a human wrote it  
- Same options presented: soften or route to legal

#### What This Tests

- Brand governance applies equally to human-written and AI-generated content  
- The AI reviews user edits when asked, not just its own output  
- The same competitive claims rule catches named competitors in user text  
- The governance system is comprehensive, not just AI-output focused

---

### Test Case 6.7: Publish Updated Page and CCC Attribution

**Feature Under Test:** End-to-end completion of the performance fix workflow with CCC traceability **CCC Context Loaded:** All relevant items

#### Setup / Before State

- Jordan has completed the diagnosis, fix, keyword alignment, tone refinement, and compliance resolution  
- The page now leads with "enterprise security" messaging, incorporates the sales deck differentiator, has natural keyword integration, a compliant competitive positioning statement, and maintains brand voice

#### User Input

"Looks good. Publish."

#### Expected AI Behavior

- **AI response (pre-publish summary):** "Publishing with these changes:  
    
  - Hero updated: now leads with enterprise security (matching search intent)  
  - Subhead: 'enterprise security' integrated naturally for keyword alignment  
  - Value prop section: incorporates competitive differentiator from sales deck  
  - Competitive claim: rephrased to comply with brand guidelines  
  - Rest of page: unchanged


  **Context used:** Analytics KPIs flagged the issue. Sales Pitch Deck v7 provided the differentiator your sales team added after launch. Brand Guidelines caught and resolved the competitive claim."


- Page publishes  
    
- **No new governance issues** — compliance was handled during editing

#### After State / Desired Outcome

- Updated page is live  
- All changes from the performance fix session are reflected  
- The audit trail captures: what changed, why (GA agent alert → diagnosis → fix), and which CCC context informed the changes  
- The explanation demonstrates cross-team knowledge connection (sales deck → marketing page)

#### What This Tests

- The fix workflow completes end-to-end: agent alert → diagnosis → fix → keyword alignment → compliance → publish  
- Pre-publish summary gives Jordan confidence in what's changing  
- CCC attribution is built into the publish flow — Jordan sees which context items influenced the changes  
- Cross-team knowledge connection is transparent (sales deck → marketing page)  
- The total elapsed time is feasible within 30 minutes (alert to published fix)  
- Audit trail exists for all changes


