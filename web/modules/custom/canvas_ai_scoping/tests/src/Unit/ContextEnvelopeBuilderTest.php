<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai_scoping\Unit;

use Drupal\canvas_ai_scoping\Service\ContextEnvelopeBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ContextEnvelopeBuilder service.
 *
 * @group canvas_ai_scoping
 * @coversDefaultClass \Drupal\canvas_ai_scoping\Service\ContextEnvelopeBuilder
 */
class ContextEnvelopeBuilderTest extends UnitTestCase {

  private ContextEnvelopeBuilder $builder;

  /**
   * Multi-region layout fixture matching LayoutScopingSubscriberTest.
   */
  private static array $testLayout = [
    'regions' => [
      'hero' => [
        'nodePathPrefix' => [0],
        'components' => [
          [
            'name' => 'sdc.byte_theme.hero',
            'uuid' => 'hero-uuid-1',
            'nodePath' => [0, 0],
            'propValues' => ['heading_text' => 'Welcome to FinDrop'],
            'slots' => [],
          ],
        ],
      ],
      'content' => [
        'nodePathPrefix' => [1],
        'components' => [
          [
            'name' => 'sdc.byte_theme.heading',
            'uuid' => 'heading-uuid-1',
            'nodePath' => [1, 0],
            'propValues' => ['heading_text' => 'Features', 'text_color' => 'default'],
            'slots' => [],
          ],
          [
            'name' => 'sdc.byte_theme.card-grid',
            'uuid' => 'cardgrid-uuid-1',
            'nodePath' => [1, 1],
            'propValues' => ['columns' => 3],
            'slots' => [
              [
                'name' => 'cards',
                'components' => [
                  [
                    'name' => 'sdc.byte_theme.card-icon',
                    'uuid' => 'card-uuid-1',
                    'nodePath' => [1, 1, 0],
                    'propValues' => ['text' => 'Card One', 'icon' => 'star'],
                    'slots' => [],
                  ],
                  [
                    'name' => 'sdc.byte_theme.card-icon',
                    'uuid' => 'card-uuid-2',
                    'nodePath' => [1, 1, 1],
                    'propValues' => ['text' => 'Card Two', 'icon' => 'heart'],
                    'slots' => [],
                  ],
                  [
                    'name' => 'sdc.byte_theme.card-icon',
                    'uuid' => 'card-uuid-3',
                    'nodePath' => [1, 1, 2],
                    'propValues' => ['text' => 'Card Three', 'icon' => 'bolt'],
                    'slots' => [],
                  ],
                ],
              ],
            ],
          ],
          [
            'name' => 'sdc.byte_theme.cta-section',
            'uuid' => 'cta-uuid-1',
            'nodePath' => [1, 2],
            'propValues' => ['heading' => 'Get Started'],
            'slots' => [],
          ],
        ],
      ],
      'footer' => [
        'nodePathPrefix' => [2],
        'components' => [
          [
            'name' => 'sdc.byte_theme.footer',
            'uuid' => 'footer-uuid-1',
            'nodePath' => [2, 0],
            'propValues' => ['copyright' => '2026 FinDrop'],
            'slots' => [],
          ],
        ],
      ],
    ],
  ];

  private static array $regionIndex = [
    [
      'region' => 'hero',
      'node_path_prefix' => [0],
      'components' => [['name' => 'sdc.byte_theme.hero', 'uuid' => 'hero-uuid-1']],
    ],
    [
      'region' => 'content',
      'node_path_prefix' => [1],
      'components' => [
        ['name' => 'sdc.byte_theme.heading', 'uuid' => 'heading-uuid-1'],
        ['name' => 'sdc.byte_theme.card-grid', 'uuid' => 'cardgrid-uuid-1'],
        ['name' => 'sdc.byte_theme.cta-section', 'uuid' => 'cta-uuid-1'],
      ],
    ],
    [
      'region' => 'footer',
      'node_path_prefix' => [2],
      'components' => [['name' => 'sdc.byte_theme.footer', 'uuid' => 'footer-uuid-1']],
    ],
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->builder = new ContextEnvelopeBuilder();
  }

