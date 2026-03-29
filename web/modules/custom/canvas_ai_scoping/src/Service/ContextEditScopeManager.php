<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_scoping\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages which ai_context items should be stripped during edit operations.
 *
 * When a content author selects a component and makes an edit request,
 * not all ai_context items are relevant. Structural docs (product page
 * templates, page building guidelines) can be stripped to save tokens.
 *
 * This service:
 * - Auto-generates content fingerprints when ai_context_item entities are saved
 * - Stores the edit-scope configuration (which items to strip) in Drupal state
 * - Provides the fingerprint map to ContextScopingSubscriber at runtime
 *
 * Site builders configure which items to strip via drush or a settings form.
 * Fingerprints are auto-regenerated when content changes.
 */
final class ContextEditScopeManager {

  /**
   * State key for the fingerprint map.
   */
  private const FINGERPRINT_STATE_KEY = 'canvas_ai_scoping.context_fingerprints';

  /**
   * State key for the strip-during-edits list.
   */
  private const STRIP_IDS_STATE_KEY = 'canvas_ai_scoping.strip_during_edits';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Gets the fingerprint map for items marked as "strip during edits."
   *
   * @return array<string, string>
   *   Map of {fingerprint => human-readable label} for items to strip.
   *   Used by ContextScopingSubscriber.
   */
  public function getStripFingerprints(): array {
    $stripIds = $this->getStripIds();
    if (empty($stripIds)) {
      return [];
    }

    $fingerprints = $this->state->get(self::FINGERPRINT_STATE_KEY, []);
    $result = [];
    foreach ($stripIds as $id) {
      if (isset($fingerprints[$id])) {
        $result[$fingerprints[$id]['fingerprint']] = $fingerprints[$id]['label'];
      }
    }
    return $result;
  }

  /**
   * Gets the list of ai_context_item IDs to strip during edits.
   *
   * @return int[]
   *   Entity IDs.
   */
  public function getStripIds(): array {
    return $this->state->get(self::STRIP_IDS_STATE_KEY, []);
  }

  /**
   * Sets which ai_context_item IDs should be stripped during edits.
   *
   * @param int[] $ids
   *   Entity IDs to strip.
   */
  public function setStripIds(array $ids): void {
    $this->state->set(self::STRIP_IDS_STATE_KEY, array_values(array_map('intval', $ids)));
    $this->logger->notice('ContextEditScope: updated strip list to @ids', [
      '@ids' => implode(', ', $ids),
    ]);
  }

  /**
   * Regenerates fingerprints for all ai_context_item entities.
   *
   * Called on entity save and can be triggered manually via drush.
   */
  public function regenerateFingerprints(): void {
    $storage = $this->entityTypeManager->getStorage('ai_context_item');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $items = $storage->loadMultiple($ids);

    $fingerprints = [];
    foreach ($items as $item) {
      $content = $item->get('content')->value ?? '';
      $fingerprint = $this->extractFingerprint($content);
      if ($fingerprint !== NULL) {
        $fingerprints[(int) $item->id()] = [
          'label' => $item->label(),
          'fingerprint' => $fingerprint,
        ];
      }
    }

    $this->state->set(self::FINGERPRINT_STATE_KEY, $fingerprints);
    $this->logger->info('ContextEditScope: regenerated @count fingerprints', [
      '@count' => count($fingerprints),
    ]);
  }

  /**
   * Regenerates the fingerprint for a single entity.
   *
   * @param int $entityId
   *   The ai_context_item entity ID.
   */
  public function updateFingerprint(int $entityId): void {
    $storage = $this->entityTypeManager->getStorage('ai_context_item');
    $item = $storage->load($entityId);
    if ($item === NULL) {
      return;
    }

    $content = $item->get('content')->value ?? '';
    $fingerprint = $this->extractFingerprint($content);

    $fingerprints = $this->state->get(self::FINGERPRINT_STATE_KEY, []);
    if ($fingerprint !== NULL) {
      $fingerprints[$entityId] = [
        'label' => $item->label(),
        'fingerprint' => $fingerprint,
      ];
    }
    else {
      unset($fingerprints[$entityId]);
    }
    $this->state->set(self::FINGERPRINT_STATE_KEY, $fingerprints);
  }

  /**
   * Lists all ai_context_item entities with their fingerprints and strip status.
   *
   * @return array<int, array{label: string, fingerprint: string|null, strip: bool}>
   *   Keyed by entity ID.
   */
  public function listItems(): array {
    $storage = $this->entityTypeManager->getStorage('ai_context_item');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $items = $storage->loadMultiple($ids);

    $fingerprints = $this->state->get(self::FINGERPRINT_STATE_KEY, []);
    $stripIds = $this->getStripIds();

    $result = [];
    foreach ($items as $item) {
      $id = (int) $item->id();
      $result[$id] = [
        'label' => $item->label(),
        'fingerprint' => $fingerprints[$id]['fingerprint'] ?? NULL,
        'strip' => in_array($id, $stripIds, TRUE),
      ];
    }
    return $result;
  }

  /**
   * Extracts a stable fingerprint from ai_context content.
   *
   * Strategy: use the first markdown heading (# ...) if present,
   * otherwise the first non-empty, non-frontmatter line of 20+ chars.
   * The fingerprint must be unique enough to identify this item
   * within the rendered system prompt.
   *
   * @param string $content
   *   The raw content from the entity.
   *
   * @return string|null
   *   A fingerprint string, or NULL if none could be extracted.
   */
  private function extractFingerprint(string $content): ?string {
    $lines = explode("\n", $content);
    $inFrontmatter = FALSE;

    foreach ($lines as $line) {
      $trimmed = trim($line);

      // Skip YAML frontmatter blocks.
      if ($trimmed === '---') {
        $inFrontmatter = !$inFrontmatter;
        continue;
      }
      if ($inFrontmatter) {
        continue;
      }

      // Use the first markdown heading.
      if (preg_match('/^#{1,3}\s+(.{10,})$/', $trimmed, $matches)) {
        return trim($matches[1]);
      }

      // Fallback: first substantial non-heading line.
      if (mb_strlen($trimmed) >= 20 && !str_starts_with($trimmed, 'purpose:')) {
        return mb_substr($trimmed, 0, 80);
      }
    }

    return NULL;
  }

}
