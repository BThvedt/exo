<?php

namespace Drupal\exo;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

/**
 * Defines a base settings form that other modules can use.
 */
abstract class ExoSettingsFormBase extends FormBase {

  /**
   * Drupal\exo\ExoSettingsInterface definition.
   *
   * @var \Drupal\exo\ExoSettingsInterface
   */
  protected $exoSettings;

  /**
   * Constructs a new ExoSettingsForm object.
   */
  public function __construct(
    ExoSettingsInterface $exo_settings,
  ) {
    $this->exoSettings = $exo_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->exoSettings->getModuleId() . '_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    // Set parents due to SubformState issue.
    // @see https://www.drupal.org/project/drupal/issues/2798261
    $form['#parents'] = [];
    $form['settings'] = [
      '#parents' => ['settings'],
    ];
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $form['settings'] += [
      '#type' => 'fieldset',
      '#title' => $this->t('Default Settings'),
    ] + $this->exoSettings->buildForm($form['settings'], $subform_state);

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset configuration'),
      '#submit' => [
        [$this, 'resetForm'],
      ],
    ];

    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->exoSettings->validateForm($form['settings'], $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->addMessage($this->t('The configuration options have been saved.'));
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->exoSettings->submitForm($form['settings'], $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $this->exoSettings->resetSettings();
  }

}
