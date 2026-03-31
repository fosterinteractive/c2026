<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Service;

/**
 * Interface for the AI provider availability checker service.
 *
 * Checks whether any AI provider is configured and usable for chat
 * operations. Used to determine whether API-key-free mode should be active.
 */
interface AiProviderAvailabilityCheckerInterface {

  /**
   * Returns TRUE if a usable AI chat provider is currently configured.
   *
   * Checks the default provider for the 'chat' operation type and verifies
   * it is usable (e.g., has a valid API key). No caching is applied — the
   * result reflects live configuration at the time of the call.
   *
   * @return bool
   *   TRUE if a chat provider is configured and reports isUsable(), FALSE
   *   otherwise (no default provider set, or provider not usable).
   */
  public function isAiAvailable(): bool;

}
