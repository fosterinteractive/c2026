# Canvas AI Region Scoping: Native Support for Component-Level Requests

**Date:** March 2026
**For:** Foster Interactive (Canvas Maintainers)
**Status:** Technical Proposal for Discussion

---

## Problem Statement

Canvas AI integration currently sends the **entire page layout JSON and all component prop values** to the LLM on every request, even when the user is editing a single component. This causes unsustainable token consumption that scales directly with page complexity.

### Current Behavior

When a user selects a component to edit:

1. Frontend `AiWizard.tsx` calls `transformLayout()`, which serializes the full page tree including all 30+ components
2. `textPropsMapString` includes every component's props across the entire page
3. `CanvasBuilder.php` stores the complete layout in tempstore on every request
4. Sub-agents re-read the full layout from tempstore on each loop iteration
5. For a simple "change this heading color" edit on a 30-component page: **100-150K tokens consumed**

### Why This Matters

Token consumption = API costs = real money. A modern marketing site with:

- **20+ components per page** (hero, features, testimonials, footer sections, etc.)
- **8-12KB of layout JSON** (full schema + all prop values)
- **50+ editing operations** during a single content authoring session

...generates **5-7.5M tokens per session** (~$75-150 per page authoring session at OpenAI pricing).

The problem compounds as Canvas adoption grows and pages become more complex. Sites that make Canvas successful (feature-rich, modular) become the most expensive to edit with AI.

---

## Proposed Solution: Progressive Region Scoping

Implement native, opt-in **region-level scoping** in Canvas that:

1. When a user selects a component (`active_component_uuid` present), send only that component's region layout to the LLM
2. Include a lightweight "region index" (region names + node path indices, ~50 bytes) so agents know what other regions exist without full layout data
3. Keep full-layout mode for `template_builder_agent` and when no component is selected
4. Add zero breaking changes to existing behavior

### What Gets Sent (Scoped vs. Current)

**Current (Full Layout Mode):**
```json
{
  "layout": {
    "nodes": [
      {
        "id": "uuid-1",
        "type": "ComponentNode",
        "props": { ... },
        "children": [
          { "id": "uuid-2", "type": "ComponentNode", "props": { ... }, ... },
          { "id": "uuid-3", "type": "ComponentNode", "props": { ... }, ... }
        ]
      },
      ... 28 more components ...
    ]
  },
  "current_layout": "{ ... 8-12KB serialized layout ... }"
}
```

**Proposed (Component Scoped Mode):**
```json
{
  "layout": {
    "nodes": [
      {
        "id": "uuid-42",
        "type": "ComponentNode",
        "props": { ... },
        "children": []  // Only this component and direct children
      }
    ]
  },
  "region_index": [
    { "region_name": "hero", "node_path": "0" },
    { "region_name": "features", "node_path": "1" },
    { "region_name": "cta", "node_path": "2" },
    ... other regions ...
  ],
  "scope": "component",
  "active_component_uuid": "uuid-42"
}
```

---

## Implementation Details

### Files Modified

#### 1. **Frontend: `ui/src/components/aiExtension/AiWizard.tsx`** (~60 lines changed)

**Change 1: Add scope parameter to `transformLayout()`** (~30 lines)
```typescript
const transformLayout = (scope?: 'component' | 'page') => {
  const layout = scope && activeComponentUuid
    ? { nodes: [findNodeByUuid(layout.nodes, activeComponentUuid)] }
    : layout;
  return JSON.stringify(layout);
};
```

**Change 2: Filter `textPropsMapString` to scoped region** (~20 lines)
```typescript
const scopedTextPropsMap = scope && activeComponentUuid
  ? Object.fromEntries(
      Object.entries(textPropsMap).filter(([uuid]) =>
        isUuidInNode(uuid, findNodeByUuid(layout.nodes, activeComponentUuid))
      )
    )
  : textPropsMap;
```

