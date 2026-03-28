# ADR-007: Maximize Deterministic Surface Area Through System Design

**Status:** Proposed
**Date:** 2026-03-28
**Context:** FinDrop Canvas AI efficiency — session 5 strategic discussion

## Decision

System design for AI-assisted page builders must **maximize the number of operations that execute deterministically** (without LLM involvement). The AI agent chain is the escalation path for ambiguous or creative operations, not the default path for all editing.

This is achieved by:
1. Treating user selection as context that resolves ambiguity (ADR-006)
2. Designing content structures (templates, presets, tokens) that make operations deterministic by default
3. Breaking pages into independently-editable units with minimal cross-unit context dependencies

## Context

A Canvas page with 30 components currently sends 100% of editing operations through the AI agent chain, regardless of complexity. But most editing operations on established pages are deterministic:

- Changing text to a specific value
- Updating a color, URL, or image
- Reordering components within a section
- Applying a style preset
- Inserting a pre-defined component type
- Propagating a content or style variable change

These operations have unambiguous targets, explicit values, and predictable outcomes. They don't benefit from LLM reasoning — they just need a prop update, a position swap, or a template insert.

The current architecture treats the LLM as the universal interface for all operations. This decision establishes that **deterministic execution is preferred** and the system should be designed to expand the set of operations that qualify.

## Mechanisms for Expanding Deterministic Surface Area

### 1. Templates as Deterministic Scaffolds

When a user selects a page template (e.g., "Product Page"), the template defines:
- Which sections exist and their order
- Which component types go in each section
- Which props need content and what type (text, image, URL)

**Building the template structure** requires AI (creative decision-making). **Populating content slots** within the template is a series of deterministic prop inserts — the target component, prop name, and prop type are all known from the template schema.

Today: template building + content population all go through the agent chain (~253K tokens).
Proposed: template selection is one AI decision, then slot population is deterministic inserts with AI only for creative content generation.

### 2. Component Presets and Catalogs

Instead of describing a component to the AI ("add a hero section with a dark background and centered text"), the user browses a visual preset catalog and clicks to insert. The component is inserted deterministically with default prop values. The user then edits individual props — most deterministically via direct editing.

This front-loads the creative work into preset design (done once by the site builder) and makes runtime operations deterministic.

### 3. Style and Content Tokens

Reusable tokens that propagate changes deterministically:

- **Style tokens**: `{{primary_color}}`, `{{heading_font}}`, `{{spacing_unit}}` — changing a token value updates all referencing components in one deterministic pass.
- **Content tokens**: `{{brand_name}}`, `{{product_name}}`, `{{cta_text}}` — updating the canonical value propagates everywhere.

A "rebrand" that changes the company name across 30 components: today requires AI to find and update each reference (potentially 30 operations × 111K tokens). With content tokens: one deterministic update, 0 tokens.

### 4. Batch Deterministic Operations

"Apply heading-lg to all section titles" — once the pattern is defined:
- Which prop to change (font size / style class)
- Which components to target (all with a "heading" prop in section-title position)
- What value to set

...applying it is N deterministic updates. The AI may help define the pattern once, but execution is deterministic.

### 5. Page Decomposition into Independent Edit Units

Pages should be decomposable into units that can be edited independently with minimal cross-unit context:

- A **component** is the atomic edit unit — editable with only its own schema and current values
- A **section** is a logical group — components within a section share layout context but don't need content from other sections
- A **page** is the composition — only operations that affect page-level concerns (navigation, structure, cross-section references) need full page context

This decomposition means that most operations need only the component or section level context, not the full page. See ADR-006 for the context envelope layers.

## Consequences

### For Canvas Architecture
- Components should expose a deterministic editing API (prop name → value) alongside the AI chat interface
- Template schemas should declare which props are "content slots" (fillable via deterministic insert)
- Style and content token systems need to be built into the component prop resolution chain
- The frontend should distinguish between "deterministic edit" and "AI-assisted edit" routing at the point of user action

### For AI Agent Design
- Agents should be designed for the creative/ambiguous minority of operations, not optimized for the deterministic majority
- Agent context (system prompt, ai_context items) should be sized for creative operations, not burdened with supporting deterministic ones
- The agent chain becomes a specialized tool, not the universal interface

### For Measurement
- Track the **deterministic ratio**: what percentage of operations in a session execute without AI
- The target is >60% deterministic for editing sessions on established pages
- New page creation sessions will have a lower deterministic ratio (more creative operations)

## Risks

- **Over-engineering the deterministic path**: Building elaborate template/token/preset systems has its own development cost. Start with the simplest mechanisms (direct prop editing via selection) and expand based on usage data.
- **Reduced discoverability**: If the AI chat becomes the escalation path, new users may not discover the creative capabilities. The AI should remain visible and accessible, just not the only entry point.
- **Template rigidity**: Over-reliance on templates may constrain creative freedom. Templates should be starting points, not cages — users can always escalate to AI for structural changes.

## Relationship to Other ADRs

| ADR | Relationship |
|-----|-------------|
| ADR-001 (cost scales with operation) | ADR-007 extends this: deterministic operations cost zero, not just less |
| ADR-004 (bypass LLM for deterministic ops) | ADR-007 is the design philosophy; ADR-004 is the first implementation |
| ADR-005 (layout scoping) | Scoping is a step toward page decomposition into independent edit units |
| ADR-006 (selection-first) | Selection is the primary mechanism for making operations deterministic |
