<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Service;

/**
 * Immutable value object representing the result of a DirectEditMatcher match.
 *
 * Carries confidence scoring and a complexity signal for downstream model
 * routing decisions. Implements \ArrayAccess for backward compatibility with
 * callers that access the legacy raw-array return shape.
 *
 * Legacy array shapes supported via ArrayAccess:
 *   - Single-prop match: ['prop' => string, 'value' => mixed]
 *   - Compound match:    ['changes' => array<int, array{prop: string, value: mixed}>]
 */
final class MatchResult implements \ArrayAccess {

  /**
   * The prop name for single-prop matches. NULL for compound or no-match.
   */
  private readonly ?string $prop;

  /**
   * The prop value for single-prop matches. NULL for compound or no-match.
   */
  private readonly mixed $value;

  /**
   * Constructs a MatchResult.
   *
   * @param bool $matched
   *   Whether a deterministic match was found.
   * @param array|null $changes
   *   The prop changes array for compound matches, or a single-element array
   *   for single-prop matches. NULL when no match.
   * @param float $confidence
   *   Confidence score in the range [0.0, 1.0].
   * @param int|null $nearestTier
   *   The closest matching tier index, or NULL for clean matches.
   * @param string $complexitySignal
   *   Complexity signal: 'trivial', 'simple', or 'complex'.
   * @param string|null $prop
   *   Prop name for single-prop matches.
   * @param mixed $value
   *   Prop value for single-prop matches.
   */
  private function __construct(
    public readonly bool $matched,
    public readonly ?array $changes,
    public readonly float $confidence,
    public readonly ?int $nearestTier,
    public readonly string $complexitySignal,
    ?string $prop = NULL,
    mixed $value = NULL,
  ) {
    $this->prop = $prop;
    $this->value = $value;
  }

  /**
   * Creates a MatchResult for a single-prop match.
   *
   * @param string $prop
   *   The matched prop name.
   * @param mixed $value
   *   The resolved prop value.
   * @param float $confidence
   *   Confidence score in [0.0, 1.0].
   *
   * @return self
   *   A matched result for a single prop change.
   */
  public static function matched(string $prop, mixed $value, float $confidence): self {
    return new self(
      matched: TRUE,
      changes: [['prop' => $prop, 'value' => $value]],
      confidence: $confidence,
      nearestTier: NULL,
      complexitySignal: self::deriveComplexitySignal($confidence),
      prop: $prop,
      value: $value,
    );
  }

  /**
   * Creates a MatchResult for a compound match (multiple prop changes).
   *
   * @param array $changes
   *   Array of prop change arrays, each with 'prop' and 'value' keys.
   * @param float $confidence
   *   Confidence score in [0.0, 1.0].
   *
   * @return self
   *   A matched result for multiple prop changes.
   */
  public static function compound(array $changes, float $confidence): self {
    return new self(
      matched: TRUE,
      changes: $changes,
      confidence: $confidence,
      nearestTier: NULL,
      complexitySignal: self::deriveComplexitySignal($confidence),
    );
  }

  /**
   * Creates a MatchResult representing no deterministic match.
   *
   * @param float $confidence
   *   Confidence score in [0.0, 1.0].
   * @param int|null $nearestTier
   *   The closest tier that was attempted, or NULL.
   *
   * @return self
   *   An unmatched result.
   */
  public static function noMatch(float $confidence, ?int $nearestTier = NULL): self {
    return new self(
      matched: FALSE,
      changes: NULL,
      confidence: $confidence,
      nearestTier: $nearestTier,
      complexitySignal: self::deriveComplexitySignal($confidence),
    );
  }

  /**
   * Derives the complexity signal from a confidence score.
   *
   * @param float $confidence
   *   Confidence score in [0.0, 1.0].
   *
   * @return string
   *   'trivial' (>= 0.8), 'simple' (>= 0.4), or 'complex' (< 0.4).
   */
  private static function deriveComplexitySignal(float $confidence): string {
    if ($confidence >= 0.8) {
      return 'trivial';
    }
    if ($confidence >= 0.4) {
      return 'simple';
    }
    return 'complex';
  }

  /**
   * {@inheritdoc}
   *
   * Supports legacy array key access:
   *   - 'prop'    → prop name (single-prop matches)
   *   - 'value'   → prop value (single-prop matches)
   *   - 'changes' → changes array (compound matches)
   *   - 'matched', 'confidence', 'nearestTier', 'complexitySignal' → DTO props
   */
  public function offsetExists(mixed $offset): bool {
    return match ($offset) {
      'prop' => $this->prop !== NULL,
      'value' => $this->prop !== NULL,
      'changes' => $this->changes !== NULL,
      'matched', 'confidence', 'nearestTier', 'complexitySignal' => TRUE,
      default => FALSE,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function offsetGet(mixed $offset): mixed {
    return match ($offset) {
      'prop' => $this->prop,
      'value' => $this->value,
      'changes' => $this->changes,
      'matched' => $this->matched,
      'confidence' => $this->confidence,
      'nearestTier' => $this->nearestTier,
      'complexitySignal' => $this->complexitySignal,
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   *
   * @throws \BadMethodCallException
   *   Always — MatchResult is immutable.
   */
  public function offsetSet(mixed $offset, mixed $value): void {
    throw new \BadMethodCallException('MatchResult is immutable and cannot be modified via array access.');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \BadMethodCallException
   *   Always — MatchResult is immutable.
   */
  public function offsetUnset(mixed $offset): void {
    throw new \BadMethodCallException('MatchResult is immutable and cannot be modified via array access.');
  }

}
