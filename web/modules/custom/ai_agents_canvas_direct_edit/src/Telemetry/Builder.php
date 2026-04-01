<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Telemetry;

/**
 * Fluent builder for TelemetryEvent.
 *
 * Obtained via TelemetryEvent::create(). Call withXxx() setters in any order,
 * then call build() to produce an immutable TelemetryEvent.
 *
 * The message hash is computed automatically from the raw message supplied to
 * withMessage(). Call withRedactedMessage() separately only when the site
 * is configured to persist message text.
 */
final class Builder {

  /**
   * Unix timestamp of the edit attempt.
   */
  private int $timestamp;

  /**
   * SDC component name.
   */
  private string $componentName = '';

  /**
   * Match tier (one of the TelemetryEvent::TIER_* constants).
   */
  private string $tier = TelemetryEvent::TIER_REJECT;

  /**
   * Whether the attempt produced a deterministic match.
   */
  private bool $matched = FALSE;

  /**
   * The matched prop name, or NULL when rejected.
   */
  private ?string $propName = NULL;

  /**
   * Confidence score (0.0–1.0).
   */
  private ?float $confidence = NULL;

  /**
   * Complexity signal label.
   */
  private ?string $complexitySignal = NULL;

  /**
   * AI model identifier used for fallback.
   */
  private ?string $modelUsed = NULL;

  /**
   * Deterministic-path latency in microseconds.
   */
  private int $latencyUs = 0;

  /**
   * Character length of the original user message.
   */
  private int $messageLength = 0;

  /**
   * SHA-256 hash of the raw user message.
   */
  private string $messageHash = '';

  /**
   * Redacted or raw message text.
   */
  private ?string $redactedMessage = NULL;

  /**
   * Whether the attempt was escalated to an AI fallback.
   */
  private bool $aiFallback = FALSE;

  /**
   * AI fallback round-trip latency in milliseconds.
   */
  private ?int $aiLatencyMs = NULL;

  /**
   * Constructs a Builder with the current timestamp pre-set.
   */
  public function __construct() {
    $this->timestamp = time();
  }

  /**
   * Sets the Unix timestamp.
   *
   * @param int $timestamp
   *   Unix timestamp. Defaults to the time the builder was constructed.
   *
   * @return static
   */
  public function withTimestamp(int $timestamp): static {
    $this->timestamp = $timestamp;
    return $this;
  }

  /**
   * Sets the SDC component name.
   *
   * @param string $componentName
   *   SDC component name (e.g. sdc.mytheme.heading).
   *
   * @return static
   */
  public function withComponentName(string $componentName): static {
    $this->componentName = $componentName;
    return $this;
  }

  /**
   * Sets the match tier.
   *
   * @param string $tier
   *   One of the TelemetryEvent::TIER_* constants.
   *
   * @return static
   */
  public function withTier(string $tier): static {
    $this->tier = $tier;
    return $this;
  }

  /**
   * Sets whether the attempt produced a deterministic match.
   *
   * @param bool $matched
   *   TRUE if a match was found, FALSE otherwise.
   *
   * @return static
   */
  public function withMatched(bool $matched): static {
    $this->matched = $matched;
    return $this;
  }

  /**
   * Sets the matched prop name.
   *
   * @param string|null $propName
   *   The prop name, or NULL when the attempt was rejected.
   *
   * @return static
   */
  public function withPropName(?string $propName): static {
    $this->propName = $propName;
    return $this;
  }

  /**
   * Sets the confidence score.
   *
   * @param float|null $confidence
   *   Score between 0.0 and 1.0, or NULL (populated by later initiatives).
   *
   * @return static
   */
  public function withConfidence(?float $confidence): static {
    $this->confidence = $confidence;
    return $this;
  }

  /**
   * Sets the complexity signal label.
   *
   * @param string|null $complexitySignal
   *   E.g. 'low', 'medium', 'high', or NULL (populated by later initiatives).
   *
   * @return static
   */
  public function withComplexitySignal(?string $complexitySignal): static {
    $this->complexitySignal = $complexitySignal;
    return $this;
  }

  /**
   * Sets the AI model identifier used for fallback.
   *
   * @param string|null $modelUsed
   *   Model name/ID, or NULL (populated by later initiatives).
   *
   * @return static
   */
  public function withModelUsed(?string $modelUsed): static {
    $this->modelUsed = $modelUsed;
    return $this;
  }

  /**
   * Sets the deterministic-path latency.
   *
   * @param int $latencyUs
   *   Latency in microseconds.
   *
   * @return static
   */
  public function withLatencyUs(int $latencyUs): static {
    $this->latencyUs = $latencyUs;
    return $this;
  }

  /**
   * Sets the raw user message, computing its hash and length automatically.
   *
   * This is the primary way to supply message data. The SHA-256 hash is
   * computed here so callers never need to hash manually.
   *
   * @param string $message
   *   The raw user message.
   *
   * @return static
   */
  public function withMessage(string $message): static {
    $this->messageLength = mb_strlen($message);
    $this->messageHash = hash('sha256', $message);
    return $this;
  }

  /**
   * Sets the redacted (or raw) message text for persistence.
   *
   * Only call this when the site is configured with store_messages: true.
   * The message hash and length should still be set via withMessage().
   *
   * @param string|null $redactedMessage
   *   The message text to persist, or NULL to omit.
   *
   * @return static
   */
  public function withRedactedMessage(?string $redactedMessage): static {
    $this->redactedMessage = $redactedMessage;
    return $this;
  }

  /**
   * Sets whether the attempt was escalated to an AI fallback.
   *
   * @param bool $aiFallback
   *   TRUE if an AI fallback was invoked, FALSE otherwise.
   *
   * @return static
   */
  public function withAiFallback(bool $aiFallback): static {
    $this->aiFallback = $aiFallback;
    return $this;
  }

  /**
   * Sets the AI fallback round-trip latency.
   *
   * @param int|null $aiLatencyMs
   *   Latency in milliseconds, or NULL (populated by later initiatives).
   *
   * @return static
   */
  public function withAiLatencyMs(?int $aiLatencyMs): static {
    $this->aiLatencyMs = $aiLatencyMs;
    return $this;
  }

  /**
   * Builds and returns an immutable TelemetryEvent.
   *
   * @return \Drupal\ai_agents_canvas_direct_edit\Telemetry\TelemetryEvent
   *   The constructed event.
   */
  public function build(): TelemetryEvent {
    return new TelemetryEvent(
      timestamp: $this->timestamp,
      componentName: $this->componentName,
      tier: $this->tier,
      matched: $this->matched,
      propName: $this->propName,
      confidence: $this->confidence,
      complexitySignal: $this->complexitySignal,
      modelUsed: $this->modelUsed,
      latencyUs: $this->latencyUs,
      messageLength: $this->messageLength,
      messageHash: $this->messageHash,
      redactedMessage: $this->redactedMessage,
      aiFallback: $this->aiFallback,
      aiLatencyMs: $this->aiLatencyMs,
    );
  }

}
