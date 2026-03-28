# Architecture Decision Records — Canvas AI Efficiency

These ADRs establish the design principles for upstream contributions to the Drupal AI module ecosystem. Every proposed patch must align with these principles.

## Principles at a Glance

### Upstream Proposals (ADR-001 through ADR-005) — Measured Evidence

| # | Principle | Key metric | Upstream target |
|---|-----------|-----------|-----------------|
| ADR-001 | Token cost scales with operation, not page size | 111K → proportional to edit scope | ai_context, canvas_ai |
| ADR-002 | Context injection is loop-aware | ~7K × 3 skipped loops = ~21K savings/edit | ai_context |
| ADR-003 | Agent chains have aggregate cost ceilings | 300 worst-case loops → budget-capped | ai_agents |
| ADR-004 | Deterministic operations bypass the LLM | 111K → 0 for qualifying edits | canvas_ai |
| ADR-005 | Layout data scoped to operation target | 79% layout reduction (proven) | canvas_ai |

### Internal Vision (ADR-006 through ADR-009) — Not for upstream issues

| # | Principle | Status | Notes |
|---|-----------|--------|-------|
| ADR-006 | Selection-first editing paradigm | Proposed | 53-90% session reduction depending on edit-type split (unmeasured) |
| ADR-007 | Maximize deterministic surface area | Proposed | Templates, presets, tokens expand deterministic operations |
| ADR-008 | Show and prove — local validation before upstream | Accepted | 8-week prototype + benchmark phase |
| ADR-009 | No slop in external deliverables | Accepted | Review checklist for all upstream-facing content |

## Status

| ADR | Status | Evidence |
|-----|--------|----------|
| ADR-001 | Accepted | Measured: 111K tokens for heading edit (N=1, needs repeated runs) |
| ADR-002 | Accepted | Measured: ~7K context × 3-4 page_builder loops per edit |
| ADR-003 | Accepted | Measured: SEO→page_builder = 300 worst-case loops |
| ADR-004 | Proposed | 111K vs 0 tokens for deterministic edits |
| ADR-005 | Accepted (proven) | Custom module: 79% layout reduction |
| ADR-006 | Proposed | Projected: 53-90% session reduction (edit-type split unknown) |
| ADR-007 | Proposed | Target: >60% deterministic for editing sessions |
| ADR-008 | Accepted | 8-week local validation before upstream filing |
| ADR-009 | Accepted | Quality gate for external deliverables |

## How These Compose

**Layer 1 — Fix the AI path (ADR-001 through ADR-005):** Upstream contributions. Layout scoping (12% savings, proven), loop-aware context (~21K/edit, corrected), cost ceilings (prevent runaway), LLM bypass for simple edits. Target: 35-45% reduction for complex edits that go through the agent chain.

**Layer 2 — Minimize the AI path (ADR-006 + ADR-007):** Internal vision for Canvas UX evolution. Selection narrows context. Templates and presets expand deterministic operations. The AI becomes the escalation path for creative work.

**Layer 3 — Execution discipline (ADR-008 + ADR-009):** Show and prove before pitching. No slop in deliverables. Every upstream proposal backed by a working local prototype with measured evidence.

Projected session-level impact depends heavily on the edit-type distribution (unknown — must be measured). Range: 53-90% depending on what percentage of real-world edits are deterministic.
