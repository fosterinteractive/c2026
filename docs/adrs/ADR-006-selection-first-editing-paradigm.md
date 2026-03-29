# ADR-006: Selection-First Editing Paradigm

**Status:** Proposed
**Date:** 2026-03-28
**Context:** FinDrop Canvas AI efficiency — session 5 strategic discussion

## Decision

Canvas AI operations must treat **user selection as the primary input** and only invoke the AI agent chain when the operation requires reasoning beyond what the selection + explicit input already provides.

Selection narrows the context funnel progressively. Each level of user specificity pushes the operation further toward deterministic execution.

## Context

The Canvas AI editing flow currently treats the AI chat panel as the starting point for all operations. Whether the user wants to change a heading to specific text or completely restyle a section, the system routes through the same agent chain: orchestrator → page_builder_agent → 3-5 LLM loops → tool call.

But Canvas already supports component selection (`active_component_uuid`). When a user clicks a component, the system knows:
- The component UUID
- Its type (hero, card, button, etc.)
- Its full prop schema (which properties exist, their types)
- All current prop values

This information is sufficient to resolve most editing operations deterministically — the AI's primary job in a typical edit is figuring out *what the user is referring to*, which the selection already answers.

### Progressive Selection Narrows Context

| User action | System knows | AI figures out | Context needed |
|-------------|-------------|----------------|----------------|
| No selection, types in chat | Nothing | Target + prop + intent + value | Full page (~20K tokens) |
| Selects a component | UUID, type, schema, values | Prop + intent + value | Component envelope (~500 tokens) |
| Selects component + focuses a prop | UUID, type, prop, current value | Intent + value | Nearly nothing (~100 tokens) |
| Focuses prop + types literal value | Everything | Nothing | Zero — deterministic |

### Projected Impact (estimated — not yet measured)

A typical 20-edit content session might break down as (these ratios are estimated, not measured — real usage telemetry is required before citing these numbers externally):

| Edit type | Assumed % | Current cost | Selection-first cost | Assumption basis |
|-----------|----------|-------------|---------------------|-----------------|
| Direct prop edits | ~60% (12 of 20) | 12 × 111K = 1.33M | 0 tokens | P4 deterministic bypass |
| Simple AI-assisted | ~25% (5 of 20) | 5 × 111K = 555K | 5 × 15-25K = 75-125K | P1+P2 with component envelope (speculative — envelope architecture not yet built) |
| Complex creative | ~15% (3 of 20) | 3 × 111K = 333K | 3 × 45-55K = 135-165K | P1+P2+P3 per strategy estimates |
| **Session total** | | **2.2M** | **210-290K** | |

**Projected reduction: ~87-90% under optimistic assumptions.**

**Sensitivity analysis — the edit-type split is the dominant variable:**

| Split (direct/simple/complex) | Session total (selection-first) | Reduction |
|-------------------------------|--------------------------------|-----------|
| 60/25/15 (optimistic) | ~210-290K | ~87-90% |
| 40/30/30 (moderate) | ~510-660K | ~70-77% |
| 20/30/50 (pessimistic) | ~810-1.03M | ~53-63% |

Even the pessimistic scenario (only 20% deterministic) delivers >50% reduction. The value of the selection-first paradigm holds across assumptions, but the headline number varies significantly. **Do not cite a specific percentage in upstream issues until the edit-type split is measured.**

## Consequences

### For Canvas UX

The AI chat panel becomes the **escalation path**, not the default editing interface. The primary flow is:

1. User clicks a component (selection)
2. A lightweight prop panel shows editable fields with current values
3. User can:
   - **Edit directly** in the prop panel → deterministic, 0 tokens, instant
   - **Type in AI chat** → system uses selection as context, AI only interprets intent with ~500 token component envelope
   - **Ask something creative** → AI gets component + neighbors + brand voice (~2-4K tokens)

### For Context Envelopes (ADR extension)

Selection determines which context envelope layer is needed:

