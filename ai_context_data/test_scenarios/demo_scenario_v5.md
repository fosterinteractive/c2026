## PART 3: WORKFLOW A — BUILDING A PAGE WITH AI (5 min)

*DRIES on stage, introducing the demo*

Let's see this in action. Jordan needs to build the new FinDrop Travel product page. The product team sent over a copy deck. Let's watch how Jordan works with Drupal AI.

### 3.1 Start from Content — Not a Blank Page (45 sec)

🎥 **VIDEO — Pre-recorded demo with narrator**

*NARRATOR: Most marketers don't start with a design. They start with content — a brief, a copy deck, notes from the product team.*

*On screen: Jordan opens a Google Doc with the FinDrop Travel product copy. Selects all. Copies as markdown.*

*NARRATOR: Jordan got a copy deck from the product team. They copy the content and bring it into Drupal.*

*On screen: Jordan switches to Drupal. Opens Canvas AI chat. Pastes the markdown text and types a prompt.*

**JORDAN (typing):** "Here's our copy deck for the new FinDrop Travel product page."

### 3.2 AI Asks, Then Builds (1.5 min)

🎥 **VIDEO**

*AI responds in chat.*

*NARRATOR: The AI recognized this as a product page and loaded the matching strategy from the CCC. But it needs two things from Jordan before it builds.*

**AI:** "Who's the target audience? I see personas for Controllers, CFOs, Travel Managers, and Office Managers. And what's the primary goal?"

**JORDAN:** "Travel Managers. And the goal is whitepaper downloads for the new product."

*On screen: The AI's thought chain appears in the chat sidebar:* *— Loaded: Content Strategy: Product Pages* *— Loaded: Brand Guidelines \> Visuals & Imagery* *— Loaded: Personas & Ideal Customer Profiles* *— Loaded: Key Value Propositions*

*AI generates a full page using FinDrop's component library — hero, value prop section, feature cards, testimonial area, CTA.*

*AI follows up, referencing a visual identity rule from the CCC:*

**AI:** "Done. I went with illustrations — they match the technical content. Your visual identity guidelines also include photography. Want me to swap?"

*NARRATOR: Notice what just happened. The AI read the unstructured text copy document and mapped it into the site's UI components. It pulled brand voice, typography, and imagery guidelines from the Context Control Center. It chose an image direction, but flagged the choice for Jordan to confirm. Jordan stays in control.*

### 3.3 Choosing a Visual Direction (30 sec)

🎥 **VIDEO**

*NARRATOR: Jordan likes the illustrations, but wants people shots for Hero and Footer*

**JORDAN:** "Switch the hero and the footer CTA to photography."

*AI swaps the hero image and footer CTA to photography from the media library. Feature cards keep illustrations.*

**AI:** "Done. Hero and footer updated to photography. Feature cards still using illustrations."

### 3.4 Iterative Refinement (45 sec)

🎥 **VIDEO**

*NARRATOR: Now Jordan starts refining.*

**JORDAN:** "Too much copy in this section." *(selects a longer content block in the middle of the page)* "Turn this into a FAQ block at the bottom."

