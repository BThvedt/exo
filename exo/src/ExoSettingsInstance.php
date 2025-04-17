<?php

namespace Drupal\exo;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Defines a settings local instance.
 */
class ExoSettingsInstance implements ExoSettingsInstanceInterface {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The unique instance id.
   *
   * Not required and only useful when having a single form with multiple
   * setting instances. Helps to resolve AJAX issues.
   *
   * @var string
   */
  protected $id;

  /**
   * The eXo settings service.
   *
   * @var \Drupal\exo\ExoSettingsInterface
   */
  protected $exoSettings;

  /**
   * The original settings of ExoSettings.
   *
   * @var array
   */
  protected $originalParentSettings;

  /**
   * The default settings.
   *
   * @var array
   */
  protected $defaultSettings;

  /**
   * The site settings.
   *
   * @var array
   */
  protected $siteSettings;

  /**
   * The local settings.
   *
   * @var array
   */
  protected $localSettings;

  /**
   * The site settings different than default settings.
   *
   * @var array
   */
  protected $siteSettingsDiff;

  /**
   * The local settings different than site settings.
   *
   * @var array
   */
  protected $localSettingsDiff;

  /**
   * The settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * Constructs a new ExoModal.
   */
  public function __construct(ExoSettingsInterface $exo_settings, $local_settings, $id = NULL) {
    $this->exoSettings = $exo_settings;
    $this->originalParentSettings = $exo_settings->getSettings();
    $this->localSettings = $this->mergePresets($local_settings);
    $this->siteSettings = $this->mergePresets($exo_settings->getSiteSettings());
    $this->defaultSettings = $exo_settings->getDefaultSettings();
    $this->id = $id ?: 'default';

    $hook = $this->exoSettings->getModuleId() . '_local_settings';
    $context = ['instance' => TRUE];
    \Drupal::moduleHandler()->alter($hook, $this->localSettings, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getExoSettings() {
    return $this->exoSettings;
  }

  /**
   * {@inheritdoc}
   */
  protected function mergePresets(array $settings) {
    return $this->exoSettings->mergePresets($settings);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings() {
    return $this->defaultSettings;
  }

  /**
   * An array of setting keys to exclude from diff comparisons.
   *
   * @return array
   *   An array of setting keys.
   */
  public function getDiffExcludes() {
    return $this->exoSettings->getDiffExcludes();
  }

  /**
   * {@inheritdoc}
   */
  public function getSiteSettings() {
    return $this->siteSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSiteSetting($key = '') {
    $settings = $this->getSiteSettings();
    $value = &NestedArray::getValue($settings, (array) $key);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalSettings() {
    return $this->localSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $values) {
    foreach ($values as $key => $value) {
      $this->setSetting($key, $value);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($key, $value) {
    // Make sure settings are correctly set before changing a value.
    // We change this setting in both the aggregate settings and the local
    // settings so that we make sure it is accessible both from get() and when
    // we extract just the local settings.
    $settings = $this->getSettings();
    $localSettings = $this->getLocalSettings();
    NestedArray::setValue($settings, (array) $key, $value, TRUE);
    NestedArray::setValue($localSettings, (array) $key, $value, TRUE);
    if ($key == 'exo_preset') {
      $settings = $this->exoSettings->mergePresets($settings);
      $localSettings = $this->exoSettings->mergePresets($localSettings);
    }
    $this->settings = $settings;
    $this->localSettings = $localSettings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key = '', $changed_only = FALSE) {
    if ($changed_only) {
      $settings = $this->getLocalSettingsDiff();
      $value = &NestedArray::getValue($settings, (array) $key);
    }
    else {
      $settings = $this->getSettings();
      $value = &NestedArray::getValue($settings, (array) $key);
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    if (!isset($this->settings)) {
      $this->settings = NestedArray::mergeDeep($this->getDefaultSettings(), $this->getSiteSettings(), $this->getLocalSettings());
    }
    return $this->settings;
  }

  /**
   * Return site settings not matching those in the default settings.
   *
   * @param bool $clean
   *   Clean settings against default settings returning the values of only
   *   those settings that exist as defaults.
   * @param bool $prepare
   *   Prepare for rendering will call alter hooks and allow the settings to be
   *   prepared for use. Should not be used when saving items. Only when using
   *   items.
   *
   * @return array
   *   An array containing only settings different than the default settings.
   */
  public function getSiteSettingsDiff($clean = TRUE, $prepare = TRUE) {
    if (!isset($this->siteSettingsDiff)) {
      $this->siteSettingsDiff = $this->exoSettings->diffDeep($this->getSiteSettings(), $this->getDefaultSettings());
    }
    $settings = $this->siteSettingsDiff;
    if ($clean) {
      $settings = array_diff_key($this->exoSettings->intersectKeyDeep($settings, $this->getDefaultSettings()), array_flip($this->getDiffExcludes()));
    }
    if ($prepare) {
      $this->exoSettings->prepareSettings($settings, 'local');
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalSettingsDiff($clean = TRUE, $prepare = TRUE) {
    if (!isset($this->localSettingsDiff)) {
      $this->localSettingsDiff = $this->exoSettings->diffDeep($this->getLocalSettings(), $this->getSiteSettingsDiff($clean) + $this->getDefaultSettings());
    }
    $settings = $this->localSettingsDiff;
    if ($clean) {
      $settings = array_diff_key($this->exoSettings->intersectKeyDeep($this->localSettingsDiff, $this->getDefaultSettings()), array_flip($this->getDiffExcludes()));
    }
    if ($prepare) {
      $settings = $this->exoSettings->prepareSettings($settings, 'local');
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $allow_defaults = TRUE) {
    $this->setState($form_state);
    if ($allow_defaults) {
      // We do not have this ID unique as the settings block can be refreshed
      // via ajax and will generate a new ID. This "may" be an issue.
      $id = Html::getUniqueId('exo-settings-' . $this->exoSettings->getModuleId() . '-' . $this->getId() . '-use-defaults');
      $form['exo_default'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use default settings'),
        '#default_value' => $this->getSetting('exo_default'),
        '#id' => $id,
      ];
      $form['settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Settings'),
        '#states' => [
          'invisible' => [
            '#' . $id => ['checked' => TRUE],
          ],
        ],
      ];
    }
    else {
      $form['settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Settings'),
      ];
    }
    // Need to find a better way to override ExoSettingsInterface settings
    // with local settings while preserving the usability of the base settings
    // class.
    $form['settings'] += $this->buildSettingsForm($form['settings'], $form_state);
    $form['settings']['#process'][] = [get_class($this->exoSettings), 'processParents'];
    $this->unsetState($form_state);
    return $form;
  }

  /**
   * Set temporary settings for use in parent settings handler.
   *
   * This should be set and unset for temporary setting switching while still
   * using the parent ExoSettings class for the majority of the form handling.
   *
   * @todo Find a better way to do this.
   */
  protected function setState(FormStateInterface $form_state) {
    $form_state->set('exo_settings_is_local', TRUE);
    $this->exoSettings->setSettings($this->getSettings());
  }

  /**
   * Unset temporary settings for use in parent settings handler.
   *
   * This should be used whenever setState is used to return the parent
   * ExoSettings object back to its initial values.
   *
   * @todo Find a better way to do this.
   */
  protected function unsetState(FormStateInterface $form_state) {
    $form_state->set('exo_settings_is_local', FALSE);
    $this->exoSettings->setSettings($this->originalParentSettings);
  }

  /**
   * Check if state is local.
   *
   * @return bool
   *   Returns TRUE if set.
   */
  protected function isLocalState(FormStateInterface $form_state) {
    return $form_state->get('exo_settings_is_local');
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSettingsForm(array $form, FormStateInterface $form_state) {
    $this->setState($form_state);
    $form = $this->exoSettings->buildForm($form, $form_state);
    $this->unsetState($form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->setState($form_state);
    $this->exoSettings->validateForm($form, $form_state);
    $this->unsetState($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $form_state) {
    $this->setState($form_state);
    $this->exoSettings->submitForm($form, $form_state);
    $this->unsetState($form_state);
  }

}
