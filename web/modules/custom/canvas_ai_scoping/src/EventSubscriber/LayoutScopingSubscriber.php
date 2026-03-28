<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\EventSubscriber;

use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Drupal\canvas_ai\CanvasAiTempStore;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Scopes Canvas AI layout context to the active component's section.
 *
 * When a user selects a component and makes an AI request, this subscriber
 * replaces the full page layout in the system prompt with only the relevant
 * section's subtree. Other sections within the same region are summarized as
 * component name + UUID only, and other regions are summarized as counts.
 *
 * Two levels of scoping:
 * - Region level: other regions (header, footer) → count summary only
 * - Section level: sibling top-level components in the active region →
 *   name + UUID summary (so the agent knows what's on the page but doesn't
 *   see full prop/slot trees for sections it isn't editing)
 */
final class LayoutScopingSubscriber implements EventSubscriberInterface {

  /**
   * Agents whose layout context should be scoped when a component is selected.
   */
  private const SCOPED_AGENTS = [
    'canvas_page_builder_agent',
    'canvas_component_agent',
  ];

  public function __construct(
    private readonly CanvasAiTempStore $canvasAiTempStore,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Run after ai_context subscriber but before token replacement.
      BuildSystemPromptEvent::EVENT_NAME => ['onBuildSystemPrompt', -10],
    ];
  }

  /**
   * Scopes the layout in the system prompt to the active section.
   */
  public function onBuildSystemPrompt(BuildSystemPromptEvent $event): void {
    if (!in_array($event->getAgentId(), self::SCOPED_AGENTS, TRUE)) {
      return;
    }

    $tokens = $event->getTokens();
    $activeUuid = $tokens['active_component_uuid'] ?? 'None';
    if ($activeUuid === 'None' || $activeUuid === '') {
      return;
    }

    $layoutRaw = $this->canvasAiTempStore->getData(CanvasAiTempStore::CURRENT_LAYOUT_KEY);
    if (empty($layoutRaw)) {
      return;
    }

    $layoutJson = (string) $layoutRaw;
    $layout = json_decode($layoutJson, TRUE);
    if (!is_array($layout) || empty($layout['regions'])) {
      return;
    }

    $activeRegion = $this->findRegionForComponent($layout['regions'], $activeUuid);
    if ($activeRegion === NULL) {
      return;
    }

    $scopedLayout = $this->buildScopedLayout($layout, $activeRegion, $activeUuid);
    $scopedJson = json_encode($scopedLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $systemPrompt = $event->getSystemPrompt();
    if (str_contains($systemPrompt, $layoutJson)) {
      $event->setSystemPrompt(
        str_replace($layoutJson, $scopedJson, $systemPrompt)
      );
      $this->logger->notice(
        'Scoped layout for @agent: section in "@region" (@orig_len → @scoped_len bytes, @pct% reduction)',
        [
          '@agent' => $event->getAgentId(),
          '@region' => $activeRegion,
          '@orig_len' => strlen($layoutJson),
          '@scoped_len' => strlen($scopedJson),
          '@pct' => round((1 - strlen($scopedJson) / strlen($layoutJson)) * 100),
        ]
      );
    }
  }

  /**
   * Finds which region contains a component with the given UUID.
   */
  private function findRegionForComponent(array $regions, string $uuid): ?string {
    foreach ($regions as $regionName => $region) {
      if ($this->componentExistsInTree($region['components'] ?? [], $uuid)) {
        return $regionName;
      }
    }
    return NULL;
  }

  /**
   * Recursively checks if a component UUID exists in a component tree.
   */
  private function componentExistsInTree(array $components, string $uuid): bool {
    foreach ($components as $component) {
      if (($component['uuid'] ?? '') === $uuid) {
        return TRUE;
      }
      foreach ($component['slots'] ?? [] as $slot) {
        if ($this->componentExistsInTree($slot['components'] ?? [], $uuid)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Finds the index of the top-level component that contains the given UUID.
   *
   * The UUID may be the top-level component itself or nested in its slots.
   */
  private function findTopLevelParentIndex(array $components, string $uuid): ?int {
    foreach ($components as $index => $component) {
      if (($component['uuid'] ?? '') === $uuid) {
        return $index;
      }
      foreach ($component['slots'] ?? [] as $slot) {
        if ($this->componentExistsInTree($slot['components'] ?? [], $uuid)) {
          return $index;
        }
      }
    }
    return NULL;
  }

  /**
   * Builds a scoped layout with section-level granularity.
   *
   * - Active section (top-level component containing the selected UUID): full
   *   detail including all slots and nested components.
   * - Sibling sections in the same region: name + UUID only (so the agent knows
   *   what's on the page without the full component tree).
   * - Other regions: component count only.
   */
  private function buildScopedLayout(array $layout, string $activeRegion, string $activeUuid): array {
    $scoped = ['regions' => []];

    foreach ($layout['regions'] as $regionName => $region) {
      if ($regionName !== $activeRegion) {
        // Other regions: just a count.
        $componentCount = count($region['components'] ?? []);
        $scoped['regions'][$regionName] = [
          'nodePathPrefix' => $region['nodePathPrefix'] ?? [],
          'components' => [],
          '_note' => "{$componentCount} component(s) omitted (outside active region)",
        ];
        continue;
      }

      // Active region: scope to the section containing the selected component.
      $components = $region['components'] ?? [];
      $activeIndex = $this->findTopLevelParentIndex($components, $activeUuid);

      if ($activeIndex === NULL) {
        // Safety fallback: include full region if we can't find the section.
        $scoped['regions'][$regionName] = $region;
        continue;
      }

      $scopedComponents = [];
      foreach ($components as $i => $component) {
        if ($i === $activeIndex) {
          // Full detail for the active section.
          $scopedComponents[] = $component;
        }
        else {
          // Summary for sibling sections: name + UUID only.
          $scopedComponents[] = [
            'name' => $component['name'] ?? 'unknown',
            'uuid' => $component['uuid'] ?? '',
            'nodePath' => $component['nodePath'] ?? [],
            '_note' => 'sibling section (details omitted)',
          ];
        }
      }

      $scoped['regions'][$regionName] = [
        'nodePathPrefix' => $region['nodePathPrefix'] ?? [],
        'components' => $scopedComponents,
      ];
    }

    return $scoped;
  }

}
