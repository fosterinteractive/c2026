# FinDrop — Drupal CMS 2.0 AI Demo

FinDrop is a Drupal CMS 2.0 (Drupal 11.3) demo site showcasing AI-powered content creation with the Canvas page builder.

This repository is a demo/reference implementation for exploring Drupal CMS 2.0, Canvas AI workflows, and deterministic page editing tools.

## What You Get

- Drupal CMS 2.0 (Drupal 11.3) with the Canvas page builder
- AI-powered page creation with Claude and GPT agents
- SEO metadata generation with Schema.org JSON-LD
- Vector search backed by Milvus
- Deterministic component editing tools exposed for MCP, CLI, and AI agent discovery

## Prerequisites

- Docker Desktop or OrbStack
- [DDEV](https://ddev.com/get-started/) (latest)
- Node.js 20.19 or newer on the host machine for building Canvas UI assets
- Composer, managed through DDEV inside the project environment
- At least one AI API key: `OPENAI_API_KEY` and/or `ANTHROPIC_API_KEY`

## Quickstart

```shell
git clone https://github.com/fosterinteractive/c2026.git
cd c2026
cp .env.template .ddev/.env
# Edit .ddev/.env -- set OPENAI_API_KEY and/or ANTHROPIC_API_KEY
ddev demo-setup
ddev drush uli  # login URL
```

`ddev demo-setup` starts DDEV, installs dependencies, provisions Drupal from the recipe, builds Canvas UI assets, and indexes searchable content.

## Troubleshooting

- Port 5173 conflict: Kill any Vite dev servers before running `demo-setup`: `lsof -ti:5173 | xargs kill`
- `npm` `ENOTEMPTY` error: Delete `node_modules` before `npm install`: `rm -rf web/modules/contrib/canvas/node_modules`
- Missing API keys: Search indexing may fail without `OPENAI_API_KEY` — this is expected and handled gracefully. AI features require at least one key.
- Canvas UI not building: Ensure Node.js 20.19 or newer is installed on your host, not just in DDEV. Run `node --version` to check.

## Exporting Content

After making changes in Drupal, use these commands to export content back into the recipes:

| Command | What it exports |
|---|---|
| `ddev export-all-content` | All content entities (canvas pages, nodes, media, menu links, taxonomy terms) |
| `ddev export-canvas-pages` | Canvas pages only |
| `ddev export-media` | Media and file entities only |
| `ddev export-ai-context` | AI Context items and usage records |
| `ddev backup` | Database and files directory snapshot to `.backups/` |

## AI Agent Audit

A static audit of all 12 Canvas AI agents was conducted to evaluate prompt quality, context injection, tool design, and security.

| Document | Description |
|---|---|
| [Static Audit Report](docs/audit/canvas-agent-static-audit.md) | Full findings for all 12 agents — system prompts, context gaps, red flags, test coverage |
| [Infrastructure Plan](docs/audit/findrop-audit-infrastructure.md) | DDEV service composition, Drush inspection, Playwright testing harness |
| [Open Questions](docs/audit/open-questions.md) | Unresolved questions from the audit |
| [Next Session Handoff](docs/handoff/handoff-next-session.md) | Priorities and environment state for continuing work |
| [Embeddings Handoff](docs/handoff/handoff-codex-embeddings.md) | Instructions for setting up OpenAI key and indexing content |

## Demo Test Results

The [DrupalCon driesnote demo script](ai_context_data/website_copy/driesnote-prompts.md) was executed via Playwright against a live DDEV instance:

| Step | Prompt | Result | Screenshot |
|------|--------|--------|------------|
| 01.A | Paste copy deck | Pass — preflight questions asked | [Building](canvas-ai-building-page.png) |
| 01.B | Travel Managers / whitepaper downloads | Pass — full page built | [Built](canvas-ai-page-built.png) |
| 02 | Switch hero to photography | Expected fail — no OpenAI key | [Screenshot](step02-media-search-unavailable.png) |
| 03 | Create FAQ from content | Pass — 3 accordion items | [Screenshot](step03-faq-created.png) |
| 04 | Add internal cross links | Expected fail — no search index | [Screenshot](step04-crosslinks-no-index.png) |
| 05 | Create AEO schema | Pass — JSON-LD applied | [Screenshot](step05-schema-generated.png) |

## Claude Code Skills

| Skill | Description |
|---|---|
| [Canvas AI Audit](.claude/skills/canvas-ai-audit.md) | Repeatable 8-step driesnote demo test with Playwright |
| [AI Observability](.claude/skills/ai-observability-module.md) | Enable/configure contrib `ai_observability` module |
| [Canvas Webapp Testing](.claude/skills/canvas-webapp-testing.md) | Playwright patterns and selectors for Canvas AI |

## Key Documentation

| File | Purpose |
|---|---|
| [CLAUDE.md](CLAUDE.md) | Project guide for Claude Code |
| [Test Scenarios](ai_context_data/test_scenarios/scenarios_checklist.md) | 27 test scenarios across 7 phases |
| [Demo Prompts](ai_context_data/website_copy/driesnote-prompts.md) | DrupalCon driesnote demo script |
| [Canvas Patch README](creating_patch_for_canvas/README.md) | How to regenerate the Canvas combined patch |
