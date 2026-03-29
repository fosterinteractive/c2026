<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads Byte theme component YAML schemas and builds alias/enum maps.
 *
 * Discovers all *.component.yml files under the byte_theme components
 * directory, parses each schema, and produces two maps consumed by
 * DirectEditMatcher:
 *
 * - Prop alias map: {sdc_name => {alias => prop_name}}
 * - Enum value map: {sdc_name => {prop_name => {alias => canonical_value}}}
 *
 * Both maps are cached with the 'canvas_ai_scoping' cache tag and rebuilt
 * on cache clear (drush cr).
 */
final class ComponentSchemaLoader implements ComponentSchemaLoaderInterface {

  /**
   * Cache ID for the prop alias map.
   */
  private const CACHE_CID_ALIASES = 'canvas_ai_scoping:prop_aliases';

  /**
   * Cache ID for the enum value map.
   */
  private const CACHE_CID_ENUMS = 'canvas_ai_scoping:enum_values';

  /**
   * Cache ID for the reverse enum index.
   */
  private const CACHE_CID_REVERSE_ENUM = 'canvas_ai_scoping:reverse_enum_index';

  /**
   * Cache ID for the boolean props map.
   */
  private const CACHE_CID_BOOLEAN_PROPS = 'canvas_ai_scoping:boolean_props';

  /**
   * Cache ID for the enum ordinals map.
   */
  private const CACHE_CID_ENUM_ORDINALS = 'canvas_ai_scoping:enum_ordinals';

  /**
   * Cache tag used to invalidate all maps together.
   */
  private const CACHE_TAG = 'canvas_ai_scoping';

  /**
   * The Byte theme machine name.
   */
  private const THEME_NAME = 'byte_theme';

  /**
   * Props where "enable" means FALSE (inverted boolean semantics).
   */
  private const INVERTED_BOOLEAN_PROPS = [
    'disabled' => TRUE,
    'overlap_navbar' => TRUE,
  ];

  /**
   * Size-category props where the first enum value is the largest (descending).
   */
  private const DESCENDING_ORDINAL_PROPS = [
    'text_size',
    'icon_size',
    'size',
    'tile_size',
    'image_size',
  ];

  /**
   * Cached prop alias map: {sdc_name => {alias => prop_name}}.
   *
   * @var array<string, array<string, string>>|null
   */
  private ?array $propAliases = NULL;

  /**
   * Cached enum value map: {sdc_name => {prop_name => {alias => value}}}.
   *
   * @var array<string, array<string, array<string, string>>>|null
   */
  private ?array $enumValues = NULL;

  /**
   * Cached reverse enum index: {sdc_name => {normalized_value => [prop, ...]}}.
   *
   * @var array<string, array<string, list<string>>>|null
   */
  private ?array $reverseEnumIndex = NULL;

  /**
   * Cached boolean props: {sdc_name => {prop => {aliases => [], inverted => bool}}}.
   *
   * @var array<string, array<string, array{aliases: list<string>, inverted: bool}>>|null
   */
  private ?array $booleanProps = NULL;

  /**
   * Cached enum ordinals: {sdc_name => {prop => {values => [], direction => string}}}.
   *
   * @var array<string, array<string, array{values: list<string>, direction: string}>>|null
   */
  private ?array $enumOrdinals = NULL;

  /**
   * Constructs a ComponentSchemaLoader.
   *
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeList
   *   The theme extension list, used to resolve the byte_theme path.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The default cache backend.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly ThemeExtensionList $themeList,
    private readonly CacheBackendInterface $cache,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns the prop alias map for a component.
   *
   * @param string $componentName
   *   The SDC component name (e.g., 'sdc.byte_theme.heading').
   *
   * @return array<string, string>
   *   Map of alias => prop_name. Empty array if component is not found.
   */
  public function getPropAliases(string $componentName): array {
    $this->ensureLoaded();
    return $this->propAliases[$componentName] ?? [];
  }

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
  public function getEnumValues(string $propName, string $componentName): ?array {
    $this->ensureLoaded();
    return $this->enumValues[$componentName][$propName] ?? NULL;
  }

