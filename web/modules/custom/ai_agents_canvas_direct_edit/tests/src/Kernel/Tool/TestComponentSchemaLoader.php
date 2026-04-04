<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents_canvas_direct_edit\Kernel\Tool;

use Drupal\ai_agents_canvas_direct_edit\Service\ComponentSchemaLoaderInterface;

/**
 * Test stub for ComponentSchemaLoaderInterface.
 *
 * Returns the same hard-coded data used in the DirectEditMatcherTest unit tests.
 */
final class TestComponentSchemaLoader implements ComponentSchemaLoaderInterface {

  /**
   * Prop alias map keyed by SDC component name.
   *
   * @var array<string, array<string, string>>
   */
  private static array $propAliases = [
    'sdc.test_theme.heading' => [
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
    'sdc.test_theme.button' => [
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
    'sdc.test_theme.card-icon' => [
      'title' => 'text',
      'heading' => 'text',
      'text' => 'text',
      'description' => 'description',
      'icon' => 'icon',
      'background' => 'background_color',
      'background color' => 'background_color',
    ],
    'sdc.test_theme.badge' => [
      'label' => 'label',
      'text' => 'label',
    ],
    'sdc.test_theme.icon' => [
      'icon' => 'icon',
      'name' => 'icon',
      'size' => 'size',
      'color' => 'color',
    ],
    'sdc.test_theme.section' => [
      'header' => 'section_header',
      'show header' => 'section_header',
      'footer' => 'section_footer',
      'show footer' => 'section_footer',
    ],
    'sdc.test_theme.group' => [
      'gap' => 'flex_gap',
      'flex gap' => 'flex_gap',
      'radius' => 'radius',
      'corner radius' => 'radius',
      'padding' => 'padding',
    ],
  ];

  /**
   * Enum value map keyed by SDC component name and prop name.
   *
   * @var array<string, array<string, array<string, string>>>
   */
  private static array $enumValues = [
    'sdc.test_theme.heading' => [
      'text_color' => [
        'default' => 'default',
        'white' => 'inverted',
        'inverted' => 'inverted',
        'light' => 'inverted',
        'primary' => 'primary',
        'blue' => 'primary',
      ],
      'align' => [
        'default' => 'default',
        'left' => 'left',
        'center' => 'center',
        'centered' => 'center',
        'middle' => 'center',
        'right' => 'right',
      ],
    ],
    'sdc.test_theme.button' => [
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
    ],
    'sdc.test_theme.group' => [
      'flex_gap' => ['sm' => 'sm', 'md' => 'md', 'lg' => 'lg', 'xl' => 'xl'],
      'radius' => ['sm' => 'sm', 'md' => 'md', 'lg' => 'lg', 'xl' => 'xl'],
      'padding' => ['sm' => 'sm', 'md' => 'md', 'lg' => 'lg', 'xl' => 'xl'],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function getPropAliases(string $componentName): array {
    return self::$propAliases[$componentName] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnumValues(string $propName, string $componentName): ?array {
    return self::$enumValues[$componentName][$propName] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedComponents(): array {
    return array_keys(self::$propAliases);
  }

  /**
   * {@inheritdoc}
   */
  public function getReverseEnumIndex(string $componentName): array {
    $enums = self::$enumValues[$componentName] ?? [];
    $reverse = [];
    foreach ($enums as $propName => $valueMap) {
      foreach ($valueMap as $alias => $canonical) {
        $reverse[$alias][] = $propName;
      }
    }
    foreach ($reverse as $value => $props) {
      $reverse[$value] = array_values(array_unique($props));
    }
    return $reverse;
  }

  /**
   * {@inheritdoc}
   */
  public function getBooleanProps(string $componentName): array {
    $booleanProps = [
      'sdc.test_theme.heading' => [],
      'sdc.test_theme.button' => [
        'disabled' => ['aliases' => ['disabled'], 'inverted' => TRUE],
        'icon_first' => ['aliases' => ['icon_first', 'icon first'], 'inverted' => FALSE],
      ],
      'sdc.test_theme.section' => [
        'section_header' => ['aliases' => ['section_header', 'show header', 'header'], 'inverted' => FALSE],
        'section_footer' => ['aliases' => ['section_footer', 'show footer', 'footer'], 'inverted' => FALSE],
      ],
    ];
    return $booleanProps[$componentName] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnumOrdinals(string $componentName): array {
    $ordinals = [
      'sdc.test_theme.heading' => [
        'text_size' => [
          'values' => [
            'default',
            'heading-responsive-8xl',
            'heading-responsive-7xl',
            'heading-responsive-6xl',
            'heading-responsive-5xl',
            'heading-responsive-4xl',
            'heading-responsive-3xl',
            'heading-responsive-2xl',
            'heading-responsive-xl',
          ],
          'direction' => 'descending',
        ],
        'text_color' => [
          'values' => ['default', 'inverted', 'primary'],
          'direction' => 'ascending',
        ],
        'align' => [
          'values' => ['left', 'center', 'right'],
          'direction' => 'ascending',
        ],
      ],
      'sdc.test_theme.button' => [
        'variant' => [
          'values' => ['primary', 'secondary', 'primary-inverted', 'secondary-inverted'],
          'direction' => 'ascending',
        ],
        'size' => [
          'values' => ['small', 'medium', 'large'],
          'direction' => 'ascending',
        ],
      ],
    ];
    return $ordinals[$componentName] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getIntegerEnumValues(string $propName, string $componentName): ?array {
    $integerEnums = [
      'sdc.test_theme.heading' => [
        'level' => [1, 2, 3, 4, 5, 6],
      ],
    ];
    return $integerEnums[$componentName][$propName] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getReverseAliasIndex(string $componentName): array {
    $enums = self::$enumValues[$componentName] ?? [];
    $fullReverse = [];
    foreach ($enums as $propName => $valueMap) {
      foreach ($valueMap as $alias => $canonical) {
        $fullReverse[$alias][] = $propName;
      }
    }
    // Determine raw values (alias === lowercase canonical).
    $rawValues = [];
    foreach ($enums as $propName => $valueMap) {
      foreach ($valueMap as $alias => $canonical) {
        if ($alias === mb_strtolower($canonical)) {
          $rawValues[$alias] = TRUE;
        }
      }
    }
    // Alias index = aliases NOT in the raw enum values set.
    $aliasIndex = [];
    foreach ($fullReverse as $alias => $props) {
      if (!isset($rawValues[$alias])) {
        $aliasIndex[$alias] = array_values(array_unique($props));
      }
    }
    return $aliasIndex;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrthogonalityReport(): array {
    return [];
  }

}
