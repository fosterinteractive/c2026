<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Telemetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;

/**
 * Persists telemetry events to the canvas_direct_edit_telemetry table.
 *
 * @internal Default implementation of TelemetryCollectorInterface.
 *
 * This service is intentionally resilient: any database failure is caught,
 * logged, and silently discarded so that telemetry collection never blocks
 * or fails the edit response path.
 */
class TelemetryCollector implements TelemetryCollectorInterface {

  /**
   * Constructs a TelemetryCollector.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel for this module.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function record(TelemetryEvent $event): void {
    $config = $this->configFactory->get('ai_agents_canvas_direct_edit.settings');

    if (!$config->get('telemetry.enabled')) {
      return;
    }

    $storeMessages = (bool) $config->get('telemetry.store_messages');

    try {
      $this->database->insert('canvas_direct_edit_telemetry')
        ->fields([
          'timestamp' => $event->timestamp,
          'component_name' => $event->componentName,
          'tier' => $event->tier,
          'matched' => (int) $event->matched,
          'prop_name' => $event->propName,
          'confidence' => $event->confidence,
          'complexity_signal' => $event->complexitySignal,
          'model_used' => $event->modelUsed,
          'latency_us' => $event->latencyUs,
          'message_length' => $event->messageLength,
          'message_hash' => $event->messageHash,
          'redacted_message' => $storeMessages ? $event->redactedMessage : NULL,
          'ai_fallback' => (int) $event->aiFallback,
          'ai_latency_ms' => $event->aiLatencyMs,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Failed to write telemetry record for component @component: @message',
        [
          '@component' => $event->componentName,
          '@message' => $e->getMessage(),
        ]
      );
    }
  }

}
