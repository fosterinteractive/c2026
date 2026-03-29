<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\Controller;

use Drupal\canvas_ai\AiResponseValidator;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\canvas_ai_scoping\Service\DirectEditMatcher;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles deterministic component edits without invoking the LLM agent chain.
 *
 * This endpoint implements ADR-004 (Simple Operations Bypass LLM). When the
 * user selects a component and sends a message that matches a deterministic
 * edit pattern ("change the heading to X"), this controller applies the edit
 * directly using the same Canvas pipeline as the AI agents, but at zero
 * LLM token cost and sub-second latency.
 *
 * The frontend can call this endpoint first; if it returns a 422, route to
 * the standard AI endpoint instead.
 */
final class DirectEditController extends ControllerBase {

  public function __construct(
    private readonly DirectEditMatcher $matcher,
    private readonly AiResponseValidator $responseValidator,
    private readonly CanvasAiPageBuilderHelper $pageBuilderHelper,
    private readonly CanvasAiTempStore $canvasAiTempStore,
    private readonly CsrfTokenGenerator $csrfTokenGenerator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('canvas_ai_scoping.direct_edit_matcher'),
      $container->get('canvas_ai.response_validator'),
      $container->get('canvas_ai.page_builder_helper'),
      $container->get('canvas_ai.tempstore'),
      $container->get('csrf_token'),
      $container->get('logger.factory')->get('canvas_ai_scoping'),
    );
  }

  /**
   * Attempts a deterministic edit on the selected component.
   *
   * Request body (JSON):
   * - message: string — the user's chat message
   * - component_uuid: string — UUID of the selected component
   * - component_name: string — SDC name (e.g., 'sdc.byte_theme.heading')
   * - current_layout: object — the full page layout (for tempstore)
   *
   * Returns:
   * - 200 with update operations if the edit was applied deterministically.
   * - 422 if the message doesn't match a deterministic pattern (route to AI).
   * - 400 for validation errors.
   * - 403 for CSRF or permission errors.
   */
  public function edit(Request $request): JsonResponse {
    $token = $request->headers->get('X-CSRF-Token') ?? '';
    if (!$this->csrfTokenGenerator->validate($token, 'canvas_ai.canvas_builder')) {
      throw new AccessDeniedHttpException('Invalid CSRF token');
    }

    $body = Json::decode($request->getContent());
    if (!is_array($body)) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Invalid request body'], 400);
    }

    $message = $body['message'] ?? '';
    $componentUuid = $body['component_uuid'] ?? '';
    $componentName = $body['component_name'] ?? '';
    $currentLayout = $body['current_layout'] ?? NULL;

    if ($message === '' || $componentUuid === '' || $componentName === '') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Missing required fields: message, component_uuid, component_name',
      ], 400);
    }

    // Validate input formats before touching any downstream service.
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $componentUuid)) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Invalid component_uuid format.'], 400);
    }
    if (!preg_match('/^sdc\.[a-z0-9_]+\.[a-z0-9_\-]+$/', $componentName)) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Invalid component_name format.'], 400);
    }
    if (mb_strlen($message) > 2000) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Message too long.'], 400);
    }

    // Store layout in tempstore for validation.
    if ($currentLayout !== NULL) {
      $this->canvasAiTempStore->setData(
        CanvasAiTempStore::CURRENT_LAYOUT_KEY,
        Json::encode($currentLayout)
      );
    }

    // Store component prop values map for validateComponentExistsInPage().
    // This mirrors CanvasBuilder::render() which stores prompt['layout']
    // (the flat {uuid: props} map) in COMPONENTS_IN_PAGE_WITH_PROP_VALUES_KEY.
    $componentProps = $body['layout'] ?? NULL;
    if ($componentProps !== NULL) {
      $this->canvasAiTempStore->setData(
        CanvasAiTempStore::COMPONENTS_IN_PAGE_WITH_PROP_VALUES_KEY,
        Json::encode($componentProps)
      );
    }

    // Attempt pattern match.
    $match = $this->matcher->match($message, $componentName);
    if ($match === NULL) {
      return new JsonResponse([
        'status' => FALSE,
        'reason' => 'no_match',
        'message' => 'Message does not match a deterministic edit pattern',
      ], 422);
    }

    // Validate that the component exists in the page.
    try {
      $this->responseValidator->validateComponentExistsInPage($componentUuid);
    }
    catch (\Exception $e) {
      $this->logger->error('DirectEdit: component validation failed for @uuid: @msg', [
        '@uuid' => $componentUuid,
        '@msg' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Component not found in current page.',
      ], 400);
    }

    // Validate the prop value against the component schema.
    $propValues = [$match['prop'] => $match['value']];
    try {
      $this->responseValidator->validateComponentPropUpdate($componentName, $propValues);
    }
    catch (\Exception $e) {
      $this->logger->error('DirectEdit: prop validation failed for @component/@prop: @msg', [
        '@component' => $componentName,
        '@prop' => $match['prop'],
        '@msg' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'The requested change is not valid for this component.',
      ], 400);
    }

    // Populate media prop values if needed.
    $propValues = $this->pageBuilderHelper->populateMediaPropIfNeeded(
      $componentName,
      $componentUuid,
      $propValues
    );

    // Build the structured output matching UpdateComponentData format.
    $updateComponents = [
      [
        'uuid' => $componentUuid,
        'fieldValues' => $propValues,
      ],
    ];

    // Use the same response builder as the AI pipeline.
    $response = ['status' => TRUE];
    $response = $this->pageBuilderHelper->includeUpdateOperations($updateComponents, $response);

    // Add metadata for tracking and measurement.
    $response['direct_edit'] = TRUE;
    $response['tokens_used'] = 0;
    $response['matched_prop'] = $match['prop'];
    $response['matched_value'] = $match['value'];

    $this->logger->notice(
      'DirectEdit: @component.@prop = @value (0 tokens, deterministic)',
      [
        '@component' => $componentName,
        '@prop' => $match['prop'],
        '@value' => is_scalar($match['value']) ? (string) $match['value'] : Json::encode($match['value']),
      ]
    );

    return new JsonResponse($response);
  }

}
