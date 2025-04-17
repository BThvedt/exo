<?php

namespace Drupal\exo;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Component\Utility\NestedArray;

/**
 * Defines a base settings implementation that other modules can use.
 */
abstract class ExoSettingsPluginBase extends ExoSettingsBase implements ExoSettingsPluginInterface {

  /**
   * The plugin collection that holds the plugin for this object.
   *
   * @var \Drupal\exo\ExoSettingsPluginCollection
   */
  protected $pluginCollection;

  /**
   * System-provided settings presets.
   *
   * @var array
   */
  protected $pluginPresets;

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinitions() {
    return $this->getPluginManager()->getDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollection() {
    if (!$this->pluginCollection) {
      $settings = [];
      foreach ($this->getPluginDefinitions() as $plugin_id => $definition) {
        $settings[$plugin_id] = ['id' => $plugin_id];
      }
      $this->pluginCollection = new ExoSettingsPluginCollection($this->getPluginManager(), $settings);
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin($plugin_id) {
    return $this->getPluginCollection()->get($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings() {
    if (!isset($this->defaultSettings)) {
      parent::getDefaultSettings();
      foreach ($this->getPluginDefinitions() as $plugin_id => $definition) {
        $this->getPluginDefaultSettings($plugin_id);
      }
    }
    return $this->defaultSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefaultSettings($plugin_id) {
    $this->defaultSettings[$plugin_id] = [];
    $instance = $this->getPlugin($plugin_id);
    if ($instance instanceof ExoSettingsPluginWithSettingsInterface) {
      $this->defaultSettings[$plugin_id] = $instance->defaultConfiguration();
    }
    return $this->defaultSettings[$plugin_id];
  }

  /**
   * An array of setting keys to exclude from diff comparisons.
   *
   * @return array
   *   An array of setting keys.
   */
  public function getPluginDiffExcludes($plugin_id) {
    $instance = $this->getPlugin($plugin_id);
    if ($instance instanceof ExoSettingsPluginWithSettingsInterface) {
      return $instance->diffExcludes();
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginSiteSettings($plugin_id) {
    $settings = $this->getSiteSettings();
    $settings = $settings[$plugin_id] ?? [];
    if (!empty($settings)) {
      // Plugin settings are empty until loading in site settings.
      $this->setPluginSettings($plugin_id, $settings);
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginSettings($plugin_id, array $settings) {
    $this->settings[$plugin_id] = $settings;
    $this->getPluginCollection()->setInstanceConfiguration($plugin_id, ['id' => $plugin_id] + $settings);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginSettings($plugin_id) {
    if (!isset($this->settings[$plugin_id])) {
      $this->settings[$plugin_id] = NestedArray::mergeDeep($this->getPluginDefaultSettings($plugin_id), $this->mergePluginPresets($plugin_id, $this->getPluginSiteSettings($plugin_id)));
    }
    return $this->settings[$plugin_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginSetting($plugin_id, $key = '') {
    $settings = $this->getPluginSettings($plugin_id);
    $value = &NestedArray::getValue($settings, (array) $key);
    return $value;
  }

  /**
   * Create a local instance of settings for a plugin.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param array $settings
   *   An array of local settings.
   *
   * @return \Drupal\exo\ExoSettingsInstanceInterface
   *   An instance of the local settings.
   */
  public function createPluginInstance($plugin_id, array $settings = []) {
    // Plugins provide their own site and default settings so we cannot rely
    // on the settings already stored here via getSiteSettings() and
    // getDefaultSettings().
    $site_settings = $this->getPluginSiteSettings($plugin_id);
    $default_settings = $this->getPluginDefaultSettings($plugin_id);
    return new ExoSettingsPluginInstance($this, $settings, $site_settings, $default_settings, $plugin_id);
  }

  /**
   * Create a local instance of settings with plugin selection..
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
  public function createPluginSelectInstance(array $settings = [], $id = NULL) {
    return new ExoSettingsPluginSelectInstance($this, $settings, $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginPresets($plugin_id) {
    if (!isset($this->pluginPresets[$plugin_id])) {
      $hook = $this->getModuleId() . '_' . $plugin_id . '_setting_presets';
      $module_handler = \Drupal::moduleHandler();
      $this->pluginPresets[$plugin_id] = $module_handler->invokeAll($hook, [$this->getEditableConfigName()]);
      $module_handler->alter($hook, $this->pluginPresets[$plugin_id]);
    }
    return $this->pluginPresets[$plugin_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginPreset($plugin_id, $preset_id) {
    $presets = $this->getPluginPresets($plugin_id);
    return $presets[$preset_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginPresetOptions($plugin_id) {
    return array_map(function ($item) {
      return $item['label'] ?? '- Undefined Label -';
    }, $this->getPluginPresets($plugin_id));
  }

  /**
   * {@inheritdoc}
   */
  public function mergePluginPresets($plugin_id, $settings) {
    if (!empty($settings['exo_preset'])) {
      $settings = NestedArray::mergeDeep($this->getPluginPreset($plugin_id, $settings['exo_preset']), $settings);
      unset($settings['label']);
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['plugins'] = [
      '#type' => 'vertical_tabs',
      '#parents' => ['plugins'],
    ];
    foreach ($this->getPluginDefinitions() as $plugin_id => $definition) {
      $this->getPluginSiteSettings($plugin_id);
      $form[$plugin_id] = [];
      $form[$plugin_id] = $this->buildPluginForm($plugin_id, $form[$plugin_id], $form_state);
      if (!empty($form[$plugin_id])) {
        $instance = $this->getPlugin($plugin_id);
        $form[$plugin_id] = [
          '#type' => 'details',
          '#title' => $instance->label(),
          '#group' => 'plugins',
          $plugin_id => $form[$plugin_id] + [
            '#process' => [[get_class(), 'processParents']],
            '#id' => $this->getFormId() . '-' . $plugin_id,
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPluginForm($plugin_id, array $form, FormStateInterface $form_state) {
    $instance = $this->getPlugin($plugin_id);
    $subform_state = SubformState::createForSubform($form, $form, $form_state);
    $form = $instance->buildConfigurationForm($form, $subform_state);
    if (!empty($form)) {
      $form = [
        '#type' => 'container',
        '#id' => $this->getFormId() . '-' . $plugin_id,
      ] + $this->buildPresetForm($plugin_id, $subform_state) + $form;
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPresetForm($plugin_id, FormStateInterface $form_state) {
    $form = [];
    $presets = $this->getPluginPresetOptions($plugin_id);
    if (!empty($presets)) {
      $preset = $this->getPluginSetting($plugin_id, 'exo_preset');
      if ($trigger = $form_state->getTriggeringElement()) {
        $complete_form_state = method_exists($form_state, 'getCompleteFormState') ? $form_state->getCompleteFormState() : $form_state;
        $preset = $complete_form_state->getValue($trigger['#parents']);
      }
      $presets = ['_none' => '- None -'] + $presets;
      $form['exo_preset'] = [
        '#type' => 'select',
        '#title' => $this->t('Presets'),
        '#default_value' => $preset,
        '#exo_settings_preset' => $this->getPluginPreset($plugin_id, $preset),
        '#options' => $presets,
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxPreset'],
          'event' => 'change',
          'wrapper' => $this->getFormId() . '-' . $plugin_id,
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
        '#exo_settings_preset' => $this->getPluginPreset($plugin_id, $preset),
        '#process' => [[get_class($this), 'processElementWithPresets']],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    foreach ($this->getPluginDefinitions() as $plugin_id => $definition) {
      $subform_state = SubformState::createForSubform($form[$plugin_id], $form, $form_state);
      $this->validatePluginForm($plugin_id, $form[$plugin_id], $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatePluginForm($plugin_id, array $form, FormStateInterface $form_state) {
    $this->getPlugin($plugin_id)->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $form_state) {
    foreach ($this->getPluginDefinitions() as $plugin_id => $definition) {
      $subform_state = SubformState::createForSubform($form[$plugin_id], $form, $form_state);
      $this->submitPluginForm($plugin_id, $form[$plugin_id], $subform_state);
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitPluginForm($plugin_id, array $form, FormStateInterface $form_state) {
    $values = $this->massagePluginFormValues($plugin_id, $form_state);
    $form_state->setValues($values);
    $this->getPlugin($plugin_id)->submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function resetSettings() {
    $settings = $this->getDefaultSettings();
    foreach ($this->getPluginDefinitions() as $plugin_id => $definition) {
      unset($settings[$plugin_id]);
    }
    $this->getConfig()->setData($settings)->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function massagePluginFormValues($plugin_id, FormStateInterface $form_state) {
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
        $values = $this->diffDeep($values, $this->getPluginPreset($plugin_id, $values['exo_preset']));
      }
    }
    if ($form_state->get('exo_settings_is_local')) {
      $values = $this->diffDeep($values, $this->getPluginSiteSettings($plugin_id));
    }
    else {
      $values = $this->diffDeep($values, $this->getPluginDefaultSettings($plugin_id));
    }
    return $values;
  }

}
