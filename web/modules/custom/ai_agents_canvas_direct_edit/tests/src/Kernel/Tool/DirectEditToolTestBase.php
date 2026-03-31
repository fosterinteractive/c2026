<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents_canvas_direct_edit\Kernel\Tool;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_agents_canvas_direct_edit\Plugin\tool\Tool\MatchDirectEdit;
use Drupal\ai_agents_canvas_direct_edit\Service\AiProviderAvailabilityCheckerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Base class for MatchDirectEdit kernel tests.
 *
 * Replaces the component schema loader with a test stub and provides a
 * helper for instantiating the plugin via the plugin manager.
 */
abstract class DirectEditToolTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'tool',
    'ai_agents_canvas_direct_edit',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Replace the component schema loader with the test stub.
    $this->container->set(
      'ai_agents_canvas_direct_edit.component_schema_loader',
      new TestComponentSchemaLoader()
    );

    // Register stub canvas_ai services so the plugin manager can discover
    // all tool plugins without canvas_ai being installed.
    $canvasAiServices = [
      'canvas_ai.tempstore',
      'canvas_ai.component_context_helper',
      'canvas_ai.page_builder_helper',
      'canvas_ai.response_validator',
    ];
    foreach ($canvasAiServices as $serviceId) {
      $this->container->set($serviceId, new \stdClass());
    }

    // Register a stub availability checker that reports AI as available by default.
    $availabilityChecker = $this->createMock(AiProviderAvailabilityCheckerInterface::class);
    $availabilityChecker->method('isAiAvailable')->willReturn(TRUE);
    $this->container->set(
      'ai_agents_canvas_direct_edit.ai_provider_availability_checker',
      $availabilityChecker
    );

    // Replace config.factory to return test settings for the module config key.
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static function (string $key) {
      if ($key === 'edit_verbs') {
        return ['change', 'set', 'update', 'modify', 'make', 'turn', 'switch', 'put'];
      }
      return NULL;
    });

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('ai_agents_canvas_direct_edit.settings')
      ->willReturn($config);

    $this->container->set('config.factory', $configFactory);
  }

  /**
   * Creates the MatchDirectEdit tool plugin via the plugin manager.
   *
   * @return \Drupal\ai_agents_canvas_direct_edit\Plugin\tool\Tool\MatchDirectEdit
   *   The plugin instance.
   */
  protected function createPlugin(): MatchDirectEdit {
    /** @var \Drupal\tool\Tool\ToolManager $manager */
    $manager = $this->container->get('plugin.manager.tool');
    $plugin = $manager->createInstance('ai_agents_canvas_direct_edit:match_direct_edit');
    $this->assertInstanceOf(MatchDirectEdit::class, $plugin);
    return $plugin;
  }

}
