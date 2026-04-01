<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Telemetry;

/**
 * Computes aggregate statistics from the canvas_direct_edit_telemetry table.
 */
interface TelemetryAggregatorInterface {

  /**
   * Returns the ratio of matched records to total records in the date range.
   *
   * @param int $since
   *   Start of range as a Unix timestamp (inclusive).
   * @param int $until
   *   End of range as a Unix timestamp (inclusive).
   *
   * @return float
   *   Hit rate between 0.0 and 1.0, or 0.0 when the dataset is empty.
   */
  public function getHitRate(int $since, int $until): float;

  /**
   * Returns tier distribution as a percentage map.
   *
   * @param int $since
   *   Start of range as a Unix timestamp (inclusive).
   * @param int $until
   *   End of range as a Unix timestamp (inclusive).
   *
   * @return array<string, float>
   *   Tier name => percentage (0–100), or an empty array when no data exists.
   */
  public function getTierDistribution(int $since, int $until): array;

  /**
   * Returns approximate latency percentiles in microseconds.
   *
   * @param int $since
   *   Start of range as a Unix timestamp (inclusive).
   * @param int $until
   *   End of range as a Unix timestamp (inclusive).
   *
   * @return array{p50: int, p95: int, p99: int}
   *   Percentile values in microseconds, or all zeros when no data exists.
   */
  public function getLatencyPercentiles(int $since, int $until): array;

  /**
   * Returns a count of records broken down by model_used.
   *
   * @param int $since
   *   Start of range as a Unix timestamp (inclusive).
   * @param int $until
   *   End of range as a Unix timestamp (inclusive).
   *
   * @return array<string, int>
   *   Model identifier => record count, or an empty array when no data exists.
   *   NULL model_used values are grouped under the key 'none'.
   */
  public function getModelBreakdown(int $since, int $until): array;

  /**
   * Returns the ratio of ai_fallback records to total records in the date range.
   *
   * @param int $since
   *   Start of range as a Unix timestamp (inclusive).
   * @param int $until
   *   End of range as a Unix timestamp (inclusive).
   *
   * @return float
   *   AI fallback rate between 0.0 and 1.0, or 0.0 when the dataset is empty.
   */
  public function getAiFallbackRate(int $since, int $until): float;

  /**
   * Returns all aggregated statistics for the date range in a single call.
   *
   * @param int $since
   *   Start of range as a Unix timestamp (inclusive).
   * @param int $until
   *   End of range as a Unix timestamp (inclusive).
   *
   * @return array{
   *   hit_rate: float,
   *   tier_distribution: array<string, float>,
   *   latency_percentiles: array{p50: int, p95: int, p99: int},
   *   model_breakdown: array<string, int>,
   *   ai_fallback_rate: float
   *   }
   *   Combined statistics suitable for JSON serialization.
   */
  public function getSummary(int $since, int $until): array;

}
