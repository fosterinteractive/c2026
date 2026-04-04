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
use Drupal\canvas_ai\CanvasAiComponentContextHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns all available Canvas components with their SDC names and descriptions.
 *
 * Use this tool to discover which components can be added to a page before
 * constructing or editing a layout. Returns a YAML-formatted catalog of
 * component IDs, labels, and descriptions.
 */
#[Tool(
  id: 'ai_agents_canvas_direct_edit:get_component_catalog',
  label: new TranslatableMarkup('Get Component Catalog'),
  description: new TranslatableMarkup('Returns all available Canvas components with their SDC names, labels, and descriptions. Use this to discover which components can be added to a page.'),
  operation: ToolOperation::Read,
  input_definitions: [],
)]
final class GetComponentCatalog extends ToolBase {

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
    $catalog = $this->componentContextHelper->getLessDetailedComponentContext();

    return ExecutableResult::success(
      new TranslatableMarkup('Component catalog retrieved.'),
      ['result' => $catalog],
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
