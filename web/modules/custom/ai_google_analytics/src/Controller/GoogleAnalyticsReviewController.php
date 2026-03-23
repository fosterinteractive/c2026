<?php

namespace Drupal\ai_google_analytics\Controller;

use Drupal\canvas\Entity\Page;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityMalformedException;
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
      'link' => $this->t('Operations'),
    ];

    $rows = [];
    foreach ($context_data as $id => $data) {
      $page = Page::load($id);
      if ($page) {
        $ai_message_prefix = "This page is underperforming against its Google Analytics goals. A summary of the page's performance is below.\r\n\r\n";
        $ai_message_suffix = "\r\n\r\nReview the page layout and provide some suggestions to improve the failing metric(s).";

        try {
          $rows[] = [
            'title' => Link::fromTextAndUrl($page->label(), $page->toUrl()),
            'summary' => $data['summary'],
            'link' => Link::createFromRoute($this->t('Work on it'), 'canvas.boot.entity', [
              'entity_type' => 'canvas_page',
              'entity' => $page->id(),
            ],
              [
                'query' => [
                  'ai_message' => $ai_message_prefix . $data['summary'] . $ai_message_suffix,
                ],
                'attributes' => [
                  'target' => '_blank',
                  'class' => ['button', 'button--secondary'],
                ],
              ])->toString(),
          ];
        }
        catch (EntityMalformedException $e) {
          \Drupal::logger('ai_google_analytics')->error($e->getMessage());
          $rows = [];
        }
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
