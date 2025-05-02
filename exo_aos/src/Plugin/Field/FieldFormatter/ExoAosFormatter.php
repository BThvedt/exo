<?php

namespace Drupal\exo_aos\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'exo_aos' formatter.
 *
 * @FieldFormatter(
 *   id = "exo_aos",
 *   label = @Translation("eXo Animate on Scroll"),
 *   field_types = {
 *     "exo_aos",
 *   }
 * )
 */
class ExoAosFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $options = parent::defaultSettings();
    $options['to_field'] = NULL;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $to_field = $this->getSetting('to_field');
    if ($to_field) {
      $field_options = $this->getFieldOptions();
      $to_field = $field_options[$to_field] ?? NULL;
    }
    $summary = [];
    $summary[] = $this->t('Animate on: @field', [
      '@field' => $to_field ? $to_field : 'Entity',
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['to_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Assign animation to a specific field'),
      '#description' => $this->t('If no field is selected, the animation will be assigned to the entity as a whole.'),
      '#options' => $this->getFieldOptions(),
      '#default_value' => $this->getSetting('to_field'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // The magic happens in exo_entity_view_alter().
    return [];
  }

  /**
   * Get the field options.
   */
  protected function getFieldOptions() {
    $entityManager = \Drupal::service('entity_field.manager');
    $fields = $entityManager->getFieldDefinitions($this->fieldDefinition->getTargetEntityTypeId(), $this->fieldDefinition->getTargetBundle());
    $options = [
      '' => $this->t('- None -'),
    ];

    foreach ($fields as $field) {
      if ($field->isDisplayConfigurable('view')) {
        $options[$field->getName()] = $field->getLabel();
      }
    }
    return $options;
  }

}
