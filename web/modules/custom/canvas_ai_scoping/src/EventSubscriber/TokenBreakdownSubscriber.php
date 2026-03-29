<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\EventSubscriber;

use Drupal\ai_agents\Event\AgentStartedExecutionEvent;
use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Drupal\canvas_ai_scoping\AiContextPromptParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs per-component token breakdown of each agent's system prompt.
 *
 * Runs LAST (priority -100) after all other subscribers have modified the
 * system prompt. Logs the final prompt size and identifies the major segments:
 * - Base prompt (agent instructions before ai_context)
 * - ai_context block (between dashed separators)
 * - Layout JSON (if present)
 * - Post-context prompt (after ai_context block)
 *
 * This data feeds the measurement tables needed for upstream evidence.
 * Enable by setting `canvas_ai_scoping.debug_logging: true` in state,
 * or it always logs for the agents in INSTRUMENTED_AGENTS.
 */
final class TokenBreakdownSubscriber implements EventSubscriberInterface {

  /**
   * Agents to instrument for token breakdown logging.
   */
  private const INSTRUMENTED_AGENTS = [
    'canvas_page_builder_agent',
    'canvas_template_builder_agent',
    'canvas_ai_orchestrator',
    'drupal_canvas_seo_agent',
    'canvas_component_agent',
  ];

  /**
   * Tracks current loop count per agent ID within a request.
   *
   * @var array<string, int>
   */
  private array $loopCounts = [];

  public function __construct(
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AgentStartedExecutionEvent::EVENT_NAME => ['onAgentStarted', 40],
      // Run last — after all modifications to the system prompt.
      BuildSystemPromptEvent::EVENT_NAME => ['onBuildSystemPrompt', -100],
    ];
  }

  /**
   * Captures loop count per agent.
   */
  public function onAgentStarted(AgentStartedExecutionEvent $event): void {
    $agentId = $event->getAgentId();
    if (in_array($agentId, self::INSTRUMENTED_AGENTS, TRUE)) {
      $loopCount = $event->getLoopCount();
      // Reset on first loop to prevent cross-request leakage in persistent
      // PHP runtimes (FrankenPHP, RoadRunner, etc.).
      if ($loopCount === 0) {
        $this->loopCounts = [];
      }
      $this->loopCounts[$agentId] = $loopCount;
    }
  }

  /**
   * Logs the token breakdown of the final system prompt.
   */
  public function onBuildSystemPrompt(BuildSystemPromptEvent $event): void {
    $agentId = $event->getAgentId();
    if (!in_array($agentId, self::INSTRUMENTED_AGENTS, TRUE)) {
      return;
    }

    $loopCount = $this->loopCounts[$agentId] ?? 0;
    $systemPrompt = $event->getSystemPrompt();
    $totalBytes = strlen($systemPrompt);



    $breakdown = $this->analyzePrompt($systemPrompt);

    $this->logger->info(
      'TokenBreakdown @agent loop=@loop | total=@total_bytes bytes (~@total_tokens tok) | base=@base_bytes (@base_tok tok) | context=@ctx_bytes (@ctx_tok tok) | layout=@layout_bytes (@layout_tok tok) | post=@post_bytes (@post_tok tok)',
      [
        '@agent' => $agentId,
        '@loop' => $loopCount,
        '@total_bytes' => $totalBytes,
        '@total_tokens' => $this->estimateTokens($totalBytes),
        '@base_bytes' => $breakdown['base_bytes'],
        '@base_tok' => $this->estimateTokens($breakdown['base_bytes']),
        '@ctx_bytes' => $breakdown['context_bytes'],
        '@ctx_tok' => $this->estimateTokens($breakdown['context_bytes']),
        '@layout_bytes' => $breakdown['layout_bytes'],
        '@layout_tok' => $this->estimateTokens($breakdown['layout_bytes']),
        '@post_bytes' => $breakdown['post_bytes'],
        '@post_tok' => $this->estimateTokens($breakdown['post_bytes']),
      ]
    );
  }

  /**
   * Analyzes a system prompt into its major segments.
   *
   * @param string $prompt
   *   The full system prompt.
   *
   * @return array{base_bytes: int, context_bytes: int, layout_bytes: int, post_bytes: int}
   *   Byte sizes for each segment.
   */
  private function analyzePrompt(string $prompt): array {
    $result = [
      'base_bytes' => strlen($prompt),
      'context_bytes' => 0,
      'layout_bytes' => 0,
      'post_bytes' => 0,
    ];

    // Find ai_context block using shared parser.
    $block = AiContextPromptParser::findBlock($prompt);
    if ($block !== NULL) {
      $result['context_bytes'] = $block['block_end'] - $block['block_start'];
      $result['base_bytes'] = $block['block_start'];
      $result['post_bytes'] = strlen($prompt) - $block['block_end'];
    }

    // Detect layout JSON by finding the {"regions": marker and using
    // json_decode to measure the complete object (handles nested braces
    // correctly, unlike regex which undercounts).
    $layoutMarker = '{"regions":';
    $layoutPos = strpos($prompt, $layoutMarker);
    if ($layoutPos !== FALSE) {
      // Try to decode from the marker position to find the full JSON object.
      // Use progressively larger substrings until json_decode succeeds.
      $remaining = substr($prompt, $layoutPos);
      $decoded = json_decode($remaining, TRUE);
      if ($decoded !== NULL && isset($decoded['regions'])) {
        // Re-encode to get the canonical length of the parsed object.
        $result['layout_bytes'] = strlen(json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
      }
    }

    return $result;
  }

  /**
   * Rough token estimate: ~4 chars per token for English text.
   */
  private function estimateTokens(int $bytes): int {
    return (int) round($bytes / 4.0);
  }

}
