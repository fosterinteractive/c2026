# Test Scenarios Setup Checklist

## CCC Context Documents to Create

### Brand Guidelines (Global, required: true)
- [ ] Create parent item with scope: Global, required: true
- [ ] Use case tags: "Writing Words", "Reviews"
- [ ] Sub-context: Writing Tone & Voice
  - Content: "confident but approachable; professional but not stiff"
  - Prohibited terms: "real-time" (use "live"), "instantly" (use "in seconds")
- [ ] Sub-context: Abbreviations & Formatting
- [ ] Sub-context: Visuals & Imagery
  - Two styles: illustration (default for product pages), photography (case studies/about/testimonials)
  - Rule: "One consistent style throughout for visual coherence"
- [ ] Boundary exclusion 1: "Do not apply Writing Tone & Voice rules to legal and compliance pages (Privacy Policy, Terms of Service, Cookie Policy)"
- [ ] Boundary exclusion 2: "Direct competitive claims (naming competitors or citing comparative stats) require legal approval before publication"

### Content Strategy: Product Pages
- [ ] Create item scoped to: Canvas Pages
- [ ] Use case tag: "Editing Canvas Blocks"
- [ ] Add Purpose field describing when it should activate
- [ ] 3 sub-contexts (define structure)

### Content Strategy: Landing Pages
- [ ] Create parent item
- [ ] Add Purpose field describing when it should activate
- [ ] Sub-context: Top of Funnel (awareness/education)
- [ ] Sub-context: Middle of Funnel (evaluation/comparison)
- [ ] Sub-context: Bottom of Funnel (conversion-focused copy, short-form persuasion, demo CTAs, proof points)

### Content Strategy: Articles
- [ ] Create parent item
- [ ] Add Purpose field
- [ ] 2 sub-contexts (define structure)

### Content Strategy: Bio Pages
- [ ] Create item
- [ ] Add Purpose field

### Key Value Propositions (Global)
- [ ] Create item with scope: Global
- [ ] Include: 90%+ adoption rate
- [ ] Include: Zero expense reports
- [ ] Include: Complete spend visibility

### Sales Pitch Deck v7
- [ ] Create item
- [ ] Use case tag: "Writing Words"
- [ ] Attachment: PPTX file (or reference)
- [ ] 1 exclusion (define)
- [ ] Content: Enterprise security competitive differentiator
- [ ] Content: "Outperforms [Competitor X] by 40%" claim (for compliance testing)
- [ ] Metadata: Source (Sales team), last updated date, lifecycle info

### Metrics / Analytics KPIs
- [ ] Create parent item
- [ ] Sub-context 1 with External Context designation
- [ ] Sub-context 2 with External Context designation
- [ ] Benchmark: Bounce rate < 45%
- [ ] Benchmark: Demo request rate > 3.5%
- [ ] Benchmark: Engaged sessions > 60%

### Personas & Ideal Customer Profiles
- [ ] Create parent item
- [ ] Sub-context: Travel Managers
- [ ] Sub-context: CFOs/Controllers (Finance Managers)
- [ ] Sub-context: Program Administrators

### SEO/AEO Guidelines
- [ ] Create item (if needed for Test Cases 3.1, 3.2)

---

## Pages to Create (Before States)

### Page 1: FinDrop Travel — Product Page
- [ ] Create new Canvas page
- [ ] Set title: "FinDrop Travel — Product Page"
- [ ] Add hero component with heading: "Business Travel Your Employees Love"
- [ ] Add section with placeholder feature card text about booking and policy enforcement

### Page 2: Book a Demo — LinkedIn Campaign Landing Page
- [ ] Create new Canvas page
- [ ] Set title: "Book a Demo — LinkedIn Campaign Landing Page"
- [ ] Leave content empty (title only)

### Page 3: FinDrop Privacy Policy
- [ ] Create Canvas page
- [ ] Set title: "FinDrop Privacy Policy"
- [ ] Paste legal-approved privacy policy text (formal legal language)
- [ ] Content should cover: data collection, user rights, retention policies
- [ ] Leave unformatted (no proper headings or bullet formatting)

### Page 4: FinDrop Cards — Product Page
- [ ] Create or use existing product page
- [ ] Hero heading: "Create and Control Corporate Cards in Seconds"
- [ ] Include feature cards
- [ ] Include testimonial section
- [ ] Include CTA section

### Page 5: Enterprise Features Page (for Workflow B)
- [ ] Create Canvas page
- [ ] Hero leads with: "enterprise features platform" messaging
- [ ] Subhead lists features (no "enterprise security" keyword)
- [ ] No security differentiator content
- [ ] URL: /enterprise-features (for GA agent reference)

### Page 6: Sarah Chen Bio (Content Mismatch Test)
- [ ] No page needed — content is pasted into empty Canvas page during test

---

## Supporting Content to Prepare

### FinDrop Travel Copy Deck (for Test Cases 1.1-1.5)
- [ ] Create markdown document with:
  - [ ] Hero: headline, body, 2 CTAs
  - [ ] Problem Section: heading, body narrative
  - [ ] Feature Benefits: 6 items with headline + description
  - [ ] Finance Team Section: 3 cards for secondary audience
  - [ ] Testimonials: 2 with quote, name, title, company
  - [ ] How It Works: 4 steps
  - [ ] FAQ: 6 Q&A pairs
  - [ ] CTA: heading, body, 2 CTAs

### LinkedIn Ad Copy (for Test Case 0.3)
- [ ] Prepare ad copy:
  ```
  Finance teams are drowning in expense reports
  The average company wastes $12,000 per employee annually on manual expense processing.
  See how virtual corporate cards can eliminate 90% of your expense admin

  Headline: Stop Drowning in Manual Expense Processing
  Description: Live walkthrough with instant ROI calculation
  Button: Request Demo
  ```

### Sarah Chen Bio Content (for Test Case 1.5)
- [ ] Prepare bio content:
  - VP of Product at FinDrop
  - Career history: Square (8 years), McKinsey (3 years)
  - Education: Wharton MBA, MIT BS Computer Science
  - Speaking, awards, personal interests

### Sales Pitch Deck Content Files
- [ ] Create `findrop-sales-deck-before.md` (version without security differentiator)
- [ ] Create `findrop-sales-deck-after.md` (version with enterprise security differentiator)

---

## Related Pages for Cross-Linking (Test Case 3.1)
- [ ] Ensure 3 existing pages that discuss related topics:
  - [ ] Expense management page
  - [ ] Corporate cards page
  - [ ] Platform overview page

---

## Media Library Setup
- [ ] Tag illustration assets appropriately
- [ ] Tag photography assets appropriately
- [ ] Ensure both styles available for:
  - [ ] Hero images (travel/workplace)
  - [ ] Feature card images
  - [ ] CTA backgrounds
  - [ ] Professional people shots (for photography style)

---

## GA Agent / Background Agent Setup (Workflow B)
- [ ] Configure background GA agent to run on Cron
- [ ] Connect Google Analytics data source
- [ ] Link CCC Metrics / Analytics KPIs benchmarks
- [ ] Configure email notification for benchmark failures
- [ ] Set up summary UI in Drupal for flagged URLs
