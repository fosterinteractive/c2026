<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit_mcp\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\tool\ToolManager;

/**
 * Bridges Drupal Tool API plugins to MCP tool schemas.
 *
 * Iterates tool plugin definitions, filters for canvas direct-edit tools,
 * and converts their input definitions to JSON Schema for MCP discovery.
 */
final class McpToolBridge {

  /**
   * Tool plugin prefix to filter on.
   */
  private const TOOL_PREFIX = 'ai_agents_canvas_direct_edit:';

  /**
   * Constructs a McpToolBridge.
   *
   * @param \Drupal\tool\ToolManager $toolManager
   *   The Tool API plugin manager.
   */
  public function __construct(
    private readonly ToolManager $toolManager,
  ) {}

  /**
   * Lists all canvas direct-edit tools in MCP format.
   *
   * @return array<int, array{name: string, description: string, inputSchema: array}>
   *   Array of MCP tool definitions.
   */
  public function listTools(): array {
    $tools = [];
    $definitions = $this->toolManager->getDefinitions();

    foreach ($definitions as $id => $definition) {
      if (!str_starts_with($id, self::TOOL_PREFIX)) {
        continue;
      }

      $tools[] = [
        'name' => $id,
        'description' => (string) ($definition['description'] ?? ''),
        'inputSchema' => $this->buildInputSchema($definition),
      ];
    }

    return $tools;
  }

  /**
   * Executes a tool by name with the given arguments.
   *
   * @param string $name
   *   The tool plugin ID.
   * @param array $arguments
   *   The tool input arguments.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for access checks.
   *
   * @return array
   *   The tool execution result.
   *
   * @throws \InvalidArgumentException
   *   If the tool is not a canvas direct-edit tool.
   * @throws \Drupal\Core\Access\AccessException
   *   If the account lacks permission.
   */
  public function executeTool(string $name, array $arguments, AccountInterface $account): array {
    if (!str_starts_with($name, self::TOOL_PREFIX)) {
      throw new \InvalidArgumentException(sprintf('Unknown tool: %s', $name));
    }

    /** @var \Drupal\tool\Tool\ToolInterface $plugin */
    $plugin = $this->toolManager->createInstance($name);

    $access = $plugin->access($arguments, $account, TRUE);
    if (!$access->isAllowed()) {
      throw new \Drupal\Core\Access\AccessException(sprintf('Access denied for tool: %s', $name));
    }

    $result = $plugin->execute($arguments, $account);

    return [
      'content' => [
        [
          'type' => 'text',
          'text' => $result->getMessage() ? (string) $result->getMessage() : '',
        ],
      ],
      'isError' => !$result->isSuccess(),
    ];
  }

  /**
   * Converts a tool plugin definition to a JSON Schema input object.
   *
   * @param array $definition
   *   The tool plugin definition.
   *
   * @return array
   *   A JSON Schema object describing the tool's input parameters.
   */
  private function buildInputSchema(array $definition): array {
    $properties = [];
    $required = [];

    $inputDefinitions = $definition['input_definitions'] ?? [];
    foreach ($inputDefinitions as $name => $inputDef) {
      $property = [
        'type' => $this->mapDataType($inputDef->getDataType()),
        'description' => (string) ($inputDef->getDescription() ?? ''),
      ];
      $properties[$name] = $property;

      if ($inputDef->isRequired()) {
        $required[] = $name;
      }
    }

    return [
      'type' => 'object',
      'properties' => $properties,
      'required' => $required,
    ];
  }

  /**
   * Maps Drupal data types to JSON Schema types.
   *
   * @param string $dataType
   *   The Drupal typed data type.
   *
   * @return string
   *   The JSON Schema type.
   */
  private function mapDataType(string $dataType): string {
    return match ($dataType) {
      'integer' => 'integer',
      'float' => 'number',
      'boolean' => 'boolean',
      default => 'string',
    };
  }

}
