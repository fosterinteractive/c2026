<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Plugin\tool\Tool;

use Drupal\canvas_ai\AiResponseValidator;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates property values on an existing Canvas component.
 *
 * Accepts a JSON-encoded object of prop_name to value pairs and applies them
 * to the specified component. Use get_component_schema to discover valid prop
 * names before calling this tool.
 */
#[Tool(
  id: 'ai_agents_canvas_direct_edit:update_component_props',
  label: new TranslatableMarkup('Update Component Props'),
  description: new TranslatableMarkup('Updates property values on an existing component in the page. Requires exact prop names and valid values. Use get_component_schema to discover valid props first.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'component_uuid' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component UUID'),
      description: new TranslatableMarkup('UUID of the component to update.'),
      required: TRUE,
    ),
    'component_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component Name'),
      description: new TranslatableMarkup('SDC component ID of the component (e.g. sdc.mytheme.heading).'),
      required: TRUE,
    ),
    'prop_values' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Prop Values'),
      description: new TranslatableMarkup('JSON-encoded object of prop_name to value pairs to apply to the component.'),
      required: TRUE,
    ),
  ],
)]
final class UpdateComponentProps extends ToolBase {

  /**
   * The Canvas AI response validator service.
   */
  protected AiResponseValidator $responseValidator;

  /**
   * The Canvas AI page builder helper service.
   */
  protected CanvasAiPageBuilderHelper $pageBuilderHelper;

  /**
   * The logger channel factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->responseValidator = $container->get('canvas_ai.response_validator');
    $instance->pageBuilderHelper = $container->get('canvas_ai.page_builder_helper');
    $instance->loggerFactory = $container->get('logger.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $uuid = $values['component_uuid'] ?? '';
    $componentName = $values['component_name'] ?? '';
    $propValuesRaw = $values['prop_values'] ?? '';

    try {
      $props = json_decode($propValuesRaw, TRUE);
      if (!is_array($props)) {
        return ExecutableResult::success(
          new TranslatableMarkup('Invalid prop_values: must be a JSON-encoded object.'),
          ['result' => json_encode(['error' => 'prop_values must be a JSON-encoded object'])],
        );
      }

      $this->responseValidator->validateComponentExistsInPage($uuid);
      $this->responseValidator->validateComponentPropUpdate($componentName, $props);

      $props = $this->pageBuilderHelper->populateMediaPropIfNeeded($componentName, $uuid, $props);

      $updateComponents = [['uuid' => $uuid, 'fieldValues' => $props]];
      $response = $this->pageBuilderHelper->includeUpdateOperations($updateComponents, ['status' => TRUE]);

      return ExecutableResult::success(
        new TranslatableMarkup('Component props updated successfully.'),
        ['result' => json_encode($response)],
      );
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_agents_canvas_direct_edit')->error($e->getMessage());
      return ExecutableResult::success(
        new TranslatableMarkup('Failed to update component props.'),
        ['result' => sprintf('Failed to update component props: %s', $e->getMessage())],
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
