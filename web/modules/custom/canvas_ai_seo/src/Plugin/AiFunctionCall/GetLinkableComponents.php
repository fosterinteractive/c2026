<?php

namespace Drupal\canvas_ai_seo\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin to get components that have linkable text content.
 */
#[FunctionCall(
  id: 'canvas_ai_seo:get_linkable_components',
  function_name: 'get_linkable_components',
  name: 'Get Linkable Components',
  description: 'Retrieves components from the current page that conatins a Rich text prop. Use this to identify components with text content that can be enriched with internal links. The output preserves the layout tree structure, showing ancestor components with their uuid and name, and only the linkable components include their content.',
  group: 'information_tools',
  module_dependencies: ['canvas_ai_seo'],
  context_definitions: [
    'param_with_no_use' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Param With No Use"),
      description: new TranslatableMarkup("Anthropic provider does not support tools that don't contain any context definitions, so create a dummy parameter."),
      required: FALSE,
    ),
  ],
)]
final class GetLinkableComponents extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The Canvas AI page builder helper.
   *
   * @var \Drupal\canvas_ai\CanvasAiPageBuilderHelper
   */
  protected CanvasAiPageBuilderHelper $pageBuilderHelper;

  /**
   * The Canvas AI tempstore.
   *
   * @var \Drupal\canvas_ai\CanvasAiTempStore
   */
  protected CanvasAiTempStore $canvasAiTempstore;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface | static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
    );
    $instance->pageBuilderHelper = $container->get('canvas_ai.page_builder_helper');
    $instance->canvasAiTempstore = $container->get('canvas_ai.tempstore');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->loggerFactory = $container->get('logger.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    try {
      // Get the contents of all components on the current page, keyed by their UUID.
      $contents = $this->pageBuilderHelper->getComponentContents();

      // Get the current layout and extract the content region.
      $current_layout_json = $this->canvasAiTempstore->getData(CanvasAiTempStore::CURRENT_LAYOUT_KEY) ?? '';
      $current_layout = Json::decode($current_layout_json);

      if (!is_array($current_layout) || !isset($current_layout['regions']['content'])) {
        $this->setOutput('There are no linkable fields');
        return;
      }

      // Only the components placed in the content region of the current page
      // are considered for internal linking.
      $content_components = $current_layout['regions']['content']['components'] ?? [];

      // Get the linkable components along with their ancestor components.
      // The ancestor components would only include their uuid and name, while the
      // linkable components also include their content.
      $tree = $this->buildLinkableTree($content_components, $contents);

      if (empty($tree)) {
        $this->setOutput('There are no linkable fields');
        return;
      }

      $this->setOutput(Yaml::dump($tree, 10, 2));
    }
    catch (\InvalidArgumentException $e) {
      $this->loggerFactory->get('canvas_ai_seo')->error($e->getMessage());
      $this->setOutput(Yaml::dump(['error' => $e->getMessage()], 10, 2));
    }
  }

  /**
   * Finds components in the page with at least one Rich text prop.
   *
   * Ancestor components (First level components in the content region) include only uuid and name.
   * Linkable components also include their content props.
   *
   * @param array $components
   *   The components placed in the content region of the current page.
   * @param array $all_contents
   *   All component contents keyed by UUID.
   *
   * @return array
   *   The linkable tree array, or empty if no linkable components found.
   */
  private function buildLinkableTree(array $components, array $all_contents): array {
    $result = [];

    foreach ($components as $component) {
      if (!is_array($component) || empty($component['uuid'])) {
        continue;
      }

      $uuid = $component['uuid'];
      $name = $component['name'] ?? '';
      // Get the Rich text props for this component, if any.
      $linkable_prop_names = $this->getLinkablePropNames($name);

      // Recursively process slots to find linkable descendants.
      $pruned_slots = [];
      if (!empty($component['slots']) && is_array($component['slots'])) {
        foreach ($component['slots'] as $slot_key => $slot_data) {
          $slot_components = $slot_data['components'] ?? [];
          $pruned_children = $this->buildLinkableTree($slot_components, $all_contents);
          if (!empty($pruned_children)) {
            $pruned_slots[$slot_key] = ['components' => $pruned_children];
          }
        }
      }

      $has_linkable_props = !empty($linkable_prop_names) && !empty($all_contents[$uuid]);

      // Include this component if it has linkable props or linkable descendants.
      if ($has_linkable_props || !empty($pruned_slots)) {
        $node = [
          'name' => $name,
          'uuid' => $uuid,
        ];

        if ($has_linkable_props) {
          $props = $all_contents[$uuid] ?? [];
          $masked = [];
          foreach ($props as $key => $value) {
            if (in_array($key, $linkable_prop_names, TRUE)) {
              // Keep the exact prop name only for linkable props, so that the Agent
              // can identify them for internal linking.
              $masked[$key] = $value;
            }
            else {
              // Append the non linkable prop names with (non linkable prop) so that the
              // Agent would not consider them for internal linking.
              $masked[$key . ' (non linkable prop)'] = $value;
            }
          }
          $node['content'] = $masked;
        }

        if (!empty($pruned_slots)) {
          $node['slots'] = $pruned_slots;
        }

        $result[] = $node;
      }
    }

    return $result;
  }

  /**
   * Gets the names of linkable props for a component by its entity ID.
   *
   * A prop is linkable if its jsonSchema defines contentMediaType as text/html.
   *
   * @param string $component_id
   *   The component entity ID (e.g. 'sdc.byte_theme.text').
   *
   * @return string[]
   *   An array of prop names that are linkable.
   */
  private function getLinkablePropNames(string $component_id): array {
    /** @var Component $component_entity */
    $component_entity = $this->entityTypeManager->getStorage('component')->load($component_id);
    if (!$component_entity) {
      return [];
    }

    $client_side = $component_entity->normalizeForClientSide();
    $prop_sources = $client_side->values['propSources'] ?? [];

    $linkable = [];
    foreach ($prop_sources as $prop_name => $prop_definition) {
      $content_media_type = $prop_definition['jsonSchema']['contentMediaType'] ?? NULL;
      // If the contentMediaType is text/html,its a rich text prop and can have internal links.
      if ($content_media_type === 'text/html') {
        $linkable[] = $prop_name;
      }
    }

    return $linkable;
  }

}
