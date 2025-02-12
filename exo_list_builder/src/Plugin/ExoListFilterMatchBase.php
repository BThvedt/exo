<?php

namespace Drupal\exo_list_builder\Plugin;

use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_list_builder\EntityListInterface;

/**
 * Base class for eXo list filters.
 */
abstract class ExoListFilterMatchBase extends ExoListFilterBase implements ExoListFieldValuesElementInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'match_operator' => '=',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValue() {
    return '';
  }

  /**
   * Returns the options for the match operator.
   *
   * @return array
   *   List of options.
   */
  protected function getMatchOperatorOptions() {
    return [
      '=' => t('Equals'),
      '>' => t('Greater than'),
      '<' => t('Less than'),
      '>=' => t('Greater than or equal'),
      '<=' => t('Less than or equal'),
      '<>' => t('Does not equal'),
      'IN' => t('IN'),
      'NOT IN' => t('NOT IN'),
      'STARTS_WITH' => t('Starts with'),
      'CONTAINS' => t('Contains'),
      'CONTAINS_ANY' => t('Contains any'),
      'ENDS_WITH' => t('Ends with'),
      'IS NOT NULL' => t('IS NOT NULL'),
      'IS NULL' => t('IS NULL'),
      'IS NULL EMPTY' => t('IS NULL or Empty String'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, EntityListInterface $entity_list, array $field) {
    $form = parent::buildConfigurationForm($form, $form_state, $entity_list, $field);
    $configuration = $this->getConfiguration();
    $form['match_operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Match operator'),
      '#options' => $this->getMatchOperatorOptions(),
      '#default_value' => $configuration['match_operator'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $value, EntityListInterface $entity_list, array $field) {
    $form = parent::buildForm($form, $form_state, $value, $entity_list, $field);
    $configuration = $this->getConfiguration();
    $form['q'] = [
      '#type' => 'textfield',
      '#title' => $configuration['label'] ?: $field['display_label'],
      '#default_value' => $value,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getValuesParents() {
    return ['q'];
  }

  /**
   * {@inheritdoc}
   */
  public function toUrlQuery(array $raw_value, EntityListInterface $entity_list, array $field) {
    return $raw_value['q'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty($raw_value) {
    return !isset($raw_value['q']) || $this->checkEmpty($raw_value['q']);
  }

  /**
   * {@inheritdoc}
   */
  public function queryAlter($query, $value, EntityListInterface $entity_list, array $field) {
    $this->queryAlterByField($field['field_name'], $query, $value, $entity_list, $field);
  }

  /**
   * {@inheritdoc}
   */
  public function allowQueryAlter(&$value, EntityListInterface $entity_list, array $field) {
    $match_operator = $this->getConfiguration()['match_operator'];
    if (in_array($match_operator, ['IS NOT NULL', 'IS NULL', 'IS NULL EMPTY'])) {
      $value = NULL;
      return TRUE;
    }
    return parent::allowQueryAlter($value, $entity_list, $field);
  }

  /**
   * Alter the query by field_id and match operator.
   *
   * @param string $field_id
   *   The field id.
   * @param \Drupal\Core\Entity\Query\QueryInterface|\Drupal\Core\Condition\ConditionInterface $query
   *   The query.
   * @param mixed $value
   *   The filter value.
   * @param \Drupal\exo_list_builder\EntityListInterface $entity_list
   *   The entity list.
   * @param array $field
   *   The field definition.
   */
  protected function queryAlterByField($field_id, $query, $value, EntityListInterface $entity_list, array $field) {
    $match_operator = $this->getConfiguration()['match_operator'];
    if ($query instanceof QueryInterface || $query instanceof ConditionInterface) {
      if (!empty($field['reference_field'])) {
        // Support reference field.
        $field_id = ($field['reference_field_query'] ?? str_replace(':', '.entity.', $field['reference_field']) . '.entity') . '.' . $field_id;
      }
      if ($match_operator === 'IS NULL EMPTY') {
        $group = $query->orConditionGroup();
        $group->condition($field_id, NULL, 'IS NULL');
        $group->condition($field_id, '', '=');
        $query->condition($group);
      }
      elseif ($match_operator === 'CONTAINS_ANY') {
        $group = $query->orConditionGroup();
        foreach (explode(' ', $value) as $part) {
          $group->condition($field_id, $part, 'CONTAINS');
        }
        $query->condition($group);
      }
      else {
        $query->condition($field_id, $value, $match_operator);
      }
    }
  }

}
