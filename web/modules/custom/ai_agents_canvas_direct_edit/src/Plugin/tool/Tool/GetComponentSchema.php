<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\canvas_ai\CanvasAiComponentContextHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns the full property schema for specific Canvas components.
 *
 * Use this tool to understand what props, slots, types, enums, and defaults
 * a component accepts before editing it. Accepts a comma-separated list of
 * component IDs and returns YAML-formatted schema data for each.
 */
#[Tool(
  id: 'ai_agents_canvas_direct_edit:get_component_schema',
  label: new TranslatableMarkup('Get Component Schema'),
  description: new TranslatableMarkup('Returns the full property schema (types, enums, defaults, slots) for specific components. Use this to understand what props a component accepts before editing.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'component_ids' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component IDs'),
      description: new TranslatableMarkup('Comma-separated list of component IDs (e.g. "sdc.mytheme.heading,sdc.mytheme.button").'),
      required: TRUE,
    ),
  ],
)]
final class GetComponentSchema extends ToolBase {

  /**
   * The Canvas AI component context helper service.
   */
  protected CanvasAiComponentContextHelper $componentContextHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->componentContextHelper = $container->get('canvas_ai.component_context_helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $componentIdsRaw = $values['component_ids'] ?? '';
    $componentIds = array_map('trim', explode(',', $componentIdsRaw));
    $componentIds = array_filter($componentIds);

    $schema = $this->componentContextHelper->getDetailedMetadataOfComponents($componentIds);

    return ExecutableResult::success(
      new TranslatableMarkup('Component schema retrieved.'),
      ['result' => $schema],
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowedIfHasPermission($account, 'use ai agents canvas direct edit');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