| Layer | Content | Tokens | Triggered by |
|-------|---------|--------|-------------|
| Component | Full props + schema for selected component | ~200-500 | Any selection |
| Neighbors | Previous/next component type + key text | ~100-200 | AI-assisted edits |
| Section | Section name, purpose, component count | ~50 | Operations affecting section |
| Page outline | Section names + types (no prop data) | ~100 | Cross-section operations |
| Brand | Relevant brand/voice context | ~1-2K | Text content or style edits |

Total for a typical AI-assisted edit: ~1-3K tokens vs. ~20K today.

### For the Deterministic Surface Area

System design should maximize operations that become deterministic through selection:

- **Templates as deterministic scaffolds**: Selecting a template resolves structure. Populating content slots becomes prop-by-prop deterministic inserts.
- **Style tokens and content variables**: `{{brand_color}}`, `{{product_name}}` propagate changes deterministically across all referencing components.
- **Component presets**: Inserting "Hero Variant B" from a catalog is deterministic. Individual prop edits afterward are deterministic if user provides explicit values.
- **Batch operations**: "Apply this heading font to all sections" — once the pattern is defined (which prop, which value), applying across N components is N deterministic updates.

### The Deterministic Spectrum

```
Fully Deterministic          Hybrid                    Fully AI
─────────────────────────────────────────────────────────────────
Template slot fill      "Restyle to match brand"    "Build me a page
Direct prop edit        "Make CTA more compelling"   about our product"
Style token change      Image selection (RAG)        "Redesign this
Batch prop apply        Content tone adjustment       section entirely"
Variable propagation    Layout restructuring
Component preset insert Cross-section moves
```

Goal: push as many operations left as possible through system design. Every operation that moves left = 0 tokens consumed.

### For Upstream Proposals

This ADR strengthens all existing proposals:

- **P4** is reframed from "detect simple edits" to "leverage selection to make edits deterministic." The classification is no longer heuristic — it's structural. If the user selected a component and provided an explicit value, it's deterministic.
- **P1** (region scoping) becomes the first step toward context envelopes — scoping to section level. The full envelope model scopes to component level with semantic neighbor summaries.
- **P2** (loop-aware context) matters less when per-call context drops from 20K to 1-3K, but still prevents duplication of even the small envelope.
- **P3** (history windowing) compounds with envelopes — smaller per-call context means history grows slower.

## Risks

- **User adoption**: Content authors accustomed to chat-first editing may resist the selection-first flow. Mitigation: the chat panel remains available; selection-first is the *optimized* path, not the only path.
- **Complex selection states**: Multi-component selection, nested component selection, selection within rich text fields — each requires clear UX for what "selected" means. Canvas already handles single-component selection; extending to other states needs design work.
- **Edit/add ambiguity persists**: A selected component + "add a testimonial section below this" is an add operation, not an edit. The system must still distinguish these, though selection narrows the spatial reference.
- **60/25/15 split is estimated**: The session breakdown needs validation against real usage data. Savings range from 53% (pessimistic: 20/30/50 split) to 90% (optimistic: 60/25/15 split). See sensitivity analysis in the Projected Impact section.

## Alternatives Considered

**"Just optimize the AI path"** (current ADR-001 through ADR-005 approach) — Reduces cost by 35-60% for AI operations but doesn't address the fundamental issue: most operations don't need AI at all. Necessary but insufficient.

**"Remove the AI chat panel for editing"** — Too aggressive. Creative operations genuinely benefit from LLM reasoning. The AI should be available, just not the default path for deterministic work.

**"Use a smaller model for simple edits"** — Still sends tokens, still adds latency. A deterministic path is faster and free.

## Open Questions

1. What percentage of real-world Canvas editing sessions are direct prop edits vs. creative AI-assisted? Need usage telemetry to validate the 60/25/15 estimate.
2. Does Canvas's frontend architecture support a lightweight prop panel alongside the AI chat panel? Need to assess the UI implementation cost.
3. How should multi-component selection work? Select multiple → batch deterministic update? Or select multiple → AI reasons about the group?
4. Should the context envelope layers be configurable per site (some sites may want brand context on every operation)?
