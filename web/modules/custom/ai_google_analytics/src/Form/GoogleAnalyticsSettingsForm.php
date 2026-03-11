<?php

namespace Drupal\ai_google_analytics\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class GoogleAnalyticsSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['ai_google_analytics.settings'];
  }

  public function getFormId(): string {
    return 'ai_google_analytics_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ai_google_analytics.settings');

    $form['property_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GA4 Property ID'),
      '#default_value' => $config->get('property_id'),
      '#description' => $this->t('The numeric GA4 property ID.'),
      '#required' => TRUE,
    ];

    $form['credentials_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Service Account Credentials JSON'),
      '#upload_location' => 'public://',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'json'],
        'FileSizeLimit' => ['fileLimit' => 1024 * 1024],
      ],
      '#default_value' => $config->get('credentials_fid') ? [$config->get('credentials_fid')] : [],
      '#description' => $this->t('Upload the Google Analytics service account credentials JSON file.'),
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

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('ai_google_analytics.settings');

    $config->set('property_id', $form_state->getValue('property_id'));

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
