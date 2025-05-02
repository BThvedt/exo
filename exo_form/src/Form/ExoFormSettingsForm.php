<?php

namespace Drupal\exo_form\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\exo\ExoSettingsFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo\ExoSettingsInterface;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\Form\SubformState;

/**
 * Class ExoFormSettingsForm.
 */
class ExoFormSettingsForm extends ExoSettingsFormBase {

  /**
   * Drupal\Core\Extension\ThemeHandler definition.
   *
   * @var \Drupal\Core\Extension\ThemeHandler
   */
  protected $themeHandler;

  /**
   * Constructs a new ExoSettingsForm object.
   */
  public function __construct(
    ExoSettingsInterface $exo_settings,
    ThemeHandler $theme_handler,
  ) {
    parent::__construct($exo_settings);
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('exo_form.settings'),
      $container->get('theme_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $themes = $this->themeHandler->listInfo();
    uasort($themes, [ModuleExtensionList::class, 'sortByName']);
    $theme_options = array_filter($themes, function ($theme) {
      return empty($theme->info['hidden']);
    });

    $form['themes'] = [
      '#tree' => TRUE,
    ];

    $settings = $this->exoSettings->getSettings();
    foreach ($theme_options as $theme) {
      $theme_id = $theme->getName();
      $enabled = isset($settings['themes'][$theme_id]);
      $theme_settings = !empty($settings['themes'][$theme_id]) ? $settings['themes'][$theme_id] : $this->exoSettings->getSettings();
      $exo_settings_instance = $this->exoSettings->createInstance($theme_settings, $theme_id);
      $states = [
        'visible' => [
          ':input[name="enabled[' . $theme_id . ']"]' => ['checked' => TRUE],
        ],
      ];

      $form['themes'][$theme_id] = [
        '#type' => 'details',
        '#title' => $theme->info['name'],
        '#open' => $enabled,
      ];

      $form['themes'][$theme_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => $enabled,
        '#parents' => ['enabled', $theme_id],
      ];

      $form['themes'][$theme_id]['settings'] = [
        '#states' => $states,
        '#process' => [[get_class($this->exoSettings), 'processParents']],
      ];
      $subform_state = SubformState::createForSubform($form['themes'][$theme_id]['settings'], $form, $form_state);
      $form['themes'][$theme_id]['settings'] += [
        '#type' => 'fieldset',
        '#title' => $this->t('Default Settings'),
      ] + $exo_settings_instance->buildForm($form['themes'][$theme_id]['settings'], $subform_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Move instance settings into the global setting scope so that they get
    // saved.
    foreach (array_filter($form_state->getValue('enabled')) as $theme_id => $status) {
      $subform_state = SubformState::createForSubform($form['themes'][$theme_id]['settings'], $form, $form_state);
      $exo_settings_instance = $this->exoSettings->createInstance($subform_state->getValues(), $theme_id);
      $exo_settings_instance->validateForm($form['themes'][$theme_id]['settings'], $subform_state);
      $exo_settings_instance->submitForm($form['themes'][$theme_id]['settings'], $subform_state);
      $form_state->setValue(['settings', 'themes', $theme_id], $subform_state->getValues());
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    drupal_flush_all_caches();
  }

}
