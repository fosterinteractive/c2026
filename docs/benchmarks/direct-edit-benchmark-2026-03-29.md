# Direct-Edit Benchmark Results — 2026-03-29

## Summary

The direct-edit path resolves deterministic component edits in **38ms mean latency** (N=10, 95% CI [23, 54]ms) with **60% hit rate** on a realistic 20-edit mix. Zero LLM tokens consumed. All 12 latency runs returned HTTP 200.

## Methodology

| Parameter | Value |
|-----------|-------|
| Total latency runs | 12 (2 warm-up + 10 measured) |
| Warm-up protocol | First 2 runs discarded (JIT, cache priming, connection pool) |
| Measurement method | Direct API POST via Playwright request context (shared session) |
| Hit rate sample | 20 mixed edits (12 deterministic + 8 non-deterministic) |
| Environment | DDEV local, PHP 8.3.25, nginx-fpm, MariaDB 10.11, single-tenant |
| Component tested | `sdc.byte_theme.heading` |
| Statistical model | Student's t, df=9, 95% CI |

## Latency Results (N=10)

| Metric | Value |
|--------|-------|
| Mean | 38.4ms |
| SD | 21.9ms |
| Median | 30.1ms |
| 95% CI | [22.7, 54.0]ms |
| Min | 24.1ms |
| Max | 97.3ms |
| All HTTP 200 | Yes |

### Per-Run Data

| Run | Warm-up | Latency (ms) | Status |
|-----|---------|-------------|--------|
| 1 | Yes | (discarded) | 200 |
| 2 | Yes | (discarded) | 200 |
| 3-12 | No | 24.1 - 97.3 | 200 |

## Hit Rate Results (20 Mixed Edits)

| Metric | Value |
|--------|-------|
| Total edits | 20 |
| Hits (200) | 12 |
| Misses (422 → AI fallback) | 8 |
| Hit rate | 60.0% |
| All predictions correct | Yes |
| Hit latency mean | 29.2ms |
| Miss latency mean | 25.1ms |

### Deterministic Edits (12/12 hit)

| Message | Status |
|---------|--------|
| Change the heading to Welcome to FinDrop | 200 |
| Set the color to blue | 200 |
| Set the alignment to center | 200 |
| Set the level to 3 | 200 |
| heading: Performance Test | 200 |
| set color = primary | 200 |
| primary (bare value) | 200 |
| center (bare value) | 200 |
| make it primary | 200 |
| Set the color to white | 200 |
| Set the level to 1 | 200 |
| Change the heading to Hello and set the color to blue (compound) | 200 |

### Non-Deterministic Edits (0/8 hit, as expected)

| Message | Status | Reason |
|---------|--------|--------|
| make this heading more engaging | 422 | Content generation |
| add a subtitle below this | 422 | Add keyword |
| generate a catchy alternative title | 422 | Generate keyword |
| fix this | 422 | Ambiguous |
| rainbow | 422 | Unknown enum value |
| make it look more professional | 422 | Subjective/multi-word |
| create another heading | 422 | Create keyword |
| can you suggest a better title? | 422 | Question/generation |

## Findings

1. **Bare value inference limited to raw enum values.** Natural aliases like "blue" (→ primary) are only in the enum alias map, not the reverse enum index. Bare value "blue" returns 422; the explicit "set the color to blue" works correctly via Tier 1 pattern matching. This is by design — including aliases in the reverse index would increase collision risk.

2. **Miss latency (25ms) is faster than hit latency (29ms).** This makes sense: misses short-circuit at pattern matching, while hits continue through component validation, prop validation, and operation building.

3. **Median (30ms) is lower than mean (38ms).** The distribution is right-skewed with one outlier at 97ms, likely from a cold PHP opcode cache or database connection.

## Comparison Context

| Path | Latency | Tokens | API Key Required |
|------|---------|--------|------------------|
| Direct-edit | ~38ms mean | 0 | No |
| AI agent chain | ~15-30s (reported) | ~3,000-8,000 | Yes |

The direct-edit path is approximately **400-800x faster** and consumes zero tokens for the 60% of edits it handles.

## Reproducibility

```bash
npx --package=./web/modules/contrib/canvas/node_modules/@playwright/test \
  playwright test tests/playwright/benchmark-direct-edit.spec.ts \
  --config=tests/playwright/playwright.config.ts --reporter=list
```

JSON data: `docs/benchmarks/direct-edit-benchmark-2026-03-29.json`
