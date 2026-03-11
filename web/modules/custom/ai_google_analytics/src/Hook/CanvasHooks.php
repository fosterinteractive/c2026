<?php

declare(strict_types=1);

namespace Drupal\ai_google_analytics\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class CanvasHooks {

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension === 'canvas' && isset($libraries['canvas-ui'])) {
      $libraries['canvas-ui']['dependencies'][] = 'ai_google_analytics/canvas_ai_init';
    }
  }

}
