<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Filter;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\Plugin\ExoListContentTrait;
use Drupal\exo_list_builder\Plugin\ExoListFieldPropertyInterface;
use Drupal\exo_list_builder\Plugin\ExoListFieldValuesInterface;
use Drupal\exo_list_builder\Plugin\ExoListFilterMatchBase;

/**
 * Defines a eXo list element for rendering a content entity field.
 *
 * @ExoListFilter(
 *   id = "content_property",
 *   label = @Translation("Property"),
 *   description = @Translation("Filter by entity property."),
 *   weight = 0,
 *   field_type = {},
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {},
 *   exclusive = FALSE,
 * )
 */
class ContentProperty extends ExoListFilterMatchBase implements ExoListFieldValuesInterface, ExoListFieldPropertyInterface {
  use ExoListContentTrait;

  /**
   * {@inheritdoc}
   */
  protected $supportsMultiple = TRUE;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'property' => NULL,
      'default_from_url' => [
        'status' => FALSE,
        'lock' => FALSE,
        'entity_type' => NULL,
        'field_name' => NULL,
        'show_in_overview' => FALSE,
      ],
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

    $form['default_from_url'] = [
      '#type' => $configuration['default_from_url']['status'] ? 'fieldset' : 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => $form['#id'] . '-default-from-url',
        'class' => ['exo-form-element'],
      ],
      '#weight' => -69,
    ];

    $form['default_from_url']['status'] = [
      '#type' => 'checkbox',
      '#id' => $form['#id'] . '-default-from-url-status',
      '#title' => $this->t('Default value from URL'),
      '#ajax' => [
        'method' => 'replace',
        'wrapper' => $form['#id'] . '-default-from-url',
        'callback' => [__CLASS__, 'ajaxReplaceDefaultFromUrl'],
      ],
      '#default_value' => $configuration['default_from_url']['status'],
    ];

    if ($configuration['default_from_url']['status']) {
      $form['default_from_url']['lock'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Lock value'),
        '#description' => $this->t('Prevent users from changing the default value.'),
        '#default_value' => !empty($configuration['default_from_url']['lock']),
      ];
      $entity_types = $this->entityTypeManager()->getDefinitions();
      $entity_type_id = $configuration['default_from_url']['entity_type'] ?? NULL;
      $options = [];
      foreach ($entity_types as $type) {
        $options[$type->id()] = $type->getLabel();
      }
      asort($options);
      $form['default_from_url']['entity_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity Type'),
        '#options' => $options,
        '#required' => TRUE,
        '#default_value' => $entity_type_id,
        '#ajax' => [
          'method' => 'replace',
          'wrapper' => $form['#id'] . '-default-from-url',
          'callback' => [__CLASS__, 'ajaxReplaceDefaultFromUrl'],
        ],
      ];

      if ($entity_type_id && isset($entity_types[$entity_type_id])) {
        $entity_type = $entity_types[$entity_type_id];
        /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_manager */
        $bundle_manager = \Drupal::service('entity_type.bundle.info');
        /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
        $field_manager = \Drupal::service('entity_field.manager');
        $field_options = [
          '' => $this->t('@entity_type_label ID', [
            '@entity_type_label' => $entity_type->getLabel(),
          ]),
        ];
        asort($field_options);
        if ($entity_type->hasKey('bundle') && $bundles = $bundle_manager->getBundleInfo($entity_type->id())) {
          foreach ($bundles as $bundle_id => $bundle) {
            foreach ($field_manager->getFieldDefinitions($entity_type_id, $bundle_id) as $field_id => $field) {
              if (in_array($field->getType(), [
                'entity_reference',
                'entity_reference_revisions',
              ])) {
                $field_options[$field_id] = $this->t('@entity_type_label -> @field_label ID', [
                  '@entity_type_label' => $entity_type->getLabel(),
                  '@field_label' => $field->getLabel(),
                ]);
              }
            }
          }
        }
        $form['default_from_url']['field_name'] = [
          '#type' => 'select',
          '#title' => $this->t('Reference Field'),
          '#options' => $field_options,
          '#default_value' => $configuration['default_from_url']['field_name'] ?? NULL,
        ];
        $form['default_from_url']['show_in_overview'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Show in overview'),
          '#default_value' => !empty($configuration['default_from_url']['show_in_overview']),
        ];
      }
    }
    return $form;
  }

  /**
   * Ajax replace callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The fields form.
   */
  public static function ajaxReplaceDefaultFromUrl(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    $parents = array_slice($form_state->getTriggeringElement()['#array_parents'], 0, -2);
    $element = NestedArray::getValue($form, $parents);
    return $element['default_from_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue(EntityListInterface $entity_list, array $field) {
    $configuration = $this->getConfiguration();
    if (!empty($configuration['default_from_url']['status']) && !empty($configuration['default_from_url']['entity_type'])) {
      $entity = \Drupal::routeMatch()->getParameter($configuration['default_from_url']['entity_type']);
      if ($entity) {
        if (!empty($configuration['default_from_url']['field_name'])) {
          $field_name = $configuration['default_from_url']['field_name'];
          if ($entity->hasField($field_name)) {
            if ($configuration['multiple']) {
              // Support multiple values.
              return array_map(function ($value) {
                return $value['target_id'];
              }, $entity->get($field_name)->getValue());
            }
            else {
              // We return an empty string so that the filter is used and no
              // results are returned.
              // @todo Support optional arguments. Would just need to return null.
              return !empty($entity->get($field_name)->entity) ? $entity->get($field_name)->entity->id() : '';
            }
          }
        }
        else {
          if ($entity instanceof EntityInterface) {
            return $entity->id();
          }
          if (is_string($entity)) {
            return $entity;
          }
        }
      }
    }
    return parent::getDefaultValue($entity_list, $field);
  }

  /**
   * {@inheritdoc}
   */
  public function isDefaultValueLocked(array $field) {
    return !empty($field['filter']['settings']['default_from_url']['lock']) || parent::isDefaultValueLocked($field);
  }

  /**
   * {@inheritdoc}
   */
  public function queryAlter($query, $value, EntityListInterface $entity_list, array $field) {
    if ($computed_filter = $this->getComputedFilterClass($field['definition'])) {
      $computed_filter::alterExoListQuery($query, $value, $entity_list, $field);
    }
    elseif ($field['definition']->isComputed()) {
      // No support for computed fields. You can use the
      // ExoListComputedFilterInterface if you have control of the field item
      // class.
    }
    else {
      $this->queryFieldAlter($query, $value, $entity_list, $field);
    }
  }

  /**
   * Alter the query with a valid field.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface|\Drupal\Core\Entity\Query\ConditionInterface $query
   *   The query.
   * @param mixed $value
   *   The filter value.
   * @param \Drupal\exo_list_builder\EntityListInterface $entity_list
   *   The entity list.
   * @param array $field
   *   The field definition.
   */
  protected function queryFieldAlter($query, $value, EntityListInterface $entity_list, array $field) {
    $field_id = $field['field_name'];
    if ($field['definition']->getType() === 'entity_reference_revisions') {
      $field_id .= '.target_revision_id';
    }
    $this->queryAlterByField($field_id . '.' . $this->getConfiguration()['property'], $query, $value, $entity_list, $field);
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions(EntityListInterface $entity_list, array $field, $input = NULL) {
    $configuration = $this->getConfiguration();
    return $this->getAvailableFieldValues($entity_list, $field, $configuration['property'], $input);
  }

  /**
   * {@inheritdoc}
   */
  public function toPreview($value, EntityListInterface $entity_list, array $field) {
    if ($this->getConfiguration()['property'] === 'target_id' && in_array($field['type'], [
      'entity_reference',
      'entity_reference_revisions',
    ])) {
      if (!is_array($value)) {
        $value = [$value];
      }
      foreach ($value as $key => $id) {
        if ($entity = $this->entityTypeManager()->getStorage($field['definition']->getSetting('target_type'))->load($id)) {
          $value[$key] = $entity->label();
        }
      }
    }
    return parent::toPreview($value, $entity_list, $field);
  }

  /**
   * {@inheritDoc}
   */
  public function getOverviewValue(EntityListInterface $entity_list, array $field) {
    $value = parent::getOverviewValue($entity_list, $field);
    if (!$value) {
      $configuration = $this->getConfiguration();
      if (!empty($configuration['default_from_url']['show_in_overview'])) {
        return $this->getDefaultValue($entity_list, $field);
      }
    }
    return $value;
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
