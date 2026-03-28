# Slop Audit: docs/proposals/canvas-ai-region-scoping.md

**Date:** 2026-03-28
**Per:** ADR-009 (No Slop in Deliverables)
**Verdict:** REVISE before sharing externally

---

## Critical (would undermine credibility)

### C1: Cost projections are unsupported extrapolations
**Lines 26-33, 234-237**: "$75-150 per session", "$3,750 → $400 monthly", "$40,000+ annual savings"

These numbers are fabricated projections, not measurements. Our actual measured data:
- Page build: 253K tokens (N=1)
- Heading edit: 111K tokens (N=1)
- No session-level aggregation has been measured

**Fix:** Replace with actual per-operation measurements. Remove session/monthly/annual projections entirely, or label them explicitly as "illustrative estimates" with stated assumptions.

### C2: "90% reduction" claim contradicts measured data
**Lines 229-231**: "Proposed (scoped) consumption: 15-30K tokens (~90% reduction)"

The actual measured reduction was layout JSON: 12,438 → 2,611 bytes (79% of layout). But total operation tokens: ~125K → ~111K (only ~11% total reduction). The 90% figure confuses layout-byte reduction with total-token reduction.

**Fix:** Use measured numbers. State clearly: "79% reduction in layout data, ~11% reduction in total operation tokens. Layout is a fraction of total cost — system prompt, ai_context, and chat history dominate."

### C3: Code examples use wrong data model
**Lines 311-342**: `ComponentNode`, `nodes`, `children` hierarchy

The actual Canvas layout format uses `regions` → `components` → `slots` → `components`, not a flat `nodes` array. These code examples wouldn't work against the real Canvas layout structure.

**Fix:** Replace with examples that match the actual layout format, or remove the code appendix and point to the working `LayoutScopingSubscriber` prototype.

---

## Major (noticeable AI smell)

### M1: Marketing tone in "Why This Should Be in Canvas Core"
**Lines 279-285**: Numbered list of value assertions ("Universal benefit", "Sustainability", "Low risk")

This reads like a sales pitch, not a technical proposal. Upstream maintainers respond to evidence, not adjective lists.

**Fix:** Replace with a single paragraph stating: "This change benefits any site where pages exceed N components. Our prototype demonstrates the approach works within Canvas's event subscriber architecture. We can contribute a patch."

### M2: "Problem compounds" rhetoric
**Line 34**: "The problem compounds as Canvas adoption grows and pages become more complex."

Unsupported trend claim.

**Fix:** Delete. The per-operation cost speaks for itself.

### M3: Speculative effort estimate
**Lines 242-251**: "Total: 3-5 days (with testing)"

We haven't implemented the frontend changes. This estimate is a guess.

**Fix:** Remove time estimate or label as "rough estimate, subject to Canvas team input."

---

## Minor (style nits)

### m1: "Sites that make Canvas successful (feature-rich, modular) become the most expensive"
**Line 34**: Editorializing.

**Fix:** Delete — the numbers make the point without commentary.

### m2: Redundant "References" section
**Lines 346-352**: Lists drupal.org URLs everyone already knows, plus a vague "Related" line.

**Fix:** Delete section. When filing on drupal.org, the context is implicit.

### m3: "For Discussion" questions could be more specific
**Lines 293-297**: Generic architecture questions.

**Fix:** Replace with specific questions grounded in the prototype findings, e.g., "Should scoping be automatic when `active_component_uuid` is present, or explicitly opted in via a separate param?"

---

## Summary

| Severity | Count | Action |
|----------|-------|--------|
| Critical | 3 | Must fix before any external sharing |
| Major | 3 | Fix to avoid AI-generated appearance |
| Minor | 3 | Fix for polish |

The proposal's core technical idea is sound (region scoping reduces layout tokens). The problem is inflated claims, wrong code examples, and marketing-style framing. Strip to measured facts + working prototype → strong upstream contribution.
