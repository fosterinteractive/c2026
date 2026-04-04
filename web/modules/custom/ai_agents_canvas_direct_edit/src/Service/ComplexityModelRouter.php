<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Routes complexity signals to AI provider/model pairs via configuration.
 *
 * @internal Default implementation of ComplexityModelRouterInterface.
 *
 * Reads from ai_agents_canvas_direct_edit.settings:model_routing on every
 * call — no caching — so configuration changes take effect immediately.
 *
 * When routing is disabled or the signal is unrecognized, falls back to
 * the default provider/model from ai.settings via AiProviderPluginManager.
 */
final class ComplexityModelRouter implements ComplexityModelRouterInterface {

  /**
   * Constructs a ComplexityModelRouter.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\ai\AiProviderPluginManager|null $aiProviderPluginManager
   *   The AI provider plugin manager, or NULL when the ai module is absent.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ?AiProviderPluginManager $aiProviderPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function route(string $complexitySignal): array {
    $settings = $this->configFactory->get('ai_agents_canvas_direct_edit.settings');
    $routing = $settings->get('model_routing') ?? [];

    if (!empty($routing['enabled'])) {
      $models = $routing['models'] ?? [];
      if (isset($models[$complexitySignal]) && $models[$complexitySignal] !== '') {
        return $this->parseModelString($models[$complexitySignal]);
      }
    }

    return $this->getDefault();
  }

  /**
   * Parses a "provider_id/model_id" string into its component parts.
   *
   * If the string contains no slash, the entire value is treated as model_id
   * and provider_id falls back to the default.
   *
   * @param string $modelString
   *   A model identifier, optionally prefixed with "provider_id/".
   *
   * @return array
   *   Array with 'provider_id' and 'model_id' keys.
   */
  private function parseModelString(string $modelString): array {
    if (str_contains($modelString, '/')) {
      [$providerId, $modelId] = explode('/', $modelString, 2);
      return [
        'provider_id' => $providerId,
        'model_id' => $modelId,
      ];
    }

    $default = $this->getDefault();
    return [
      'provider_id' => $default['provider_id'],
      'model_id' => $modelString,
    ];
  }

  /**
   * Returns the default provider/model pair from ai.settings.
   *
   * Falls back to empty strings when the ai module is not installed.
   *
   * @return array
   *   Array with 'provider_id' and 'model_id' keys.
   */
  private function getDefault(): array {
    if ($this->aiProviderPluginManager === NULL) {
      return ['provider_id' => '', 'model_id' => ''];
    }

    $default = $this->aiProviderPluginManager->getDefaultProviderForOperationType('chat');

    return [
      'provider_id' => $default['provider_id'] ?? '',
      'model_id' => $default['model_id'] ?? '',
    ];
  }

}
