<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\Service;

/**
 * Matches user messages against deterministic edit patterns.
 *
 * When a component is selected and the user's message matches a pattern
 * like "change the heading to X" or "set the color to primary", this service
 * extracts the prop name and value without invoking the LLM agent chain.
 *
 * Only matches unambiguous, single-prop edits. Returns NULL for anything
 * that requires LLM reasoning (multi-prop changes, ambiguous references,
 * content generation, add/remove operations).
 */
final class DirectEditMatcher {

  /**
   * Natural language aliases mapped to canonical prop names per component.
   *
   * Format: component_name => [alias => prop_name].
   * Aliases are lowercase. The matcher normalizes user input before matching.
   */
  private const PROP_ALIASES = [
    'sdc.byte_theme.heading' => [
      'heading' => 'heading_text',
      'title' => 'heading_text',
      'text' => 'heading_text',
      'level' => 'level',
      'heading level' => 'level',
      'size' => 'text_size',
      'text size' => 'text_size',
      'font size' => 'text_size',
      'color' => 'text_color',
      'text color' => 'text_color',
      'alignment' => 'align',
      'align' => 'align',
    ],
    'sdc.byte_theme.button' => [
      'label' => 'label',
      'text' => 'label',
      'button text' => 'label',
      'style' => 'variant',
      'variant' => 'variant',
      'size' => 'size',
      'icon' => 'icon',
      'link' => 'href',
      'url' => 'href',
      'href' => 'href',
    ],
    'sdc.byte_theme.card-icon' => [
      'title' => 'text',
      'heading' => 'text',
      'text' => 'text',
      'description' => 'description',
      'icon' => 'icon',
      'background' => 'background_color',
      'background color' => 'background_color',
    ],
    'sdc.byte_theme.badge' => [
      'label' => 'label',
      'text' => 'label',
    ],
    'sdc.byte_theme.icon' => [
      'icon' => 'icon',
      'name' => 'icon',
      'size' => 'size',
      'color' => 'color',
    ],
  ];

  /**
   * Enum values for props that only accept specific values.
   *
   * Format: prop_name => [normalized_alias => canonical_value].
   */
  private const ENUM_VALUES = [
    'text_color' => [
      'default' => 'default',
      'white' => 'inverted',
      'inverted' => 'inverted',
      'light' => 'inverted',
      'primary' => 'primary',
      'blue' => 'primary',
    ],
    'align' => [
      'left' => 'left',
      'center' => 'center',
      'centered' => 'center',
      'middle' => 'center',
      'right' => 'right',
    ],
    'variant' => [
      'primary' => 'primary',
      'secondary' => 'secondary',
      'primary inverted' => 'primary-inverted',
      'secondary inverted' => 'secondary-inverted',
    ],
    'size' => [
      'small' => 'small',
      'medium' => 'medium',
      'large' => 'large',
    ],
  ];

  /**
   * Keywords that indicate the user wants to ADD or CREATE — not a simple edit.
   *
   * Note: "make" is intentionally excluded. It's a common edit verb
   * ("make it blue", "make this bigger") and single-word blocking would
   * reject valid edits. Add-intent with "make" is caught by the phrase
   * patterns in ADD_PHRASES instead.
   */
  private const ADD_KEYWORDS = [
    'add', 'create', 'insert', 'generate', 'build',
    'new', 'another', 'below', 'above', 'after', 'before',
  ];

  /**
   * Phrase patterns that indicate add/create intent even with edit verbs.
   *
   * These catch "make a new...", "make me a...", etc. without blocking
   * "make it blue" or "make the heading bigger".
   */
  private const ADD_PHRASES = [
    '/\bmake\s+(?:a|me|us)\s+(?:new\b|another\b)/i',
    '/\bmake\s+(?:a|an|some)\b/i',
  ];

  /**
   * Attempts to match a user message to a deterministic prop edit.
   *
   * @param string $message
   *   The user's chat message.
   * @param string $componentName
   *   The SDC component name (e.g., 'sdc.byte_theme.heading').
   *
   * @return array{prop: string, value: mixed}|null
   *   The matched prop name and value, or NULL if no deterministic match.
   */
  public function match(string $message, string $componentName): ?array {
    $message = trim($message);
    if ($message === '' || mb_strlen($message) > 500) {
      return NULL;
    }

    // Reject if the message contains add/create keywords or phrases.
    $messageLower = mb_strtolower($message);
    foreach (self::ADD_KEYWORDS as $keyword) {
      // Match as whole word to avoid false positives (e.g., "address" contains "add").
      if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $messageLower)) {
        return NULL;
      }
    }
    foreach (self::ADD_PHRASES as $pattern) {
      if (preg_match($pattern, $messageLower)) {
        return NULL;
      }
    }

    // Try to match "change/set/update X to Y" patterns.
    $patterns = [
      // "change the heading to New Title"
      '/(?:change|set|update|modify|make)\s+(?:the\s+)?(.+?)\s+to\s+["\']?(.+?)["\']?\s*$/i',
      // "heading: New Title"
      '/^(.+?):\s+["\']?(.+?)["\']?\s*$/i',
      // "set X = Y"
      '/(?:set|change)\s+(.+?)\s*=\s*["\']?(.+?)["\']?\s*$/i',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $message, $matches)) {
        $propAlias = trim(mb_strtolower($matches[1]));
        $value = trim($matches[2]);

        $result = $this->resolveEdit($propAlias, $value, $componentName);
        if ($result !== NULL) {
          return $result;
        }
      }
    }

    return NULL;
  }

  /**
   * Resolves a prop alias and value to a canonical prop edit.
   *
   * @param string $propAlias
   *   The normalized prop alias from the user message.
   * @param string $rawValue
   *   The raw value string from the user message.
   * @param string $componentName
   *   The SDC component name.
   *
   * @return array{prop: string, value: mixed}|null
   *   Resolved prop and value, or NULL if unresolvable.
   */
  private function resolveEdit(string $propAlias, string $rawValue, string $componentName): ?array {
    $aliases = self::PROP_ALIASES[$componentName] ?? [];
    if (empty($aliases)) {
      return NULL;
    }

    $propName = $aliases[$propAlias] ?? NULL;
    if ($propName === NULL) {
      return NULL;
    }

    // If the prop has enum constraints, resolve the value.
    if (isset(self::ENUM_VALUES[$propName])) {
      $normalizedValue = mb_strtolower(trim($rawValue));
      $canonicalValue = self::ENUM_VALUES[$propName][$normalizedValue] ?? NULL;
      if ($canonicalValue === NULL) {
        // Value doesn't match any known enum — can't resolve deterministically.
        return NULL;
      }
      return ['prop' => $propName, 'value' => $canonicalValue];
    }

    // For the 'level' prop (heading), accept numeric values 1-6.
    if ($propName === 'level') {
      $numericValue = (int) $rawValue;
      if ($numericValue >= 1 && $numericValue <= 6 && (string) $numericValue === trim($rawValue)) {
        return ['prop' => $propName, 'value' => $numericValue];
      }
      return NULL;
    }

    // For string props (heading_text, label, etc.), accept the raw value.
    return ['prop' => $propName, 'value' => $rawValue];
  }

  /**
   * Returns the list of component names that support deterministic editing.
   *
   * @return string[]
   *   Component SDC names.
   */
  public function getSupportedComponents(): array {
    return array_keys(self::PROP_ALIASES);
  }

}
