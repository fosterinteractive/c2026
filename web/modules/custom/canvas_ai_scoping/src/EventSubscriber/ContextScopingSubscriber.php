<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\EventSubscriber;

use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Drupal\canvas_ai_scoping\AiContextPromptParser;
use Drupal\canvas_ai_scoping\Service\ContextEditScopeManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Strips non-essential ai_context items during component-edit operations.
 *
 * When a user selects a component and makes an edit request, this subscriber
 * removes context items that are only needed for page building (structural
 * guidelines, content strategy docs, product facts) while keeping items
 * needed for text quality (brand voice, writing tone, formatting rules).
 *
 * Which items to strip is configured via ContextEditScopeManager, which
 * auto-generates content fingerprints from ai_context_item entities on save.
 * Site builders configure which items to strip — no hardcoded content strings.
 *
 * This runs after ai_context's SystemPromptSubscriber (priority 0) and after
 * LayoutScopingSubscriber (priority -10), at priority -20.
 */
final class ContextScopingSubscriber implements EventSubscriberInterface {

  /**
   * Agents whose ai_context should be scoped during edit operations.
   *
   * Both page_builder and component_agent handle component edits. Without
   * scoping on both, the component_agent would receive the full ai_context
   * block (10-12K tokens) for every single-component edit.
   */
  private const SCOPED_AGENTS = [
    'canvas_page_builder_agent',
    'canvas_component_agent',
  ];

  public function __construct(
    private readonly ContextEditScopeManager $scopeManager,
    private readonly LoggerInterface $logger,
  ) {}

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
    if ($activeUuid === 'None' || $activeUuid === '') {
      return;
    }

    $systemPrompt = $event->getSystemPrompt();

    // Find the ai_context block using the shared parser.
    $block = AiContextPromptParser::findBlock($systemPrompt);
    if ($block === NULL) {
      return;
    }

    $contextBlock = $block['content'];
    $beforeContext = substr($systemPrompt, 0, $block['content_start']);
    $afterContext = substr($systemPrompt, $block['content_end']);

    // Split into individual context items by "- ID: " markers.
    // The renderer outputs: "- ID: <numeric>\n  Tags: ...\n  Guidance:\n    <content>"
    $items = preg_split('/(?=^- ID: )/m', $contextBlock, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($items)) {
      return;
    }

    $originalCount = count($items);
    $strippedCount = 0;
    $strippedNames = [];

    // Get the fingerprint map from the scope manager.
    $stripFingerprints = $this->scopeManager->getStripFingerprints();
    if (empty($stripFingerprints)) {
      return;
    }

    // Filter out items whose Guidance content matches a strip fingerprint.
    $keptItems = [];
    foreach ($items as $item) {
      $shouldStrip = FALSE;
      $itemLower = mb_strtolower($item);
      foreach ($stripFingerprints as $fingerprint => $name) {
        if (str_contains($itemLower, mb_strtolower($fingerprint))) {
          $shouldStrip = TRUE;
          $strippedCount++;
          $strippedNames[] = $name;
          break;
        }
      }
      if (!$shouldStrip) {
        $keptItems[] = $item;
      }
    }

    if ($strippedCount === 0) {
      // No fingerprints matched — either the items aren't in the prompt
      // (expected on non-edit operations) or the fingerprints are stale
      // (content entities were edited in the Drupal UI). Log a warning
      // so stale fingerprints are detectable in logs.
      $this->logger->warning(
        'ContextScopingSubscriber: 0 of @count fingerprints matched for @agent. Fingerprints may be stale if ai_context items were recently edited.',
        [
          '@count' => count(self::STRIP_FINGERPRINTS),
          '@agent' => $event->getAgentId(),
        ]
      );
      return;
    }

    // Verify we didn't strip everything — fail-open safety check.
    if (empty($keptItems)) {
      $this->logger->warning(
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
    $this->logger->notice(
      'ContextScopingSubscriber: stripped @names (@stripped of @total items, @orig → @new bytes, @pct% reduction)',
      [
        '@names' => implode(', ', $strippedNames),
        '@stripped' => $strippedCount,
        '@total' => $originalCount,
        '@orig' => $originalLen,
        '@new' => $newLen,
        '@pct' => round((1 - $newLen / $originalLen) * 100),
      ]
    );
  }

}
