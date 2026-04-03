<?php

declare(strict_types=1);

namespace Drupal\ai_google_analytics;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Evaluates Canvas page GA metrics against benchmark thresholds.
 *
 * Performs deterministic comparison of analytics metrics against configured
 * thresholds. Per-page overrides take precedence over global defaults.
 */
class BenchmarkEvaluator {

  /**
   * Constructs a BenchmarkEvaluator.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Evaluates a page's GA metrics against benchmark thresholds.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $page
   *   The Canvas page entity with GA metric fields.
   *
   * @return array{passed: bool, failures: string[]}
   *   An array with 'passed' (bool) and 'failures' (list of human-readable
   *   failure descriptions). Empty failures array when all benchmarks pass.
   */
  public function evaluate(ContentEntityInterface $page): array {
    $config = $this->configFactory->get('ai_google_analytics.settings');
    $failures = [];

    $engaged_sessions = $this->getMetricValue($page, 'engaged_sessions');
    $bounce_rate = $this->getMetricValue($page, 'bounce_rate');
    $key_event_rate = $this->getMetricValue($page, 'key_event_rate');

    // Engaged sessions: actual must be >= threshold.
    $threshold = $this->getThreshold($page, 'benchmark_engaged_sessions_min', 'benchmarks.engaged_sessions_min', $config);
    if ($threshold !== NULL && $engaged_sessions !== NULL && $engaged_sessions < $threshold) {
      $failures[] = sprintf(
        'Engaged sessions (%s) is below minimum threshold (%s)',
        number_format($engaged_sessions, 1),
        number_format($threshold, 1),
      );
    }

    // Bounce rate: actual must be <= threshold.
    $threshold = $this->getThreshold($page, 'benchmark_bounce_rate_max', 'benchmarks.bounce_rate_max', $config);
    if ($threshold !== NULL && $bounce_rate !== NULL && $bounce_rate > $threshold) {
      $failures[] = sprintf(
        'Bounce rate (%.1f%%) exceeds maximum threshold (%.1f%%)',
        $bounce_rate,
        $threshold,
      );
    }

    // Key event rate: actual must be >= threshold.
    $threshold = $this->getThreshold($page, 'benchmark_key_event_rate_min', 'benchmarks.key_event_rate_min', $config);
    if ($threshold !== NULL && $key_event_rate !== NULL && $key_event_rate < $threshold) {
      $failures[] = sprintf(
        'Key event rate (%.1f%%) is below minimum threshold (%.1f%%)',
        $key_event_rate,
        $threshold,
      );
    }

    return [
      'passed' => empty($failures),
      'failures' => $failures,
    ];
  }

  /**
   * Gets a metric value from the page entity, cast to float.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $page
   *   The Canvas page entity.
   * @param string $field_name
   *   The metric field name.
   *
   * @return float|null
   *   The metric value as a float, or NULL if the field is empty.
   */
  protected function getMetricValue(ContentEntityInterface $page, string $field_name): ?float {
    $value = $page->get($field_name)->value;
    if ($value === NULL || $value === '') {
      return NULL;
    }
    return (float) $value;
  }

  /**
   * Gets the effective threshold for a metric (per-page override or global).
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $page
   *   The Canvas page entity.
   * @param string $page_field
   *   The per-page override field name.
   * @param string $config_key
   *   The global config key (e.g., 'benchmarks.bounce_rate_max').
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module config object.
   *
   * @return float|null
   *   The threshold value, or NULL if no threshold is configured.
   */
  protected function getThreshold(ContentEntityInterface $page, string $page_field, string $config_key, $config): ?float {
    $page_value = $page->get($page_field)->value;
    if ($page_value !== NULL) {
      return (float) $page_value;
    }

    $global_value = $config->get($config_key);
    if ($global_value !== NULL) {
      return (float) $global_value;
    }

    return NULL;
  }

}