  /**
   * Returns all component SDC names that have prop aliases defined.
   *
   * @return string[]
   *   List of SDC component names.
   */
  public function getSupportedComponents(): array {
    $this->ensureLoaded();
    return array_keys($this->propAliases ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function getReverseEnumIndex(string $componentName): array {
    $this->ensureLoaded();
    return $this->reverseEnumIndex[$componentName] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getBooleanProps(string $componentName): array {
    $this->ensureLoaded();
    return $this->booleanProps[$componentName] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnumOrdinals(string $componentName): array {
    $this->ensureLoaded();
    return $this->enumOrdinals[$componentName] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOrthogonalityReport(): array {
    $this->ensureLoaded();
    $report = [];

    foreach ($this->reverseEnumIndex ?? [] as $sdcName => $valueMap) {
      $collisions = [];
      foreach ($valueMap as $value => $props) {
        if (count($props) > 1) {
          $collisions[] = ['value' => $value, 'props' => $props];
        }
      }
      $report[$sdcName] = [
        'orthogonal' => empty($collisions),
        'collisions' => $collisions,
      ];
    }

    return $report;
  }

  /**
   * Ensures the alias and enum maps are loaded (from cache or built fresh).
   */
  private function ensureLoaded(): void {
    if ($this->propAliases !== NULL) {
      return;
    }

    $cachedAliases = $this->cache->get(self::CACHE_CID_ALIASES);
    $cachedEnums = $this->cache->get(self::CACHE_CID_ENUMS);
    $cachedReverseEnum = $this->cache->get(self::CACHE_CID_REVERSE_ENUM);
    $cachedBooleanProps = $this->cache->get(self::CACHE_CID_BOOLEAN_PROPS);
    $cachedEnumOrdinals = $this->cache->get(self::CACHE_CID_ENUM_ORDINALS);

    if ($cachedAliases !== FALSE && $cachedEnums !== FALSE
      && $cachedReverseEnum !== FALSE && $cachedBooleanProps !== FALSE
      && $cachedEnumOrdinals !== FALSE) {
      $this->propAliases = $cachedAliases->data;
      $this->enumValues = $cachedEnums->data;
      $this->reverseEnumIndex = $cachedReverseEnum->data;
      $this->booleanProps = $cachedBooleanProps->data;
      $this->enumOrdinals = $cachedEnumOrdinals->data;
      return;
    }

    $this->buildMaps();

    $cacheSets = [
      self::CACHE_CID_ALIASES => $this->propAliases,
      self::CACHE_CID_ENUMS => $this->enumValues,
      self::CACHE_CID_REVERSE_ENUM => $this->reverseEnumIndex,
      self::CACHE_CID_BOOLEAN_PROPS => $this->booleanProps,
      self::CACHE_CID_ENUM_ORDINALS => $this->enumOrdinals,
    ];
    foreach ($cacheSets as $cid => $data) {
      $this->cache->set(
        $cid,
        $data,
        CacheBackendInterface::CACHE_PERMANENT,
        [self::CACHE_TAG],
      );
    }
  }

  /**
   * Builds the prop alias and enum maps from all discovered component YAMLs.
   */
  private function buildMaps(): void {
    $this->propAliases = [];
    $this->enumValues = [];
    $this->reverseEnumIndex = [];
    $this->booleanProps = [];
    $this->enumOrdinals = [];

    $themePath = $this->resolveThemePath();
    if ($themePath === NULL) {
      $this->logger->warning('ComponentSchemaLoader: byte_theme not found; alias map will be empty.');
      return;
    }

    $componentsDir = $themePath . '/components';
    if (!is_dir($componentsDir)) {
      $this->logger->warning('ComponentSchemaLoader: components directory not found at @path.', [
        '@path' => $componentsDir,
      ]);
      return;
    }

    $yamlFiles = glob($componentsDir . '/*/*.component.yml') ?: [];
    foreach ($yamlFiles as $file) {
      $this->processComponentFile($file);
    }
  }

  /**
   * Resolves the absolute filesystem path of byte_theme.
   *
   * @return string|null
   *   Absolute path, or NULL if the theme is not installed.
   */
  private function resolveThemePath(): ?string {
    try {
      $theme = $this->themeList->get(self::THEME_NAME);
      $relativePath = $theme->getPath();
      // getPath() returns a path relative to the Drupal root (DRUPAL_ROOT).
      return DRUPAL_ROOT . '/' . $relativePath;
    }
    catch (\Exception $e) {
      $this->logger->warning('ComponentSchemaLoader: could not resolve byte_theme path: @msg', [
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Parses one component YAML file and populates the alias/enum maps.
   *
   * @param string $file
   *   Absolute path to the *.component.yml file.
   */
  private function processComponentFile(string $file): void {
    try {
      $schema = Yaml::parseFile($file);
    }
    catch (\Exception $e) {
      $this->logger->warning('ComponentSchemaLoader: failed to parse @file: @msg', [
        '@file' => $file,
        '@msg' => $e->getMessage(),
      ]);
      return;
    }

    if (!is_array($schema)) {
      return;
    }

    // Derive the SDC name from the directory name.
    // File: .../components/heading/heading.component.yml → sdc.byte_theme.heading
    $componentDir = basename(dirname($file));
    $sdcName = 'sdc.' . self::THEME_NAME . '.' . $componentDir;

    $properties = $schema['props']['properties'] ?? [];
    if (empty($properties) || !is_array($properties)) {
      return;
    }

    $aliases = [];
    $enumMap = [];
    $reverseEnum = [];
    $boolProps = [];
    $ordinals = [];

    foreach ($properties as $propName => $propDef) {
      if (!is_array($propDef)) {
        continue;
      }

      // Generate natural language aliases from the prop name.
      $generatedAliases = $this->generateAliases($propName);
      foreach ($generatedAliases as $alias) {
        // Do not overwrite an alias already assigned to another prop.
        if (!isset($aliases[$alias])) {
          $aliases[$alias] = $propName;
        }
      }

      // Detect boolean props.
      $propType = $propDef['type'] ?? NULL;
      if ($propType === 'boolean') {
        $boolProps[$propName] = [
          'aliases' => $generatedAliases,
          'inverted' => isset(self::INVERTED_BOOLEAN_PROPS[$propName]),
        ];
      }

      // Build enum map for props with enum constraints.
      if (!isset($propDef['enum']) || !is_array($propDef['enum'])) {
        continue;
      }

      // Skip numeric-only enums (e.g., heading level — handled specially).
      $enumValues = $propDef['enum'];
      $allNumeric = array_reduce($enumValues, static function (bool $carry, mixed $v): bool {
        return $carry && is_numeric($v);
      }, TRUE);
      if ($allNumeric) {
        continue;
      }

      $metaEnum = $propDef['meta:enum'] ?? [];
      $propEnumMap = $this->buildEnumAliases($enumValues, is_array($metaEnum) ? $metaEnum : []);
      if (!empty($propEnumMap)) {
        $enumMap[$propName] = $propEnumMap;
      }

      // Build reverse enum index: normalized_value => [prop_name, ...].
      foreach ($enumValues as $value) {
        if (!is_string($value)) {
          continue;
        }
        $normalized = mb_strtolower($value);
        $reverseEnum[$normalized][] = $propName;
      }

      // Build enum ordinals: ordered values with direction metadata.
      $stringValues = array_values(array_filter($enumValues, 'is_string'));
      if (!empty($stringValues)) {
        $direction = in_array($propName, self::DESCENDING_ORDINAL_PROPS, TRUE)
          ? 'descending'
          : 'ascending';
        $ordinals[$propName] = [
          'values' => $stringValues,
          'direction' => $direction,
        ];
      }
    }

    if (!empty($aliases)) {
      $this->propAliases[$sdcName] = $aliases;
    }
    if (!empty($enumMap)) {
      $this->enumValues[$sdcName] = $enumMap;
    }

    // De-duplicate reverse enum index prop lists.
    if (!empty($reverseEnum)) {
      foreach ($reverseEnum as $value => $props) {
        $reverseEnum[$value] = array_values(array_unique($props));
      }
      $this->reverseEnumIndex[$sdcName] = $reverseEnum;
    }
    if (!empty($boolProps)) {
      $this->booleanProps[$sdcName] = $boolProps;
    }
    if (!empty($ordinals)) {
      $this->enumOrdinals[$sdcName] = $ordinals;
    }
  }

  /**
   * Generates natural language aliases from a prop name.
   *
   * Rules:
   * - The prop name itself is always an alias.
   * - Words split by underscore are aliased individually if they are
   *   meaningful (length > 2) and not stop-words.
   * - Common suffix/prefix combinations produce compound aliases:
   *   e.g., heading_text → heading, title, text
   *        text_color    → color, text color
   *        background_color → background, background color
   *        text_size / font_size → size, font size, text size
   *        text_align / align → align, alignment
   *        icon_size → size (unless conflicts; icon_size keeps 'size' where
   *        no other size prop exists)
   *
   * @param string $propName
   *   The canonical prop name (snake_case).
   *
   * @return string[]
   *   List of unique lowercase aliases including the prop name itself.
   */
  private function generateAliases(string $propName): array {
    $aliases = [$propName];
    $words = explode('_', $propName);

    // Semantic alias rules keyed by prop name.
    $semanticMap = [
      'heading_text' => ['heading', 'title', 'text'],
      'text' => ['text', 'content', 'body'],
      'text_color' => ['color', 'text color'],
      'text_size' => ['size', 'text size', 'font size'],
      'text_align' => ['alignment', 'align', 'text align'],
      'align' => ['align', 'alignment'],
      'background_color' => ['background', 'background color'],
      'background' => ['background', 'background color'],
      'icon_size' => ['icon size'],
      'icon_align' => ['icon alignment', 'icon align'],
      'icon_first' => ['icon first'],
      'label' => ['label', 'text', 'button text'],
      'href' => ['link', 'url', 'href'],
      'url' => ['link', 'url'],
      'variant' => ['variant', 'style'],
      'style' => ['style', 'variant'],
      'size' => ['size'],
      'icon' => ['icon', 'name'],
      'level' => ['level', 'heading level'],
      'heading_level' => ['level', 'heading level'],
      'border_radius' => ['radius', 'border radius', 'corner radius'],
      'radius' => ['radius', 'corner radius'],
      'tile_size' => ['aspect ratio', 'tile size'],
      'image_size' => ['aspect ratio', 'image size'],
      'image_position' => ['image position'],
      'image_radius' => ['image radius'],
      'flex_direction' => ['direction', 'flex direction'],
      'flex_gap' => ['gap', 'space', 'flex gap'],
      'flex_align' => ['align', 'flex align'],
      'items_align' => ['items align', 'alignment'],
      'flex_position' => ['position', 'content position'],
      'object_position' => ['image position', 'object position'],
      'overlay_opacity' => ['opacity', 'overlay opacity'],
      'height' => ['height'],
      'width' => ['width'],
      'columns' => ['columns', 'layout', 'grid layout'],
      'mobile_columns' => ['mobile columns'],
      'views_columns' => ['views columns'],
      'margin_block_start' => ['margin top'],
      'margin_block_end' => ['margin bottom'],
      'padding_block_start' => ['padding top'],
      'padding_block_end' => ['padding bottom'],
      'padding' => ['padding'],
      'section_header' => ['show header', 'header'],
      'section_footer' => ['show footer', 'footer'],
      'hero_flex_gap' => ['flex gap', 'gap'],
      'hero_flex_direction_mobile' => ['mobile direction'],
      'symbol_position' => ['symbol position'],
      'open_by_default' => ['open by default'],
      'cite_name' => ['citation name', 'author'],
      'cite_text' => ['citation text'],
      'cite_url' => ['citation link'],
      'text_align' => ['text align', 'align', 'alignment'],
      'overlap_navbar' => ['overlap header'],
      'mobile_width' => ['mobile width'],
      'menu_align' => ['menu alignment', 'menu align'],
      'promote' => ['highlight', 'promote'],
      'date' => ['date'],
      'author' => ['author'],
      'price' => ['price'],
      'description' => ['description'],
      'title' => ['title', 'heading'],
      'caption' => ['caption'],
      'id' => ['id', 'anchor id'],
      'orientation' => ['orientation'],
    ];

    if (isset($semanticMap[$propName])) {
      foreach ($semanticMap[$propName] as $alias) {
        $aliases[] = $alias;
      }
    }
    else {
      // Fallback: add individual words longer than 2 chars.
      foreach ($words as $word) {
        if (mb_strlen($word) > 2 && $word !== $propName) {
          $aliases[] = $word;
        }
      }
      // Add the human-readable version with spaces.
      $spaced = str_replace('_', ' ', $propName);
      if ($spaced !== $propName) {
        $aliases[] = $spaced;
      }
    }

    return array_values(array_unique($aliases));
  }

  /**
   * Builds the enum alias map for a single prop.
   *
   * Uses meta:enum labels (lowercased) as additional aliases alongside the
   * raw enum values. Also adds common natural language aliases for known
   * value patterns.
   *
   * @param array<mixed> $enumValues
   *   The raw enum values from the YAML schema.
   * @param array<string, string> $metaEnum
   *   The meta:enum map (value => label).
   *
   * @return array<string, string>
   *   Map of alias => canonical_value.
   */
  private function buildEnumAliases(array $enumValues, array $metaEnum): array {
    $map = [];

    foreach ($enumValues as $value) {
      if (!is_string($value)) {
        continue;
      }
      $normalized = mb_strtolower($value);
      $map[$normalized] = $value;

      // Add meta:enum label as an alias.
      if (isset($metaEnum[$value])) {
        $labelAlias = mb_strtolower((string) $metaEnum[$value]);
        if ($labelAlias !== $normalized) {
          $map[$labelAlias] = $value;
        }
      }

      // Add common natural language aliases for known value patterns.
      $naturalAliases = $this->getNaturalAliasesForEnumValue($value);
      foreach ($naturalAliases as $alias) {
        if (!isset($map[$alias])) {
          $map[$alias] = $value;
        }
      }
    }

    return $map;
  }

  /**
   * Returns natural language aliases for a known enum value.
   *
   * Covers color aliases (white → inverted, blue → primary), size aliases
   * (big/large → large, small/tiny → small), alignment aliases
   * (middle/centered → center), and style aliases.
   *
   * @param string $value
   *   The canonical enum value.
   *
   * @return string[]
   *   Additional aliases that map to this value.
   */
  private function getNaturalAliasesForEnumValue(string $value): array {
    $naturalAliasMap = [
      // Color aliases.
      'inverted' => ['white', 'light', 'inverted text'],
      'primary' => ['blue', 'brand'],
      'secondary' => ['grey', 'gray'],
      'accent' => ['highlight accent'],
      'muted' => ['subtle', 'muted background'],
      // Alignment aliases.
      'center' => ['centered', 'middle'],
      'left' => ['start'],
      'right' => ['end'],
      // Size aliases.
      'large' => ['big'],
      'small' => ['tiny'],
      'medium' => ['mid', 'normal size'],
      'extra-large' => ['xl', 'extra large'],
      'extra-small' => ['xs', 'extra small'],
      // Text size aliases (heading).
      'heading-responsive-8xl' => ['8xl', 'extra extra extra large'],
      'heading-responsive-7xl' => ['7xl'],
      'heading-responsive-6xl' => ['6xl'],
      'heading-responsive-5xl' => ['5xl'],
      'heading-responsive-4xl' => ['4xl'],
      'heading-responsive-3xl' => ['3xl'],
      'heading-responsive-2xl' => ['2xl'],
      'heading-responsive-xl' => ['xl heading'],
      // Text size aliases (text component).
      'text-xs' => ['xs', 'smallest', 'tiny text'],
      'text-sm' => ['sm', 'small text'],
      'normal' => ['default size', 'regular'],
      'text-lg' => ['lg'],
      'text-xl' => ['xl text'],
      'text-2xl' => ['2xl text'],
      'text-3xl' => ['3xl text'],
      // Button variant aliases.
      'primary-inverted' => ['primary inverted', 'inverted primary'],
      'secondary-inverted' => ['secondary inverted', 'inverted secondary'],
      // Button/badge style aliases.
      'framed' => ['bordered', 'with border'],
      'full' => ['full width'],
      // Orientation / direction aliases.
      'vertical' => ['portrait', 'top to bottom'],
      'horizontal' => ['landscape', 'side by side'],
      // Hero billboard height aliases.
      'full' => ['fullscreen', 'full screen'],
      'ribbon' => ['thin', 'narrow'],
      // Symbol position aliases.
      'before' => ['prefix', 'in front'],
      'after' => ['suffix', 'behind'],
    ];

    return $naturalAliasMap[$value] ?? [];
  }

}
