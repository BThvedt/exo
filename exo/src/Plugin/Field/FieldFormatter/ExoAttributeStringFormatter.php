<?php

namespace Drupal\exo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\BasicStringFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'basic_string' formatter.
 *
 * @FieldFormatter(
 *   id = "exo_attribute_string",
 *   label = @Translation("Rendered"),
 *   field_types = {
 *     "exo_attribute"
 *   }
 * )
 */
class ExoAttributeStringFormatter extends BasicStringFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'form_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form Mode'),
      '#required' => TRUE,
      '#options' => $this->getFormModesAsOptions(),
      '#default_value' => $this->getSetting('form_mode'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormModesAsOptions() {
    $options = [];
    foreach (\Drupal::service('entity_display.repository')->getFormModes($this->fieldDefinition->getTargetEntityTypeId()) as $id => $form_mode) {
      $options[$id] = $form_mode['label'];
    }
    return ['default' => $this->t('Default')] + $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $options = $this->getFormModesAsOptions();
    $summary[] = $this->t('Form Mode: %value', [
      '%value' => $options[$this->getSetting('form_mode')] ?? $this->t('- Missing -'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $display = EntityFormDisplay::load($this->fieldDefinition->getTargetEntityTypeId() . '.' . $this->fieldDefinition->getTargetBundle() . '.' . $this->getSetting('form_mode'));
    if ($display) {
      $component = $display->getComponent($this->fieldDefinition->getName());
      if ($component) {
        $component['field_definition'] = $this->fieldDefinition;
        $instance = \Drupal::service('plugin.manager.field.widget')->createInstance($component['type'], $component);
        $options = $instance->getDefaultOptions();
        if ($items->count()) {
          foreach ($items as $delta => $item) {
            $value = $item->value;
            // The text value has no text format assigned to it, so the user input
            // should equal the output, including newlines.
            if (isset($options[$value])) {
              $elements[$delta] = [
                '#markup' => Markup::create('<div class="exo-element-options">' . $options[$value] . '</div>'),
                '#attached' => [
                  'library' => ['exo/element.options'],
                ],
              ];
            }
            else {
              $elements[$delta] = [
                '#type' => 'inline_template',
                '#template' => '{{ value|nl2br }}',
                '#context' => ['value' => $value],
              ];
            }
          }
        }
        else {
          $settings = $instance->getSettings();
          $elements[0] = [
            '#type' => 'inline_template',
            '#template' => '<small>{{ value|nl2br }}</small>',
            '#context' => ['value' => !empty($settings['empty_option']) ? $settings['empty_option'] : $this->t('- Auto -')],
          ];
        }
      }
    }
    if (empty($elements) && $items->count()) {
      $elements[0] = [
        '#type' => 'inline_template',
        '#template' => '<small>{{ value|nl2br }}</small>',
        '#context' => ['value' => $items->first()->value],
      ];
    }

    return $elements;
  }

}
