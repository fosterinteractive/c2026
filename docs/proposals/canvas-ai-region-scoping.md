# Canvas AI Region Scoping: Native Support for Component-Level Requests

**Date:** March 2026
**For:** Foster Interactive (Canvas Maintainers)
**Status:** Technical Proposal for Discussion
**Prototype:** Working `canvas_ai_scoping` module with measured results

---

## Problem Statement

Canvas AI sends the entire page layout JSON and all component prop values to the LLM on every request, even when the user is editing a single component.

### Current Behavior

When a user selects a component to edit:

1. Frontend `AiWizard.tsx` calls `transformLayout()`, which serializes the full page tree
2. `textPropsMapString` includes every component's props across the entire page
3. `CanvasBuilder.php` stores the complete layout in tempstore on every request
4. Sub-agents re-read the full layout from tempstore on each loop iteration

### Measured Cost

On a FinDrop Travel demo page (15 components across 3 regions):

| Operation | Total tokens | Layout portion |
|-----------|-------------|---------------|
| Heading text edit | 111K | ~2.9K (layout JSON: 12,438 bytes) |
| Full page build | 253K | ~2.9K |

Layout JSON is **~10% of total operation tokens**. System prompt, ai_context items, and chat history dominate the remaining ~90%. Region scoping addresses the layout portion; other optimizations (loop-aware context injection, deterministic edit bypass) address the larger cost centers.

---

## Proposed Solution: Progressive Region Scoping

Implement native, opt-in **region-level scoping** in Canvas:

1. When `active_component_uuid` is present, send only the relevant region layout to the LLM
2. Include a lightweight "region index" (region names + top-level component summaries, ~50-200 bytes) for cross-region awareness
3. Keep full-layout mode for `template_builder_agent` and when no component is selected
4. Zero breaking changes to existing behavior

### What Gets Sent (Scoped vs. Current)

**Current (Full Layout Mode):**
```json
{
  "regions": {
    "hero": {
      "nodePathPrefix": [0],
      "components": [
        { "name": "sdc.byte_theme.hero", "uuid": "...", "propValues": { ... }, "slots": [] }
      ]
    },
    "content": {
      "nodePathPrefix": [1],
      "components": [
        { "name": "sdc.byte_theme.heading", "uuid": "...", "propValues": { ... }, "slots": [] },
        { "name": "sdc.byte_theme.card-grid", "uuid": "...", "propValues": { ... },
          "slots": [{ "name": "cards", "components": [ ... 5 nested cards ... ] }]
        },
        "... 10 more top-level components ..."
      ]
    },
    "footer": { "..." }
  }
}
```

**Proposed (Section Scoped Mode — when editing the heading):**
```json
{
  "region_index": [
    { "region": "hero", "node_path_prefix": [0], "components": [{ "name": "sdc.byte_theme.hero", "uuid": "..." }] },
    { "region": "content", "node_path_prefix": [1], "components": [{ "name": "sdc.byte_theme.heading", "uuid": "..." }, "..."] },
    { "region": "footer", "node_path_prefix": [2], "components": [{ "name": "sdc.byte_theme.footer", "uuid": "..." }] }
  ],
  "regions": {
    "hero": { "nodePathPrefix": [0], "components": [], "_note": "1 component(s) omitted (outside active region)" },
    "content": {
      "nodePathPrefix": [1],
      "components": [
        { "name": "sdc.byte_theme.heading", "uuid": "...", "propValues": { ... }, "slots": [] },
        { "name": "sdc.byte_theme.card-grid", "uuid": "...", "_note": "sibling section (details omitted)" },
        "... other siblings summarized ..."
      ]
    },
    "footer": { "nodePathPrefix": [2], "components": [], "_note": "1 component(s) omitted (outside active region)" }
  }
}
```

---

## Measured Results

Our `LayoutScopingSubscriber` prototype implements section-level scoping via `BuildSystemPromptEvent`:

| Metric | Before | After | Reduction |
|--------|--------|-------|-----------|
| Layout JSON | 12,438 bytes | 2,611 bytes | 79% |
| Total operation tokens (heading edit) | ~125K | ~111K | ~11% |

**Why 79% layout reduction yields only ~11% total token reduction:** Layout JSON is a fraction of total cost. System prompt instructions (~14K), ai_context items (~86K on loop 0), and tool definitions (~12K) dominate. Region scoping is one layer of a multi-layer optimization strategy.

Combined with other optimizations measured on the same page:

| Optimization | Standalone effect | Cumulative |
|--------------|------------------|------------|
| Layout scoping (this proposal) | -11% (layout) | 101K → ~90K |
| Loop-aware context injection | -52% (ai_context on loops 1+) | → 48K |
| Context item filtering | -35% (non-edit ai_context) | → 31K |
| Deterministic edit bypass | -100% (qualifying edits) | → 0K |

---

## Implementation Approach

### Files Modified

#### 1. Frontend: `ui/src/components/aiExtension/AiWizard.tsx`

- Add `scope` parameter to `transformLayout()` — when `activeComponentUuid` is present, serialize only the containing region
- Filter `textPropsMapString` to scoped region
- Generate region index from full layout before scoping

#### 2. Backend: `modules/canvas_ai/src/Controller/CanvasBuilder.php`

- Accept and validate `scope` parameter (defaults to `'page'` for backward compatibility)
- Store scoped layout in tempstore conditionally

#### 3. Tempstore: `modules/canvas_ai/src/CanvasAiTempStore.php`

- Add `REGION_INDEX_KEY` constant and `setRegionIndex()`/`getRegionIndex()` methods

#### 4. Validation Tools

- `SetAIGeneratedTemplateData.php`: Read region index for boundary validation
- `MoveComponentInPage.php`: Use region index for cross-region boundary detection
- `GetCurrentLayout.php` and `UpdateComponentData.php`: No changes needed

### No Changes Required

- Title/metadata agents (don't use layout context)
- Template builder (uses full layout, unaffected)
- Component schema validation (unchanged)

---

## Edge Cases & Handling

| Scenario | Behavior |
|----------|----------|
| Cross-region move ("move this to footer") | Region index provides all region names + paths; agent can construct move |
| Template builder requests | Always receives full layout (no scoping applied) |
| No component selected | Full layout sent (backward compatible) |
| Nested components | Scoped layout includes full subtree of containing section |
| Component not found in any region | Full layout (fail-open) |

---

## Our Workaround

We built `canvas_ai_scoping` — a custom Drupal module that subscribes to `BuildSystemPromptEvent` and scopes layout data before it reaches the LLM. This works without modifying Canvas but has limitations:

- Only scopes data already in the system prompt (can't scope data missing from it)
- Uses string replacement on serialized layout JSON (fragile)
- Can't influence frontend layout serialization without Canvas patches
- Requires custom code per deployment

Native Canvas support would be more robust and benefit all Canvas users.

---

## For Discussion

1. **Scope inference:** Should scoping be automatic when `active_component_uuid` is present, or opted in via a separate `scope` parameter?
2. **Region index ownership:** Should the region index be generated by the frontend (where the layout tree lives) or by the backend (closer to the agent)?
3. **Envelope mode:** Our prototype also implements a component-level envelope (only the selected component + neighbors + section metadata). Should Canvas support this as a more aggressive scoping level, or is section-level sufficient for the agent?
4. **Backward compatibility:** Is an opt-in parameter sufficient, or does this need a feature flag in Canvas settings?

### Proposed Path Forward

1. Discuss architecture with Canvas maintainers
2. Contribute the `LayoutScopingSubscriber` as a reference implementation with test coverage
3. Iterate on frontend integration based on maintainer feedback
