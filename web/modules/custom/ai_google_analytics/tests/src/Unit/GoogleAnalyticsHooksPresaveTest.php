<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_google_analytics\Unit;

use Drupal\ai_google_analytics\BenchmarkEvaluator;
use Drupal\ai_google_analytics\Hook\GoogleAnalyticsHooks;
use Drupal\canvas\Entity\Page;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the presave hook logic in GoogleAnalyticsHooks.
 *
 * @coversDefaultClass \Drupal\ai_google_analytics\Hook\GoogleAnalyticsHooks
 * @group ai_google_analytics
 */
class GoogleAnalyticsHooksPresaveTest extends UnitTestCase {

  /**
   * The mock benchmark evaluator.
   */
  protected BenchmarkEvaluator $evaluator;

  /**
   * The mock AI agent manager.
   */
  protected PluginManagerInterface $agentManager;

  /**
   * The mock mail manager.
   */
  protected MailManagerInterface $mailManager;

  /**
   * The mock state service.
   */
  protected StateInterface $state;

  /**
   * The mock logger.
   */
  protected LoggerChannelInterface $logger;

  /**
   * The hooks instance under test.
   */
  protected GoogleAnalyticsHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->evaluator = $this->createMock(BenchmarkEvaluator::class);
    $this->agentManager = $this->createMock(PluginManagerInterface::class);
    $this->mailManager = $this->createMock(MailManagerInterface::class);
    $this->state = $this->createMock(StateInterface::class);

    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')
      ->with('ai_google_analytics')
      ->willReturn($this->logger);

    $siteConfig = $this->createMock(ImmutableConfig::class);
    $siteConfig->method('get')
      ->with('mail')
      ->willReturn('admin@example.com');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('system.site')
      ->willReturn($siteConfig);

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('getPreferredLangcode')
      ->willReturn('en');

