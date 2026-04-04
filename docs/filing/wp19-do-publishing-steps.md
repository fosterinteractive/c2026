# WP19: drupal.org Publishing Steps

## Step 1: Create the d.o. Project

1. Go to https://www.drupal.org/node/add/project-module
2. Fill in:
   - **Project name:** AI Agents Canvas Direct Edit
   - **Machine name:** `ai_agents_canvas_direct_edit`
   - **Description:** (paste from below)
   - **Module package:** AI Tools
   - **Maintenance status:** Actively maintained
   - **Development status:** Under active development
3. Save the project

### Project description (paste this)

Deterministic property editing for Canvas page builder components. When users
make simple changes like "set the color to blue" or "change the heading to
Welcome," this module resolves the edit directly from SDC component schemas —
no AI model call needed.

For edits that require reasoning (content generation, ambiguous references,
add/remove operations), the module returns a structured miss so the existing
AI agent chain handles them. Zero false positives by design.

**Key features:**

- 7 match tiers: exact, alias, bare value, relative, boolean, reset, compound
- 8 Tool API plugins (automatic MCP/CLI/AI agent discovery)
- Optional MCP server submodule (JSON-RPC 2.0)
- Schema-driven — adapts automatically when theme components change
- Config-driven aliases — site builders customize without patching
- Opt-in telemetry with PII-safe defaults
- Works without AI providers configured (Canvas Lite mode)

**Measured results** (15-component demo page):

- Deterministic path: 0 tokens, <7ms
- AI baseline: ~101K tokens, 16.4s
- Hit rate: 60% on mixed edits, zero false positives

Requires: [AI Agents](https://www.drupal.org/project/ai_agents),
[Tool](https://www.drupal.org/project/tool),
[Canvas](https://www.drupal.org/project/canvas).

---

## Step 2: Initialize Git and Push

```bash
# Clone the empty d.o. repo
git clone git@git.drupal.org:project/ai_agents_canvas_direct_edit.git
cd ai_agents_canvas_direct_edit

# Create 1.0.x branch
git checkout -b 1.0.x

# Copy module files from c2026 (ONLY the module directory contents)
rsync -av --exclude='.git' \
  ~/claude/c2026/web/modules/custom/ai_agents_canvas_direct_edit/ \
  ./

# Verify no FinDrop artifacts leaked
grep -r "findrop\|FinDrop\|byte_theme" . && echo "STOP: FinDrop refs found" || echo "Clean"

# Verify structure
ls -la
# Should see: .info.yml, .module, .install, .services.yml, .routing.yml,
# .permissions.yml, composer.json, README.md, REVIEWER_HANDOFF.md,
# config/, src/, modules/, tests/

# Commit
git add -A
git commit -m "Initial commit: ai_agents_canvas_direct_edit 1.0.0-alpha1

Deterministic Canvas component property editing without LLM.
Resolves simple prop edits from SDC schemas in <7ms at 0 tokens.

8 Tool API plugins, HTTP bridge, telemetry system, MCP server submodule.
59 kernel tests, 221 assertions."

# Push
git push origin 1.0.x
```

## Step 3: Tag and Create Release

```bash
# Tag alpha1
git tag 1.0.0-alpha1
git push origin 1.0.0-alpha1
```

Then on drupal.org:
1. Go to your project page → Releases → Add new release
2. Select tag `1.0.0-alpha1`
3. Release notes (paste from below)

### Release notes for 1.0.0-alpha1

**First alpha release.**

Deterministic Canvas component property editing without LLM invocation. When a
user's message matches a known edit pattern, the change resolves directly from
the SDC component schema at zero token cost and sub-7ms latency.

**What's included:**

- `DirectEditMatcher` with 7 resolution tiers (exact, alias, bare value,
  relative, boolean, reset, compound)
- 8 Tool API plugins (4 read, 4 write) — automatic discovery by AI agents,
  MCP clients, and Drush CLI
- HTTP bridge controller at `POST /admin/api/canvas/direct-edit`
- Optional MCP server submodule (`ai_agents_canvas_direct_edit_mcp`) with
  JSON-RPC 2.0 endpoint
- Telemetry system with opt-in tracking, PII-safe defaults, cron cleanup
- AI availability checking (Canvas Lite — works without AI providers)
- Confidence scoring and complexity signals for downstream model routing

**Requirements:**

- Drupal 10.3+ or 11.x
- drupal/ai_agents ^1.2
- drupal/tool ^1.0@beta
- drupal/canvas ^1.0@dev

**Test coverage:** 59 kernel tests, 221 assertions.

**Status:** Experimental (`experimental: true`). API surface marked with
`@api` and `@internal` annotations. Public contracts are stable; internal
implementations may change.

**AI disclosure:** AI tools assisted development. Architecture, test design,
and code review were human-directed.

---

## Step 4: Comment on Canvas Issue #3549232

Post this as a follow-up comment. The original comment asked "Is deterministic
routing a direction the Canvas AI team would consider?" — the maintainer
responded positively. This closes the loop.

### Comment (copy below this line)

---

Following up on the deterministic routing discussion — we've published the
module as a standalone contrib project:

**https://www.drupal.org/project/ai_agents_canvas_direct_edit**

The initial alpha includes everything discussed in the previous comment, now
implemented on the Tool API surface (`drupal/tool` ^1.0@beta) rather than
`AiFunctionCall`:

- **DirectEditMatcher** — 7 resolution tiers, schema-driven, config-driven
  aliases
- **8 Tool API plugins** — 4 read (page layout, component catalog, schema,
  props) and 4 write (match, update props, add component, move component)
- **HTTP bridge** at `POST /admin/api/canvas/direct-edit` — same request/response
  format as the Canvas AI panel, zero frontend changes needed
- **Optional MCP server submodule** — JSON-RPC 2.0 endpoint for external
  clients (Claude Desktop, Cursor, etc.)

The module extends canvas_ai — it acts as a pre-filter that resolves
deterministic edits before they reach the agent chain. Anything the matcher
can't resolve with certainty returns 422, falling through to the existing AI
path unchanged. Same `AiResponseValidator` and `CanvasAiPageBuilderHelper`
services, same response format.

59 kernel tests. All public surfaces annotated with `@api` or `@internal`.
The module includes a `REVIEWER_HANDOFF.md` that can be used as a Claude Code
context file for reviewing.

We kept the `experimental: true` flag given that `canvas` and `tool` are both
pre-stable. Happy to discuss integration patterns or architectural feedback.

---

### Filing notes (do not post)

- Tone: informative follow-up, not a sales pitch
- Lead with the project link so they can look at the code
- Mention Tool API (not AiFunctionCall) since that was a discussion point
- "extends canvas_ai" framing addresses the "does this compete?" concern
- Mention REVIEWER_HANDOFF.md — signals we thought about their review burden
- Do NOT mention token savings or speed numbers — the original comment
  already covered that, let them reference it
- If asked about the MCP server: "It's an optional submodule. The base module
  works without it. We included it because Tool API plugins are a natural fit
  for MCP exposure."
- If asked about telemetry: "Opt-in, disabled by default, PII-safe. Separate
  from AI Logging because it tracks deterministic match attempts, not LLM
  calls."
