# Architecture Decision Records — Canvas AI Efficiency

These ADRs establish the design principles for upstream contributions to the Drupal AI module ecosystem. Every proposed patch must align with these principles.

## Principles at a Glance

| # | Principle | Key metric | Upstream target |
|---|-----------|-----------|-----------------|
| ADR-001 | Token cost scales with operation, not page size | 111K → proportional to edit scope | ai_context, canvas_ai |
| ADR-002 | Context injection is loop-aware | 10-12K × N loops → 10-12K × 1 | ai_agents, ai_context |
| ADR-003 | Agent chains have aggregate cost ceilings | 300 worst-case loops → budget-capped | ai_agents |
| ADR-004 | Deterministic operations bypass the LLM | 111K → 0 for simple edits | canvas_ai |
| ADR-005 | Layout data scoped to operation target | 79% layout reduction (proven) | canvas_ai |

## Status

| ADR | Status | Evidence |
|-----|--------|----------|
| ADR-001 | Accepted | Measured: 111K tokens for heading edit |
| ADR-002 | Accepted | Measured: 10-12K identical context × 5 loops |
| ADR-003 | Accepted | Measured: SEO→page_builder = 300 worst-case loops |
| ADR-004 | Proposed | 111K vs 0 tokens for deterministic edits |
| ADR-005 | Accepted (proven) | Custom module: 79% layout reduction |

## How These Compose

ADR-005 (layout scoping) alone saves 12%. ADR-002 (loop-aware context) alone saves 36-43%. Combined with ADR-001 (operation-scoped context), edit operations drop from 111K to an estimated 20-40K tokens. ADR-004 (LLM bypass) eliminates tokens entirely for the ~40-60% of edits that are deterministic. ADR-003 (cost ceilings) prevents runaway chains regardless of other optimizations.

The elegant solution is not any single ADR — it's the composition of all five into an "efficient AI operations" layer in the Drupal AI ecosystem.
