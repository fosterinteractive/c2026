<?php

declare(strict_types=1);

namespace Drupal\ai_google_analytics\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Settings form for Google Analytics API credentials.
 */
class GoogleAnalyticsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ai_google_analytics.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_google_analytics_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ai_google_analytics.settings');

    $form['property_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GA4 Property ID'),
      '#default_value' => $config->get('property_id'),
      '#description' => $this->t('The numeric GA4 property ID.'),
      '#required' => TRUE,
    ];

    $form['benchmarks'] = [
      '#type' => 'details',
      '#title' => $this->t('Benchmark Thresholds'),
      '#description' => $this->t('Global defaults for analytics benchmarks. Individual pages can override these values.'),
      '#open' => TRUE,
    ];

    $form['benchmarks']['engaged_sessions_min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum engaged sessions'),
      '#default_value' => $config->get('benchmarks.engaged_sessions_min'),
      '#min' => 0,
      '#step' => 'any',
      '#required' => TRUE,
    ];

    $form['benchmarks']['bounce_rate_max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum bounce rate (%)'),
      '#default_value' => $config->get('benchmarks.bounce_rate_max'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 'any',
      '#required' => TRUE,
    ];

    $form['benchmarks']['key_event_rate_min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum key event rate (%)'),
      '#default_value' => $config->get('benchmarks.key_event_rate_min'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 'any',
      '#required' => TRUE,
    ];

    $form['credentials_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Service Account Credentials JSON'),
      '#upload_location' => 'private://',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'json'],
        'FileSizeLimit' => ['fileLimit' => 1024 * 1024],
      ],
      '#default_value' => $config->get('credentials_fid') ? [$config->get('credentials_fid')] : [],
      '#description' => $this->t('Upload the Google Analytics service account credentials JSON file. Stored in the private file system.'),
    ];

    $credentials_uri = $config->get('credentials_uri');
    if ($credentials_uri) {
      $form['credentials_current'] = [
        '#type' => 'item',
        '#title' => $this->t('Current credentials file'),
        '#markup' => '<code>' . $credentials_uri . '</code>',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('ai_google_analytics.settings');

    $config->set('property_id', $form_state->getValue('property_id'));
    $config->set('benchmarks.engaged_sessions_min', (float) $form_state->getValue('engaged_sessions_min'));
    $config->set('benchmarks.bounce_rate_max', (float) $form_state->getValue('bounce_rate_max'));
    $config->set('benchmarks.key_event_rate_min', (float) $form_state->getValue('key_event_rate_min'));

    $fids = $form_state->getValue('credentials_file');
    if (!empty($fids)) {
      $fid = reset($fids);
      $old_fid = $config->get('credentials_fid');

      if ($old_fid && $old_fid !== $fid) {
        $old_file = File::load($old_fid);
        if ($old_file) {
          $old_file->delete();
        }
      }

      $file = File::load($fid);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $config->set('credentials_fid', $fid);
        $config->set('credentials_uri', $file->getFileUri());
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