**Change 3: Add scope detection and request payload** (~5 lines)
```typescript
const scope = activeComponentUuid ? 'component' : 'page';
const body = {
  layout: scopedTextPropsMap,
  current_layout: transformLayout(scope),
  scope,
  active_component_uuid: activeComponentUuid,
};
```

**Change 4: Region lookup from selected UUID** (~5 lines)
```typescript
const regionIndex = generateRegionIndex(layout.nodes);
```

#### 2. **Backend: `modules/canvas_ai/src/Controller/CanvasBuilder.php`** (~40 lines changed)

**Change 1: Accept and validate `scope` param** (~15 lines)
```php
$scope = $request->request->get('scope', 'page');
if (!in_array($scope, ['component', 'page'])) {
  $scope = 'page';
}
$activeComponentUuid = $request->request->get('active_component_uuid');
```

**Change 2: Store scoped layout in tempstore** (~25 lines)
```php
if ($scope === 'component' && $activeComponentUuid) {
  $this->canvasAiTempStore->setCurrentLayout($layout, $activeComponentUuid);
  $this->canvasAiTempStore->setRegionIndex($regionIndex);
} else {
  $this->canvasAiTempStore->setCurrentLayout($layout);
}
```

#### 3. **Tempstore: `modules/canvas_ai/src/CanvasAiTempStore.php`** (~20 lines changed)

**Addition: Region index constant and methods** (~20 lines)
```php
const REGION_INDEX_KEY = 'canvas_ai.region_index';

public function setRegionIndex(array $index): void {
  $this->tempStore->set(self::REGION_INDEX_KEY, $index);
}

public function getRegionIndex(): array {
  return $this->tempStore->get(self::REGION_INDEX_KEY) ?? [];
}
```

#### 4. **Validation Tools** (~30 lines changed across 3 files)

**`SetAIGeneratedTemplateData.php`:** Read region index instead of full layout
```php
$regionIndex = $this->canvasAiTempStore->getRegionIndex();
// Validate within region bounds, not full page bounds
```

**`MoveComponentInPage.php`:** Use region index for cross-region boundary detection
```php
$regions = $this->canvasAiTempStore->getRegionIndex();
if ($targetRegion && !isset($regions[$targetRegion])) {
  throw new \Exception("Target region not found in index");
}
```

**`GetCurrentLayout.php`:** No changes (already reads from tempstore)

**`UpdateComponentData.php`:** No changes (already reads from tempstore)

### No Changes Required

