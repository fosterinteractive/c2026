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
   * Markers used to conservatively split compound deterministic edits.
   */
  private const COMPOUND_DELIMITER = "\n__DIRECT_EDIT_SPLIT__\n";

  /**
   * Compound split patterns.
   *
   * Only split when the next fragment begins with an edit verb to avoid
   * splitting ordinary text values like "apples and oranges".
   */
  private const COMPOUND_SPLIT_PATTERNS = [
    '/,\s*(?:and\s+)?(?=(?:change|set|update|modify|make)\b)/i',
    '/;\s*(?=(?:change|set|update|modify|make)\b)/i',
    '/\s+(?:and|also|plus|then)\s+(?=(?:change|set|update|modify|make)\b)/i',
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
   * @return array{prop: string, value: mixed}|array{changes: array<int, array{prop: string, value: mixed}>}|null
   *   A single matched prop change, a list of matched changes for a compound
   *   deterministic edit, or NULL if no deterministic match.
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

    $fragments = $this->splitCompoundMessage($message);
    if (count($fragments) > 1) {
      $changes = [];
      foreach ($fragments as $fragment) {
        $result = $this->matchSingle($fragment, $componentName);
        if ($result === NULL) {
          return NULL;
        }
        $changes[] = $result;
      }

      $props = array_column($changes, 'prop');
      if (count($props) !== count(array_unique($props))) {
        return NULL;
      }

      return ['changes' => $changes];
    }

    return $this->matchSingle($message, $componentName);
  }

  /**
   * Attempts to match a single deterministic prop edit.
   */
  private function matchSingle(string $message, string $componentName): ?array {
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

    // Try to match "change/set/update X to Y" patterns (Tier 1).
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

    // Phase 1: Bare value type inference.
    // If the message is a bare value or "make it/this {value}", attempt to
    // resolve by scanning all enum props on the component. Only resolves
    // when exactly one prop accepts the value (unambiguous).
    $result = $this->matchBareValue($messageLower, $componentName);
    if ($result !== NULL) {
      return $result;
    }

    // Phase 2: Boolean toggle patterns.
    // "show the header", "hide the footer", "enable overlap", "disable it"
    $result = $this->matchBooleanToggle($messageLower, $componentName);
    if ($result !== NULL) {
      return $result;
    }

    return NULL;
  }

  /**
   * Matches boolean toggle patterns (show/hide/enable/disable).
   *
   * @param string $messageLower
   *   Lowercased, trimmed user message.
   * @param string $componentName
   *   The SDC component name.
   *
   * @return array{prop: string, value: bool}|null
   *   Resolved prop and boolean value, or NULL if no match.
   */
  private function matchBooleanToggle(string $messageLower, string $componentName): ?array {
    $booleanProps = $this->schemaLoader->getBooleanProps($componentName);
    if (empty($booleanProps)) {
      return NULL;
    }

    // Match toggle verb patterns.
    // Group 1: verb (determines true/false)
    // Group 2: optional "the" article
    // Group 3: the prop reference
    $pattern = '/^(show|hide|enable|disable|turn\s+on|turn\s+off|activate|deactivate)\s+(?:the\s+)?(.+?)\s*$/i';
    if (!preg_match($pattern, $messageLower, $matches)) {
      return NULL;
    }

    $verb = mb_strtolower(trim($matches[1]));
    $propRef = mb_strtolower(trim($matches[2]));

    // Determine intent from verb.
    $enableVerbs = ['show', 'enable', 'turn on', 'activate'];
    $wantsEnabled = in_array($verb, $enableVerbs, TRUE);

    // Find which boolean prop matches the reference.
    foreach ($booleanProps as $propName => $meta) {
      $aliases = $meta['aliases'] ?? [];
      if (in_array($propRef, $aliases, TRUE) || $propRef === $propName) {
        // Apply polarity inversion (e.g., "enable" on "disabled" = false).
        $inverted = $meta['inverted'] ?? FALSE;
        $value = $inverted ? !$wantsEnabled : $wantsEnabled;
        return ['prop' => $propName, 'value' => $value];
      }
    }

    return NULL;
  }

  /**
   * Attempts to resolve a bare value or "make it/this {value}" pattern.
   *
   * Strips implicit prefixes ("make it", "make this", "make the"),
   * then checks the component's reverse enum index for unambiguous matches.
   *
   * @param string $messageLower
   *   Lowercased, trimmed user message.
   * @param string $componentName
   *   The SDC component name.
   *
   * @return array{prop: string, value: mixed}|null
   *   Resolved prop and value, or NULL if ambiguous or no match.
   */
  private function matchBareValue(string $messageLower, string $componentName): ?array {
    // Strip "make it/this/the" prefix to extract the bare value.
    // "make it blue" → "blue", "make this centered" → "centered"
    // Must not match "make a"/"make me" (those are ADD_PHRASES, already rejected).
    $bareValue = preg_replace(
      '/^(?:make\s+(?:it|this|the)\s+)/i',
      '',
      $messageLower
    );
    $bareValue = trim($bareValue);

    if ($bareValue === '' || $bareValue === $messageLower) {
      // If nothing was stripped and the message has multiple words with spaces,
      // it's likely a sentence — don't treat it as a bare value.
      // Single words or hyphenated values (like "extra-large") are fine.
      if (str_contains($messageLower, ' ')) {
        return NULL;
      }
      $bareValue = $messageLower;
    }

    return $this->resolveByTypeInference($bareValue, $componentName);
  }

  /**
   * Resolves a value by scanning the component's reverse enum index.
   *
   * If the value maps to exactly one prop, it's unambiguous — resolve.
   * If it maps to zero or multiple props, reject.
   *
   * @param string $value
   *   Normalized (lowercase, trimmed) value string.
   * @param string $componentName
   *   The SDC component name.
   *
   * @return array{prop: string, value: mixed}|null
   *   Resolved prop and value, or NULL if ambiguous or no match.
   */
  private function resolveByTypeInference(string $value, string $componentName): ?array {
    $reverseIndex = $this->schemaLoader->getReverseEnumIndex($componentName);
    if (empty($reverseIndex)) {
      return NULL;
    }

    $matchingProps = $reverseIndex[$value] ?? [];

    if (count($matchingProps) !== 1) {
      // Zero matches (unknown value) or multiple matches (ambiguous) — reject.
      return NULL;
    }

    $propName = $matchingProps[0];

    // Resolve to the canonical enum value via the existing enum map.
    $enumValues = $this->schemaLoader->getEnumValues($propName, $componentName);
    if ($enumValues === NULL) {
      return NULL;
    }

    $canonicalValue = $enumValues[$value] ?? NULL;
    if ($canonicalValue === NULL) {
      return NULL;
    }

    return ['prop' => $propName, 'value' => $canonicalValue];
  }

  /**
   * Splits a compound deterministic edit into fragments.
   *
   * @return string[]
   *   One or more trimmed fragments. A single-fragment result means "do not
   *   treat this as a compound edit".
   */
  private function splitCompoundMessage(string $message): array {
    $normalized = preg_replace(
      self::COMPOUND_SPLIT_PATTERNS,
      self::COMPOUND_DELIMITER,
      $message
    );

    if (!is_string($normalized) || $normalized === $message) {
      return [$message];
    }

    $fragments = array_values(
      array_filter(
        array_map('trim', explode(self::COMPOUND_DELIMITER, $normalized)),
        static fn(string $fragment): bool => $fragment !== ''
      )
    );

    return count($fragments) > 1 ? $fragments : [$message];
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
