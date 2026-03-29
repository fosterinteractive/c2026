<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\Service;

/**
 * Builds a structured context envelope for a selected component.
 *
 * When a user selects a component, the full page layout (~10-20K tokens)
 * can be replaced with a compact envelope (~200-500 tokens) that gives the
 * agent only what it needs: the component's props, its immediate neighbors,
 * section metadata, and a page-level outline.
 *
 * This implements the context envelope architecture from ADR-006
 * (Selection-First Editing Paradigm), layers 1-4. Layer 5 (brand context)
 * is handled separately by ContextScopingSubscriber.
 *
 * @see \Drupal\canvas_ai_scoping\EventSubscriber\LayoutScopingSubscriber
 */
final class ContextEnvelopeBuilder {

  /**
   * Builds a context envelope for the given active component.
   *
   * @param array $layout
   *   The full parsed layout array with 'regions' key.
   * @param string $activeUuid
   *   The UUID of the selected component.
   * @param array $regionIndex
   *   Pre-computed region index from LayoutScopingSubscriber.
   *
   * @return array|null
   *   The structured envelope, or NULL if the component wasn't found.
   */
  public function build(array $layout, string $activeUuid, array $regionIndex): ?array {
    $location = $this->findComponent($layout['regions'] ?? [], $activeUuid);
    if ($location === NULL) {
      return NULL;
    }

    return [
      'scope' => 'component',
      'active_component' => $this->buildComponentLayer($location['component']),
      'neighbors' => $this->buildNeighborLayer($location),
      'section' => $this->buildSectionLayer($location),
      'page_outline' => $regionIndex,
    ];
  }

  /**
   * Layer 1: Full component props and structure.
   *
   * Returns the selected component with all its prop values and slots
   * intact — this is the agent's primary editing target.
   */
  private function buildComponentLayer(array $component): array {
    return [
      'uuid' => $component['uuid'] ?? '',
      'name' => $component['name'] ?? 'unknown',
      'nodePath' => $component['nodePath'] ?? [],
      'propValues' => $component['propValues'] ?? [],
      'slots' => $component['slots'] ?? [],
    ];
  }

  /**
   * Layer 2: Neighbor component summaries.
   *
   * Provides the previous and next sibling component name + UUID so the
   * agent understands positional context without seeing full prop trees.
   * If the component is nested in a slot, neighbors are slot siblings.
   */
  private function buildNeighborLayer(array $location): array {
    $siblings = $location['siblings'];
    $index = $location['sibling_index'];

    $previous = NULL;
    if ($index > 0) {
      $prev = $siblings[$index - 1];
      $previous = [
        'name' => $prev['name'] ?? 'unknown',
        'uuid' => $prev['uuid'] ?? '',
      ];
    }

    $next = NULL;
    if ($index < count($siblings) - 1) {
      $nxt = $siblings[$index + 1];
      $next = [
        'name' => $nxt['name'] ?? 'unknown',
        'uuid' => $nxt['uuid'] ?? '',
      ];
    }

    return [
      'previous' => $previous,
      'next' => $next,
    ];
  }

  /**
   * Layer 3: Section metadata.
   *
   * Identifies which region and position the component lives in, plus how
   * many sibling components exist at the same nesting level.
   */
  private function buildSectionLayer(array $location): array {
    return [
      'region' => $location['region'],
      'position' => $location['sibling_index'] + 1,
      'total_in_level' => count($location['siblings']),
      'nesting_depth' => $location['depth'],
    ];
  }

  /**
   * Locates a component in the layout tree by UUID.
   *
   * Returns the component, its siblings list, index within siblings,
   * containing region name, and nesting depth.
   *
   * @return array{component: array, siblings: array, sibling_index: int, region: string, depth: int}|null
   */
  private function findComponent(array $regions, string $uuid): ?array {
    foreach ($regions as $regionName => $region) {
      $result = $this->searchTree(
        $region['components'] ?? [],
        $uuid,
        $regionName,
        0,
      );
      if ($result !== NULL) {
        return $result;
      }
    }
    return NULL;
  }

  /**
   * Recursively searches a component tree for a UUID.
   *
   * @return array{component: array, siblings: array, sibling_index: int, region: string, depth: int}|null
   */
  private function searchTree(array $components, string $uuid, string $region, int $depth): ?array {
    foreach ($components as $index => $component) {
      if (($component['uuid'] ?? '') === $uuid) {
        return [
          'component' => $component,
          'siblings' => $components,
          'sibling_index' => $index,
          'region' => $region,
          'depth' => $depth,
        ];
      }

      // Search nested slots.
      foreach ($component['slots'] ?? [] as $slot) {
        $result = $this->searchTree(
          $slot['components'] ?? [],
          $uuid,
          $region,
          $depth + 1,
        );
        if ($result !== NULL) {
          return $result;
        }
      }
    }
    return NULL;
  }

}
