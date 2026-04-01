<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit_mcp\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Handles MCP JSON-RPC 2.0 requests.
 *
 * Parses incoming JSON-RPC requests, routes to the appropriate handler
 * (initialize, tools/list, tools/call), and returns JSON-RPC responses.
 */
final class McpRequestHandler {

  /**
   * MCP protocol version.
   */
  private const PROTOCOL_VERSION = '2025-03-26';

  /**
   * Server name for MCP initialize response.
   */
  private const SERVER_NAME = 'drupal-canvas-direct-edit';

  /**
   * Server version.
   */
  private const SERVER_VERSION = '1.0.0';

  /**
   * Constructs an McpRequestHandler.
   *
   * @param \Drupal\ai_agents_canvas_direct_edit_mcp\Service\McpToolBridge $toolBridge
   *   The tool bridge service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly McpToolBridge $toolBridge,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Processes a JSON-RPC 2.0 request.
   *
   * @param array $request
   *   The decoded JSON-RPC request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The authenticated user account.
   *
   * @return array
   *   A JSON-RPC 2.0 response.
   */
  public function handle(array $request, AccountInterface $account): array {
    $id = $request['id'] ?? NULL;
    $method = $request['method'] ?? '';
    $params = $request['params'] ?? [];

    if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0') {
      return $this->errorResponse($id, -32600, 'Invalid Request: missing or invalid jsonrpc version');
    }

    if ($method === '') {
      return $this->errorResponse($id, -32600, 'Invalid Request: missing method');
    }

    return match ($method) {
      'initialize' => $this->handleInitialize($id),
      'tools/list' => $this->handleToolsList($id),
      'tools/call' => $this->handleToolsCall($id, $params, $account),
      default => $this->errorResponse($id, -32601, sprintf('Method not found: %s', $method)),
    };
  }

  /**
   * Checks whether the MCP server is enabled.
   *
   * @return bool
   *   TRUE if the server is enabled.
   */
  public function isEnabled(): bool {
    return (bool) $this->configFactory
      ->get('ai_agents_canvas_direct_edit_mcp.settings')
      ->get('enabled');
  }

  /**
   * Returns allowed CORS origins from config.
   *
   * @return string[]
   *   Array of allowed origin URLs.
   */
  public function getAllowedOrigins(): array {
    return $this->configFactory
      ->get('ai_agents_canvas_direct_edit_mcp.settings')
      ->get('allowed_origins') ?? [];
  }

  /**
   * Handles the 'initialize' method.
   */
  private function handleInitialize(mixed $id): array {
    return $this->successResponse($id, [
      'protocolVersion' => self::PROTOCOL_VERSION,
      'capabilities' => [
        'tools' => ['listChanged' => FALSE],
      ],
      'serverInfo' => [
        'name' => self::SERVER_NAME,
        'version' => self::SERVER_VERSION,
      ],
    ]);
  }

  /**
   * Handles the 'tools/list' method.
   */
  private function handleToolsList(mixed $id): array {
    return $this->successResponse($id, [
      'tools' => $this->toolBridge->listTools(),
    ]);
  }

  /**
   * Handles the 'tools/call' method.
   */
  private function handleToolsCall(mixed $id, array $params, AccountInterface $account): array {
    $name = $params['name'] ?? '';
    $arguments = $params['arguments'] ?? [];

    if ($name === '') {
      return $this->errorResponse($id, -32602, 'Invalid params: missing tool name');
    }

    try {
      $result = $this->toolBridge->executeTool($name, $arguments, $account);
      return $this->successResponse($id, $result);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($id, -32602, $e->getMessage());
    }
    catch (\Drupal\Core\Access\AccessException $e) {
      return $this->errorResponse($id, -32603, $e->getMessage());
    }
    catch (\Exception $e) {
      return $this->errorResponse($id, -32603, 'Internal error: ' . $e->getMessage());
    }
  }

  /**
   * Builds a JSON-RPC 2.0 success response.
   */
  private function successResponse(mixed $id, mixed $result): array {
    return [
      'jsonrpc' => '2.0',
      'id' => $id,
      'result' => $result,
    ];
  }

  /**
   * Builds a JSON-RPC 2.0 error response.
   */
  private function errorResponse(mixed $id, int $code, string $message): array {
    return [
      'jsonrpc' => '2.0',
      'id' => $id,
      'error' => [
        'code' => $code,
        'message' => $message,
      ],
    ];
  }

}
