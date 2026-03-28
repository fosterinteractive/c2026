# ADR-009: No Slop in External Deliverables

**Status:** Accepted
**Date:** 2026-03-28
**Context:** Upstream Drupal contribution audience is hostile to AI-generated filler

## Decision

All external-facing deliverables (drupal.org issues, patches, RFC text, proposals, demo materials) must be reviewed for AI slop before publication. The audience — Drupal contrib maintainers, core committers, and Canvas developers — will reject content that reads as AI-generated, regardless of technical merit.

## What Counts as Slop

- **Filler preamble**: "In today's rapidly evolving landscape of AI-powered content management..."
- **Restating the obvious**: "Drupal is a content management system that..." to people who maintain Drupal
- **Weasel hedging**: "This could potentially help to possibly reduce..." — either it does or state the uncertainty directly
- **Bullet point padding**: 5 bullets where 2 carry the meaning and 3 rephrase the same point
- **Synonym chains**: "efficient, optimized, streamlined, and performant"
- **Empty transitions**: "With that in mind, let's now turn our attention to..."
- **Overclaiming**: "revolutionary paradigm shift" for what is a data loading optimization
- **Gratuitous structure**: H2 → H3 → H4 → H5 nesting for content that fits in a flat list
- **Fake precision**: "approximately 93.7% reduction" when the inputs are estimates

## What Good Looks Like

From Drupal issue queues — how maintainers actually write:

> **Problem:** `BuildSystemPromptEvent` fires every loop iteration. `SystemPromptSubscriber` re-appends all context items each time. For an agent with 7 context items (~6-8K tokens), a 5-loop operation re-sends 30-40K tokens of identical content.
>
> **Proposed fix:** Add `getLoopIteration()` to the event. Subscribers check iteration and skip re-injection on loop > 1. Default behavior unchanged (backwards compatible).
>
> **Patch attached.** Includes test coverage for loop-aware and backwards-compatible paths.

That's it. Problem, fix, patch. No "executive summary," no "background context" section explaining what Drupal is, no "as we can see from the data" narration.

## Review Checklist Before Publishing

Before any content goes to drupal.org, Foster Interactive, or any external audience:

1. **Read it aloud.** If it sounds like a corporate memo, rewrite it.
2. **Cut the first paragraph.** Most AI-generated text front-loads throat-clearing. The real content starts at paragraph 2.
3. **Delete every sentence that restates something the audience already knows.** These people wrote the code. They know what `BuildSystemPromptEvent` does.
4. **Numbers need sources.** "79% layout reduction" must link to the measurement methodology or it's a marketing claim.
5. **One idea per paragraph.** If a paragraph makes two points, split it or cut one.
6. **No selling.** The code and data make the case. Adjectives ("elegant," "powerful," "innovative") don't.
7. **Would catch respond to this?** If the writing style would trigger a Drupal core committer's AI-content detector, rewrite it from scratch in your own voice.

## Consequences

- All drupal.org issue text is drafted, then reviewed against this checklist before posting
- The Foster Interactive proposal (`docs/proposals/canvas-ai-region-scoping.md`) needs a slop audit before sending — it was written in an earlier session and may have filler
- Demo recordings use plain narration, not scripted marketing language
- Internal docs (ADRs, plans, handoff notes) don't need this level of polish — they're working documents for us
