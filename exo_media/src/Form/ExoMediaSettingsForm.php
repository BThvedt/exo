<?php

namespace Drupal\exo_media\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ExoMediaSettingsForm.
 */
class ExoMediaSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_media_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['import'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Re-import default config'),
    ];
    $form['import']['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand the risk'),
      '#description' => $this->t('This is a destructive change and will reset all views, entity browsers, media entity types, image styles, etc. associated with the eXo Media module.'),
    ];
    $form['import']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#submit' => ['::submitReimportForm'],
      '#states' => [
        'disabled' => [
          ':input[name*="confirm"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#access' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitReimportForm(array &$form, FormStateInterface $form_state) {
    \Drupal::moduleHandler()->loadInclude('install', 'exo_media', 'exo_media');
    exo_media_install();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
