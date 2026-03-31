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
use Symfony\Component\Yaml\Yaml;

/**
 * Adds a new component to a Canvas page region.
 *
 * Builds the operation structure expected by the Canvas UI and delegates
 * YAML-to-array mapping to CanvasAiPageBuilderHelper::customYamlToArrayMapper,
 * mirroring the approach used by SetAIGeneratedComponentStructure.
 */
#[Tool(
  id: 'ai_agents_canvas_direct_edit:add_component',
  label: new TranslatableMarkup('Add Component'),
  description: new TranslatableMarkup('Adds a component to a page region with optional positioning relative to an existing component. Use get_component_catalog to discover available components.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'component_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component ID'),
      description: new TranslatableMarkup('Component ID to add (e.g. sdc.byte_theme.heading).'),
      required: TRUE,
    ),
    'region' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Region'),
      description: new TranslatableMarkup('Target region name where the component should be placed.'),
      required: TRUE,
    ),
    'prop_values' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Prop Values'),
      description: new TranslatableMarkup('JSON-encoded initial prop values for the new component. Optional.'),
      required: FALSE,
    ),
    'reference_uuid' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Reference UUID'),
      description: new TranslatableMarkup('UUID of an existing component to position relative to. Optional.'),
      required: FALSE,
    ),
    'placement' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Placement'),
      description: new TranslatableMarkup("Placement relative to reference component: 'above' or 'below'. Defaults to 'below'. Optional."),
      required: FALSE,
    ),
  ],
)]
final class AddComponent extends ToolBase {

  /**
   * The Canvas AI page builder helper service.
   */
  protected CanvasAiPageBuilderHelper $pageBuilderHelper;

  /**
   * The Canvas AI response validator service.
   */
  protected AiResponseValidator $responseValidator;

  /**
   * The logger channel factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->pageBuilderHelper = $container->get('canvas_ai.page_builder_helper');
    $instance->responseValidator = $container->get('canvas_ai.response_validator');
    $instance->loggerFactory = $container->get('logger.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $componentId = $values['component_id'] ?? '';
    $region = $values['region'] ?? '';
    $propValuesRaw = $values['prop_values'] ?? NULL;
    $referenceUuid = isset($values['reference_uuid']) && $values['reference_uuid'] !== ''
      ? $values['reference_uuid']
      : NULL;
    $placement = isset($values['placement']) && $values['placement'] !== ''
      ? $values['placement']
      : 'below';

    try {
      $props = [];
      if ($propValuesRaw !== NULL && $propValuesRaw !== '') {
        $decoded = json_decode($propValuesRaw, TRUE);
        if (is_array($decoded)) {
          $props = $decoded;
        }
      }

      if ($referenceUuid !== NULL) {
        $this->responseValidator->validateComponentExistsInPage($referenceUuid);
      }

      // Build the operation structure that customYamlToArrayMapper expects.
      // When reference_uuid is given use above/below placement; otherwise use
      // 'inside' placement targeting the region directly.
      $operation = [
        'components' => [
          [$componentId => ['props' => $props]],
        ],
      ];

      if ($referenceUuid !== NULL) {
        $operation['placement'] = $placement;
        $operation['reference_uuid'] = $referenceUuid;
      }
      else {
        $operation['placement'] = 'inside';
        $operation['target'] = $region;
      }

      $structureArray = ['operations' => [$operation]];
      $structureYaml = Yaml::dump($structureArray, 10, 2);

      $this->responseValidator->validateComponentStructure($operation['components']);

      $mapped = $this->pageBuilderHelper->customYamlToArrayMapper($structureYaml);

      return ExecutableResult::success(
        new TranslatableMarkup('Component added successfully.'),
        ['result' => json_encode($mapped)],
      );
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_agents_canvas_direct_edit')->error($e->getMessage());
      return ExecutableResult::success(
        new TranslatableMarkup('Failed to add component.'),
        ['result' => sprintf('Failed to add component: %s', $e->getMessage())],
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
