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
use Drupal\ai_agents_canvas_direct_edit\Service\AiProviderAvailabilityCheckerInterface;
use Drupal\ai_agents_canvas_direct_edit\Service\DirectEditMatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deterministic Canvas component property edit matcher.
 *
 * Attempts to resolve a simple Canvas component property edit without invoking
 * the LLM. Returns matched prop/value pairs on success, or a structured miss
 * when AI reasoning is required. Call this before update_component_inputs.
 */
#[Tool(
  id: 'ai_agents_canvas_direct_edit:match_direct_edit',
  label: new TranslatableMarkup('Match Direct Edit'),
  description: new TranslatableMarkup('Attempts to resolve a simple Canvas component property edit deterministically from SDC schemas. Returns matched prop/value pairs on success, or a structured miss when the edit requires AI reasoning. Call this before update_component_inputs to skip the LLM for trivial changes.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'message' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('User Message'),
      description: new TranslatableMarkup('The user chat message describing the desired property change.'),
      required: TRUE,
    ),
    'component_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component Name'),
      description: new TranslatableMarkup('The SDC component ID of the selected component (e.g. sdc.mytheme.heading).'),
      required: TRUE,
    ),
    'current_prop_values' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Current Prop Values'),
      description: new TranslatableMarkup('JSON-encoded object of current prop values for the component. Required for relative adjustments (bigger/smaller). Pass null or omit if unavailable.'),
      required: FALSE,
    ),
  ],
)]
class MatchDirectEdit extends ToolBase {

  /**
   * The direct edit matcher service.
   */
  protected DirectEditMatcher $matcher;

  /**
   * The AI provider availability checker.
   */
  protected AiProviderAvailabilityCheckerInterface $availabilityChecker;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->matcher = $container->get('ai_agents_canvas_direct_edit.direct_edit_matcher');
    $instance->availabilityChecker = $container->get('ai_agents_canvas_direct_edit.ai_provider_availability_checker');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $message = $values['message'] ?? '';
    $componentName = $values['component_name'] ?? '';
    $currentPropValuesRaw = $values['current_prop_values'] ?? NULL;

    $currentPropValues = NULL;
    if ($currentPropValuesRaw !== NULL && $currentPropValuesRaw !== '') {
      $decoded = json_decode($currentPropValuesRaw, TRUE);
      if (is_array($decoded)) {
        $currentPropValues = $decoded;
      }
    }

    $matchResult = $this->matcher->match($message, $componentName, $currentPropValues);

    if (!$matchResult->matched) {
      $output = json_encode([
        'status' => 'no_match',
        'component_name' => $componentName,
        'ai_available' => $this->availabilityChecker->isAiAvailable(),
        'complexity_signal' => $matchResult->complexitySignal,
        'confidence' => $matchResult->confidence,
      ]);
      return ExecutableResult::success(
        new TranslatableMarkup('No deterministic match found. Proceed with LLM reasoning.'),
        ['result' => $output],
      );
    }

    if (isset($matchResult['changes'])) {
      $output = json_encode([
        'status' => 'matched',
        'changes' => $matchResult['changes'],
        'component_name' => $componentName,
      ]);
    }
    else {
      $output = json_encode([
        'status' => 'matched',
        'changes' => [['prop' => $matchResult['prop'], 'value' => $matchResult['value']]],
        'component_name' => $componentName,
      ]);
    }

    return ExecutableResult::success(
      new TranslatableMarkup('Deterministic match found. Use the returned changes with update_component_inputs.'),
      ['result' => $output],
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
