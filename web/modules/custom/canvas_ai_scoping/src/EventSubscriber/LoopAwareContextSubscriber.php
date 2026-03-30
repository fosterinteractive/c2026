<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\EventSubscriber;

use Drupal\ai_agents\Event\AgentStartedExecutionEvent;
use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Drupal\canvas_ai_scoping\AiContextPromptParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prevents ai_context from re-injecting context on every agent loop iteration.
 *
 * ai_context's SystemPromptSubscriber appends 10-12K tokens of context items
 * to the system prompt via BuildSystemPromptEvent on every loop iteration.
 * Since the system prompt is rebuilt each loop, this means the LLM receives
 * the same context items in every call — a major token waste for agents that
 * loop 5-15+ times.
 *
 * This subscriber strips the ai_context block from the system prompt on
 * loop > 0 (second iteration onward). The context was already provided on
 * loop 0 and is available in the LLM's conversation history.
 *
 * Runs at priority -5, after ai_context (priority 0) but before
 * LayoutScopingSubscriber (-10) and ContextScopingSubscriber (-20).
 */
final class LoopAwareContextSubscriber implements EventSubscriberInterface {

  /**
   * Agents whose ai_context should be loop-gated.
   *
   * Only agents that loop multiple times benefit from this optimization.
   * The orchestrator typically runs 1-2 loops; builders run 5-15+.
   */
  private const LOOP_GATED_AGENTS = [
    'canvas_page_builder_agent',
    'canvas_template_builder_agent',
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
      // Capture loop count before BuildSystemPromptEvent fires.
      AgentStartedExecutionEvent::EVENT_NAME => ['onAgentStarted', 50],
      // Run after ai_context (0) but before layout/context scoping (-10, -20).
      BuildSystemPromptEvent::EVENT_NAME => ['onBuildSystemPrompt', -5],
    ];
  }

  /**
   * Captures the loop count when an agent starts a loop iteration.
   */
  public function onAgentStarted(AgentStartedExecutionEvent $event): void {
    $agentId = $event->getAgentId();
    if (in_array($agentId, self::LOOP_GATED_AGENTS, TRUE)) {
      $loopCount = $event->getLoopCount();
      // Reset this agent's tracking on first loop to prevent cross-request
      // data leakage in persistent PHP runtimes (FrankenPHP, RoadRunner).
      // Per-agent reset avoids wiping loop counts for concurrently executing
      // agents (e.g., orchestrator spawning a sub-agent in the same process).
      if ($loopCount === 0) {
        unset($this->loopCounts[$agentId]);
      }
      $this->loopCounts[$agentId] = $loopCount;
    }
  }

  /**
   * Strips ai_context block from the system prompt on loop > 0.
   */
  public function onBuildSystemPrompt(BuildSystemPromptEvent $event): void {
    $agentId = $event->getAgentId();
    if (!in_array($agentId, self::LOOP_GATED_AGENTS, TRUE)) {
      return;
    }

    $loopCount = $this->loopCounts[$agentId] ?? 0;
    if ($loopCount === 0) {
      // First loop — let ai_context through. Log the context size for metrics.
      $this->logContextSize($event, $agentId, $loopCount);
      return;
    }

    // Loop > 0: strip the ai_context block from the system prompt.
    $systemPrompt = $event->getSystemPrompt();
    $stripped = AiContextPromptParser::stripBlock($systemPrompt);

    if ($stripped === NULL) {
      return;
    }

    $event->setSystemPrompt($stripped['prompt']);

    $this->logger->notice(
      'LoopAwareContext: stripped ai_context on loop @loop for @agent (@bytes bytes removed)',
      [
        '@loop' => $loopCount,
        '@agent' => $agentId,
        '@bytes' => $stripped['bytes_removed'],
      ]
    );
  }

  /**
   * Logs the ai_context block size on the first loop for measurement.
   */
  private function logContextSize(BuildSystemPromptEvent $event, string $agentId, int $loopCount): void {
    $contextSize = AiContextPromptParser::measureBlockSize($event->getSystemPrompt());
    if ($contextSize === 0) {
      return;
    }

    $this->logger->info(
      'LoopAwareContext: ai_context block size for @agent on loop @loop: @size bytes (~@tokens tokens)',
      [
        '@agent' => $agentId,
        '@loop' => $loopCount,
        '@size' => $contextSize,
        '@tokens' => (int) ($contextSize / 4),
      ]
    );
  }

}