  /**
   * @covers ::build
   */
  public function testEnvelopeForTopLevelComponent(): void {
    $envelope = $this->builder->build(
      self::$testLayout,
      'heading-uuid-1',
      self::$regionIndex,
    );

    $this->assertNotNull($envelope);
    $this->assertSame('component', $envelope['scope']);

    // Layer 1: active component with full props.
    $component = $envelope['active_component'];
    $this->assertSame('heading-uuid-1', $component['uuid']);
    $this->assertSame('sdc.byte_theme.heading', $component['name']);
    $this->assertSame('Features', $component['propValues']['heading_text']);

    // Layer 2: neighbors.
    $this->assertNull($envelope['neighbors']['previous']);
    $this->assertSame('sdc.byte_theme.card-grid', $envelope['neighbors']['next']['name']);

    // Layer 3: section metadata.
    $this->assertSame('content', $envelope['section']['region']);
    $this->assertSame(1, $envelope['section']['position']);
    $this->assertSame(3, $envelope['section']['total_in_level']);
    $this->assertSame(0, $envelope['section']['nesting_depth']);

    // Layer 4: page outline.
    $this->assertSame(self::$regionIndex, $envelope['page_outline']);
  }

  /**
   * @covers ::build
   */
  public function testEnvelopeForMiddleComponent(): void {
    $envelope = $this->builder->build(
      self::$testLayout,
      'cardgrid-uuid-1',
      self::$regionIndex,
    );

    $this->assertNotNull($envelope);

    // Card-grid is between heading and cta.
    $this->assertSame('sdc.byte_theme.heading', $envelope['neighbors']['previous']['name']);
    $this->assertSame('sdc.byte_theme.cta-section', $envelope['neighbors']['next']['name']);

    $this->assertSame(2, $envelope['section']['position']);
  }

  /**
   * @covers ::build
   */
  public function testEnvelopeForLastComponent(): void {
    $envelope = $this->builder->build(
      self::$testLayout,
      'cta-uuid-1',
      self::$regionIndex,
    );

    $this->assertNotNull($envelope);

    $this->assertSame('sdc.byte_theme.card-grid', $envelope['neighbors']['previous']['name']);
    $this->assertNull($envelope['neighbors']['next']);

    $this->assertSame(3, $envelope['section']['position']);
  }

  /**
   * @covers ::build
   */
  public function testEnvelopeForNestedSlotComponent(): void {
    $envelope = $this->builder->build(
      self::$testLayout,
      'card-uuid-2',
      self::$regionIndex,
    );

    $this->assertNotNull($envelope);

    // Layer 1: the card itself.
    $this->assertSame('card-uuid-2', $envelope['active_component']['uuid']);
    $this->assertSame('sdc.byte_theme.card-icon', $envelope['active_component']['name']);
    $this->assertSame('Card Two', $envelope['active_component']['propValues']['text']);

    // Layer 2: neighbors within the slot.
    $this->assertSame('card-uuid-1', $envelope['neighbors']['previous']['uuid']);
    $this->assertSame('card-uuid-3', $envelope['neighbors']['next']['uuid']);

    // Layer 3: nested depth.
    $this->assertSame('content', $envelope['section']['region']);
    $this->assertSame(3, $envelope['section']['total_in_level']);
    $this->assertSame(1, $envelope['section']['nesting_depth']);
  }

  /**
   * @covers ::build
   */
  public function testEnvelopeForSingleComponentRegion(): void {
    $envelope = $this->builder->build(
      self::$testLayout,
      'hero-uuid-1',
      self::$regionIndex,
    );

    $this->assertNotNull($envelope);
    $this->assertSame('sdc.byte_theme.hero', $envelope['active_component']['name']);

    // Only component in hero — no neighbors.
    $this->assertNull($envelope['neighbors']['previous']);
    $this->assertNull($envelope['neighbors']['next']);

    $this->assertSame('hero', $envelope['section']['region']);
    $this->assertSame(1, $envelope['section']['total_in_level']);
  }

  /**
   * @covers ::build
   */
  public function testEnvelopeReturnsNullForUnknownUuid(): void {
    $envelope = $this->builder->build(
      self::$testLayout,
      'nonexistent-uuid',
      self::$regionIndex,
    );

    $this->assertNull($envelope);
  }

  /**
   * @covers ::build
   */
  public function testEnvelopeIsCompact(): void {
    $envelope = $this->builder->build(
      self::$testLayout,
      'heading-uuid-1',
      self::$regionIndex,
    );

    $envelopeJson = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $layoutJson = json_encode(self::$testLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Envelope should be smaller than the full layout. On small test fixtures
    // the region index is a larger proportion; on real pages (10KB+) the
    // envelope is typically <10% of the layout.
    $this->assertLessThan(
      strlen($layoutJson),
      strlen($envelopeJson),
      sprintf(
        'Envelope (%d bytes) should be smaller than full layout (%d bytes)',
        strlen($envelopeJson),
        strlen($layoutJson),
      ),
    );
  }

  /**
   * @covers ::build
   */
  public function testEnvelopeWithEmptyLayout(): void {
    $envelope = $this->builder->build([], 'any-uuid', []);
    $this->assertNull($envelope);
  }

}
