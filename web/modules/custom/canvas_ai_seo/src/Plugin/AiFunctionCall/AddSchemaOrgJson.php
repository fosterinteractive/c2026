<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_seo\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\canvas_ai\Plugin\AiFunctionCall\BuilderResponseFunctionCallInterface;

/**
 * Plugin implementation of the add Schema.org JSON-LD function.
 */
#[FunctionCall(
  id: 'ai_agent:add_schema_org_json',
  function_name: 'ai_agent_add_schema_org_json',
  name: 'Add Schema.org JSON-LD',
  description: 'This method allows you to add Schema.org JSON-LD structured data.',
  group: 'modification_tools',
  module_dependencies: ['canvas_ai_seo'],
  context_definitions: [
    'schema_org_data' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Schema.org JSON-LD data"),
      description: new TranslatableMarkup("The Schema.org JSON-LD structured data to add."),
      required: TRUE
    ),
  ],
)]
final class AddSchemaOrgJson extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface, BuilderResponseFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    try {
      $schema_org_data = $this->getContextValue('schema_org_data');
      $decoded = json_decode($schema_org_data, TRUE, 512, JSON_THROW_ON_ERROR);
      $canonical = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      $this->setStructuredOutput([
        'schema_org_data' => $canonical,
      ]);
      $this->setOutput('Schema.org JSON-LD data added successfully.');
    }
    catch (\JsonException $e) {
      $this->setOutput(\sprintf('Failed to process Schema.org JSON-LD data: %s', $e->getMessage()));
    }
  }

}
