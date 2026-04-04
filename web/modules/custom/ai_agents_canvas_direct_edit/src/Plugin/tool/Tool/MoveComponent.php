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
 * Moves an existing Canvas component to a new position in the page.
 *
 * Mirrors the logic of MoveComponentInPage AiFunctionCall. Specify either a
 * target region (for an empty region) or a reference_uuid with placement to
 * position the component relative to an existing component.
 */
#[Tool(
  id: 'ai_agents_canvas_direct_edit:move_component',
  label: new TranslatableMarkup('Move Component'),
  description: new TranslatableMarkup('Moves an existing component to a new position in the page. Specify a target region or position relative to another component.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'component_uuid' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component UUID'),
      description: new TranslatableMarkup('UUID of the component to move.'),
      required: TRUE,
    ),
    'region' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Region'),
      description: new TranslatableMarkup('Target region name. Use only when moving to an empty region.'),
      required: FALSE,
    ),
    'reference_uuid' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Reference UUID'),
      description: new TranslatableMarkup('UUID of an existing component to position relative to.'),
      required: FALSE,
    ),
    'placement' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Placement'),
      description: new TranslatableMarkup("Placement relative to the reference component: 'above' or 'below'. Required when reference_uuid is provided."),
      required: FALSE,
    ),
  ],
)]
final class MoveComponent extends ToolBase {

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
    $region = isset($values['region']) && $values['region'] !== ''
      ? $values['region']
      : NULL;
    $referenceUuid = isset($values['reference_uuid']) && $values['reference_uuid'] !== ''
      ? $values['reference_uuid']
      : NULL;
    $placement = isset($values['placement']) && $values['placement'] !== ''
      ? $values['placement']
      : NULL;

    try {
      $this->responseValidator->validateComponentExistsInPage($uuid);

      if ($region !== NULL && $referenceUuid !== NULL) {
        return ExecutableResult::success(
          new TranslatableMarkup('Invalid parameters.'),
          ['result' => 'If region is used, reference_uuid must not be provided.'],
        );
      }

      if ($referenceUuid !== NULL) {
        $this->responseValidator->validateComponentExistsInPage($referenceUuid);
        if ($placement === NULL) {
          return ExecutableResult::success(
            new TranslatableMarkup('Invalid parameters.'),
            ['result' => 'If reference_uuid is provided, placement must also be provided.'],
          );
        }
      }

      $nodePath = $this->pageBuilderHelper->calculateNodepathToMoveComponent($region, $referenceUuid, $placement);

      $result = ['uuid' => $uuid, 'nodePath' => $nodePath];

      return ExecutableResult::success(
        new TranslatableMarkup('Component moved successfully.'),
        ['result' => json_encode($result)],
      );
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_agents_canvas_direct_edit')->error($e->getMessage());
      return ExecutableResult::success(
        new TranslatableMarkup('Failed to move component.'),
        ['result' => sprintf('Failed to move component: %s', $e->getMessage())],
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
