<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Telemetry;

/**
 * Defines the interface for the telemetry collector service.
 *
 * @api
 */
interface TelemetryCollectorInterface {

  /**
   * Records a telemetry event to persistent storage.
   *
   * Implementations must never throw. Any internal failure must be caught and
   * logged so that telemetry collection never blocks the edit response path.
   *
   * @param \Drupal\ai_agents_canvas_direct_edit\Telemetry\TelemetryEvent $event
   *   The event to persist.
   */
  public function record(TelemetryEvent $event): void;

}
