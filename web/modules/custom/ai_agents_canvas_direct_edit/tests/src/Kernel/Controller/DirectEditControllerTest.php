<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents_canvas_direct_edit\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\ai_agents_canvas_direct_edit\Controller\DirectEditController;
use Drupal\ai_agents_canvas_direct_edit\Service\AiProviderAvailabilityCheckerInterface;
use Drupal\ai_agents_canvas_direct_edit\Service\DirectEditMatcher;
use Drupal\canvas_ai\AiResponseValidator;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\ai_agents_canvas_direct_edit\Kernel\Tool\TestComponentSchemaLoader;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Kernel tests for DirectEditController.
 *
 * The controller is tested with constructor injection so that canvas_ai and its
 * heavy contrib dependencies do not need to be installed. The real
 * DirectEditMatcher is used with TestComponentSchemaLoader; all canvas_ai
 * services (AiResponseValidator, CanvasAiPageBuilderHelper, CanvasAiTempStore)
 * and CsrfTokenGenerator are mocked.
 *
 * @group ai_agents_canvas_direct_edit
 * @coversDefaultClass \Drupal\ai_agents_canvas_direct_edit\Controller\DirectEditController
 */
#[RunTestsInSeparateProcesses]
final class DirectEditControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'tool',
    'ai_agents_canvas_direct_edit',
  ];

  /**
   * A valid v4 UUID for use across tests.
   */
  private const VALID_UUID = '550e8400-e29b-41d4-a716-446655440000';

  /**
   * A valid component name for use across tests.
   */
  private const VALID_COMPONENT = 'sdc.byte_theme.heading';

  /**
   * The CSRF token generator mock, set to validate successfully by default.
   */
  private CsrfTokenGenerator $csrfTokenGenerator;

  /**
   * The AiResponseValidator mock.
   */
  private AiResponseValidator $responseValidator;

  /**
   * The CanvasAiPageBuilderHelper mock.
   */
  private CanvasAiPageBuilderHelper $pageBuilderHelper;

  /**
   * The CanvasAiTempStore mock.
   */
  private CanvasAiTempStore $canvasAiTempStore;

  /**
   * The logger mock.
   */
  private LoggerInterface $logger;

  /**
   * The config factory mock, configured with test settings.
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The AI provider availability checker mock. Returns TRUE by default.
   */
  private AiProviderAvailabilityCheckerInterface $availabilityChecker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Replace the schema loader with the test stub so DirectEditMatcher works
    // without a real theme system.
    $this->container->set(
      'ai_agents_canvas_direct_edit.component_schema_loader',
      new TestComponentSchemaLoader()
    );

    // Build a config factory that returns test settings for the module key.
    $this->configFactory = $this->buildTestConfigFactory(telemetryEnabled: FALSE);

    // Swap config.factory in the container so DirectEditMatcher reads test verbs.
    $this->container->set('config.factory', $this->configFactory);

    // CSRF token generator: validates successfully by default.
    $this->csrfTokenGenerator = $this->createMock(CsrfTokenGenerator::class);
    $this->csrfTokenGenerator
      ->method('validate')
      ->willReturn(TRUE);

    // AiResponseValidator: no-ops by default (both validate methods return void).
    $this->responseValidator = $this->createMock(AiResponseValidator::class);

    // CanvasAiPageBuilderHelper: mirrors the real pipeline behaviour.
    $this->pageBuilderHelper = $this->createMock(CanvasAiPageBuilderHelper::class);
    $this->pageBuilderHelper
      ->method('populateMediaPropIfNeeded')
      ->willReturnArgument(2);
    $this->pageBuilderHelper
      ->method('includeUpdateOperations')
      ->willReturnCallback(static function (array $updateComponents, array $response): array {
        $response['update_components'] = $updateComponents;
        return $response;
      });

    // CanvasAiTempStore: returns NULL (no prior page load) by default.
    $this->canvasAiTempStore = $this->createMock(CanvasAiTempStore::class);
    $this->canvasAiTempStore
      ->method('getData')
      ->willReturn(NULL);

    // Logger: record calls but do nothing.
    $this->logger = $this->createMock(LoggerInterface::class);

    // Availability checker: reports AI as available by default.
    $this->availabilityChecker = $this->createMock(AiProviderAvailabilityCheckerInterface::class);
    $this->availabilityChecker->method('isAiAvailable')->willReturn(TRUE);
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Builds a config factory mock that returns settings for the module config.
   */
  private function buildTestConfigFactory(bool $telemetryEnabled = FALSE): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static function (string $key) use ($telemetryEnabled) {
      return match ($key) {
        'edit_verbs' => ['change', 'set', 'update', 'modify', 'make', 'turn', 'switch', 'put'],
        'telemetry_enabled' => $telemetryEnabled,
        default => NULL,
      };
    });

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('ai_agents_canvas_direct_edit.settings')
      ->willReturn($config);

    return $configFactory;
  }

  /**
   * Creates the controller under test using constructor injection.
   *
   * Rebuilds DirectEditMatcher from the container so it picks up the
   * TestComponentSchemaLoader and the mocked config.factory.
   */
  private function createController(): DirectEditController {
    /** @var \Drupal\ai_agents_canvas_direct_edit\Service\DirectEditMatcher $matcher */
    $matcher = $this->container->get('ai_agents_canvas_direct_edit.direct_edit_matcher');

    return new DirectEditController(
      $matcher,
      $this->responseValidator,
      $this->pageBuilderHelper,
      $this->canvasAiTempStore,
      $this->csrfTokenGenerator,
      $this->logger,
      $this->configFactory,
      $this->availabilityChecker,
    );
  }

  /**
   * Builds a POST request with a JSON body and a valid CSRF token header.
   */
  private function buildRequest(mixed $body): Request {
    $content = is_string($body) ? $body : json_encode($body);
    $request = Request::create(
      '/admin/api/canvas/direct-edit',
      'POST',
      [],
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $content,
    );
    $request->headers->set('X-CSRF-Token', 'valid-token');
    return $request;
  }

  /**
   * Builds a minimal valid request body array.
   */
  private function validBody(string $message = 'change the heading to Hello'): array {
    return [
      'message' => $message,
      'component_uuid' => self::VALID_UUID,
      'component_name' => self::VALID_COMPONENT,
    ];
  }

  // ---------------------------------------------------------------------------
  // CSRF validation (403)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::edit
   */
  public function testInvalidCsrfTokenThrowsAccessDenied(): void {
    $this->csrfTokenGenerator = $this->createMock(CsrfTokenGenerator::class);
    $this->csrfTokenGenerator
      ->method('validate')
      ->willReturn(FALSE);

    $controller = $this->createController();
    $request = $this->buildRequest($this->validBody());

    $this->expectException(AccessDeniedHttpException::class);
    $controller->edit($request);
  }

  /**
   * @covers ::edit
   */
  public function testMissingCsrfTokenHeaderThrowsAccessDenied(): void {
    $this->csrfTokenGenerator = $this->createMock(CsrfTokenGenerator::class);
    $this->csrfTokenGenerator
      ->method('validate')
      ->with('', 'canvas_ai.canvas_builder')
      ->willReturn(FALSE);

    $controller = $this->createController();

    $request = Request::create(
      '/admin/api/canvas/direct-edit',
      'POST',
      [],
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode($this->validBody()),
    );
    // No X-CSRF-Token header set.

    $this->expectException(AccessDeniedHttpException::class);
    $controller->edit($request);
  }

  // ---------------------------------------------------------------------------
  // Input validation — 400 responses
  // ---------------------------------------------------------------------------

  /**
   * @covers ::edit
   */
  public function testNonJsonBodyReturns400(): void {
    $controller = $this->createController();
    $request = $this->buildRequest('not-json-at-all');

    $response = $controller->edit($request);

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
  }

  /**
   * @covers ::edit
   */
  public function testEmptyMessageReturns400(): void {
    $controller = $this->createController();
    $body = $this->validBody();
    $body['message'] = '';

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
    $this->assertStringContainsString('message', $data['message']);
  }

  /**
   * @covers ::edit
   */
  public function testEmptyComponentUuidReturns400(): void {
    $controller = $this->createController();
    $body = $this->validBody();
    $body['component_uuid'] = '';

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
  }

  /**
   * @covers ::edit
   */
  public function testEmptyComponentNameReturns400(): void {
    $controller = $this->createController();
    $body = $this->validBody();
    $body['component_name'] = '';

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
  }

  /**
   * @covers ::edit
   */
  public function testMissingAllFieldsReturns400(): void {
    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest([]));

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
  }

  /**
   * @covers ::edit
   */
  public function testInvalidUuidFormatReturns400(): void {
    $controller = $this->createController();
    $body = $this->validBody();
    $body['component_uuid'] = 'not-a-uuid';

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
    $this->assertStringContainsString('component_uuid', $data['message']);
  }

  /**
   * @covers ::edit
   */
  public function testUuidV3RejectedAsInvalidFormat(): void {
    // v3 UUIDs use the 3xxx pattern and should fail v4 validation.
    $controller = $this->createController();
    $body = $this->validBody();
    $body['component_uuid'] = '550e8400-e29b-31d4-a716-446655440000';

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(400, $response->getStatusCode());
  }

  /**
   * @covers ::edit
   */
  public function testInvalidComponentNameFormatReturns400(): void {
    $controller = $this->createController();
    $body = $this->validBody();
    $body['component_name'] = 'not.valid.Component Name';

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
    $this->assertStringContainsString('component_name', $data['message']);
  }

  /**
   * @covers ::edit
   */
  public function testComponentNameWithoutSdcPrefixReturns400(): void {
    $controller = $this->createController();
    $body = $this->validBody();
    $body['component_name'] = 'byte_theme.heading';

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(400, $response->getStatusCode());
  }

  /**
   * @covers ::edit
   */
  public function testComponentNameWithUppercaseReturns400(): void {
    $controller = $this->createController();
    $body = $this->validBody();
    $body['component_name'] = 'sdc.Byte_Theme.Heading';

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(400, $response->getStatusCode());
  }

  /**
   * @covers ::edit
   */
  public function testMessageExceeding2000CharsReturns400(): void {
    $controller = $this->createController();
    $body = $this->validBody();
    $body['message'] = str_repeat('a', 2001);

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
    $this->assertStringContainsString('long', $data['message']);
  }

  /**
   * @covers ::edit
   */
  public function testMessageExactly2000CharsPassesLengthValidation(): void {
    $controller = $this->createController();
    // A 2000-char message will pass the length check but then hit the matcher.
    // The matcher internally rejects messages over 500 chars, returning 422.
    $body = $this->validBody();
    $body['message'] = str_repeat('a', 2000);

    $response = $controller->edit($this->buildRequest($body));

    // Should NOT be 400 — length validation passes. Expect 422 (no match).
    $this->assertNotSame(400, $response->getStatusCode());
  }

  // ---------------------------------------------------------------------------
  // No-match responses (422)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::edit
   */
  public function testMessageWithNoMatchReturns422(): void {
    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest($this->validBody('add a new section')));

    $this->assertSame(422, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
    $this->assertSame('no_match', $data['reason']);
  }

  /**
   * @covers ::edit
   */
  public function testMessageWithUnknownEnumValueReturns422(): void {
    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest($this->validBody('set the color to rainbow')));

    $this->assertSame(422, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('no_match', $data['reason']);
  }

  /**
   * @covers ::edit
   */
  public function testVeryLongMessageThatExceedsMatcherLimitReturns422(): void {
    // Messages > 500 chars pass the controller's 2000-char check but are
    // rejected by the matcher's own 500-char fast-reject guard.
    $controller = $this->createController();
    $body = $this->validBody();
    $body['message'] = str_repeat('change the heading to ', 30);

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(422, $response->getStatusCode());
  }

  // ---------------------------------------------------------------------------
  // Successful matches (200)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::edit
   */
  public function testSinglePropEditReturns200WithDirectEditMetadata(): void {
    $controller = $this->createController();

    $response = $controller->edit(
      $this->buildRequest($this->validBody('change the heading to Hello World'))
    );

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertTrue($data['status']);
    $this->assertTrue($data['direct_edit']);
    $this->assertSame(0, $data['tokens_used']);
  }

  /**
   * @covers ::edit
   */
  public function testSinglePropEditIncludesMatchedPropAndValue(): void {
    $controller = $this->createController();

    $response = $controller->edit(
      $this->buildRequest($this->validBody('change the heading to Welcome to FinDrop'))
    );

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('heading_text', $data['matched_prop']);
    $this->assertSame('Welcome to FinDrop', $data['matched_value']);
  }

  /**
   * @covers ::edit
   */
  public function testEnumPropEditResolvesCanonicalValue(): void {
    $controller = $this->createController();

    $response = $controller->edit(
      $this->buildRequest($this->validBody('set the color to blue'))
    );

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    // "blue" is an alias for the "primary" canonical enum value.
    $this->assertSame('text_color', $data['matched_prop']);
    $this->assertSame('primary', $data['matched_value']);
  }

  /**
   * @covers ::edit
   */
  public function testIntegerPropEditReturnsIntegerValue(): void {
    $controller = $this->createController();

    $response = $controller->edit(
      $this->buildRequest($this->validBody('set the level to 3'))
    );

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('level', $data['matched_prop']);
    $this->assertSame(3, $data['matched_value']);
  }

  /**
   * @covers ::edit
   */
  public function testCompoundPropEditReturnsMatchedPropsArray(): void {
    $controller = $this->createController();
    $body = $this->validBody('change the heading to Welcome and set the color to blue');

    $response = $controller->edit($this->buildRequest($body));

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertTrue($data['status']);
    $this->assertTrue($data['direct_edit']);
    $this->assertSame(0, $data['tokens_used']);
    // Compound edits use matched_props (plural) not matched_prop.
    $this->assertContains('heading_text', $data['matched_props']);
    $this->assertContains('text_color', $data['matched_props']);
    $this->assertArrayNotHasKey('matched_prop', $data);
  }

  /**
   * @covers ::edit
   */
  public function testCompoundPropEditIncludesMessageWithCount(): void {
    $controller = $this->createController();
    $body = $this->validBody('change the heading to Welcome and set the color to blue');

    $response = $controller->edit($this->buildRequest($body));

    $data = json_decode($response->getContent(), TRUE);
    $this->assertStringContainsString('2', $data['message']);
  }

  /**
   * @covers ::edit
   */
  public function testSuccessfulEditCallsIncludeUpdateOperations(): void {
    $this->pageBuilderHelper = $this->createMock(CanvasAiPageBuilderHelper::class);
    $this->pageBuilderHelper
      ->method('populateMediaPropIfNeeded')
      ->willReturnArgument(2);
    $this->pageBuilderHelper
      ->expects($this->once())
      ->method('includeUpdateOperations')
      ->willReturnCallback(static function (array $updateComponents, array $response): array {
        $response['update_components'] = $updateComponents;
        return $response;
      });

    $controller = $this->createController();
    $controller->edit($this->buildRequest($this->validBody('change the heading to Hello')));
  }

  /**
   * @covers ::edit
   */
  public function testSuccessfulEditPassesCorrectUuidToUpdateComponents(): void {
    $capturedUpdate = NULL;
    $this->pageBuilderHelper = $this->createMock(CanvasAiPageBuilderHelper::class);
    $this->pageBuilderHelper
      ->method('populateMediaPropIfNeeded')
      ->willReturnArgument(2);
    $this->pageBuilderHelper
      ->method('includeUpdateOperations')
      ->willReturnCallback(static function (array $updateComponents, array $response) use (&$capturedUpdate): array {
        $capturedUpdate = $updateComponents;
        $response['update_components'] = $updateComponents;
        return $response;
      });

    $controller = $this->createController();
    $controller->edit($this->buildRequest($this->validBody('change the heading to Hello')));

    $this->assertNotNull($capturedUpdate);
    $this->assertCount(1, $capturedUpdate);
    $this->assertSame(self::VALID_UUID, $capturedUpdate[0]['uuid']);
  }

  // ---------------------------------------------------------------------------
  // Component validation failures after a match (400 via responseValidator)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::edit
   */
  public function testComponentNotFoundInPageReturns400(): void {
    $this->responseValidator = $this->createMock(AiResponseValidator::class);
    $this->responseValidator
      ->method('validateComponentExistsInPage')
      ->willThrowException(new \Exception('Component not found'));

    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest($this->validBody('change the heading to Hello')));

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
    $this->assertStringContainsString('not found', $data['message']);
  }

  /**
   * @covers ::edit
   */
  public function testPropValidationFailureReturns400(): void {
    $this->responseValidator = $this->createMock(AiResponseValidator::class);
    // validateComponentExistsInPage is void — no return stub needed.
    $this->responseValidator
      ->method('validateComponentPropUpdate')
      ->willThrowException(new \Exception('Prop schema violation'));

    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest($this->validBody('change the heading to Hello')));

    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
    $this->assertStringContainsString('not valid', $data['message']);
  }

  // ---------------------------------------------------------------------------
  // Layout / tempstore seeding
  // ---------------------------------------------------------------------------

  /**
   * @covers ::edit
   */
  public function testValidLayoutInBodySeedsTheTempstore(): void {
    $componentUuid = self::VALID_UUID;
    $layout = json_encode([
      $componentUuid => ['propValues' => ['heading_text' => 'Old Title']],
    ]);

    $this->canvasAiTempStore = $this->createMock(CanvasAiTempStore::class);
    $this->canvasAiTempStore
      ->expects($this->once())
      ->method('setData')
      ->with(CanvasAiTempStore::COMPONENTS_IN_PAGE_WITH_PROP_VALUES_KEY, $layout);
    $this->canvasAiTempStore
      ->method('getData')
      ->willReturn($layout);

    $controller = $this->createController();
    $body = $this->validBody('change the heading to Hello');
    $body['layout'] = $layout;

    $controller->edit($this->buildRequest($body));
  }

  /**
   * @covers ::edit
   */
  public function testLayoutNotInBodyDoesNotCallSetData(): void {
    $this->canvasAiTempStore = $this->createMock(CanvasAiTempStore::class);
    $this->canvasAiTempStore
      ->expects($this->never())
      ->method('setData');
    $this->canvasAiTempStore
      ->method('getData')
      ->willReturn(NULL);

    $controller = $this->createController();
    $controller->edit($this->buildRequest($this->validBody('change the heading to Hello')));
  }

  /**
   * @covers ::edit
   */
  public function testCurrentPropValuesFromTempstorePassedToMatcher(): void {
    // Seed tempstore with prop values so relative adjustments resolve.
    $componentUuid = self::VALID_UUID;
    $componentData = json_encode([
      $componentUuid => ['propValues' => ['text_size' => 'heading-responsive-5xl']],
    ]);

    $this->canvasAiTempStore = $this->createMock(CanvasAiTempStore::class);
    $this->canvasAiTempStore
      ->method('getData')
      ->willReturn($componentData);

    $controller = $this->createController();
    $body = $this->validBody('bigger');

    $response = $controller->edit($this->buildRequest($body));

    // text_size ordinal is descending (8xl=biggest at index 1, 5xl at index 4).
    // "bigger" steps toward lower index (larger text): 5xl → 6xl.
    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('text_size', $data['matched_prop']);
    $this->assertSame('heading-responsive-6xl', $data['matched_value']);
  }

  // ---------------------------------------------------------------------------
  // Telemetry
  // ---------------------------------------------------------------------------

  /**
   * @covers ::edit
   */
  public function testNoMatchWithTelemetryDisabledLogsOnlyBasicTiming(): void {
    // With telemetry disabled, only the basic timing log is written (not the
    // detailed JSON telemetry log). Expect info() called exactly once.
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->logger
      ->expects($this->once())
      ->method('info')
      ->with($this->stringContains('elapsed'));

    $controller = $this->createController();
    $controller->edit($this->buildRequest($this->validBody('add a new section')));
  }

  /**
   * @covers ::edit
   */
  public function testNoMatchWithTelemetryEnabledLogsBasicTimingAndTelemetryData(): void {
    // With telemetry enabled, two info() calls are expected: the timing log
    // and the detailed JSON telemetry log.
    $this->configFactory = $this->buildTestConfigFactory(telemetryEnabled: TRUE);
    $this->container->set('config.factory', $this->configFactory);

    $this->logger = $this->createMock(LoggerInterface::class);
    $this->logger
      ->expects($this->exactly(2))
      ->method('info');

    $controller = $this->createController();
    $controller->edit($this->buildRequest($this->validBody('add a new section')));
  }

  /**
   * @covers ::edit
   */
  public function testMatchWithTelemetryDisabledLogsOnlyBasicTiming(): void {
    $this->logger = $this->createMock(LoggerInterface::class);
    // One info() for timing, one notice() for the successful edit.
    $this->logger
      ->expects($this->once())
      ->method('info')
      ->with($this->stringContains('elapsed'));
    $this->logger
      ->expects($this->once())
      ->method('notice');

    $controller = $this->createController();
    $controller->edit($this->buildRequest($this->validBody('change the heading to Hello')));
  }

  /**
   * @covers ::edit
   */
  public function testMatchWithTelemetryEnabledLogsTimingTelemetryAndNotice(): void {
    $this->configFactory = $this->buildTestConfigFactory(telemetryEnabled: TRUE);
    $this->container->set('config.factory', $this->configFactory);

    $this->logger = $this->createMock(LoggerInterface::class);
    // Two info() calls (timing + telemetry JSON) and one notice().
    $this->logger
      ->expects($this->exactly(2))
      ->method('info');
    $this->logger
      ->expects($this->once())
      ->method('notice');

    $controller = $this->createController();
    $controller->edit($this->buildRequest($this->validBody('change the heading to Hello')));
  }

  // ---------------------------------------------------------------------------
  // Response structure
  // ---------------------------------------------------------------------------

  /**
   * @covers ::edit
   */
  public function testSuccessResponseIsApplicationJson(): void {
    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest($this->validBody('change the heading to Hello')));

    $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
  }

  /**
   * @covers ::edit
   */
  public function test422ResponseBodyContainsStatusFalseAndReason(): void {
    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest($this->validBody('add a new section')));

    $this->assertSame(422, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('status', $data);
    $this->assertArrayHasKey('reason', $data);
    $this->assertArrayHasKey('message', $data);
    $this->assertFalse($data['status']);
    $this->assertSame('no_match', $data['reason']);
  }

  // ---------------------------------------------------------------------------
  // AI availability: 503 vs 422 on no-match (WP08)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::edit
   */
  public function testNoMatchWithAiUnavailableReturns503(): void {
    $this->availabilityChecker = $this->createMock(AiProviderAvailabilityCheckerInterface::class);
    $this->availabilityChecker->method('isAiAvailable')->willReturn(FALSE);

    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest($this->validBody('add a new section')));

    $this->assertSame(503, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertFalse($data['status']);
    $this->assertSame('ai_unavailable', $data['reason']);
    $this->assertStringContainsString('API key', $data['message']);
  }

  /**
   * @covers ::edit
   */
  public function testNoMatchWithAiAvailableReturns422(): void {
    // Default mock returns TRUE — no-match should still be 422.
    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest($this->validBody('add a new section')));

    $this->assertSame(422, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('no_match', $data['reason']);
  }

  /**
   * @covers ::edit
   */
  public function testDeterministicMatchSucceedsWithAiUnavailable(): void {
    // A successful deterministic match must work regardless of AI availability.
    $this->availabilityChecker = $this->createMock(AiProviderAvailabilityCheckerInterface::class);
    $this->availabilityChecker->method('isAiAvailable')->willReturn(FALSE);

    $controller = $this->createController();

    $response = $controller->edit(
      $this->buildRequest($this->validBody('change the heading to Hello World'))
    );

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertTrue($data['status']);
    $this->assertTrue($data['direct_edit']);
  }

  /**
   * @covers ::edit
   */
  public function test503ResponseBodyStructure(): void {
    $this->availabilityChecker = $this->createMock(AiProviderAvailabilityCheckerInterface::class);
    $this->availabilityChecker->method('isAiAvailable')->willReturn(FALSE);

    $controller = $this->createController();

    $response = $controller->edit($this->buildRequest($this->validBody('add a new section')));

    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('status', $data);
    $this->assertArrayHasKey('reason', $data);
    $this->assertArrayHasKey('message', $data);
    $this->assertFalse($data['status']);
    $this->assertSame('ai_unavailable', $data['reason']);
    $this->assertStringContainsString('AI settings', $data['message']);
  }

}