- Title/metadata agents (don't use layout context)
- Template builder (uses full layout, unaffected)
- Component schema validation (unchanged)

---

## Edge Cases & Handling

| Scenario | Behavior |
|----------|----------|
| Cross-region move ("move this to footer") | Region index provides all region names + paths; agent can construct full move |
| Template builder requests | Always receives full layout (no `scope` filtering applied) |
| No component selected | Full layout sent (backward compatible) |
| Legacy Canvas code | Works unchanged (scope param is optional, defaults to full layout) |
| Nested components | Scoped layout includes full subtree of selected component |

---

## Estimated Impact

### Token Reduction

**Scenario:** Editing a single component's text on a 30-component page (e.g., changing a heading)

- **Current consumption:** 100-150K tokens
  - Full layout: 8-12KB = ~2,000-3,000 tokens
  - Component props (all 30): 4-6KB = ~1,000-1,500 tokens
  - System prompt & agent context: ~4K tokens
  - History/conversation loop iterations: multiplied by 3-5 loops

- **Proposed (scoped) consumption:** 15-30K tokens (~90% reduction)
  - Scoped layout (1 component): 200-400 bytes = ~50-100 tokens
  - Region index: ~50 bytes = ~10 tokens
  - System prompt & agent context: ~4K tokens
  - Same loop iterations, but operating on 1/30th the layout data

### Real Cost Impact

- **Per-page editing session (50 operations):** $75-150 → $8-16
- **Monthly budget (10 authors, 5 pages each):** $3,750 → $400
- **Annual savings:** $40,000+

---

## Effort Estimate

| Task | Files | LOC | Complexity | Duration |
|------|-------|-----|-----------|----------|
| Frontend scoping logic | AiWizard.tsx | ~60 | Low | 1-2 days |
| Backend scope handling | CanvasBuilder.php | ~40 | Low | 1 day |
| Tempstore region index | CanvasAiTempStore.php | ~20 | Low | 0.5 day |
| Validation tool updates | 3 files | ~30 | Low | 1 day |
| Testing (unit + integration) | test files | ~200 | Medium | 2-3 days |
| Documentation & examples | docs | ~150 | Low | 1 day |

**Total: 3-5 days** (with testing)

### Testing Matrix

- [ ] Scoped requests (component selected) serialize layout correctly
- [ ] Unscoped requests (no selection) send full layout (backward compatible)
- [ ] Region index is accurate and complete
- [ ] Cross-region moves work correctly with region index only
- [ ] Template builder always receives full layout
- [ ] Nested component selection includes full subtree
- [ ] Multiple loop iterations maintain scope consistency
- [ ] Legacy Canvas installations work unchanged

---

## Our Workaround (Context)

We've built **`canvas_ai_scoping`** — a custom Drupal module that subscribes to `BuildSystemPromptEvent` and scopes layout data before it reaches the LLM. This works without modifying Canvas but has limitations:

- Only scopes data already in the system prompt (can't scope context missing from prompt)
- Uses fragile string replacement on serialized layout JSON
- Can't influence frontend (region index, scope detection) without Canvas changes
- Requires custom code per deployment

Native Canvas support would be cleaner, more robust, and benefit all Canvas users.

---

## Why This Should Be in Canvas Core

1. **Universal benefit:** Every Canvas site with complex pages faces this token cost
2. **Sustainability:** Scoping is required for Canvas to scale to enterprise page complexity
3. **Backward compatible:** Existing behavior unchanged; scoping is opt-in and progressive
4. **Low risk:** Isolated to layout serialization; doesn't touch core component logic
5. **Community contribution:** Zivtech can contribute reference implementation + tests

---

## Next Steps

### For Discussion

1. **Architecture review:** Does progressive region scoping align with Canvas's direction?
2. **API design:** Should `scope` be a request param, or inferred from `active_component_uuid`?
3. **Integration:** Should region index be generated by Canvas or by consuming agents?
4. **Backward compatibility:** Do we need a feature flag, or is opt-in param sufficient?

### Proposed Path Forward

1. **Drupal.org issue:** File a feature request on canvas.drupal.org with this proposal
2. **RFC discussion:** Gather feedback from Canvas maintainers and community
3. **Reference implementation:** We can contribute code as a patch for review
4. **Testing & documentation:** Community review cycle before merge

---

## Appendix: Code Examples

### Frontend Helper: Find Node by UUID

```typescript
const findNodeByUuid = (nodes: ComponentNode[], uuid: string): ComponentNode | null => {
  for (const node of nodes) {
    if (node.id === uuid) return node;
    if (node.children) {
      const found = findNodeByUuid(node.children, uuid);
      if (found) return found;
    }
  }
  return null;
};
```

### Frontend Helper: Generate Region Index

```typescript
const generateRegionIndex = (nodes: ComponentNode[]): RegionIndexEntry[] => {
  return nodes.map((node, index) => ({
    region_name: node.props?.region || `region_${index}`,
    node_path: String(index),
  }));
};
```

### Backend: Extract Scoped Layout in CanvasBuilder

```php
private function extractScopedLayout(array $layout, string $uuid): array {
  $helper = new LayoutHelper();
  return $helper->findNodeByUuid($layout['nodes'], $uuid);
}
```

---

## References

- Canvas Module: drupal.org/project/canvas
- Canvas AI Module: drupal.org/project/canvas_ai
- Issue tracker: drupal.org/project/issues/canvas
- Related: Token optimization patterns for LLM-integrated page builders
