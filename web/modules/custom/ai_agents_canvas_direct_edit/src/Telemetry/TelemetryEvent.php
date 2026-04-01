<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Telemetry;

/**
 * Immutable value object carrying all fields for a single telemetry record.
 *
 * Construct via the fluent builder:
 * @code
 * $event = TelemetryEvent::create()
 *   ->withComponentName('sdc.mytheme.heading')
 *   ->withTier('exact')
 *   ->withMatched(TRUE)
 *   ->withPropName('heading_text')
 *   ->withLatencyUs(4200)
 *   ->withMessage('change the heading to Hello')
 *   ->withAiFallback(FALSE)
 *   ->build();
 * @endcode
 */
final class TelemetryEvent {

  /**
   * Tier constants for the match-tier column.
   */
  public const TIER_EXACT = 'exact';
  public const TIER_ALIAS = 'alias';
  public const TIER_ENUM = 'enum';
  public const TIER_RELATIVE = 'relative';
  public const TIER_BOOLEAN = 'boolean';
  public const TIER_RESET = 'reset';
  public const TIER_COMPOUND = 'compound';
  public const TIER_REJECT = 'reject';

  /**
   * Constructs a TelemetryEvent.
   *
   * Use TelemetryEvent::create() to obtain a builder instead of calling
   * this constructor directly.
   *
   * @param int $timestamp
   *   Unix timestamp of the edit attempt.
   * @param string $componentName
   *   SDC component name (e.g. sdc.mytheme.heading).
   * @param string $tier
   *   Match tier (one of the TIER_* constants).
   * @param bool $matched
   *   Whether the attempt produced a deterministic match.
   * @param string|null $propName
   *   The matched prop name, or NULL when the attempt was rejected.
   * @param float|null $confidence
   *   Confidence score (0.0–1.0), populated by later initiatives.
   * @param string|null $complexitySignal
   *   Complexity signal label, populated by later initiatives.
   * @param string|null $modelUsed
   *   AI model identifier used for fallback, populated by later initiatives.
   * @param int $latencyUs
   *   Deterministic-path latency in microseconds.
   * @param int $messageLength
   *   Character length of the original user message.
   * @param string $messageHash
   *   SHA-256 hash of the raw user message.
   * @param string|null $redactedMessage
   *   Redacted or raw message; only set when store_messages is enabled.
   * @param bool $aiFallback
   *   Whether the attempt was escalated to an AI fallback.
   * @param int|null $aiLatencyMs
   *   AI fallback round-trip latency in milliseconds, populated by later initiatives.
   */
  public function __construct(
    public readonly int $timestamp,
    public readonly string $componentName,
    public readonly string $tier,
    public readonly bool $matched,
    public readonly ?string $propName,
    public readonly ?float $confidence,
    public readonly ?string $complexitySignal,
    public readonly ?string $modelUsed,
    public readonly int $latencyUs,
    public readonly int $messageLength,
    public readonly string $messageHash,
    public readonly ?string $redactedMessage,
    public readonly bool $aiFallback,
    public readonly ?int $aiLatencyMs,
  ) {}

  /**
   * Returns a new builder instance.
   *
   * @return \Drupal\ai_agents_canvas_direct_edit\Telemetry\Builder
   *   A fresh builder with all fields at their defaults.
   */
  public static function create(): Builder {
    return new Builder();
  }

}
