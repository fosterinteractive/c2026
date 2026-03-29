---
name: canvas-webapp-testing
description: Playwright testing configuration for Canvas AI chatbot interactions on the FinDrop demo site
triggers:
  - test canvas
  - test ai chatbot
  - playwright canvas
  - test demo
---

# Canvas Webapp Testing

Configures Playwright-based testing for the FinDrop Canvas AI chatbot. Use `webapp-testing` skill with these project-specific patterns.

## Prerequisites

- DDEV running: `ddev start`
- Site installed: `ddev demo-setup` or equivalent
- Playwright MCP available
- Browser viewport: 1440x900 minimum (Canvas requires >= 1024px wide)

## Getting a Session

```bash
# Get a fresh login URL
ddev drush uli --uri=https://c2026.ddev.site

# Navigate to it via Playwright, then go to Canvas editor
```

## Canvas Editor URLs

| URL | Purpose |
|-----|---------|
| `/canvas/editor/canvas_page/{id}` | Edit existing Canvas page |
| `/admin/content/pages` | List all Canvas pages |

To create a new test page:
```bash
ddev drush php:eval "\$p = \Drupal::entityTypeManager()->getStorage('canvas_page')->create(['title' => 'Test Page']); \$p->save(); echo \$p->id();"
```

## AI Panel Interaction Pattern

1. **Open AI Panel**: Click button with name "Open AI Panel" in the top toolbar
2. **Type prompt**: Fill the textbox with placeholder "Build me a ..."
3. **Submit**: Press Enter
4. **Wait**: The AI shows "Thinking" or agent-specific status ("Designing the page", "Drupal Canvas SEO Agent working")
5. **Check result**: Wait for "Thinking" to disappear, then snapshot or screenshot

## Key Selectors

- AI Panel toggle: `button[name="Open AI Panel"]` or `button[name="Close AI Panel"]`
- Chat input: `textbox[name="Build me a ..."]`
- Status indicators: Text content "Thinking", "Designing the page", "Finding components to place", "Drupal Canvas SEO Agent working"

## Canvas API Endpoints (for network interception)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/admin/api/canvas/token` | POST | CSRF token |
| `/admin/api/canvas/ai` | POST | Send AI prompt |
| `/admin/api/canvas/ai-progress` | GET | Poll agent progress |
| `/canvas/api/v0/layout/{type}/{id}` | PATCH | Apply layout changes |

## Checking Results

After AI interactions:
```bash
# Check prompt logs
ddev drush watchdog:show --type=ai --count=20

# Check if Schema.org was generated
ddev drush php:eval "\$p = \Drupal::entityTypeManager()->getStorage('canvas_page')->load({ID}); echo \$p->get('schema_jsonld')->value;"

# Check page title was set
ddev drush php:eval "\$p = \Drupal::entityTypeManager()->getStorage('canvas_page')->load({ID}); echo \$p->label();"
```

## Common Issues

- **"Browser window too narrow"**: Resize to 1440x900 before navigating to Canvas editor
- **Refs go stale**: After AI completes, snapshot refs change. Re-navigate or take a fresh snapshot.
- **Media search fails**: Requires OpenAI key for embeddings. Steps involving image search degrade gracefully.
- **Deep-chat shadow DOM**: The AI chat input is rendered by a deep-chat web component. Playwright's accessibility snapshot can see it, but `document.querySelector` cannot without traversing shadow roots.
