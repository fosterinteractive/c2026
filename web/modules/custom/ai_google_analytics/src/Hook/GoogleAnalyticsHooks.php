<?php

declare(strict_types=1);

namespace Drupal\ai_google_analytics\Hook;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\canvas\Entity\Page;
use Drupal\Core\Entity\EntityInterface;
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

  /**
   * Implements hook_canvas_page_presave().
   */
  #[Hook('canvas_page_presave')]
  public function canvasPagePresave(EntityInterface $entity): void {
    // Only check updates, not new entities.
    if ($entity->isNew() || !isset($entity->original)) {
      return;
    }

    // Define watched fields.
    $watched = ['engaged_sessions', 'bounce_rate', 'conversion_rate'];
    $changed = FALSE;

    foreach ($watched as $field) {
      $new = $entity->get($field)->value;
      $old = $entity->original->get($field)->value;
      if ($new !== $old) {
        // Field was changed!
        $changed = TRUE;
        break;
      }
    }

    if (!$changed) {
      return;
    }

    $text = 'Google Analytics data has changed for page ID ' . $entity->id() . ' (' . $entity->label() . ') has changed. Current data for the page is below.' . PHP_EOL;
    foreach ($watched as $field) {
      $text = $text . $field . ': ' . $entity->get($field)->value . PHP_EOL;
    }

    // Instantiate the agent.
    $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('analytics_monitoring_agent');

    // Set agent inputs.
    $input = new ChatInput([
      new ChatMessage('user', $text),
    ]);

    $agent->setChatInput($input);
    $agent->determineSolvability();
    $output = $agent->solve();

    // Parse JSON out of the text output.
    preg_match('/\{.*\}/s', $output, $matches);
    $json_string = $matches[0] ?? '';
    $json = json_decode($json_string, TRUE);

    if (isset($json['notify']) && $json['notify'] == TRUE) {
      // Benchmark failed, notify user.
      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'ai_google_analytics';
      $key = 'content_performance_report';
      $to = \Drupal::config('system.site')->get('mail');
      $params['subject'] = 'Underperforming Content Detected';
      $params['message'] = "<p>Your Analytics Monitoring Agent has identified opportunities to improve your website content.</p>";
      $params['message'] .= "<p>To view, review and publish suggested changes, please visit the <a href=\"" . \Drupal::request()->getSchemeAndHttpHost() . "/admin/content/ga-page-review\">AI Analytics Review</a> page.</p>";
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = TRUE;
      $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
      if ($result['result'] !== TRUE) {
        \Drupal::logger('ai_google_analytics')->error('There was a problem sending the content performance report to %email.', ['%email' => $to]);
      }
      else {
        \Drupal::logger('ai_google_analytics')->notice('Content performance report sent to %email.', ['%email' => $to]);
      }

      // Update state variable.
      // Replace and don't append for now, to avoid duplicate entries.
      // $context_data = \Drupal::state()->get('ai_google_analytics.context_data', []);
      $context_data = [];
      $context_data[$entity->id()] = [
        'summary' => $json['summary'],
      ];

      // Including for testing, to avoid duplicate entries.
      \Drupal::state()->delete('ai_google_analytics.context_data');

      \Drupal::state()->set('ai_google_analytics.context_data', $context_data);
    }
  }

}
