<?php

declare(strict_types=1);

namespace Drupal\ai_google_analytics\Hook;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_google_analytics\BenchmarkEvaluator;
use Drupal\canvas\Entity\Page;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for Google Analytics entity integration.
 *
 * Adds monitoring and metrics fields to Canvas page entities, and invokes the
 * analytics monitoring agent when GA data changes.
 */
class GoogleAnalyticsHooks {

  /**
   * Constructs a GoogleAnalyticsHooks instance.
   *
   * @param \Drupal\ai_google_analytics\BenchmarkEvaluator $benchmarkEvaluator
   *   The benchmark evaluator service.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $aiAgentManager
   *   The AI agent plugin manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    protected readonly BenchmarkEvaluator $benchmarkEvaluator,
    protected readonly PluginManagerInterface $aiAgentManager,
    protected readonly MailManagerInterface $mailManager,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    protected readonly StateInterface $state,
    protected readonly RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_entity_base_field_info().
   *
   * Adds monitoring toggle and GA metric fields to Canvas page entities.
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
        ->setDisplayConfigurable('form', TRUE)
        ->setInternal(TRUE)
        ->setProvider('ai_google_analytics');

      $fields['engaged_sessions'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Engaged sessions'))
        ->setSetting('max_length', 255)
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setInternal(TRUE)
        ->setProvider('ai_google_analytics');

      $fields['bounce_rate'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Bounce rate'))
        ->setSetting('max_length', 255)
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setInternal(TRUE)
        ->setProvider('ai_google_analytics');

      $fields['key_event_rate'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Key event rate'))
        ->setSetting('max_length', 255)
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setInternal(TRUE)
        ->setProvider('ai_google_analytics');

      // Per-page benchmark overrides. NULL = use global default.
      $fields['benchmark_engaged_sessions_min'] = BaseFieldDefinition::create('float')
        ->setLabel(new TranslatableMarkup('Minimum engaged sessions (override)'))
        ->setDescription(new TranslatableMarkup('Leave blank to use the global default.'))
        ->setDisplayOptions('form', [
          'type' => 'number',
          'weight' => 20,
          'settings' => [
            'min' => 0,
          ],
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setInternal(TRUE)
        ->setProvider('ai_google_analytics');

      $fields['benchmark_bounce_rate_max'] = BaseFieldDefinition::create('float')
        ->setLabel(new TranslatableMarkup('Maximum bounce rate % (override)'))
        ->setDescription(new TranslatableMarkup('Leave blank to use the global default.'))
        ->setDisplayOptions('form', [
          'type' => 'number',
          'weight' => 21,
          'settings' => [
            'min' => 0,
            'max' => 100,
          ],
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setInternal(TRUE)
        ->setProvider('ai_google_analytics');

      $fields['benchmark_key_event_rate_min'] = BaseFieldDefinition::create('float')
        ->setLabel(new TranslatableMarkup('Minimum key event rate % (override)'))
        ->setDescription(new TranslatableMarkup('Leave blank to use the global default.'))
        ->setDisplayOptions('form', [
          'type' => 'number',
          'weight' => 22,
          'settings' => [
            'min' => 0,
            'max' => 100,
          ],
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setInternal(TRUE)
        ->setProvider('ai_google_analytics');
    }

    return $fields;
  }

  /**
   * Implements hook_canvas_page_presave().
   *
   * When GA metrics change on a Canvas page, evaluates performance against
   * benchmark thresholds deterministically. If any benchmark fails, invokes the
   * AI agent to generate a human-readable summary and notifies the admin.
   * If all benchmarks pass, clears any existing flagged state.
   */
  #[Hook('canvas_page_presave')]
  public function canvasPagePresave(EntityInterface $entity): void {
    if ($entity->isNew() || !isset($entity->original)) {
      return;
    }

    $watched = ['engaged_sessions', 'bounce_rate', 'key_event_rate'];
    $changed = FALSE;

    foreach ($watched as $field) {
      if ($entity->get($field)->value !== $entity->original->get($field)->value) {
        $changed = TRUE;
        break;
      }
    }

    if (!$changed) {
      return;
    }

    $logger = $this->loggerFactory->get('ai_google_analytics');
    $result = $this->benchmarkEvaluator->evaluate($entity);

    // If all benchmarks pass, clear any existing flagged state and return.
    if ($result['passed']) {
      $context_data = $this->state->get('ai_google_analytics.context_data', []);
      if (isset($context_data[$entity->id()])) {
        unset($context_data[$entity->id()]);
        $this->state->set('ai_google_analytics.context_data', $context_data);
        $logger->notice('Page %label now meets all benchmarks; cleared from review queue.', [
          '%label' => $entity->label(),
        ]);
      }
      return;
    }

    // Benchmarks failed — call the AI agent for a human-readable summary.
    $failures_text = implode(PHP_EOL, $result['failures']);
    $text = 'Page "' . $entity->label() . '" (ID ' . $entity->id() . ') has failed the following analytics benchmarks:' . PHP_EOL . PHP_EOL
      . $failures_text . PHP_EOL . PHP_EOL
      . 'Please provide a brief summary of the performance issues and actionable recommendations to improve the failing metrics.';

    $summary = '';
    try {
      $agent = $this->aiAgentManager->createInstance('analytics_monitoring_agent');
      $input = new ChatInput([
        new ChatMessage('user', $text),
      ]);
      $agent->setChatInput($input);
      $agent->determineSolvability();
      $output = $agent->solve();

      $json = json_decode($output, TRUE);
      if (is_array($json) && isset($json['summary'])) {
        $summary = $json['summary'];
        if (!empty($json['recommendations'])) {
          $summary .= PHP_EOL . PHP_EOL . $json['recommendations'];
        }
      }
      else {
        // Fallback: use the raw output as summary text.
        $summary = is_string($output) ? $output : $failures_text;
      }
    }
    catch (\Throwable $e) {
      $logger->error('AI agent failed for page %label: @message', [
        '%label' => $entity->label(),
        '@message' => $e->getMessage(),
      ]);
      // Use the deterministic failure descriptions as the summary.
      $summary = $failures_text;
    }

    // Update flagged state.
    $context_data = $this->state->get('ai_google_analytics.context_data', []);
    $context_data[$entity->id()] = [
      'summary' => $summary,
    ];
    $this->state->set('ai_google_analytics.context_data', $context_data);

    // Send notification email.
    $to = $this->configFactory->get('system.site')->get('mail');
    $request = $this->requestStack->getCurrentRequest();
    $base_url = $request ? $request->getSchemeAndHttpHost() : '';
    $params = [
      'subject' => 'Underperforming Content Detected',
      'message' => '<p>Your Analytics Monitoring Agent has identified content that does not meet your analytics goals.</p>'
        . '<p>For details, please visit the <a href="' . $base_url . '/admin/content/ga-page-review">AI Analytics Review</a> page.</p>',
    ];
    $langcode = $this->currentUser->getPreferredLangcode();
    $mail_result = $this->mailManager->mail('ai_google_analytics', 'content_performance_report', $to, $langcode, $params);

    if ($mail_result['result'] !== TRUE) {
      $logger->error('There was a problem sending the content performance report to %email.', ['%email' => $to]);
    }
    else {
      $logger->notice('Content performance report sent to %email.', ['%email' => $to]);
    }
  }

  /**
   * Implements hook_entity_delete().
   *
   * Cleans up flagged analytics state when a Canvas page is deleted.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (!$entity instanceof Page) {
      return;
    }

    $context_data = $this->state->get('ai_google_analytics.context_data', []);
    if (isset($context_data[$entity->id()])) {
      unset($context_data[$entity->id()]);
      $this->state->set('ai_google_analytics.context_data', $context_data);
    }
  }

}
