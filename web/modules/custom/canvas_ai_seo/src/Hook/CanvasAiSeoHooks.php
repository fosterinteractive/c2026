<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_seo\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\Entity\Page;

/**
 * Hook implementations for canvas_ai_seo module.
 */
final class CanvasAiSeoHooks {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    if ($entity_type->id() === Page::ENTITY_TYPE_ID && $this->moduleHandler->moduleExists('metatag')) {
      $fields['schema_jsonld'] = BaseFieldDefinition::create('string_long')
        ->setLabel(new TranslatableMarkup('Schema.org JSON-LD'))
        ->setDescription(new TranslatableMarkup('AI-generated Schema.org JSON-LD structured data.'))
        ->setTranslatable(TRUE)
        ->setRevisionable(TRUE)
        ->setDisplayOptions('form', [
          'type' => 'string_textarea',
          'settings' => ['rows' => 4],
          'weight' => 100,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setInternal(TRUE)
        ->setProvider('canvas_ai_seo');
    }
    return $fields;
  }

  /**
   * Implements hook_metatags_attachments_alter().
   */
  #[Hook('metatags_attachments_alter')]
  public function metatagAttachmentsAlter(array &$metatag_attachments): void {
    $entity = metatag_get_route_entity();
    if (!$entity instanceof Page) {
      return;
    }

    $jsonld = $entity->get('schema_jsonld')->value;
    if (empty($jsonld)) {
      return;
    }

    $metatag_attachments['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => $jsonld,
        '#attributes' => ['type' => 'application/ld+json'],
      ],
      'canvas_ai_seo_jsonld',
    ];
  }

}
