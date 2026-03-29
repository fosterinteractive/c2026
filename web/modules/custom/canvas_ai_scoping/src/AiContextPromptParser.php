<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping;

/**
 * Parses ai_context blocks from system prompts.
 *
 * ai_context's SystemPromptSubscriber appends context blocks to the system
 * prompt in this format:
 *
 *   \n\n<prefix text>\n<SEPARATOR>\n<context items>\n<SEPARATOR>\n
 *
 * This utility centralizes the separator-based parsing logic used by
 * multiple event subscribers to find, measure, and strip these blocks.
 *
 * Format dependency: if ai_context changes its separator or block format,
 * this is the single place to update.
 */
final class AiContextPromptParser {

  /**
   * The separator used by ai_context's SystemPromptSubscriber.
   *
   * @see \Drupal\ai_context\EventSubscriber\SystemPromptSubscriber::onPreSystemPrompt()
   */
  public const SEPARATOR = '-----------------------------------------------';

  /**
   * Maximum characters to search backward for the context prefix.
   */
  private const PREFIX_SEARCH_WINDOW = 300;

  /**
   * Finds the ai_context block boundaries in a system prompt.
   *
   * @param string $prompt
   *   The full system prompt.
   *
   * @return array{block_start: int, block_end: int, content_start: int, content_end: int, content: string}|null
   *   Block boundaries and content, or NULL if no block found.
   *   - block_start: position of the prefix (before separator), for full removal
   *   - block_end: position after the closing separator + newline
   *   - content_start: position after the opening separator
   *   - content_end: position of the closing separator
   *   - content: the text between the two separators
   */
  public static function findBlock(string $prompt): ?array {
    $startPos = strpos($prompt, self::SEPARATOR);
    if ($startPos === FALSE) {
      return NULL;
    }

    $endPos = strpos($prompt, self::SEPARATOR, $startPos + strlen(self::SEPARATOR));
    if ($endPos === FALSE) {
      return NULL;
    }

    // Walk back from the first separator to find the prefix ("\n\n" before it).
    $prefixSearchStart = max(0, $startPos - self::PREFIX_SEARCH_WINDOW);
    $beforeSeparator = substr($prompt, $prefixSearchStart, $startPos - $prefixSearchStart);
    $lastDoubleNewline = strrpos($beforeSeparator, "\n\n");

    $blockStart = $lastDoubleNewline !== FALSE
      ? $prefixSearchStart + $lastDoubleNewline
      : $startPos;

    $contentStart = $startPos + strlen(self::SEPARATOR) + 1;
    $contentEnd = $endPos;
    $blockEnd = min($endPos + strlen(self::SEPARATOR) + 1, strlen($prompt));

    return [
      'block_start' => $blockStart,
      'block_end' => $blockEnd,
      'content_start' => $contentStart,
      'content_end' => $contentEnd,
      'content' => substr($prompt, $contentStart, $contentEnd - $contentStart),
    ];
  }

  /**
   * Strips the ai_context block from a system prompt.
   *
   * @param string $prompt
   *   The full system prompt.
   *
   * @return array{prompt: string, bytes_removed: int}|null
   *   The modified prompt and bytes removed, or NULL if no block found.
   */
  public static function stripBlock(string $prompt): ?array {
    $block = self::findBlock($prompt);
    if ($block === NULL) {
      return NULL;
    }

    $newPrompt = substr($prompt, 0, $block['block_start'])
      . substr($prompt, $block['block_end']);

    return [
      'prompt' => $newPrompt,
      'bytes_removed' => strlen($prompt) - strlen($newPrompt),
    ];
  }

  /**
   * Measures the ai_context block size in a system prompt.
   *
   * @param string $prompt
   *   The full system prompt.
   *
   * @return int
   *   The block size in bytes, or 0 if no block found.
   */
  public static function measureBlockSize(string $prompt): int {
    $block = self::findBlock($prompt);
    if ($block === NULL) {
      return 0;
    }
    return $block['block_end'] - $block['block_start'];
  }

}
