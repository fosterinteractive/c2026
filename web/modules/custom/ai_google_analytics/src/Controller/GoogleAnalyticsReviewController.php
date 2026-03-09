<?php

namespace Drupal\ai_google_analytics\Controller;

use Drupal\canvas\Entity\Page;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;

class GoogleAnalyticsReviewController extends ControllerBase {

  public function content() {
    // Load the context data.
    $context_data = \Drupal::state()->get('ai_google_analytics.context_data', []);
    // Create a table to display the context data.
    $header = [
      'title' => $this->t('Page'),
      'summary' => $this->t('Summary'),
      'recommendations' => $this->t('Recommendations'),
      'link' => '',
    ];

    $rows = [];
    foreach ($context_data as $data) {
      /** @var \Drupal\path_alias\AliasManagerInterface $alias_manager */
      $alias_manager = \Drupal::service('path_alias.manager');

      // Convert alias to internal path.
      $internal_path = $alias_manager->getPathByAlias($data['url']);

      // Example result: '/page/12'
      if (preg_match('#^/page/(\d+)$#', $internal_path, $matches)) {
        $id = $matches[1];
        $page = Page::load($id);
        $rows[] = [
          'title' => Link::fromTextAndUrl($page->label(), $page->toUrl()),
          'summary' => $data['summary'],
          'recommendations' => $data['recommendations'],
          'link' => Link::createFromRoute($this->t('Work on it'), 'canvas.boot.entity', [
            'entity_type' => 'canvas_page',
            'entity' => $page->id(),
          ])->toString(),
        ];
      }

    }

    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No content found.'),
      '#cache' => ['max-age' => 0],
    ];
    return $build;
  }

}
