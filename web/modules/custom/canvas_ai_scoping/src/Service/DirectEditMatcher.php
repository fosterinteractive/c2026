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
 *
 * Prop aliases and enum value maps are loaded dynamically from the Byte theme
 * component YAML schemas via ComponentSchemaLoader, covering all 23 components.
 */
final class DirectEditMatcher {

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
    'another', 'below', 'above', 'after', 'before',
  ];

  /**
   * Phrase patterns that indicate add/create intent.
   *
   * These catch phrases where context-dependent words ("make", "new")
   * indicate creation rather than editing. Single-word blocking would
   * reject valid edits like "make it blue" or "heading: New Title".
   */
  private const ADD_PHRASES = [
    '/\bmake\s+(?:a|me|us)\s+(?:new\b|another\b)/i',
    '/\bmake\s+(?:a|an|some)\b/i',
    '/\b(?:a|an)\s+new\b/i',
    '/\bnew\s+(?:section|component|card|heading|button|image|row|column|block)\b/i',
  ];

  /**
   * Constructs a DirectEditMatcher.
   *
   * @param \Drupal\canvas_ai_scoping\Service\ComponentSchemaLoaderInterface $schemaLoader
   *   The component schema loader, providing dynamic prop alias and enum maps.
   */
  public function __construct(
    private readonly ComponentSchemaLoaderInterface $schemaLoader,
  ) {}

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
    // Deterministic edit commands are short. Messages beyond 500 chars are
    // almost certainly content generation or multi-paragraph instructions
    // that need LLM reasoning. This limit is intentionally lower than the
    // controller's 2000-char validation to fast-reject verbose messages
    // before running regex patterns.
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
    $aliases = $this->schemaLoader->getPropAliases($componentName);
    if (empty($aliases)) {
      return NULL;
    }

    $propName = $aliases[$propAlias] ?? NULL;
    if ($propName === NULL) {
      return NULL;
    }

    // For the 'level' prop (heading), accept numeric values 1-6.
    // This is not derivable from schema alone since level is a numeric enum.
    if ($propName === 'level') {
      $numericValue = (int) $rawValue;
      if ($numericValue >= 1 && $numericValue <= 6 && (string) $numericValue === trim($rawValue)) {
        return ['prop' => $propName, 'value' => $numericValue];
      }
      return NULL;
    }

    // If the prop has enum constraints, resolve the value.
    $enumValues = $this->schemaLoader->getEnumValues($propName, $componentName);
    if ($enumValues !== NULL) {
      $normalizedValue = mb_strtolower(trim($rawValue));
      $canonicalValue = $enumValues[$normalizedValue] ?? NULL;
      if ($canonicalValue === NULL) {
        // Value doesn't match any known enum — can't resolve deterministically.
        return NULL;
      }
      return ['prop' => $propName, 'value' => $canonicalValue];
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
    return $this->schemaLoader->getSupportedComponents();
  }

}
