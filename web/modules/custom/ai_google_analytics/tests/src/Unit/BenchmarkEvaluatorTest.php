<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_google_analytics\Unit;

use Drupal\ai_google_analytics\BenchmarkEvaluator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the BenchmarkEvaluator service.
 *
 * @coversDefaultClass \Drupal\ai_google_analytics\BenchmarkEvaluator
 * @group ai_google_analytics
 */
class BenchmarkEvaluatorTest extends UnitTestCase {

  /**
   * The config factory mock.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The evaluator under test.
   */
  protected BenchmarkEvaluator $evaluator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnMap([
        ['benchmarks.engaged_sessions_min', 100.0],
        ['benchmarks.bounce_rate_max', 70.0],
        ['benchmarks.key_event_rate_min', 2.0],
      ]);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('ai_google_analytics.settings')
      ->willReturn($config);

    $this->evaluator = new BenchmarkEvaluator($this->configFactory);
  }

  /**
   * Creates a mock Canvas page entity with the given field values.
   *
   * @param array $values
   *   Field name => value pairs.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The mocked entity.
   */
  protected function createPageMock(array $values): ContentEntityInterface {
    $page = $this->createMock(ContentEntityInterface::class);
    $page->method('get')
      ->willReturnCallback(function (string $field_name) use ($values) {
        $item_list = $this->createMock(FieldItemListInterface::class);
        $item_list->value = $values[$field_name] ?? NULL;
        return $item_list;
      });
    return $page;
  }

  /**
   * Tests that a page passing all benchmarks returns passed = true.
   *
   * @covers ::evaluate
   */
  public function testAllBenchmarksPass(): void {
    $page = $this->createPageMock([
      'engaged_sessions' => '200',
      'bounce_rate' => '50',
      'key_event_rate' => '5',
      'benchmark_engaged_sessions_min' => NULL,
      'benchmark_bounce_rate_max' => NULL,
      'benchmark_key_event_rate_min' => NULL,
    ]);

    $result = $this->evaluator->evaluate($page);

    $this->assertTrue($result['passed']);
    $this->assertEmpty($result['failures']);
  }

  /**
   * Tests that a page failing all benchmarks returns passed = false.
   *
   * @covers ::evaluate
   */
  public function testAllBenchmarksFail(): void {
    $page = $this->createPageMock([
      'engaged_sessions' => '10',
      'bounce_rate' => '85',
      'key_event_rate' => '0.5',
      'benchmark_engaged_sessions_min' => NULL,
      'benchmark_bounce_rate_max' => NULL,
      'benchmark_key_event_rate_min' => NULL,
    ]);

    $result = $this->evaluator->evaluate($page);

    $this->assertFalse($result['passed']);
    $this->assertCount(3, $result['failures']);
    $this->assertStringContainsString('Engaged sessions', $result['failures'][0]);
    $this->assertStringContainsString('Bounce rate', $result['failures'][1]);
    $this->assertStringContainsString('Key event rate', $result['failures'][2]);
  }

  /**
   * Tests that only the failing benchmark is reported.
   *
   * @covers ::evaluate
   */
  public function testSingleBenchmarkFails(): void {
    $page = $this->createPageMock([
      'engaged_sessions' => '200',
      'bounce_rate' => '85',
      'key_event_rate' => '5',
      'benchmark_engaged_sessions_min' => NULL,
      'benchmark_bounce_rate_max' => NULL,
      'benchmark_key_event_rate_min' => NULL,
    ]);

    $result = $this->evaluator->evaluate($page);

    $this->assertFalse($result['passed']);
    $this->assertCount(1, $result['failures']);
    $this->assertStringContainsString('Bounce rate', $result['failures'][0]);
    $this->assertStringContainsString('85.0%', $result['failures'][0]);
    $this->assertStringContainsString('70.0%', $result['failures'][0]);
  }

  /**
   * Tests that per-page overrides take precedence over global defaults.
   *
   * @covers ::evaluate
   */
  public function testPerPageOverrideTakesPrecedence(): void {
    // Global bounce_rate_max is 70, but this page overrides to 90.
    $page = $this->createPageMock([
      'engaged_sessions' => '200',
      'bounce_rate' => '85',
      'key_event_rate' => '5',
      'benchmark_engaged_sessions_min' => NULL,
      'benchmark_bounce_rate_max' => 90.0,
      'benchmark_key_event_rate_min' => NULL,
    ]);

    $result = $this->evaluator->evaluate($page);

    // 85 <= 90, so this should pass with the per-page override.
    $this->assertTrue($result['passed']);
    $this->assertEmpty($result['failures']);
  }

  /**
   * Tests that a stricter per-page override causes a failure.
   *
   * @covers ::evaluate
   */
  public function testStricterPerPageOverrideCausesFailure(): void {
    // Global engaged_sessions_min is 100, page overrides to 300.
    $page = $this->createPageMock([
      'engaged_sessions' => '200',
      'bounce_rate' => '50',
      'key_event_rate' => '5',
      'benchmark_engaged_sessions_min' => 300.0,
      'benchmark_bounce_rate_max' => NULL,
      'benchmark_key_event_rate_min' => NULL,
    ]);

    $result = $this->evaluator->evaluate($page);

    $this->assertFalse($result['passed']);
    $this->assertCount(1, $result['failures']);
    $this->assertStringContainsString('Engaged sessions', $result['failures'][0]);
  }

  /**
   * Tests that pages with no GA data (empty metrics) pass without error.
   *
   * @covers ::evaluate
   */
  public function testEmptyMetricsPass(): void {
    $page = $this->createPageMock([
      'engaged_sessions' => NULL,
      'bounce_rate' => NULL,
      'key_event_rate' => NULL,
      'benchmark_engaged_sessions_min' => NULL,
      'benchmark_bounce_rate_max' => NULL,
      'benchmark_key_event_rate_min' => NULL,
    ]);

    $result = $this->evaluator->evaluate($page);

    $this->assertTrue($result['passed']);
    $this->assertEmpty($result['failures']);
  }

  /**
   * Tests that empty string metrics (from GA) are treated as no data.
   *
   * @covers ::evaluate
   */
  public function testEmptyStringMetricsTreatedAsNull(): void {
    $page = $this->createPageMock([
      'engaged_sessions' => '',
      'bounce_rate' => '',
      'key_event_rate' => '',
      'benchmark_engaged_sessions_min' => NULL,
      'benchmark_bounce_rate_max' => NULL,
      'benchmark_key_event_rate_min' => NULL,
    ]);

    $result = $this->evaluator->evaluate($page);

    $this->assertTrue($result['passed']);
    $this->assertEmpty($result['failures']);
  }

  /**
   * Tests boundary values — metrics exactly at thresholds pass.
   *
   * @covers ::evaluate
   */
  public function testBoundaryValuesPass(): void {
    $page = $this->createPageMock([
      'engaged_sessions' => '100',
      'bounce_rate' => '70',
      'key_event_rate' => '2',
      'benchmark_engaged_sessions_min' => NULL,
      'benchmark_bounce_rate_max' => NULL,
      'benchmark_key_event_rate_min' => NULL,
    ]);

    $result = $this->evaluator->evaluate($page);

    $this->assertTrue($result['passed']);
    $this->assertEmpty($result['failures']);
  }

  /**
   * Tests values just past thresholds fail.
   *
   * @covers ::evaluate
   */
  public function testJustPastBoundaryFails(): void {
    $page = $this->createPageMock([
      'engaged_sessions' => '99.9',
      'bounce_rate' => '70.1',
      'key_event_rate' => '1.9',
      'benchmark_engaged_sessions_min' => NULL,
      'benchmark_bounce_rate_max' => NULL,
      'benchmark_key_event_rate_min' => NULL,
    ]);

    $result = $this->evaluator->evaluate($page);

    $this->assertFalse($result['passed']);
    $this->assertCount(3, $result['failures']);
  }

}