    $request = new Request();
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $this->hooks = new GoogleAnalyticsHooks(
      $this->evaluator,
      $this->agentManager,
      $this->mailManager,
      $configFactory,
      $currentUser,
      $loggerFactory,
      $this->state,
      $requestStack,
    );
  }

  /**
   * Creates a mock Canvas page entity with metric field values.
   *
   * @param array $values
   *   Field name => value pairs for current entity.
   * @param array $original_values
   *   Field name => value pairs for original entity.
   * @param string $id
   *   The entity ID.
   * @param string $label
   *   The entity label.
   *
   * @return \Drupal\canvas\Entity\Page
   *   The mocked page entity.
   */
  protected function createPageMock(array $values, array $original_values, string $id = '1', string $label = 'Test Page'): Page {
    $page = $this->createMock(Page::class);
    $page->method('isNew')->willReturn(FALSE);
    $page->method('id')->willReturn($id);
    $page->method('label')->willReturn($label);
    $page->method('get')
      ->willReturnCallback(function (string $field) use ($values) {
        $item = $this->createMock(FieldItemListInterface::class);
        $item->value = $values[$field] ?? NULL;
        return $item;
      });

    $original = $this->createMock(Page::class);
    $original->method('get')
      ->willReturnCallback(function (string $field) use ($original_values) {
        $item = $this->createMock(FieldItemListInterface::class);
        $item->value = $original_values[$field] ?? NULL;
        return $item;
      });

    $page->original = $original;

    return $page;
  }

  /**
   * Tests that unchanged metrics skip evaluation entirely.
   *
   * @covers ::canvasPagePresave
   */
  public function testUnchangedMetricsSkipsEvaluation(): void {
    $values = [
      'engaged_sessions' => '200',
      'bounce_rate' => '50',
      'key_event_rate' => '5',
    ];
    $page = $this->createPageMock($values, $values);

    $this->evaluator->expects($this->never())->method('evaluate');
    $this->state->expects($this->never())->method('set');

    $this->hooks->canvasPagePresave($page);
  }

  /**
   * Tests that passing benchmarks clear stale state.
   *
   * @covers ::canvasPagePresave
   */
  public function testPassingBenchmarksClearStaleState(): void {
    $page = $this->createPageMock(
      ['engaged_sessions' => '200', 'bounce_rate' => '50', 'key_event_rate' => '5'],
      ['engaged_sessions' => '100', 'bounce_rate' => '80', 'key_event_rate' => '1'],
      '42',
    );

    $this->evaluator->method('evaluate')
      ->willReturn(['passed' => TRUE, 'failures' => []]);

    // Page 42 is currently flagged in state.
    $this->state->method('get')
      ->with('ai_google_analytics.context_data', [])
      ->willReturn(['42' => ['summary' => 'Old failure']]);

    // Expect state to be updated with page 42 removed.
    $this->state->expects($this->once())
      ->method('set')
      ->with('ai_google_analytics.context_data', []);

    $this->hooks->canvasPagePresave($page);
  }

  /**
   * Tests that failing benchmarks call agent and update state.
   *
   * @covers ::canvasPagePresave
   */
  public function testFailingBenchmarksCallAgentAndUpdateState(): void {
    $page = $this->createPageMock(
      ['engaged_sessions' => '10', 'bounce_rate' => '85', 'key_event_rate' => '0.5'],
      ['engaged_sessions' => '200', 'bounce_rate' => '50', 'key_event_rate' => '5'],
      '7',
    );

    $this->evaluator->method('evaluate')
      ->willReturn([
        'passed' => FALSE,
        'failures' => ['Bounce rate (85.0%) exceeds maximum threshold (70.0%)'],
      ]);

    // Mock agent returning structured output.
    $agent = $this->createMock(\stdClass::class, ['setChatInput', 'determineSolvability', 'solve']);
    $agent->method('solve')
      ->willReturn('{"summary": "High bounce rate detected", "recommendations": "Improve page load time"}');

    // Use a callback for createInstance to handle method chaining.
    $mockAgent = new class {

      public function setChatInput($input): void {}

      public function determineSolvability(): void {}

      public function solve(): string {
        return '{"summary": "High bounce rate detected", "recommendations": "Improve page load time"}';
      }

    };
    $this->agentManager->method('createInstance')
      ->with('analytics_monitoring_agent')
      ->willReturn($mockAgent);

    $this->state->method('get')
      ->with('ai_google_analytics.context_data', [])
      ->willReturn([]);

    $this->mailManager->method('mail')
      ->willReturn(['result' => TRUE]);

    // Expect state updated with the page flagged.
    $this->state->expects($this->once())
      ->method('set')
      ->with(
        'ai_google_analytics.context_data',
        $this->callback(function ($data) {
          return isset($data['7']['summary'])
            && str_contains($data['7']['summary'], 'High bounce rate detected');
        }),
      );

    $this->hooks->canvasPagePresave($page);
  }

  /**
   * Tests that agent failure falls back to deterministic failure text.
   *
   * @covers ::canvasPagePresave
   */
  public function testAgentFailureFallsBackToFailureText(): void {
    $page = $this->createPageMock(
      ['engaged_sessions' => '10', 'bounce_rate' => '85', 'key_event_rate' => '0.5'],
      ['engaged_sessions' => '200', 'bounce_rate' => '50', 'key_event_rate' => '5'],
      '9',
    );

    $this->evaluator->method('evaluate')
      ->willReturn([
        'passed' => FALSE,
        'failures' => ['Bounce rate (85.0%) exceeds maximum threshold (70.0%)'],
      ]);

    // Agent throws an exception.
    $this->agentManager->method('createInstance')
      ->willThrowException(new \RuntimeException('LLM provider unavailable'));

    $this->state->method('get')
      ->with('ai_google_analytics.context_data', [])
      ->willReturn([]);

    $this->mailManager->method('mail')
      ->willReturn(['result' => TRUE]);

    // State should still be updated with the deterministic failure text.
    $this->state->expects($this->once())
      ->method('set')
      ->with(
        'ai_google_analytics.context_data',
        $this->callback(function ($data) {
          return isset($data['9']['summary'])
            && str_contains($data['9']['summary'], 'Bounce rate (85.0%) exceeds maximum threshold');
        }),
      );

    // Error should be logged.
    $this->logger->expects($this->atLeastOnce())
      ->method('error');

    $this->hooks->canvasPagePresave($page);
  }

  /**
   * Tests that new entities are skipped.
   *
   * @covers ::canvasPagePresave
   */
  public function testNewEntitySkipped(): void {
    $page = $this->createMock(Page::class);
    $page->method('isNew')->willReturn(TRUE);

    $this->evaluator->expects($this->never())->method('evaluate');

    $this->hooks->canvasPagePresave($page);
  }

  /**
   * Tests entity delete clears state.
   *
   * @covers ::entityDelete
   */
  public function testEntityDeleteClearsState(): void {
    $page = $this->createMock(Page::class);
    $page->method('id')->willReturn('5');

    $this->state->method('get')
      ->with('ai_google_analytics.context_data', [])
      ->willReturn(['5' => ['summary' => 'Some failure'], '8' => ['summary' => 'Other']]);

    $this->state->expects($this->once())
      ->method('set')
      ->with('ai_google_analytics.context_data', ['8' => ['summary' => 'Other']]);

    $this->hooks->entityDelete($page);
  }

}
