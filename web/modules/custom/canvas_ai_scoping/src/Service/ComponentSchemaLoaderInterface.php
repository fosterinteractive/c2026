<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\Service;

/**
 * Interface for the component schema loader service.
 *
 * Provides prop alias and enum value maps derived from Byte theme component
 * YAML schemas, consumed by DirectEditMatcher for deterministic edit routing.
 */
interface ComponentSchemaLoaderInterface {

  /**
   * Returns the prop alias map for a component.
   *
   * @param string $componentName
   *   The SDC component name (e.g., 'sdc.byte_theme.heading').
   *
   * @return array<string, string>
   *   Map of alias => prop_name. Empty array if component is not found.
   */
  public function getPropAliases(string $componentName): array;

  /**
   * Returns the enum value map for a prop on a specific component.
   *
   * @param string $propName
   *   The canonical prop name (e.g., 'text_color').
   * @param string $componentName
   *   The SDC component name (e.g., 'sdc.byte_theme.heading').
   *
   * @return array<string, string>|null
   *   Map of alias => canonical_value, or NULL if the prop has no enum.
   */
  public function getEnumValues(string $propName, string $componentName): ?array;

  /**
   * Returns all component SDC names that have prop aliases defined.
   *
   * @return string[]
   *   List of SDC component names.
   */
  public function getSupportedComponents(): array;

}
