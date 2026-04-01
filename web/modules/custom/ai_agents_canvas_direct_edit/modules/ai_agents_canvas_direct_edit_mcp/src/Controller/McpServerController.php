<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit_mcp\Controller;

use Drupal\ai_agents_canvas_direct_edit_mcp\Service\McpRequestHandler;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the MCP JSON-RPC endpoint.
 *
 * Handles POST /api/mcp/canvas — validates content type, checks enabled
 * state, parses JSON-RPC, and delegates to McpRequestHandler.
 */
final class McpServerController extends ControllerBase {

  public function __construct(
    private readonly McpRequestHandler $requestHandler,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai_agents_canvas_direct_edit_mcp.request_handler'),
      $container->get('current_user'),
    );
  }

  /**
   * Handles an incoming MCP request.
   */
  public function handle(Request $request): JsonResponse {
    // Check if server is enabled.
    if (!$this->requestHandler->isEnabled()) {
      return new JsonResponse([
        'jsonrpc' => '2.0',
        'id' => NULL,
        'error' => [
          'code' => -32000,
          'message' => 'MCP server is disabled',
        ],
      ], 503);
    }

    // Validate content type.
    $contentType = $request->headers->get('Content-Type', '');
    if (!str_contains($contentType, 'application/json')) {
      return new JsonResponse([
        'jsonrpc' => '2.0',
        'id' => NULL,
        'error' => [
          'code' => -32700,
          'message' => 'Parse error: Content-Type must be application/json',
        ],
      ], 400);
    }

    // Parse request body.
    $body = Json::decode($request->getContent());
    if (!is_array($body)) {
      return new JsonResponse([
        'jsonrpc' => '2.0',
        'id' => NULL,
        'error' => [
          'code' => -32700,
          'message' => 'Parse error: invalid JSON',
        ],
      ], 400);
    }

    // Handle the JSON-RPC request.
    $response = $this->requestHandler->handle($body, $this->currentUser->getAccount());

    // Build HTTP response with CORS headers.
    $jsonResponse = new JsonResponse($response);
    $this->addCorsHeaders($jsonResponse, $request);

    // Track MCP session via header.
    $sessionId = $request->headers->get('Mcp-Session-Id');
    if ($sessionId !== NULL) {
      $jsonResponse->headers->set('Mcp-Session-Id', $sessionId);
    }

    return $jsonResponse;
  }

  /**
   * Adds CORS headers based on allowed origins configuration.
   */
  private function addCorsHeaders(JsonResponse $response, Request $request): void {
    $allowedOrigins = $this->requestHandler->getAllowedOrigins();
    if (empty($allowedOrigins)) {
      return;
    }

    $origin = $request->headers->get('Origin', '');
    if ($origin !== '' && in_array($origin, $allowedOrigins, TRUE)) {
      $response->headers->set('Access-Control-Allow-Origin', $origin);
      $response->headers->set('Access-Control-Allow-Methods', 'POST');
      $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Mcp-Session-Id');
    }
  }

}
