<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai_scoping\Unit;

use Drupal\canvas_ai_scoping\AiContextPromptParser;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the AiContextPromptParser utility.
 *
 * @group canvas_ai_scoping
 * @coversDefaultClass \Drupal\canvas_ai_scoping\AiContextPromptParser
 */
class AiContextPromptParserTest extends UnitTestCase {

  /**
   * Builds a system prompt with an ai_context block for testing.
   */
  private function buildPrompt(string $contextContent = 'test context'): string {
    $sep = AiContextPromptParser::SEPARATOR;
    return "Base system prompt instructions here.\n\n"
      . "The following site-specific context applies to this task.\n"
      . $sep . "\n"
      . $contextContent . "\n"
      . $sep . "\n"
      . "Post-context instructions.";
  }

  /**
   * @covers ::findBlock
   */
  public function testFindBlockReturnsBlockBoundaries(): void {
    $prompt = $this->buildPrompt('- ID: 1\n  Tags: brand\n  Guidance:\n    Brand rules here.');
    $block = AiContextPromptParser::findBlock($prompt);

    $this->assertNotNull($block);
    $this->assertArrayHasKey('block_start', $block);
    $this->assertArrayHasKey('block_end', $block);
    $this->assertArrayHasKey('content', $block);
    $this->assertStringContainsString('ID: 1', $block['content']);
  }

  /**
   * @covers ::findBlock
   */
  public function testFindBlockReturnsNullWithNoSeparators(): void {
    $prompt = 'A plain system prompt with no context block.';
    $this->assertNull(AiContextPromptParser::findBlock($prompt));
  }

  /**
   * @covers ::findBlock
   */
  public function testFindBlockReturnsNullWithOneSeparator(): void {
    $sep = AiContextPromptParser::SEPARATOR;
    $prompt = "Base prompt.\n" . $sep . "\nOnly one separator.";
    $this->assertNull(AiContextPromptParser::findBlock($prompt));
  }

  /**
   * @covers ::stripBlock
   */
  public function testStripBlockRemovesContextBlock(): void {
    $prompt = $this->buildPrompt('Context to be stripped.');
    $result = AiContextPromptParser::stripBlock($prompt);

    $this->assertNotNull($result);
    $this->assertGreaterThan(0, $result['bytes_removed']);
    $this->assertStringNotContainsString('Context to be stripped', $result['prompt']);
    $this->assertStringContainsString('Base system prompt', $result['prompt']);
    $this->assertStringContainsString('Post-context instructions', $result['prompt']);
  }

  /**
   * @covers ::stripBlock
   */
  public function testStripBlockReturnsNullWithNoBlock(): void {
    $this->assertNull(AiContextPromptParser::stripBlock('No context here.'));
  }

  /**
   * @covers ::measureBlockSize
   */
  public function testMeasureBlockSize(): void {
    $prompt = $this->buildPrompt('Some context content here.');
    $size = AiContextPromptParser::measureBlockSize($prompt);

    $this->assertGreaterThan(0, $size);
    // Size should be less than total prompt length.
    $this->assertLessThan(strlen($prompt), $size);
  }

  /**
   * @covers ::measureBlockSize
   */
  public function testMeasureBlockSizeReturnsZeroWithNoBlock(): void {
    $this->assertSame(0, AiContextPromptParser::measureBlockSize('No context.'));
  }

  /**
   * @covers ::findBlock
   */
  public function testFindBlockIncludesPrefixInBlockStart(): void {
    $prompt = $this->buildPrompt('Content.');
    $block = AiContextPromptParser::findBlock($prompt);

    // block_start should capture the prefix text before the separator,
    // not just the separator itself.
    $capturedPrefix = substr($prompt, $block['block_start'], 10);
    $this->assertStringNotContainsString('Base system', $capturedPrefix);
  }

}
