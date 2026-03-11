<?php

namespace Drupal\ai_google_analytics\Controller;

use Drupal\canvas\Entity\Page;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;

class GoogleAnalyticsReviewController extends ControllerBase
{

  public function content()
  {
    // Load the context data.
    $context_data = \Drupal::state()->get('ai_google_analytics.context_data', []);
    // Create a table to display the context data.
    $header = [
      'title' => $this->t('Page'),
      'summary' => $this->t('Summary'),
      'link' => '',
    ];

    $rows = [];
    foreach ($context_data as $id => $data) {
      $page = Page::load($id);
      if ($page) {
        $rows[] = [
          'title' => Link::fromTextAndUrl($page->label(), $page->toUrl()),
          'summary' => $data['summary'],
          'link' => Link::createFromRoute($this->t('Work on it'), 'canvas.boot.entity', [
            'entity_type' => 'canvas_page',
            'entity' => $page->id(),
          ],
            [
              'query' => [
                'ai_message' => $data['summary'],
              ],
              'attributes' => [
                'target' => '_blank',
              ],
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
