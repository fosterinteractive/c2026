<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\EventSubscriber;

use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Strips non-essential ai_context items during component-edit operations.
 *
 * When a user selects a component and makes an edit request, this subscriber
 * removes context items that are only needed for page building (structural
 * guidelines, content strategy docs, product facts) while keeping items
 * needed for text quality (brand voice, writing tone, formatting rules).
 *
 * This runs after ai_context's SystemPromptSubscriber (priority 0) and after
 * LayoutScopingSubscriber (priority -10), at priority -20.
 *
 * Format dependency: relies on ai_context's AiContextRenderer output format:
 *   "- ID: <title>\n  Tags: ...\n  Guidance:\n    <content>"
 * wrapped in "-------" separators. If the format changes, stripping silently
 * fails (items leak back in) — fail-open by design.
 */
final class ContextScopingSubscriber implements EventSubscriberInterface {

  /**
   * Agents whose ai_context should be scoped during edit operations.
   */
  private const SCOPED_AGENTS = [
    'canvas_page_builder_agent',
  ];

  /**
   * Context items to REMOVE during edit operations.
   *
   * These are structural/strategic docs needed for page building but not
   * for editing existing component props.
   */
  private const STRIP_DURING_EDITS = [
    'Content Structure: Product Pages',
    'General Page Building Guidelines',
    'FinDrop Key Facts & Value Propositions',
    'Visuals & Imagery',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Run after ai_context (0) and LayoutScopingSubscriber (-10).
      BuildSystemPromptEvent::EVENT_NAME => ['onBuildSystemPrompt', -20],
    ];
  }

  /**
   * Strips non-essential context items from the system prompt during edits.
   */
  public function onBuildSystemPrompt(BuildSystemPromptEvent $event): void {
    if (!in_array($event->getAgentId(), self::SCOPED_AGENTS, TRUE)) {
      return;
    }

    $tokens = $event->getTokens();
    $activeUuid = $tokens['active_component_uuid'] ?? 'None';
    if ($activeUuid === 'None' || $activeUuid === '' || $activeUuid === NULL) {
      return;
    }

    $systemPrompt = $event->getSystemPrompt();

    // Find the ai_context block between dashed separators.
    $separator = '-----------------------------------------------';
    $startPos = strpos($systemPrompt, $separator);
    if ($startPos === FALSE) {
      return;
    }
    $endPos = strpos($systemPrompt, $separator, $startPos + strlen($separator));
    if ($endPos === FALSE) {
      return;
    }

    // Extract the context block between the two separator lines.
    $blockStart = $startPos + strlen($separator) + 1;
    $blockLength = $endPos - $blockStart;
    $contextBlock = substr($systemPrompt, $blockStart, $blockLength);
    $beforeContext = substr($systemPrompt, 0, $blockStart);
    $afterContext = substr($systemPrompt, $endPos);

    // Split into individual context items by "- ID: " markers.
    $items = preg_split('/(?=^- ID: )/m', $contextBlock, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($items)) {
      return;
    }

    $originalCount = count($items);
    $strippedCount = 0;

    // Filter out items that match our strip list.
    $keptItems = [];
    foreach ($items as $item) {
      $shouldStrip = FALSE;
      foreach (self::STRIP_DURING_EDITS as $stripTitle) {
        if (str_contains($item, '- ID: ' . $stripTitle)) {
          $shouldStrip = TRUE;
          $strippedCount++;
          break;
        }
      }
      if (!$shouldStrip) {
        $keptItems[] = $item;
      }
    }

    if ($strippedCount === 0) {
      return;
    }

    // Verify we didn't strip everything — fail-open safety check.
    if (empty($keptItems)) {
      \Drupal::logger('canvas_ai_scoping')->warning(
        'ContextScopingSubscriber: All @count context items would be stripped — skipping to fail-open.',
        ['@count' => $originalCount]
      );
      return;
    }

    // Reconstruct the context block.
    $newContextBlock = implode("\n", $keptItems);
    $newPrompt = $beforeContext . $newContextBlock . $afterContext;

    $event->setSystemPrompt($newPrompt);

    $originalLen = strlen($systemPrompt);
    $newLen = strlen($newPrompt);
    \Drupal::logger('canvas_ai_scoping')->notice(
      'Stripped @stripped of @total context items for edit operation (@orig → @new bytes, @pct% reduction in prompt)',
      [
        '@stripped' => $strippedCount,
        '@total' => $originalCount,
        '@orig' => $originalLen,
        '@new' => $newLen,
        '@pct' => round((1 - $newLen / $originalLen) * 100),
      ]
    );
  }

}
