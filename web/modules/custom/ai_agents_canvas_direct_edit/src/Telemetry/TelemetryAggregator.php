<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Telemetry;

use Drupal\Core\Database\Connection;

/**
 * Reads the telemetry table and computes aggregate statistics.
 *
 * @internal Default implementation of TelemetryAggregatorInterface.
 *
 * All methods accept Unix timestamp boundaries and return structured arrays
 * suitable for JSON serialization. Empty datasets are handled gracefully:
 * rates return 0.0, distributions return [], and percentiles return all zeros.
 */
class TelemetryAggregator implements TelemetryAggregatorInterface {

  /**
   * Name of the telemetry database table.
   */
  private const TABLE = 'canvas_direct_edit_telemetry';

  /**
   * Constructs a TelemetryAggregator.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    protected readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getHitRate(int $since, int $until): float {
    $result = $this->database->select(self::TABLE, 't')
      ->condition('t.timestamp', $since, '>=')
      ->condition('t.timestamp', $until, '<=')
      ->addExpression('COUNT(*)', 'total')
      ->execute()
      ->fetchField();

    $total = (int) $result;

    if ($total === 0) {
      return 0.0;
    }

    $matched = (int) $this->database->select(self::TABLE, 't')
      ->condition('t.timestamp', $since, '>=')
      ->condition('t.timestamp', $until, '<=')
      ->condition('t.matched', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    return $matched / $total;
  }

  /**
   * {@inheritdoc}
   */
  public function getTierDistribution(int $since, int $until): array {
    $query = $this->database->select(self::TABLE, 't');
    $query->addField('t', 'tier');
    $query->addExpression('COUNT(*)', 'cnt');
    $query->condition('t.timestamp', $since, '>=');
    $query->condition('t.timestamp', $until, '<=');
    $query->groupBy('t.tier');
    $rows = $query->execute()->fetchAllAssoc('tier');

    if (empty($rows)) {
      return [];
    }

    $total = array_sum(array_map(fn($row) => (int) $row->cnt, $rows));

    if ($total === 0) {
      return [];
    }

    $distribution = [];
    foreach ($rows as $tier => $row) {
      $distribution[(string) $tier] = round(((int) $row->cnt / $total) * 100, 2);
    }

    return $distribution;
  }

  /**
   * {@inheritdoc}
   */
  public function getLatencyPercentiles(int $since, int $until): array {
    $total = (int) $this->database->select(self::TABLE, 't')
      ->condition('t.timestamp', $since, '>=')
      ->condition('t.timestamp', $until, '<=')
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($total === 0) {
      return ['p50' => 0, 'p95' => 0, 'p99' => 0];
    }

    $percentiles = [
      'p50' => (int) floor($total * 0.50),
      'p95' => (int) floor($total * 0.95),
      'p99' => (int) floor($total * 0.99),
    ];

    $result = ['p50' => 0, 'p95' => 0, 'p99' => 0];

    foreach ($percentiles as $label => $offset) {
      // Clamp offset so it never exceeds the last valid row index.
      $safeOffset = max(0, min($offset, $total - 1));

      $value = $this->database->select(self::TABLE, 't')
        ->fields('t', ['latency_us'])
        ->condition('t.timestamp', $since, '>=')
        ->condition('t.timestamp', $until, '<=')
        ->orderBy('t.latency_us', 'ASC')
        ->range($safeOffset, 1)
        ->execute()
        ->fetchField();

      $result[$label] = $value !== FALSE ? (int) $value : 0;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelBreakdown(int $since, int $until): array {
    $query = $this->database->select(self::TABLE, 't');
    $query->addField('t', 'model_used');
    $query->addExpression('COUNT(*)', 'cnt');
    $query->condition('t.timestamp', $since, '>=');
    $query->condition('t.timestamp', $until, '<=');
    $query->groupBy('t.model_used');
    $rows = $query->execute()->fetchAllAssoc('model_used');

    if (empty($rows)) {
      return [];
    }

    $breakdown = [];
    foreach ($rows as $model => $row) {
      $key = ($model === '' || $model === NULL) ? 'none' : (string) $model;
      $breakdown[$key] = (int) $row->cnt;
    }

    return $breakdown;
  }

  /**
   * {@inheritdoc}
   */
  public function getAiFallbackRate(int $since, int $until): float {
    $total = (int) $this->database->select(self::TABLE, 't')
      ->condition('t.timestamp', $since, '>=')
      ->condition('t.timestamp', $until, '<=')
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($total === 0) {
      return 0.0;
    }

    $fallbacks = (int) $this->database->select(self::TABLE, 't')
      ->condition('t.timestamp', $since, '>=')
      ->condition('t.timestamp', $until, '<=')
      ->condition('t.ai_fallback', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    return $fallbacks / $total;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(int $since, int $until): array {
    return [
      'hit_rate' => $this->getHitRate($since, $until),
      'tier_distribution' => $this->getTierDistribution($since, $until),
      'latency_percentiles' => $this->getLatencyPercentiles($since, $until),
      'model_breakdown' => $this->getModelBreakdown($since, $until),
      'ai_fallback_rate' => $this->getAiFallbackRate($since, $until),
    ];
  }

}