*\[NOTE: Canvas AI can't delete content — it generates a new FAQ component at the bottom using the selected content. The original block stays in place.\]*

*AI creates FAQ component at the bottom of the page with the content restructured as questions and answers.*

*NARRATOR: The AI generated the FAQ section, but it left the original block to compare. Jordan reviews the new section, likes it, and deletes the original content manually.*

### 3.5 Cross-Linking with Semantic Search (45 sec)

🎥 **VIDEO**

*NARRATOR: Jordan notices the benefit cards reference FinDrop's other products — Virtual Credit Cards and Expense Management. There should be links to those pages.*

*Jordan selects the benefit cards.*

**JORDAN:** "Add links on the cards to related pages"

*AI runs a semantic search across the site's content using vector search.*

**AI:** "Found 3 relevant pages across the site. I've added links to the matching cards."

*NARRATOR: The AI used semantic search — not just keyword matching — to find relevant content across the site and add cross-links automatically.*

### 3.6 AEO Schema & Final Checks (45 sec)

🎥 **VIDEO**

*NARRATOR: Jordan wants this page to show up in AI search tools — Google's AI summaries, ChatGPT, that kind of thing. That requires structured data.*

**JORDAN:** "Create an AEO schema for this page."

*AI generates a Schema.org JSON-LD file and populates it into a field on the right-hand side of the editor.*

**AI:** "Done. I've generated FAQPage and Product schema based on the page content. It's in the structured data field."

⚠️ **OPTIONAL — Prepare version with and without this step**

**JORDAN:** "Check accessibility on the page."

*AI checks heading levels.*

**AI:** "Heading structure looks good — all levels nest correctly. H1 \> H2 \> H3 throughout."

*Jordan publishes.*

*DRIES returns to stage:*

That's a product page — from copy deck to published — with brand compliance, the right imagery, cross-links to related content, and structured data all handled. Let's talk about what happens next.

---

## PART 4: WORKFLOW B — WHEN CONTENT UNDERPERFORMS (4.5 min)

*DRIES on stage*

Making good content is hard. But spotting underperforming content at scale — that's harder. Pages go live, people move on, and nobody checks until someone complains.

This is where background agents come in. Let me show you what happens when Jordan's pages are out in the world.

### 4.1 The Setup & Alert (1 min)

🎥 **VIDEO**

*On screen: The published FinDrop Travel page. Then a quick shot of Jordan's agent interface showing the Travel page marked as important with performance thresholds set.*

*NARRATOR: Jordan launched the FinDrop Travel page a couple of weeks ago. Life moved on — other projects, other deadlines. But Jordan did something smart after launch. Using the agent interface, Jordan marked the Travel page as important and set performance thresholds: bounce rate, engaged sessions, and whitepaper downloads.*

⚠️ **TBD — Analytics data approach: Performance fields may be shown in a UI that would normally auto-populate from GA4 via the Metrics / Analytics KPIs context in the CCC. For demo purposes, values may be manually set. Finalize approach closer to recording.**

*On screen: Jordan gets a notification. Opens the agent dashboard. An event is flagged: "Underperforming content detected." Fields show current bounce rate vs. threshold, engaged sessions below benchmark, whitepaper downloads falling short.*

*NARRATOR: Two weeks later, a background agent flags a problem. Not from Google Analytics — from Drupal. The system already did the analysis.*

*NARRATOR: The agent hasn't just flagged the problem. It's analyzed the page against the current CCC and prepared a short list of recommended changes.*

**AI:** "Three suggestions: (1) Lead with buyer outcomes instead of features in the hero. (2) Update competitive positioning using the revised sales deck. (3) Strengthen the whitepaper CTA with a specific benefit statement. Which would you like to start with?"

**JORDAN:** "Start with 1 and 2."

*Jordan clicks "Work on it" to open the page in Canvas AI. The Canvas sidebar opens with a pre-loaded prompt summarizing the performance issues and selected suggestions.*

### 4.2 Diagnosis, Fix & Compliance Catch (1.5 min)

🎥 **VIDEO**

*The Canvas AI sidebar shows the pre-loaded context: bounce rate data, session metrics, download numbers. AI cross-references with the CCC.*

*On screen: The AI's thought chain appears:* *— Loaded: Key Value Propositions \> Sales Pitch Deck v7 (updated 1 day ago)* *— Loaded: Brand Guidelines*

*NARRATOR: The AI cross-referenced the performance data with the CCC and found something. Since Jordan built this page, the sales team had updated the Sales Pitch Deck with new competitive positioning. That context didn't exist when the page went live. It does now.*

**AI:** "I found a new positioning statement focused on outcomes to the buyer. Want me to update the hero and value prop to lead with this?"

**JORDAN:** "Yes. Outcomes first, not features."

*AI generates a new hero variant with updated copy drawn from the revised sales deck in the CCC.*

*NARRATOR: But the AI flags something.*

**AI:** "The sales deck names a competitor directly. Your brand guidelines require legal approval for competitive claims. Soften it, or will you confirm with legal?"

**JORDAN:** "Can't wait for legal. Rephrase without naming the competitor."

**AI:** "OK — updated."

*NARRATOR: The AI pulled in intelligence the sales team added after launch, proposed a fix, and caught a brand violation before it went live. Not after a legal letter. Before publish.*

*Jordan publishes the updated page.*