# ADR-005: Layout Data Must Be Scoped to the Operation Target

**Status:** Accepted (proven by implementation)
**Date:** 2026-03-27
**Context:** FinDrop Canvas AI token efficiency audit (WS1)

## Decision

When an AI operation targets a specific component (identified by `active_component_uuid`), the layout data sent to the LLM must be scoped to that component's containing section, not the entire page.

A lightweight "region index" (section names + node path indices) provides awareness of the full page structure without sending full layout data.

## Context

Canvas currently serializes the entire page layout — all components, all props, all sections — on every request. For a 30-component page:

| Scope | Layout size | Tokens |
|-------|------------|--------|
| Full page | 8-12KB | 2,000-3,000 |
| Section (containing target) | 1-2KB | 250-500 |
| Component only | 200-400B | 50-100 |
| Region index | ~50B | ~10 |

**Measured reduction:** Section-level scoping achieved 79% layout reduction (from ~2,800 tokens to ~600 tokens per call).

**However:** Layout is only ~12% of per-call cost. The 79% layout reduction translated to only 12% overall token savings (125K → 111K). This ADR is necessary but insufficient alone — it must combine with ADR-001 through ADR-004 for meaningful impact.

## Implementation

### Proven: Custom module approach (canvas_ai_scoping)

Our `LayoutScopingSubscriber` subscribes to `BuildSystemPromptEvent` and:
1. Detects edit operations via `active_component_uuid` in tempstore
2. Parses the layout JSON from the system prompt
3. Identifies the section containing the target component
4. Replaces the full layout with the scoped section + region index

**Limitations of the custom approach:**
- Operates on string replacement of already-serialized layout (fragile)
- Cannot scope data that isn't in the system prompt (e.g., tempstore layout)
- Cannot influence frontend serialization

### Proposed: Native Canvas support

Canvas should scope layout data at the source:
1. Frontend (`AiWizard.tsx`): when `activeComponentUuid` is set, serialize only the containing section
2. Backend (`CanvasBuilder.php`): store scoped layout in tempstore
3. Tools (`GetCurrentLayout.php`): return scoped layout when scope is active

This is cleaner, more robust, and eliminates string surgery on serialized JSON.

## Consequences

- Full layout mode remains the default (backwards compatible)
- Template builder always gets full layout (it operates on the whole page)
- Cross-region operations (moves) use the region index to identify targets
- Nested components include the full subtree of the selected component

## Evidence

| Scenario | Tokens | Layout reduction | Overall reduction |
|----------|--------|-----------------|-------------------|
| Baseline (no scoping) | 125,607 | 0% | 0% |
| Region scoping (3-4 sections) | 125,607 | 13% | ~0% |
| Section scoping (1 section) | 111,004 | 79% | 12% |
| Section + context strip (attempted) | 108,839 | 79% | 13% |

All measurements on FinDrop DDEV, March 2026.
