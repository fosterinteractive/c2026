<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Service;

/**
 * Interface for the complexity-based model router service.
 *
 * @internal Experimental — API not stable.
 *
 * Maps a complexity signal (e.g., 'simple', 'complex') to a specific AI
 * provider and model pair, allowing high-complexity tasks to be routed to
 * more capable models and low-complexity tasks to faster, cheaper ones.
 */
interface ComplexityModelRouterInterface {

  /**
   * Returns the provider/model pair for the given complexity signal.
   *
   * When routing is disabled in configuration, or when the signal is not
   * recognized, falls back to the default provider and model from ai.settings
   * via AiProviderPluginManager.
   *
   * @param string $complexitySignal
   *   The complexity signal string, e.g. 'trivial', 'simple', or 'complex'.
   *
   * @return array
   *   An associative array with keys:
   *   - 'provider_id' (string): The AI provider plugin ID.
   *   - 'model_id' (string): The model identifier within that provider.
   */
  public function route(string $complexitySignal): array;

}
