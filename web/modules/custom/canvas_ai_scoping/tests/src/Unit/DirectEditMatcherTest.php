<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai_scoping\Unit;

use Drupal\canvas_ai_scoping\Service\ComponentSchemaLoaderInterface;
use Drupal\canvas_ai_scoping\Service\DirectEditMatcher;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the DirectEditMatcher service.
 *
 * @group canvas_ai_scoping
 * @coversDefaultClass \Drupal\canvas_ai_scoping\Service\DirectEditMatcher
 */
class DirectEditMatcherTest extends UnitTestCase {

  private DirectEditMatcher $matcher;

  /**
   * Prop alias map equivalent to the previous hardcoded PROP_ALIASES constant.
   *
   * Keyed by SDC component name; values are alias => prop_name maps.
   *
   * @var array<string, array<string, string>>
   */
  private static array $propAliases = [
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
   * Enum value map equivalent to the previous hardcoded ENUM_VALUES constant.
   *
   * Keyed by SDC component name, then prop name; values are alias => canonical.
   *
   * @var array<string, array<string, array<string, string>>>
   */
  private static array $enumValues = [
    'sdc.byte_theme.heading' => [
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
    ],
    'sdc.byte_theme.button' => [
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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $schemaLoader = $this->createMock(ComponentSchemaLoaderInterface::class);

    $schemaLoader->method('getPropAliases')
      ->willReturnCallback(static function (string $componentName): array {
        return self::$propAliases[$componentName] ?? [];
      });

    $schemaLoader->method('getEnumValues')
      ->willReturnCallback(static function (string $propName, string $componentName): ?array {
        return self::$enumValues[$componentName][$propName] ?? NULL;
      });

    $schemaLoader->method('getSupportedComponents')
      ->willReturn(array_keys(self::$propAliases));

    $this->matcher = new DirectEditMatcher($schemaLoader);
  }

  /**
   * @covers ::match
   * @dataProvider singlePropMatchProvider
   */
  public function testSinglePropMatches(string $message, string $component, string $expectedProp, mixed $expectedValue): void {
    $result = $this->matcher->match($message, $component);
    $this->assertNotNull($result, "Expected match for: \"$message\"");
    $this->assertSame($expectedProp, $result['prop']);
    $this->assertSame($expectedValue, $result['value']);
  }

  /**
   * Data provider for single-prop matches.
   */
  public static function singlePropMatchProvider(): array {
    return [
      // Heading text changes.
      'change heading text' => [
        'change the heading to Welcome to FinDrop',
        'sdc.byte_theme.heading',
        'heading_text',
        'Welcome to FinDrop',
      ],
      'set title' => [
        'set the title to Hello World',
        'sdc.byte_theme.heading',
        'heading_text',
        'Hello World',
      ],

      // Enum resolution — text_color.
      'set color primary' => [
        'set the color to primary',
        'sdc.byte_theme.heading',
        'text_color',
        'primary',
      ],
      'color alias blue' => [
        'change the color to blue',
        'sdc.byte_theme.heading',
        'text_color',
        'primary',
      ],
      'color alias white' => [
        'set the color to white',
        'sdc.byte_theme.heading',
        'text_color',
        'inverted',
      ],

      // Enum resolution — align.
      'set alignment center' => [
        'set the alignment to center',
        'sdc.byte_theme.heading',
        'align',
        'center',
      ],
      'align alias centered' => [
        'set the alignment to centered',
        'sdc.byte_theme.heading',
        'align',
        'center',
      ],

      // Numeric prop — level.
      'set level 3' => [
        'set the level to 3',
        'sdc.byte_theme.heading',
        'level',
        3,
      ],
      'set level 1' => [
        'change the level to 1',
        'sdc.byte_theme.heading',
        'level',
        1,
      ],

      // Button component.
      'button label' => [
        'change the label to Get Started',
        'sdc.byte_theme.button',
        'label',
        'Get Started',
      ],
      'button variant' => [
        'set the variant to secondary',
        'sdc.byte_theme.button',
        'variant',
        'secondary',
      ],
      'button size' => [
        'set the size to large',
        'sdc.byte_theme.button',
        'size',
        'large',
      ],

      // "make" as edit verb (was previously blocked).
      'make color blue' => [
        'make the color to blue',
        'sdc.byte_theme.heading',
        'text_color',
        'primary',
      ],

      // Colon format.
      'colon format heading' => [
        'heading: New Title Here',
        'sdc.byte_theme.heading',
        'heading_text',
        'New Title Here',
      ],

      // Equals format.
      'equals format color' => [
        'set color = primary',
        'sdc.byte_theme.heading',
        'text_color',
        'primary',
      ],
    ];
  }

  /**
   * @covers ::match
   * @dataProvider rejectProvider
   */
  public function testRejects(string $message, string $component, string $reason): void {
    $result = $this->matcher->match($message, $component);
    $this->assertNull($result, "Expected NULL (reject) for: \"$message\" ($reason)");
  }

  /**
   * Data provider for messages that should NOT match.
   */
  public static function rejectProvider(): array {
    return [
      // Add/create keywords.
      'add keyword' => ['add a new section below', 'sdc.byte_theme.heading', 'add keyword'],
      'create keyword' => ['create a heading', 'sdc.byte_theme.heading', 'create keyword'],
      'insert keyword' => ['insert a card here', 'sdc.byte_theme.heading', 'insert keyword'],
      'generate keyword' => ['generate a better title', 'sdc.byte_theme.heading', 'generate keyword'],
      'build keyword' => ['build a new section', 'sdc.byte_theme.heading', 'build keyword'],

      // "make" with add-intent phrases.
      'make a new' => ['make a new heading', 'sdc.byte_theme.heading', 'make-a-new phrase'],
      'make me a' => ['make me a section', 'sdc.byte_theme.heading', 'make-me-a phrase'],
      'make another' => ['make another card below', 'sdc.byte_theme.heading', 'another keyword'],

      // Ambiguous — no prop/value match.
      'ambiguous improve' => ['make this look better', 'sdc.byte_theme.heading', 'no prop match'],
      'ambiguous rewrite' => ['rewrite this to be more engaging', 'sdc.byte_theme.heading', 'no pattern match'],
      'vague request' => ['fix this', 'sdc.byte_theme.heading', 'no pattern match'],

      // Unknown component.
      'unknown component' => ['change the heading to Hello', 'sdc.unknown_theme.widget', 'unknown component'],

      // Invalid enum value.
      'invalid enum' => ['set the color to rainbow', 'sdc.byte_theme.heading', 'invalid enum value'],

      // Invalid level (out of range).
      'level too high' => ['set the level to 7', 'sdc.byte_theme.heading', 'level out of range'],
      'level zero' => ['set the level to 0', 'sdc.byte_theme.heading', 'level out of range'],
      'level text' => ['set the level to big', 'sdc.byte_theme.heading', 'level non-numeric'],

      // Empty and too-long messages.
      'empty message' => ['', 'sdc.byte_theme.heading', 'empty message'],
      'too long message' => [str_repeat('x', 501), 'sdc.byte_theme.heading', 'exceeds 500 chars'],
    ];
  }

  /**
   * @covers ::getSupportedComponents
   */
  public function testGetSupportedComponents(): void {
    $components = $this->matcher->getSupportedComponents();
    $this->assertContains('sdc.byte_theme.heading', $components);
    $this->assertContains('sdc.byte_theme.button', $components);
    $this->assertContains('sdc.byte_theme.card-icon', $components);
    $this->assertGreaterThanOrEqual(5, count($components));
  }

}
