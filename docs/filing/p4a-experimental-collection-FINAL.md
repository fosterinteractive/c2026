# P4 Path A: Deterministic Direct-Edit Agent — Ready to Post

**Target:** New issue on [ai_agents_experimental_collection](https://www.drupal.org/project/ai_agents_experimental_collection)
**Title:** `New agent: Canvas Direct Edit — deterministic property editing without LLM`
**Category:** Feature request
**Priority:** Normal

---

## Issue body (copy below this line)

---

<h3>Summary</h3>

<p>A new experimental agent/tool that handles simple Canvas component property edits deterministically — without invoking the LLM agent chain. When a component is selected and the user message matches a known pattern ("change the heading to Welcome", "set the color to blue"), the edit is resolved directly from the SDC schema. Edits the matcher can't resolve fall through to the standard AI path (422 response).</p>

<p>This complements the existing <strong>Canvas Page Manager</strong> agent by handling the subset of edits that are objectively deterministic — similar to how Drupal's static page cache skips the full bootstrap for requests that don't need it.</p>

<h3>Why this belongs in the experimental collection</h3>

<ul>
<li>Standalone submodule — no modifications to canvas or canvas_ai needed</li>
<li>Uses the same <code>AiResponseValidator</code> and <code>CanvasAiPageBuilderHelper</code> services as the AI pipeline</li>
<li>Returns the same JSON response format the Canvas frontend expects</li>
<li>Fail-open: unmatched edits return 422, frontend falls through to AI</li>
<li>Could also be exposed as a Tool via <a href="https://www.drupal.org/project/tool/issues/3575927">#3575927</a> (Drush CLI for tools) when that lands</li>
</ul>

<h3>What it handles</h3>

<ul>
<li>Message matches "change/set/update X to Y" where X resolves to a known prop alias from the SDC schema</li>
<li>Value resolves to a valid enum value or simple scalar for the target prop</li>
<li>Compound edits ("change heading to X and set color to blue") split and resolve independently</li>
<li>Bare values ("blue") resolve via reverse enum index when unambiguous</li>
<li>Boolean toggles ("show the header") resolve against boolean prop metadata</li>
<li>Relative adjustments ("bigger") navigate enum ordinals based on current prop values</li>
</ul>

<h3>What still routes to AI</h3>

<ul>
<li>Content generation ("write a better heading")</li>
<li>Ambiguous references ("fix this", "make it look better")</li>
<li>Add/move/delete operations</li>
<li>Cross-component references ("match the style of the hero")</li>
<li>Any message the matcher can't resolve with certainty</li>
</ul>

<h3>Measured results</h3>

<p>All measurements on a 15-component demo page (N=1 for token counts, N=5 for AI latency, N=10 for direct-edit latency):</p>

<ul>
<li>Deterministic path: 0 tokens, &lt;7ms latency</li>
<li>AI path baseline: ~101K tokens, 16.4s mean latency</li>
<li>Component catalog (23 Byte theme components, 125 props): 48.8% of props are deterministically addressable</li>
<li>Hit rate: 60% on 20 mixed edits. All deterministic predictions correct.</li>
</ul>

<h3>Architecture</h3>

<ul>
<li><strong>DirectEditMatcher</strong> — pattern-matches user messages against component schemas. Pure logic + config dependency.</li>
<li><strong>DirectEditController</strong> — HTTP endpoint at <code>/admin/api/canvas/direct-edit</code> (already called by Canvas frontend). Validates input, calls matcher, returns response.</li>
<li><strong>ComponentSchemaLoader</strong> — loads SDC YAML schemas from active theme dynamically via <code>ThemeHandlerInterface::getDefault()</code>. Builds alias/enum maps. Cached.</li>
<li>Config-driven: edit verbs and enum value aliases in <code>settings.yml</code>. Algorithmic fallback for values not in config.</li>
</ul>

<h3>Limitations</h3>

<ul>
<li><strong>English only.</strong> Pattern matcher uses English verbs. Non-English sites fall through to AI.</li>
<li><strong>Theme-driven.</strong> Prop schemas from active theme's SDC YAML. Config-driven aliases are theme-portable.</li>
<li><strong>Concrete class coupling.</strong> Depends on <code>AiResponseValidator</code> and <code>CanvasAiPageBuilderHelper</code> (no interface contract).</li>
</ul>

<h3>Test coverage</h3>

<p>144 PHPUnit tests, 632 assertions. 16 Playwright E2E specs covering all matcher tiers and rejection tests.</p>

<h3>Prototype</h3>

<p>Working custom module with full test suite. Happy to contribute an MR with the submodule code.</p>

---

## Filing notes (do not post)

- This is the LOW-BAR path — experimental collection explicitly accepts AI-generated code
- No larowlan gate — different maintainer group
- Reference the Canvas Page Manager agent as the integration point
- Reference #3575927 (Drush CLI for tools) as a future integration surface
- If they ask about canvas_ai integration: "we also have a proposal for native canvas_ai integration (Path B), but wanted to start with a standalone experimental agent"
- The Canvas frontend already calls `/admin/api/canvas/direct-edit` (AiWizard.tsx:751) — no frontend changes needed
- AI disclosure: be transparent. "AI tools assisted development; architecture and testing were human-directed"
