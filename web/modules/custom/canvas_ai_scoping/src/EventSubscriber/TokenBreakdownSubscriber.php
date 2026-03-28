<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\EventSubscriber;

use Drupal\ai_agents\Event\AgentStartedExecutionEvent;
use Drupal\ai_agents\Event\BuildSystemPromptEvent;
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
      $this->loopCounts[$agentId] = $event->getLoopCount();
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
    $separator = '-----------------------------------------------';
    $result = [
      'base_bytes' => strlen($prompt),
      'context_bytes' => 0,
      'layout_bytes' => 0,
      'post_bytes' => 0,
    ];

    // Find ai_context block.
    $startPos = strpos($prompt, $separator);
    if ($startPos !== FALSE) {
      $endPos = strpos($prompt, $separator, $startPos + strlen($separator));
      if ($endPos !== FALSE) {
        $blockEnd = $endPos + strlen($separator);
        // Walk back to find the context prefix ("\n\n" before the prefix text).
        $prefixSearch = max(0, $startPos - 300);
        $before = substr($prompt, $prefixSearch, $startPos - $prefixSearch);
        $lastNl = strrpos($before, "\n\n");
        $blockStart = $lastNl !== FALSE ? $prefixSearch + $lastNl : $startPos;

        $result['context_bytes'] = $blockEnd - $blockStart;
        $result['base_bytes'] = $blockStart;
        $result['post_bytes'] = strlen($prompt) - $blockEnd;
      }
    }

    // Detect layout JSON within the base prompt or post-context.
    // Layout is typically a large JSON block with "regions" key.
    if (preg_match('/\{"regions":\{.+?\}\}/s', $prompt, $matches, PREG_OFFSET_CAPTURE)) {
      $result['layout_bytes'] = strlen($matches[0][0]);
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
