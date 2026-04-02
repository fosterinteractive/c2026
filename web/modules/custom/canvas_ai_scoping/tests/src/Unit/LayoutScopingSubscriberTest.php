<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai_scoping\Unit;

use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\canvas_ai_scoping\EventSubscriber\LayoutScopingSubscriber;
use Drupal\canvas_ai_scoping\Service\ContextEnvelopeBuilder;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the LayoutScopingSubscriber.
 *
 * @group canvas_ai_scoping
 * @coversDefaultClass \Drupal\canvas_ai_scoping\EventSubscriber\LayoutScopingSubscriber
 */
class LayoutScopingSubscriberTest extends UnitTestCase {

  private LayoutScopingSubscriber $subscriber;
  private CanvasAiTempStore $tempStore;
  private LoggerInterface $logger;

  /**
   * A realistic multi-region layout with hero, content, and footer regions.
   *
   * @var array
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
            'propValues' => ['heading_text' => 'Features'],
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
                    'propValues' => ['text' => 'Card One'],
                    'slots' => [],
                  ],
                  [
                    'name' => 'sdc.byte_theme.card-icon',
                    'uuid' => 'card-uuid-2',
                    'nodePath' => [1, 1, 1],
                    'propValues' => ['text' => 'Card Two'],
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
            'propValues' => [],
            'slots' => [],
          ],
        ],
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->tempStore = $this->createMock(CanvasAiTempStore::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->subscriber = new LayoutScopingSubscriber(
      $this->tempStore,
      new ContextEnvelopeBuilder(),
      $this->logger,
    );
  }

  /**
   * @covers ::generateRegionIndex
   */
  public function testRegionIndexContainsAllRegions(): void {
    $index = $this->subscriber->generateRegionIndex(self::$testLayout);

    $this->assertCount(3, $index);

    $regionNames = array_column($index, 'region');
    $this->assertSame(['hero', 'content', 'footer'], $regionNames);
  }

  /**
   * @covers ::generateRegionIndex
   */
  public function testRegionIndexIncludesTopLevelComponentSummaries(): void {
    $index = $this->subscriber->generateRegionIndex(self::$testLayout);

    // Hero: 1 component.
    $hero = $index[0];
    $this->assertSame('hero', $hero['region']);
    $this->assertSame([0], $hero['node_path_prefix']);
    $this->assertCount(1, $hero['components']);
    $this->assertSame('sdc.byte_theme.hero', $hero['components'][0]['name']);
    $this->assertSame('hero-uuid-1', $hero['components'][0]['uuid']);

    // Content: 3 top-level components.
    $content = $index[1];
    $this->assertSame('content', $content['region']);
    $this->assertCount(3, $content['components']);
    $this->assertSame('sdc.byte_theme.heading', $content['components'][0]['name']);
    $this->assertSame('sdc.byte_theme.card-grid', $content['components'][1]['name']);
    $this->assertSame('sdc.byte_theme.cta-section', $content['components'][2]['name']);

    // Footer: 1 component.
    $footer = $index[2];
    $this->assertSame('footer', $footer['region']);
    $this->assertCount(1, $footer['components']);
  }

  /**
   * @covers ::generateRegionIndex
   */
  public function testRegionIndexExcludesNestedComponents(): void {
    $index = $this->subscriber->generateRegionIndex(self::$testLayout);

    // Content region has card-grid with 2 nested card-icons in slots.
    // The region index should only list the 3 top-level components.
    $content = $index[1];
    $componentNames = array_column($content['components'], 'name');
    $this->assertNotContains('sdc.byte_theme.card-icon', $componentNames);
  }

  /**
   * @covers ::generateRegionIndex
   */
  public function testRegionIndexExcludesPropValues(): void {
    $index = $this->subscriber->generateRegionIndex(self::$testLayout);

    // Region index should not leak prop values — just names and UUIDs.
    $json = json_encode($index);
    $this->assertStringNotContainsString('Welcome to FinDrop', $json);
    $this->assertStringNotContainsString('propValues', $json);
    $this->assertStringNotContainsString('slots', $json);
  }

