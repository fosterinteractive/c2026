<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_seo\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures schema_jsonld[0][value] is always present in entity_form_fields.
 *
 * Canvas builds entity_form_fields from the Drupal form, but fields added by
 * other modules may return an empty string which the UI treats as "no value",
 * causing stale data from a previous page to persist. Injecting an empty value
 * here guarantees the UI always receives and applies the field.
 */
class LayoutResponseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => 'onResponse',
    ];
  }

  /**
   * Injects schema_jsonld[0][value] into entity_form_fields if absent.
   */
  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    if (!str_starts_with($request->getPathInfo(), '/canvas/api/v0/layout/canvas_page/')) {
      return;
    }

    $response = $event->getResponse();
    if (!$response instanceof JsonResponse) {
      return;
    }

    $data = json_decode($response->getContent(), TRUE);
    if (!isset($data['entity_form_fields'])) {
      return;
    }

    if (!array_key_exists('schema_jsonld[0][value]', $data['entity_form_fields'])) {
      $data['entity_form_fields']['schema_jsonld[0][value]'] = '';
      $response->setData($data);
    }
  }

}
