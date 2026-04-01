<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Service;

use Drupal\ai\AiProviderPluginManager;

/**
 * Checks whether any AI provider is configured and usable for chat operations.
 *
 * @internal Default implementation of AiProviderAvailabilityCheckerInterface.
 *
 * Reads live configuration on every call — no caching — so that changes to
 * the AI provider settings take effect immediately without a cache clear.
 */
final class AiProviderAvailabilityChecker implements AiProviderAvailabilityCheckerInterface {

  /**
   * Constructs an AiProviderAvailabilityChecker.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderPluginManager
   *   The AI provider plugin manager.
   */
  public function __construct(
    private readonly ?AiProviderPluginManager $aiProviderPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isAiAvailable(): bool {
    if ($this->aiProviderPluginManager === NULL) {
      return FALSE;
    }

    $default = $this->aiProviderPluginManager->getDefaultProviderForOperationType('chat');

    if (empty($default['provider_id'])) {
      return FALSE;
    }

    try {
      $provider = $this->aiProviderPluginManager->createInstance($default['provider_id']);
    }
    catch (\Exception) {
      return FALSE;
    }

    return $provider->isUsable('chat');
  }

}
