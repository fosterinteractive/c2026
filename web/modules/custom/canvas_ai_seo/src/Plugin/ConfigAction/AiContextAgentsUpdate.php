<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_seo\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action that resolves ai_context_item labels to entity IDs.
 *
 * Accepts the same structure as ai_context.agents config, but with
 * human-readable labels instead of numeric IDs in always_include,
 * excluded_subcontext, and context_items. Resolves each label to its
 * entity ID and saves the config with IDs.
 */
#[ConfigAction(
  id: 'aiContextAgentsUpdate',
  admin_label: new TranslatableMarkup('AI Context agents update by label'),
)]
final class AiContextAgentsUpdate implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigFactoryInterface::class),
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    $config = $this->configFactory->getEditable($configName);
    if ($config->isNew()) {
      throw new ConfigActionException(sprintf('Config %s does not exist so can not be updated', $configName));
    }

    if (!is_array($value) || !isset($value['agents'])) {
      throw new ConfigActionException(sprintf('Config action for %s expects an "agents" key with agent configurations', $configName));
    }

    $labelMap = $this->buildLabelToIdMap();

    $agents = [];
    foreach ($value['agents'] as $agentData) {
      if (empty($agentData['id'])) {
        throw new ConfigActionException('Each agent entry must have an "id" key');
      }

      $agents[] = [
        'id' => $agentData['id'],
        'context_items' => $this->resolveLabels($agentData['context_items'] ?? [], $labelMap),
        'always_include' => $this->resolveLabels($agentData['always_include'] ?? [], $labelMap),
        'excluded_subcontext' => $this->resolveLabels($agentData['excluded_subcontext'] ?? [], $labelMap),
        'scope_subscriptions' => $agentData['scope_subscriptions'] ?? [],
      ];
    }

    $config->set('agents', $agents)->save();
  }

  /**
   * Builds a map of ai_context_item labels to their entity IDs.
   *
   * @return array<string, string>
   *   Keyed by label, values are entity ID strings.
   */
  private function buildLabelToIdMap(): array {
    $storage = $this->entityTypeManager->getStorage('ai_context_item');
    $items = $storage->loadMultiple();
    $map = [];
    foreach ($items as $item) {
      $map[$this->normalizeLabel($item->label())] = (string) $item->id();
    }
    return $map;
  }

  /**
   * Resolves an array of labels to entity IDs.
   *
   * @param array $labels
   *   Array of human-readable labels.
   * @param array<string, string> $labelMap
   *   Label-to-ID map.
   *
   * @return array
   *   Array of entity ID strings.
   *
   * @throws \Drupal\Core\Config\Action\ConfigActionException
   *   If a label cannot be resolved.
   */
  private function resolveLabels(array $labels, array $labelMap): array {
    $ids = [];
    foreach ($labels as $label) {
      $normalized = $this->normalizeLabel($label);
      if (!isset($labelMap[$normalized])) {
        throw new ConfigActionException(sprintf('AI Context Item with label "%s" not found. Available items: %s', $label, implode(', ', array_keys($labelMap))));
      }
      $ids[] = $labelMap[$normalized];
    }
    return $ids;
  }

  /**
   * Normalizes a label for comparison.
   *
   * Trims, lowercases, and replaces whitespace sequences with a single hyphen.
   */
  private function normalizeLabel(string $label): string {
    return preg_replace('/\s+/', '-', mb_strtolower(trim($label)));
  }

}
