# P4: Deterministic Routing — Ready to Post

**Target:** Comment on [canvas #3549232](https://www.drupal.org/project/canvas/issues/3549232) (or new issue if stale)
**Type:** Comment on existing issue

---

## Comment body (copy below this line)

---

The `update_component_data` tool introduced in this issue enables an interesting optimization: for simple property edits where the correct result is deterministic, routing directly to this tool without invoking the LLM agent chain.

### The reliability angle

Following the discussion in #3551659 about AI producing incorrect results that vary by model — for simple property edits ("change the heading to Welcome", "set the color to blue"), the correct result is objectively deterministic. It's a known prop on a known component with a known set of valid values. The LLM path introduces unnecessary variability for these cases.

### Approach

When a component is selected and the user message matches a deterministic pattern, bypass the agent chain:

1. Pattern matcher detects "component selected + recognized prop + explicit value"
2. Routes to a direct-edit endpoint
3. Validates component exists and prop value is schema-valid
4. Calls the same `AiResponseValidator` and `CanvasAiPageBuilderHelper` services as the AI path
5. Returns the same JSON response format

The pattern matcher is intentionally conservative — it only resolves edits where there is zero ambiguity:

- Message matches "change/set/update X to Y" where X resolves to a known prop alias from the SDC schema
- No add/create/generate keywords present (those require LLM reasoning)
- Value resolves to a valid enum value or is a simple scalar for the target prop
- Compound edits ("change heading to X and set color to blue") split on conservative boundaries
- Bare values ("blue") resolve via reverse enum index when unambiguous
- Boolean toggles ("show the header") resolve against boolean prop metadata

### What still routes to AI

Anything that requires reasoning:

- Content generation ("write a better heading")
- Ambiguous references ("fix this", "make it look better")
- Add/move/delete operations
- Cross-component references ("match the style of the hero")
- Any message the matcher can't resolve with certainty

The matcher returns 422 for anything it can't handle — fail-open to the existing AI chain.

### Measured results (N=1 heading edit, 15-component demo page)

- Deterministic path: 0 tokens, <7ms latency
- AI path baseline: ~101K tokens, 16.4s mean latency (N=5, SD=838ms)
- Component catalog survey (23 Byte theme components, 125 props): 48.8% of props are deterministically addressable (40% enum-constrained, 8.8% boolean)
- Hit rate: 60% on 20 mixed edits. All deterministic predictions correct — zero false positives in testing.

### Limitations

- **English only.** Pattern matcher uses English verbs. Non-English sites route all edits to the AI chain. Localized verb/alias maps could be contributed later.
- **Theme-driven.** Prop schemas come from the active theme's SDC YAML files. The prototype discovers the theme dynamically via `ThemeHandlerInterface::getDefault()` (same pattern as `CanvasAiPageBuilderHelper`). Enum value aliases are config-driven, not hardcoded — theme developers can customize them.
- **Concrete class coupling.** The endpoint depends on `AiResponseValidator` and `CanvasAiPageBuilderHelper` directly (no interface contract). If Canvas refactors these services, the endpoint needs updating.
- **False positive design.** Zero false positives is the design goal — when in doubt, reject to the AI chain (422). False negatives (missing a match) are safe. The compound splitter has a known ambiguity with conjunctions in text values ("change the heading to Welcome and Goodbye"), handled by requiring the next fragment to begin with an edit verb.

### Prototype

`DirectEditMatcher` + `DirectEditController` in a custom module. Uses the same services as the AI pipeline. 144 PHPUnit tests, 632 assertions. 16 Playwright E2E specs covering all matcher tiers and rejection tests.

Schema-driven: when components update their YAML schemas, the matcher auto-adapts. No manual maintenance.

Is deterministic routing for simple property edits a direction the Canvas AI team would consider? Happy to share the architecture doc and contribute a patch.

---

## Filing notes (do not post)

- Lead with RELIABILITY (lauriii's #3551659 pain point), not speed or cost savings
- Do NOT mention "430x speedup" — let them discover the numbers
- Do NOT use "Canvas Lite" or "works without AI" framing
- The question at the end ("Is this a direction...?") is intentional — ask permission, don't assume
- If lauriii responds positively: offer to share the architecture doc (patch-3-deterministic-routing-architecture.md)
- If asked "how did you measure?": explain the benchmark methodology openly
- If asked about AI tooling: "AI tools assisted development and measurement; architecture and testing were human-directed"
- Test count is 144/632 (updated after config-driven alias migration)
- The endpoint `/admin/api/canvas/direct-edit` already exists in the Canvas frontend (AiWizard.tsx:751) — if they ask about frontend changes, the answer is "none needed"
