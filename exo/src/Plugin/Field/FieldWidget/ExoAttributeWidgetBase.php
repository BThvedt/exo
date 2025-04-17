<?php

namespace Drupal\exo\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\Core\Render\Markup;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Provides a base for attribute widgets.
 */
abstract class ExoAttributeWidgetBase extends WidgetBase implements ExoAttributeWidgetInterface {
  use ExoIconTranslationTrait;

  /**
   * Get options.
   *
   * @return array
   *   An array of options.
   */
  abstract public static function defaultOptions();

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'empty_option' => 'Auto',
      'enabled_options' => [],
      // inline, grid, grid-compact, stacked.
      'exo_style' => 'inline',
    ] + parent::defaultSettings();
  }

  /**
   * Get the default options.
   *
   * @return array
   *   An array of options.
   */
  public function getDefaultOptions() {
    return static::defaultOptions();
  }

  /**
   * Returns whether the widget handles multiple values.
   *
   * @return bool
   *   TRUE if a single copy of formElement() can handle multiple field values,
   *   FALSE if multiple values require separate copies of formElement().
   */
  protected function handlesMultipleValues() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['empty_option'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty Label'),
      '#default_value' => $this->getSetting('empty_option'),
      '#required' => TRUE,
    ];
    $form['enabled_options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled Options'),
      '#description' => $this->t('If no options are selected, then all options are enabled.'),
      '#options' => $this->defaultOptions(),
      '#default_value' => $this->getSetting('enabled_options'),
      '#element_validate' => [[get_class($this), 'enabledOptionsValidate']],
    ];
    return $form;
  }

  /**
   * Form element validation handler; Cleans enabled options.
   */
  public static function enabledOptionsValidate(array $element, FormStateInterface $form_state) {
    $value = $form_state->getValue($element['#parents']);
    $form_state->setValueForElement($element, array_filter($value));
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = t('Empty label: %empty_option', ['%empty_option' => $this->getSetting('empty_option')]);
    $enabled_options = $this->getSetting('enabled_options');
    if (!empty($enabled_options)) {
      $enabled_options = Markup::create(implode(', ', array_intersect_key($this->defaultOptions(), $enabled_options)));
    }
    else {
      $enabled_options = $this->t('All');
    }
    $summary[] = t('Enabled options: @enabled_options', ['@enabled_options' => $enabled_options]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $options = $this->defaultOptions();
    $enabled_options = $this->getSetting('enabled_options');
    $supports_multiple = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality() != 1;
    $value = $items[$delta]->value ?? '';
    if ($supports_multiple) {
      $value = explode('|', $value);
    }
    if (!empty($enabled_options)) {
      $options = array_intersect_key($options, $enabled_options);
    }
    if (!$supports_multiple && !$this->fieldDefinition->isRequired()) {
      $options = ['' => $this->getSetting('empty_option')] + $options;
    }
    $element['value'] = $element + [
      '#type' => $supports_multiple ? 'exo_checkboxes' : 'exo_radios',
      '#exo_style' => $this->getSetting('exo_style') ?: 'inline',
      '#default_value' => $value,
      '#required' => $this->fieldDefinition->isRequired(),
      '#options' => $options,
      '#element_validate' => [[$this, 'validateValue']],
    ];

    return $element;
  }

  /**
   * Validation callback for the value element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateValue(array &$element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    $supports_multiple = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality() != 1;
    if ($supports_multiple) {
      $values = implode('|', $values);
    }
    $form_state->setValue($element['#parents'], $values);
  }

}
