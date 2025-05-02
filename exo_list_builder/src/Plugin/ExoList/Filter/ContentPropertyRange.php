<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\Plugin\ExoListContentTrait;
use Drupal\exo_list_builder\Plugin\ExoListFieldPropertyInterface;
use Drupal\exo_list_builder\Plugin\ExoListFilterBase;

/**
 * Defines a eXo list element for rendering a content entity field.
 *
 * @ExoListFilter(
 *   id = "content_property_range",
 *   label = @Translation("Property Range"),
 *   description = @Translation("Filter range by entity property."),
 *   weight = 0,
 *   field_type = {},
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {},
 *   exclusive = FALSE,
 * )
 */
class ContentPropertyRange extends ExoListFilterBase implements ExoListFieldPropertyInterface {
  use ExoListContentTrait;

  /**
   * {@inheritdoc}
   */
  protected $supportsMultiple = FALSE;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'property' => NULL,
      'field_type' => 'textfield',
      'field_prefix' => NULL,
      'field_suffix' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, EntityListInterface $entity_list, array $field) {
    $form = parent::buildConfigurationForm($form, $form_state, $entity_list, $field);
    $configuration = $this->getConfiguration();
    $properties = $this->getPropertyOptions($field['definition']);
    if (empty($configuration['property'])) {
      $configuration['property'] = key($properties);
    }
    $form['property'] = [
      '#type' => 'radios',
      '#title' => $this->t('Property'),
      '#options' => $properties,
      '#default_value' => $configuration['property'],
      '#required' => TRUE,
    ];
    if (count($properties) > 5) {
      $form['property']['#type'] = 'select';
    }
    $form['field_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Field type'),
      '#options' => [
        'textfield' => $this->t('Textfield'),
        'number' => $this->t('Number'),
      ],
      '#default_value' => $configuration['field_type'],
    ];
    $form['field_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field prefix'),
      '#default_value' => $configuration['field_prefix'],
    ];
    $form['field_suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field suffix'),
      '#default_value' => $configuration['field_suffix'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $value, EntityListInterface $entity_list, array $field) {
    $form = parent::buildForm($form, $form_state, $value, $entity_list, $field);
    $configuration = $this->getConfiguration();
    $form['range'] = [
      '#type' => 'fieldset',
      '#title' => $field['display_label'],
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['range']['s'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start'),
      '#default_value' => $value['s'] ?? NULL,
      '#field_prefix' => $configuration['field_prefix'],
      '#field_suffix' => $configuration['field_suffix'],
    ];
    $form['range']['e'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End'),
      '#default_value' => $value['e'] ?? NULL,
      '#field_prefix' => $configuration['field_prefix'],
      '#field_suffix' => $configuration['field_suffix'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrlQuery(array $raw_value, EntityListInterface $entity_list, array $field) {
    $query = [];
    if (!empty($raw_value['range']['s'])) {
      $query['s'] = $raw_value['range']['s'];
    }
    if (!empty($raw_value['range']['e'])) {
      $query['e'] = $raw_value['range']['e'];
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty($raw_value) {
    return $this->checkEmpty($raw_value['range']['s']) && $this->checkEmpty($raw_value['range']['e']);
  }

  /**
   * {@inheritdoc}
   */
  public function toPreview($value, EntityListInterface $entity_list, array $field) {
    $output = [];
    $configuration = $this->getConfiguration();
    if (!empty($value['s'])) {
      $output[] = $configuration['field_prefix'] . $value['s'] . $configuration['field_suffix'];
    }
    if (!empty($value['e'])) {
      $output[] = $configuration['field_prefix'] . $value['e'] . $configuration['field_suffix'];
    }
    return implode(' — ', $output);
  }

  /**
   * {@inheritdoc}
   */
  public function queryAlter($query, $value, EntityListInterface $entity_list, array $field) {
    if (!empty($value['s'])) {
      $query->condition($this->getQueryFieldName($field) . '.' . $this->getConfiguration()['property'], $value['s'], '>=');
    }
    if (!empty($value['e'])) {
      $query->condition($this->getQueryFieldName($field) . '.' . $this->getConfiguration()['property'], $value['e'], '<=');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies(array $field) {
    // Fields can compute their own values.
    if ($this->getComputedFilterClass($field['definition'])) {
      return TRUE;
    }
    // Computed fields are not supported unless they implement the
    // ExoListComputedFilterInterface interface.
    if ($field['definition']->isComputed()) {
      return FALSE;
    }
    return TRUE;
  }

}
