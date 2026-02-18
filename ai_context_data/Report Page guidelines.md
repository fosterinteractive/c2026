Description: Instructions for creating a Report page

Purpose: These guidelines outline how to construct a "Gated Content" or "Research Report" page. The layout focuses on high-value data previewing, a detailed table of contents, and a prominent lead generation form.

Content: 
These guidelines outline how to construct a "Research Report" page. The layout focuses on high-value data previewing, a detailed table of contents, and a prominent lead generation form.

Follow this structure, but Do not mention component names (Eg: 3 cards, Headline: Get in touch etc..). Use actual content

---

## 1. Page Purpose Overview
The primary goal is **Lead Generation**. The page promotes a downloadable asset (e.g., a whitepaper or research report). It must hook the user with statistics, outline the value of the content, and end with a clear exchange of value (data for document).

**Key Visual Traits:**
*   **Asymmetric Layouts:** Uses side-by-side heroes and form sections.
*   **Data Highlight:** Uses framed cards to make statistics pop.
*   **Constrained Widths:** Uses 80% width containers to keep long-form text readable.

---

## 2. Hero Section (Side-by-Side)

### Purpose
To display the digital asset (book cover/report) alongside the title and primary "Download" action.

### Component Structure
*   **Container:** `Hero side-by-side`
*   **Hero Slot:**
    1.  `Heading` ( Eyebrow/Year)
    2.  `Heading` (Main Title)
    3.  `Text` (Summary)
    4.  `Button` (Link to report).

### Prop values
*   **Hero Container:**
    *   **Image Position:** Right (Places the book cover/asset to the right).
    *   **Aspect ratio:** 16x9 (Ideal for report covers).
    *   **Image border radius:** Large.
    *   **Padding Top and Bottom:** 32
    *   Do not select any value for background color
*   **Headings (The "Eyebrow" Pattern):**
    *   **Top Heading:** Heading level 4, Text size value 'Default' and Text color: Default text.
    *   **Main Heading:** Heading level 1, Text size value 'Default'.
    *   **Alignment:** `left`.
    *   Keep text size as default here to properly reflect heading level. Do not choose any other value.
*   **Button:**
    *   **Variant:** `primary`.
    *   **Icon:** `arrow-right`.
    *   **Label:** Strong action (e.g., "Download Free Report").

---

## 3. Key Statistics Section (Grid)

### Purpose
To provide "Teaser Data"—high-impact stats found inside the report to prove its value immediately.

### Component Structure
*   **Container:** `Section`
*   **Header Slot:** `Heading` (Must be Level 2).
*   **Main Slot:** 3x  Image cards.

### Prop values
*   **Section:**
    *   **Margin Top:** `128` (Distinct separation from Hero).
    *   **Grid layout:** `33-33-33`.
    *   **Width:** `100%`.
    *   **Show header region:** enabled.
*   **Heading:** Level 2, Text size value 'Default', center aligned. Do not mention 3 cards in heading text.
*   **Cards (Data Highlight Style):**
    *   **Style:** `framed` (Crucial for making the stat stand out).
    *   **Orientation:** `vertical`.
    *   **Center Text:** `true`.
    *   **Heading Level:** `3`.
    *   **Heading Text:** Use the number/stat here (e.g., "87%").
    *   **Media:** Use abstract data visualizations or office photography.

---

## 4. "What's Inside" / Chapter List (Stacked Groups)

### Purpose
A Table of Contents that breaks down the report into digestible chapters, reducing the "risk" of downloading by showing exactly what the user gets.

### Component Structure
*   **Container:** `Section`
*   **Header Slot:** `Heading`.
*   **Main Slot:** A stack of `Group` components.
    *   **Inside each Group:** `Heading` + `Text`.

### Prop values
*   **Section:**
    *   **Width:** `80%` (Constrained width prevents text lines from becoming too long).
    *   **Grid layout:** `100` (Items stacked vertically).
    *   **Margin Top and Margin bottom:** `96`.
*   **Section Header: Heading component**
    *   **Text Size:** Level 2, Text size value 'Default', center aligned.
*   **Groups (Chapter Rows):**
    *   **Background:** `primary` (Creates a colored bar for each chapter).
    *   **Padding:** `sm`.
    *   **Radius:** `lg`.
    *   **Flex Direction:** `column`.
*   **Chapter Content:**
    *   **Heading:** `Level 3`, `text size: 3xl` Text color: Default text
    *   **Text:** `text_size: 14px`, default color (Smaller body text for descriptions).
    *   Do not use  inverted text color in heading and text here

---

## 5. Download / Lead Gen Section (Split Form)

### Purpose
The functional conclusion of the page. This collects user data in exchange for the asset.

### Component Structure
*   **Container:** `Section`
*   **Header Slot:** `Heading` + `Text` (Social proof, e.g., "Join 2,000 others").
*   **Main Slot:**
    1.  `Group` (Left Column) -> Contains `Webform Block` + `Text` (Privacy notice).
    2.  `Image` (Right Column) -> Visual reinforcement (Cover art).

### Prop values
*   **Section:**
    *   **Width:** `80%`.
    *   **Columns:** `50-50` (Form on left, Image on right).
    *   **Margin Block Start:** `96`.
*   **Form Group:**
    *   **Items Align:** `center`.
    *   **Flex Direction:** `column`.
*   **Privacy Text:**
    *   **Text Size:** 12 px
    *   **Style:** Italicized (using `<em>` tags in the HTML) to denote it is fine print.
*   **Image:**
    *   **Size:** Aspect ratio`4x3`.
    *   **Radius:** `small`.