<?php

namespace Drupal\exo;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Form\ConfigFormBaseTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;

/**
 * Defines a base settings implementation that other modules can use.
 */
abstract class ExoSettingsBase implements ExoSettingsInterface {
  use StringTranslationTrait;
  use ConfigFormBaseTrait;

  /**
   * The config factory.
   *
   * Subclasses should use the self::config() method, which may be overridden to
   * address specific needs when loading config, rather than this property
   * directly. See \Drupal\Core\Form\ConfigFormBase::config() for an example of
   * this.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * The settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * System-provided settings presets.
   *
   * @var array
   */
  protected $presets;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigName() {
    return $this->getModuleId() . '.settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      $this->getEditableConfigName(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfig() {
    return $this->config($this->getEditableConfigName());
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings() {
    if (!isset($this->defaultSettings)) {
      $file = \Drupal::service('extension.list.module')->getPath($this->getModuleId()) . '/config/install/' . $this->getEditableConfigName() . '.yml';
      $this->defaultSettings = [];
      if (file_exists($file)) {
        $array1 = Yaml::decode(file_get_contents($file));
        $this->defaultSettings = $array1;
      }
    }
    return $this->defaultSettings;
  }

  /**
   * An array of setting keys to exclude from diff comparisons.
   *
   * @return array
   *   An array of setting keys.
   */
  public function getDiffExcludes() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSiteSettings() {
    if (!isset($this->siteSettings)) {
      $this->siteSettings = $this->getConfig()->get();
      unset($this->siteSettings['_core']);
      $hook = $this->getModuleId() . '_site_settings';
      \Drupal::moduleHandler()->alter($hook, $this->siteSettings);
    }
    return $this->siteSettings;
  }

  /**
   * Prepare settings for use.
   *
   * @param array $settings
   *   An array of setting.
   * @param string $context
   *   The setting level. Either site or local.
   */
  public function prepareSettings(array $settings, $context) {
    return $settings;
  }

  /**
   * Create a local instance of settings.
   *
   * @param array $settings
   *   An array of local settings.
   * @param string $id
   *   An optional instance id. Useful when showing multiple instances of the
   *   same type on the same page.
   *
   * @return \Drupal\exo\ExoSettingsInstanceInterface
   *   An instance of the local settings.
   */
  public function createInstance(array $settings, $id = NULL) {
    return new ExoSettingsInstance($this, $settings, $id);
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings) {
    $this->settings = $settings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($key, $value) {
    $settings = $this->getSettings();
    NestedArray::setValue($settings, (array) $key, $value);
    $this->setSettings($settings);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    if (!isset($this->settings)) {
      $this->settings = NestedArray::mergeDeep($this->getDefaultSettings(), $this->mergePresets($this->getSiteSettings()));
    }
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key = '') {
    $settings = $this->getSettings();
    $value = &NestedArray::getValue($settings, (array) $key);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormId() {
    return Html::getId($this->getEditableConfigName() . '-form');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $element_id = $this->getFormId();
    $form['#id'] = $element_id;
    $presets = $this->getPresetOptions();
    if (!empty($presets)) {
      $preset = $this->getSetting('exo_preset');
      if (($trigger = $form_state->getTriggeringElement()) && !empty($trigger['#exo_preset_select'])) {
        $complete_form_state = method_exists($form_state, 'getCompleteFormState') ? $form_state->getCompleteFormState() : $form_state;
        $preset = $complete_form_state->getValue($trigger['#parents']);
      }
      $presets = ['_none' => '- None -'] + $presets;
      $form['exo_preset'] = [
        '#type' => 'select',
        '#title' => $this->t('Presets'),
        '#exo_preset_select' => TRUE,
        '#default_value' => $preset,
        '#options' => $presets,
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxPreset'],
          'event' => 'change',
          'wrapper' => $element_id,
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Loading Preset'),
          ],
        ],
      ];
      // This is set as a hidden field to avoid '#process conflicts where AJAX
      // does not run.
      $form['exo_preset_value'] = [
        '#type' => 'value',
        '#exo_settings_preset' => $this->getPreset($preset),
        '#process' => [[get_class($this), 'processElementWithPresets']],
      ];
    }
    return $form;
  }

  /**
   * After build callback.
   */
  public static function processElementWithPresets(&$element, FormStateInterface $form_state, &$complete_form) {
    $form = NestedArray::getValue($complete_form, array_slice($element['#array_parents'], 0, -1));
    self::disablePresetFields($form, $element['#exo_settings_preset']);
    NestedArray::setValue($complete_form, array_slice($element['#array_parents'], 0, -1), $form);
    return $element;
  }

  /**
   * Disable preset fields.
   */
  protected static function disablePresetFields(&$element, $preset) {
    if (!empty($preset)) {
      foreach ($preset as $key => $value) {
        if (isset($element[$key])) {
          if (is_array($value)) {
            self::disablePresetFields($element[$key], $value);
          }
          else {
            $element[$key]['#required'] = FALSE;
            $element[$key]['#disabled'] = TRUE;
            $element[$key]['#value'] = $value;
            $element[$key]['#default_value'] = $value;
          }
        }
        else {
          if (isset($element['#type']) && in_array($element['#type'], [
            'container',
            'fieldset',
            'details',
          ])) {
            foreach (Element::children($element) as $key) {
              self::disablePresetFields($element[$key], $preset);
            }
          }
        }
      }
    }
  }

  /**
   * AJAX function to get display IDs for a particular View.
   */
  public static function ajaxPreset(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    // Go one level up in the form, to the widgets container.
    return NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -1));
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $form_state) {
    $values = $this->massageFormValues($form_state);
    $form_state->setValues($values);
    if (!$form_state->get('exo_settings_is_local')) {
      $this->saveSettings($values);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (!empty($values['exo_default'])) {
      $values = [
        'exo_default' => 1,
      ];
    }
    if (!empty($values['exo_preset'])) {
      unset($values['exo_preset_value']);
      if ($values['exo_preset'] == '_none') {
        unset($values['exo_preset']);
      }
      else {
        $values = $this->diffDeep($this->mergePresets($values), $this->getPreset($values['exo_preset']));
      }
    }
    if ($form_state->get('exo_settings_is_local')) {
      $values = $this->diffDeep($values, $this->getSiteSettings() + $this->getDefaultSettings());
    }
    else {
      $values = $this->diffDeep($values, $this->getDefaultSettings());
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function saveSettings(array $settings) {
    $this->getConfig()->setData($settings)->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $this->getConfig()->setData($this->getSettings())->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function resetSettings() {
    $this->getConfig()->setData($this->getDefaultSettings())->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPresets() {
    if (!isset($this->presets)) {
      $this->presets = exo_presets($this->getModuleId());
    }
    return $this->presets;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreset($preset_id) {
    $presets = $this->getPresets();
    return $presets[$preset_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPresetOptions() {
    return array_map(function ($item) {
      return $item['label'] ?? '- Undefined Label -';
    }, $this->getPresets());
  }

  /**
   * {@inheritdoc}
   */
  public function mergePresets($settings) {
    if (!empty($settings['exo_preset'])) {
      $settings = NestedArray::mergeDeep($this->getPreset($settings['exo_preset']), $settings);
      unset($settings['label']);
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSiteSettingsDiff() {
    return $this->diffDeep($this->getSiteSettings(), $this->getDefaultSettings());
  }

  /**
   * Compare a settings array to another and return that which share keys.
   *
   * @param array $array1
   *   First options array.
   * @param array $array2
   *   Second options array.
   *
   * @return array
   *   The settings containing only the results that share keys.
   */
  public function intersectKeyDeep(array $array1, array $array2) {
    $array1 = array_intersect_key($array1, $array2);
    foreach ($array1 as $key => $array) {
      if (is_array($array1[$key]) && is_array($array2[$key])) {
        $array1[$key] = $this->intersectKeyDeep($array1[$key], $array2[$key]);
      }
    }
    return $array1;
  }

  /**
   * Compare a settings array to another and return that which differ in keys.
   *
   * @param array $array1
   *   First options array.
   * @param array $array2
   *   Second options array.
   *
   * @return array
   *   The settings containing only the results that share keys.
   */
  public function diffKeyDeep(array $array1, array $array2) {
    $array1 = array_diff_key($array1, $array2);
    foreach ($array1 as $key => $array) {
      if (is_array($array1[$key]) && is_array($array2[$key])) {
        $array1[$key] = $this->diffKeyDeep($array1[$key], $array2[$key]);
      }
    }
    return $array1;
  }

  /**
   * Compare a settings array to another and return that which differs.
   *
   * @param array $array1
   *   First options array.
   * @param array $array2
   *   Second options array.
   *
   * @return array
   *   The settings containing only the results that differ.
   */
  public function diffDeep(array $array1, array $array2) {
    $result = [];
    foreach ($array1 as $key => $value) {
      if (array_key_exists($key, $array2)) {
        if (is_array($value) && is_array($array2[$key])) {
          $aRecursiveDiff = $this->diffDeep($value, $array2[$key]);
          if (count($aRecursiveDiff)) {
            $result[$key] = $aRecursiveDiff;
          }
        }
        else {
          if ($value != $array2[$key] || is_null($value) != is_null($array2[$key]) || gettype($value) !== gettype($array2[$key])) {
            $result[$key] = $value;
          }
        }
      }
      else {
        $result[$key] = $value;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function selectRemoveEmpty(array &$element, FormStateInterface $form_state) {
    $form_state->setValueForElement($element, array_filter($form_state->getValue($element['#parents'])));
  }

  /**
   * {@inheritdoc}
   */
  public static function processParents(&$element, FormStateInterface $form_state, &$complete_form) {
    array_pop($element['#parents']);
    return $element;
  }

}