  /**
   * @covers ::generateRegionIndex
   */
  public function testRegionIndexIsCompact(): void {
    $index = $this->subscriber->generateRegionIndex(self::$testLayout);
    $json = json_encode($index, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // The full layout fixture is ~1.5KB. The region index for 3 regions
    // with 5 top-level components should be well under 500 bytes.
    $this->assertLessThan(500, strlen($json),
      "Region index should be compact; got " . strlen($json) . " bytes"
    );
  }

  /**
   * @covers ::generateRegionIndex
   */
  public function testRegionIndexWithEmptyLayout(): void {
    $index = $this->subscriber->generateRegionIndex([]);
    $this->assertSame([], $index);

    $index = $this->subscriber->generateRegionIndex(['regions' => []]);
    $this->assertSame([], $index);
  }

  /**
   * @covers ::generateRegionIndex
   */
  public function testRegionIndexWithEmptyRegion(): void {
    $layout = [
      'regions' => [
        'empty_region' => [
          'nodePathPrefix' => [0],
          'components' => [],
        ],
      ],
    ];
    $index = $this->subscriber->generateRegionIndex($layout);

    $this->assertCount(1, $index);
    $this->assertSame('empty_region', $index[0]['region']);
    $this->assertSame([], $index[0]['components']);
  }

  /**
   * Tests that scoped layout includes the region index.
   *
   * @covers ::onBuildSystemPrompt
   */
  public function testScopedLayoutIncludesRegionIndex(): void {
    $layoutJson = json_encode(self::$testLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $this->tempStore->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn($layoutJson);

    $event = $this->createMock(BuildSystemPromptEvent::class);
    $event->method('getAgentId')
      ->willReturn('canvas_page_builder_agent');
    $event->method('getTokens')
      ->willReturn(['active_component_uuid' => 'heading-uuid-1']);

    // The system prompt must contain the layout JSON for replacement to work.
    $systemPrompt = "You are a page builder. Current layout: {$layoutJson}";
    $event->method('getSystemPrompt')
      ->willReturn($systemPrompt);

    $capturedPrompt = NULL;
    $event->method('setSystemPrompt')
      ->willReturnCallback(function (string $prompt) use (&$capturedPrompt): void {
        $capturedPrompt = $prompt;
      });

    $this->subscriber->onBuildSystemPrompt($event);

    $this->assertNotNull($capturedPrompt, 'System prompt should have been updated');

    // Extract the scoped layout JSON from the updated prompt.
    $prefix = 'You are a page builder. Current layout: ';
    $scopedJson = substr($capturedPrompt, strlen($prefix));
    $scoped = json_decode($scopedJson, TRUE);

    $this->assertArrayHasKey('region_index', $scoped);
    $this->assertCount(3, $scoped['region_index']);

    $regionNames = array_column($scoped['region_index'], 'region');
    $this->assertSame(['hero', 'content', 'footer'], $regionNames);
  }

  /**
   * Tests that content region is scoped to the active section.
   *
   * @covers ::onBuildSystemPrompt
   */
  public function testScopedLayoutScopesActiveRegion(): void {
    $layoutJson = json_encode(self::$testLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $this->tempStore->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn($layoutJson);

    $event = $this->createMock(BuildSystemPromptEvent::class);
    $event->method('getAgentId')
      ->willReturn('canvas_page_builder_agent');
    $event->method('getTokens')
      ->willReturn(['active_component_uuid' => 'heading-uuid-1']);
    $event->method('getSystemPrompt')
      ->willReturn($layoutJson);

    $capturedPrompt = NULL;
    $event->method('setSystemPrompt')
      ->willReturnCallback(function (string $prompt) use (&$capturedPrompt): void {
        $capturedPrompt = $prompt;
      });

    $this->subscriber->onBuildSystemPrompt($event);

    $scoped = json_decode($capturedPrompt, TRUE);

    // Active region (content): heading-uuid-1 is first component — full detail.
    $contentComponents = $scoped['regions']['content']['components'];
    $this->assertCount(3, $contentComponents);

    // First component (active): has propValues.
    $this->assertArrayHasKey('propValues', $contentComponents[0]);
    $this->assertSame('heading-uuid-1', $contentComponents[0]['uuid']);

    // Second component (sibling): summarized.
    $this->assertArrayHasKey('_note', $contentComponents[1]);
    $this->assertArrayNotHasKey('propValues', $contentComponents[1]);
    $this->assertArrayNotHasKey('slots', $contentComponents[1]);

    // Other regions: count only.
    $heroComponents = $scoped['regions']['hero']['components'];
    $this->assertSame([], $heroComponents);
    $this->assertStringContainsString('omitted', $scoped['regions']['hero']['_note']);
  }

  /**
   * Tests section scoping with nested component via page_builder_agent.
   *
   * @covers ::onBuildSystemPrompt
   */
  public function testSectionScopingWithNestedActiveComponent(): void {
    $layoutJson = json_encode(self::$testLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $this->tempStore->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn($layoutJson);

    $event = $this->createMock(BuildSystemPromptEvent::class);
    $event->method('getAgentId')
      ->willReturn('canvas_page_builder_agent');
    // card-uuid-1 is nested inside card-grid's slot.
    $event->method('getTokens')
      ->willReturn(['active_component_uuid' => 'card-uuid-1']);
    $event->method('getSystemPrompt')
      ->willReturn($layoutJson);

    $capturedPrompt = NULL;
    $event->method('setSystemPrompt')
      ->willReturnCallback(function (string $prompt) use (&$capturedPrompt): void {
        $capturedPrompt = $prompt;
      });

    $this->subscriber->onBuildSystemPrompt($event);

    $scoped = json_decode($capturedPrompt, TRUE);
    $contentComponents = $scoped['regions']['content']['components'];

    // card-uuid-1 is nested under card-grid (index 1). Card-grid should be
    // the active section with full detail; heading and cta should be summaries.
    $this->assertArrayHasKey('_note', $contentComponents[0]); // heading = summary
    $this->assertArrayHasKey('slots', $contentComponents[1]);  // card-grid = full
    $this->assertArrayHasKey('_note', $contentComponents[2]); // cta = summary
  }

  /**
   * Tests component_agent gets an envelope instead of section scoping.
   *
   * @covers ::onBuildSystemPrompt
   */
  public function testComponentAgentGetsEnvelope(): void {
    $layoutJson = json_encode(self::$testLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $this->tempStore->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn($layoutJson);

    $event = $this->createMock(BuildSystemPromptEvent::class);
    $event->method('getAgentId')
      ->willReturn('canvas_component_agent');
    $event->method('getTokens')
      ->willReturn(['active_component_uuid' => 'heading-uuid-1']);
    $event->method('getSystemPrompt')
      ->willReturn($layoutJson);

    $capturedPrompt = NULL;
    $event->method('setSystemPrompt')
      ->willReturnCallback(function (string $prompt) use (&$capturedPrompt): void {
        $capturedPrompt = $prompt;
      });

    $this->subscriber->onBuildSystemPrompt($event);

    $envelope = json_decode($capturedPrompt, TRUE);

    // Should be an envelope, not section-scoped layout.
    $this->assertSame('component', $envelope['scope']);
    $this->assertArrayHasKey('active_component', $envelope);
    $this->assertArrayHasKey('neighbors', $envelope);
    $this->assertArrayHasKey('section', $envelope);
    $this->assertArrayHasKey('page_outline', $envelope);

    // No 'regions' key — this is not section scoping.
    $this->assertArrayNotHasKey('regions', $envelope);

    $this->assertSame('heading-uuid-1', $envelope['active_component']['uuid']);
    $this->assertSame('Features', $envelope['active_component']['propValues']['heading_text']);
  }

  /**
   * Tests that non-scoped agents are not affected.
   *
   * @covers ::onBuildSystemPrompt
   */
  public function testSkipsNonScopedAgents(): void {
    $event = $this->createMock(BuildSystemPromptEvent::class);
    $event->method('getAgentId')
      ->willReturn('canvas_ai_orchestrator');
    $event->expects($this->never())->method('setSystemPrompt');

    $this->subscriber->onBuildSystemPrompt($event);
  }

  /**
   * Tests that events without an active component UUID are not affected.
   *
   * @covers ::onBuildSystemPrompt
   */
  public function testSkipsWithoutActiveComponent(): void {
    $event = $this->createMock(BuildSystemPromptEvent::class);
    $event->method('getAgentId')
      ->willReturn('canvas_page_builder_agent');
    $event->method('getTokens')
      ->willReturn(['active_component_uuid' => 'None']);
    $event->expects($this->never())->method('setSystemPrompt');

    $this->subscriber->onBuildSystemPrompt($event);
  }

  /**
   * Tests layout_data token presence does not break temp-store scoping.
   *
   * @covers ::onBuildSystemPrompt
   */
  public function testLayoutDataTokenIsPresent(): void {
    $layoutJson = json_encode(self::$testLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $this->tempStore->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn($layoutJson);

    $event = $this->createMock(BuildSystemPromptEvent::class);
    $event->method('getAgentId')
      ->willReturn('canvas_page_builder_agent');
    $event->method('getTokens')
      ->willReturn([
        'active_component_uuid' => 'heading-uuid-1',
        'layout_data' => self::$testLayout,
      ]);
    $event->method('getSystemPrompt')
      ->willReturn($layoutJson);
    $event->expects($this->once())
      ->method('setSystemPrompt');

    $this->subscriber->onBuildSystemPrompt($event);
  }

  /**
   * Tests missing layout_data token does not break temp-store scoping.
   *
   * @covers ::onBuildSystemPrompt
   */
  public function testLayoutDataTokenMissingDoesNotBreakScoping(): void {
    $layoutJson = json_encode(self::$testLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $this->tempStore->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn($layoutJson);

    $event = $this->createMock(BuildSystemPromptEvent::class);
    $event->method('getAgentId')
      ->willReturn('canvas_page_builder_agent');
    $event->method('getTokens')
      ->willReturn([
        'active_component_uuid' => 'heading-uuid-1',
      ]);
    $event->method('getSystemPrompt')
      ->willReturn($layoutJson);
    $event->expects($this->once())
      ->method('setSystemPrompt');

    $this->subscriber->onBuildSystemPrompt($event);
  }

}
