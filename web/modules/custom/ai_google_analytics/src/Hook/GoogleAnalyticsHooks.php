<?php

declare(strict_types=1);

namespace Drupal\ai_google_analytics\Hook;

use Drupal\canvas\Entity\Page;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

class GoogleAnalyticsHooks
{

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];

    if ($entity_type->id() === Page::ENTITY_TYPE_ID) {
      $fields['monitor'] = BaseFieldDefinition::create('boolean')
        ->setLabel(new TranslatableMarkup('Monitor analytics for this page'))
        ->setDisplayOptions('form', [
          'type' => 'boolean_checkbox',
          'settings' => [
            'display_label' => TRUE,
          ],
        ])
        ->setDisplayConfigurable('form', TRUE);

      $fields['engaged_sessions'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Engaged sessions'))
        ->setSetting('max_length', 255)
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE);

      $fields['bounce_rate'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Bounce rate'))
        ->setSetting('max_length', 255)
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE);

      $fields['conversion_rate'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Conversion rate'))
        ->setSetting('max_length', 255)
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE);

    }

    return $fields;
  }

}
