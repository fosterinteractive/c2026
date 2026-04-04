<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents_canvas_direct_edit\Kernel\Tool;

use Drupal\ai_agents_canvas_direct_edit\Service\AiProviderAvailabilityCheckerInterface;

/**
 * Kernel tests for the MatchDirectEdit #[Tool] plugin.
 *
 * Tests plugin discovery, input handling, and the full match/miss contract
 * through the Tool plugin layer, using a test schema loader stub.
 *
 * @group ai_agents_canvas_direct_edit
 * @coversDefaultClass \Drupal\ai_agents_canvas_direct_edit\Plugin\tool\Tool\MatchDirectEdit
 */
final class MatchDirectEditTest extends DirectEditToolTestBase {

  /**
   * @covers ::create
   */
  public function testPluginExists(): void {
    $plugin = $this->createPlugin();
    $this->assertNotNull($plugin);
  }

  /**
   * @covers ::doExecute
   */
  public function testSinglePropStringMatch(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'change the heading to Welcome to Our Site');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertSame('sdc.test_theme.heading', $output['component_name']);
    $this->assertCount(1, $output['changes']);
    $this->assertSame('heading_text', $output['changes'][0]['prop']);
    $this->assertSame('Welcome to Our Site', $output['changes'][0]['value']);
  }

  /**
   * @covers ::doExecute
   */
  public function testEnumResolutionColorAlias(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'set the color to blue');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertSame('text_color', $output['changes'][0]['prop']);
    $this->assertSame('primary', $output['changes'][0]['value']);
  }

  /**
   * @covers ::doExecute
   */
  public function testEnumResolutionAlignmentAlias(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'set the alignment to centered');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertSame('align', $output['changes'][0]['prop']);
    $this->assertSame('center', $output['changes'][0]['value']);
  }

  /**
   * @covers ::doExecute
   */
  public function testIntegerPropLevel(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'set the level to 3');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertSame('level', $output['changes'][0]['prop']);
    $this->assertSame(3, $output['changes'][0]['value']);
  }

  /**
   * @covers ::doExecute
   */
  public function testCompoundMatch(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'change the heading to Welcome and set the color to blue');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertCount(2, $output['changes']);
    $this->assertSame('heading_text', $output['changes'][0]['prop']);
    $this->assertSame('Welcome', $output['changes'][0]['value']);
    $this->assertSame('text_color', $output['changes'][1]['prop']);
    $this->assertSame('primary', $output['changes'][1]['value']);
  }

  /**
   * @covers ::doExecute
   */
  public function testAddKeywordMissReturnsSuccess(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'add a new section');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $result = $plugin->getResult();
    // Misses are not failures — the tool always returns success.
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('no_match', $output['status']);
    $this->assertSame('sdc.test_theme.heading', $output['component_name']);
  }

  /**
   * @covers ::doExecute
   */
  public function testInvalidEnumMiss(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'set the color to rainbow');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('no_match', $output['status']);
  }

  /**
   * @covers ::doExecute
   */
  public function testBareValueMatch(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'blue');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertSame('text_color', $output['changes'][0]['prop']);
    $this->assertSame('primary', $output['changes'][0]['value']);
  }

  /**
   * @covers ::doExecute
   */
  public function testBooleanToggleShowHeader(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'show the header');
    $plugin->setInputValue('component_name', 'sdc.test_theme.section');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertSame('section_header', $output['changes'][0]['prop']);
    $this->assertTrue($output['changes'][0]['value']);
  }

  /**
   * @covers ::doExecute
   */
  public function testRelativeAdjustmentWithCurrentProps(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'bigger');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->setInputValue(
      'current_prop_values',
      '{"text_size":"heading-responsive-5xl","text_color":"default"}'
    );
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertSame('text_size', $output['changes'][0]['prop']);
    $this->assertSame('heading-responsive-6xl', $output['changes'][0]['value']);
  }

  /**
   * @covers ::doExecute
   */
  public function testRelativeAdjustmentWithoutCurrentPropsIsMiss(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'bigger');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    // No current_prop_values set.
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('no_match', $output['status']);
  }

  /**
   * @covers ::doExecute
   */
  public function testResetPattern(): void {
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'reset the color');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());

    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertSame('text_color', $output['changes'][0]['prop']);
    $this->assertSame('default', $output['changes'][0]['value']);
  }

  /**
   * @covers ::getInputDefinitions
   */
  public function testInputDefinitionsRegistered(): void {
    $plugin = $this->createPlugin();
    $definitions = $plugin->getInputDefinitions(TRUE);

    $this->assertArrayHasKey('message', $definitions);
    $this->assertArrayHasKey('component_name', $definitions);
    $this->assertArrayHasKey('current_prop_values', $definitions);

    $this->assertTrue($definitions['message']->isRequired());
    $this->assertTrue($definitions['component_name']->isRequired());
    $this->assertFalse($definitions['current_prop_values']->isRequired());
  }

  /**
   * @covers ::doExecute
   */
  public function testEmptyCurrentPropValuesIgnored(): void {
    // Passing an empty string for current_prop_values should not crash —
    // it should be treated as no current values (relative adjustments miss).
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'bigger');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->setInputValue('current_prop_values', '');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());
    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('no_match', $output['status']);
  }

  /**
   * @covers ::doExecute
   */
  public function testInvalidJsonCurrentPropValuesIgnored(): void {
    // Passing invalid JSON for current_prop_values should not crash.
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'bigger');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->setInputValue('current_prop_values', 'not-valid-json');
    $plugin->execute();

    $result = $plugin->getResult();
    $this->assertTrue($result->isSuccess());
    $output = json_decode($result->getContextValues()['result'], TRUE);
    $this->assertSame('no_match', $output['status']);
  }

  // ---------------------------------------------------------------------------
  // ai_available field in no_match response (WP08)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::doExecute
   */
  public function testNoMatchIncludesAiAvailableTrueWhenProviderConfigured(): void {
    // Default base setUp registers availability checker returning TRUE.
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'add a new section');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $output = json_decode($plugin->getResult()->getContextValues()['result'], TRUE);
    $this->assertSame('no_match', $output['status']);
    $this->assertArrayHasKey('ai_available', $output);
    $this->assertTrue($output['ai_available']);
  }

  /**
   * @covers ::doExecute
   */
  public function testNoMatchIncludesAiAvailableFalseWhenNoProviderConfigured(): void {
    $unavailable = $this->createMock(AiProviderAvailabilityCheckerInterface::class);
    $unavailable->method('isAiAvailable')->willReturn(FALSE);
    $this->container->set(
      'ai_agents_canvas_direct_edit.ai_provider_availability_checker',
      $unavailable
    );

    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'add a new section');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $output = json_decode($plugin->getResult()->getContextValues()['result'], TRUE);
    $this->assertSame('no_match', $output['status']);
    $this->assertArrayHasKey('ai_available', $output);
    $this->assertFalse($output['ai_available']);
  }

  /**
   * @covers ::doExecute
   */
  public function testMatchedResultDoesNotIncludeAiAvailableField(): void {
    // The ai_available field should only appear in no_match results.
    $plugin = $this->createPlugin();
    $plugin->setInputValue('message', 'change the heading to Hello');
    $plugin->setInputValue('component_name', 'sdc.test_theme.heading');
    $plugin->execute();

    $output = json_decode($plugin->getResult()->getContextValues()['result'], TRUE);
    $this->assertSame('matched', $output['status']);
    $this->assertArrayNotHasKey('ai_available', $output);
  }

}
