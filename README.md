# Driesnote Chicago 2026

1. Install DDEV following the [documentation](https://ddev.com/get-started/)
2. Open the command line and `cd` to the root directory of this project
3. Set up your environment:
```shell
cp .env.template .ddev/.env
```
Then open `.ddev/.env` and set your OpenAI key in the file.

4. From the root of the project, run:
```shell
ddev demo-setup
```

## Steps to recreate the demo

1. **Create the product page from the copy deck** — Open Canvas AI and prompt:

   > "Here's the copy for the new FinDrop product page — create this product page from the copy below"

   Paste the contents of [travel-page-text-only-v2.md](ai_context_data/website_copy/travel-page-text-only-v2.md).

2. **Provide audience + goal context** when Canvas asks:

   > "Audience is Travel Managers. Goal is to get whitepaper downloads."

3. **Swap the hero image** — prompt:

   > "Switch the hero to photography with Cindy Liu."

4. **Generate an FAQ block** — prompt:

   > "Use the content in section 'Learn How We Make Travel Expense Management Easy' to write a new FAQ block above the CTA. Use the current content and rewrite the heading as questions."

5. **Add internal cross-links** — prompt:

   > "Review the page and add internal cross links."

6. **Generate AEO schema** — prompt:

   > "Create an AEO schema for this page."

7. **Start a new session** (refresh the browser to simulate time passing).

8. **Trigger the performance agent** — prompt:

   > "This page is underperforming against its Google Analytics goals. Not performing to bounce threshold. Review the page layout and provide suggestions to improve the failing metric(s)."

9. **Edit the CTA** — prompt:

   > "Edit the CTA Title to 'Go live in 10 business days, not 6+ months like with SAQ'."

10. **Pre-publish review** — prompt:

    > "Please review before I publish live."

### Reference material

- **Full prompt script:** [driesnote-prompts.md](ai_context_data/website_copy/driesnote-prompts.md)
- **Alternate copy decks:** [product-page-findrop-cards-v2.md](ai_context_data/website_copy/product-page-findrop-cards-v2.md), [product-page-findrop-expense-v2.md](ai_context_data/website_copy/product-page-findrop-expense-v2.md), [travel-page-with-strategy-specs-v2.md](ai_context_data/website_copy/travel-page-with-strategy-specs-v2.md)
- **Brand + content context:** [FinDrop Brand Guidelines.md](ai_context_data/FinDrop%20Brand%20Guidelines.md), [FinDrop Key Facts & Value Propositions.md](ai_context_data/FinDrop%20Key%20Facts%20&%20Value%20Propositions.md)
- **Full test-scenario walkthroughs:** [test_scenarios/](ai_context_data/test_scenarios/)

## Exporting Content

After making changes in Drupal, use these commands to export content back into the recipes:

| Command | What it exports |
|---|---|
| `ddev export-all-content` | All content entities (canvas pages, nodes, media, menu links, taxonomy terms) |
| `ddev export-canvas-pages` | Canvas pages only |
| `ddev export-media` | Media and file entities only |
| `ddev export-ai-context` | AI Context items and usage records |
| `ddev backup` | Database and files directory snapshot to `.backups/` |

