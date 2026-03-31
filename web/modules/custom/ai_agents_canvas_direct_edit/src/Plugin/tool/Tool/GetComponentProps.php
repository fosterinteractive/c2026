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
use Drupal\canvas_ai\AiResponseValidator;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Returns current property values for components in the page.
 *
 * Optionally filter by UUID to retrieve props for a single component. When no
 * UUID is given all components' props are returned. Mirrors the validation
 * behaviour of the canvas_ai:get_component_content function call plugin.
 */
#[Tool(
  id: 'ai_agents_canvas_direct_edit:get_component_props',
  label: new TranslatableMarkup('Get Component Props'),
  description: new TranslatableMarkup('Returns current property values for components in the page. Optionally filter by UUID for a specific component.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'component_uuid' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component UUID'),
      description: new TranslatableMarkup('UUID of a specific component. If omitted, returns all components\' props.'),
      required: FALSE,
    ),
  ],
)]
final class GetComponentProps extends ToolBase {

  /**
   * The Canvas AI page builder helper service.
   */
  protected CanvasAiPageBuilderHelper $pageBuilderHelper;

  /**
   * The Canvas AI response validator service.
   */
  protected AiResponseValidator $responseValidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->pageBuilderHelper = $container->get('canvas_ai.page_builder_helper');
    $instance->responseValidator = $container->get('canvas_ai.response_validator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $componentUuid = isset($values['component_uuid']) && $values['component_uuid'] !== ''
      ? $values['component_uuid']
      : NULL;

    try {
      if ($componentUuid !== NULL) {
        $this->responseValidator->validateComponentExistsInPage($componentUuid);
      }

      $contents = $this->pageBuilderHelper->getComponentContents($componentUuid);

      return ExecutableResult::success(
        new TranslatableMarkup('Component props retrieved.'),
        ['result' => Yaml::dump($contents, 10, 2)],
      );
    }
    catch (\InvalidArgumentException $e) {
      return ExecutableResult::success(
        new TranslatableMarkup('Component not found.'),
        ['result' => Yaml::dump(['error' => $e->getMessage()], 10, 2)],
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowedIfHasPermission($account, 'use ai agents canvas direct edit');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
