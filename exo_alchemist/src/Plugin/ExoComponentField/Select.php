<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldFieldableBase;

/**
 * A 'select' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "select",
 *   label = @Translation("Select")
 * )
 */
class Select extends ExoComponentFieldFieldableBase {

  /**
   * {@inheritdoc}
   */
  public function getStorageConfig() {
    return [
      'type' => 'string',
      'settings' => [
        'max_length' => '255',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetConfig() {
    return [
      'type' => 'string_textfield',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    return [
      'value' => $this->t('The string value.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue($delta = 0) {
    $field = $this->getFieldDefinition();
    return [
      'value' => $this->t('Placeholder for @label', [
        '@label' => strtolower($field->getLabel()),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function widgetAlter(WidgetInterface $widget, FormStateInterface $form_state) {
    $options = $this->getFieldDefinition()->getAdditionalValue('options');
    ksm($options);
  }

  /**
   * {@inheritdoc}
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    return [
      'value' => $item->value,
    ];
  }

}
