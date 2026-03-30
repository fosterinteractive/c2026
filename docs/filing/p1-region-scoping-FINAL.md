# P1: Region Scoping — Ready to Post

**Target:** Comment on [canvas #3545816](https://www.drupal.org/project/canvas/issues/3545816)
**Type:** Comment on existing issue (not a new issue)

---

## Comment body (copy below this line)

---

Following up on the metadata optimization discussion here. We've built a complementary approach: horizontal scoping that reduces which components the agent sees during edit operations, rather than reducing metadata per component.

### The problem

When editing a single heading, the page builder agent receives the full page layout — every region, every section, every nested component with all props and slots. On a 15-component demo page, the full layout JSON is ~11.5K bytes (~2,900 tokens). The agent only needs the section containing the selected component.

### Approach

A `BuildSystemPromptEvent` subscriber (priority -10, after ai_context at 0) that runs when `active_component_uuid` is set:

1. Identifies which region contains the selected component
2. Identifies which top-level section (within that region) contains it
3. Replaces the full layout with a scoped version:
   - Active section: full detail (all props, slots, nested components)
   - Sibling sections in same region: name + UUID only
   - Other regions: component count only
   - Region index: lightweight map of all regions (~200 bytes) for cross-region awareness

Fail-open design: if the selected component can't be located in the layout, the subscriber falls through to the full layout. Never degrades the editing experience.

### Known limitation

The subscriber replaces layout JSON in the system prompt via string matching. If the serialization format between the tempstore and the prompt differs (whitespace, key ordering), the match fails silently (falls through to full layout). This works but is fragile.

Would a structured layout accessor on the event be a cleaner path? Something like a `layout_data` token via the existing `setToken()`/`getTokens()` API, so subscribers can work with parsed data rather than doing string surgery on the prompt? We prototyped this using the token bag and it works without requiring changes to `BuildSystemPromptEvent` itself.

### Measured results (N=1 heading edit, 15-component demo page)

Layout is approximately 10% of total per-loop cost — system prompt instructions and ai_context items dominate the other 90%. Layout scoping yields a modest reduction on its own but compounds with other optimizations:

| Layer | What it addresses | Measured savings |
|-------|-------------------|-----------------|
| Loop-aware context injection (separate issue for ai_context) | ai_context re-injected every loop | 52% total |
| Region scoping (this) | Layout sent for irrelevant components | ~10% of per-loop cost |

### How this complements #3545816

- #3545816 reduces tokens per component description (vertical optimization)
- Region scoping reduces which components are sent (horizontal optimization)
- Applied together: only the relevant components in the relevant section, with compressed metadata for each

### Cross-region edits

Scoped layout preserves cross-region awareness via the region index but limits cross-region component detail. Operations requiring full cross-section context ("match the style of the hero section") would need the agent to request the full layout via existing tools, or would fall through to an unscoped prompt.

### Prototype

Working `LayoutScopingSubscriber` in a custom module. Uses `CanvasAiTempStore` to read the current layout and `BuildSystemPromptEvent` to replace layout JSON in the system prompt. Unit tests covering region index generation, section scoping, nested components, and edge cases.

Happy to share the code or contribute a patch if this direction is useful.

---

## Filing notes (do not post)

- This is a COMMENT on an existing issue, not a new issue
- Frame the layout_data token as a QUESTION ("would a structured accessor be cleaner?"), not a prescription
- Don't oversell the 10% savings — acknowledge it's modest, emphasize compounding
- If asked about the other layers (loop-aware, deterministic): "those are separate proposals for ai_context and canvas_ai respectively"
- The subscriber lives in canvas_ai, not canvas core — if they ask where this belongs, say "canvas_ai"
