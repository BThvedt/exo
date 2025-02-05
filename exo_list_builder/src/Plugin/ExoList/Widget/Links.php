<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\Plugin\ExoListFieldPropertyInterface;
use Drupal\exo_list_builder\Plugin\ExoListFilterInterface;
use Drupal\exo_list_builder\Plugin\ExoListWidgetBase;
use Drupal\exo_list_builder\Plugin\ExoListWidgetValuesInterface;

/**
 * Defines a eXo list action for batch operations.
 *
 * @ExoListWidget(
 *   id = "links",
 *   label = @Translation("Links"),
 *   description = @Translation("Links widget."),
 * )
 */
class Links extends ExoListWidgetBase implements ExoListWidgetValuesInterface {
  use ExoIconTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'group' => NULL,
      'total' => FALSE,
      'all' => FALSE,
      'limit' => 50,
      'reset' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, EntityListInterface $entity_list, ExoListFilterInterface $filter, array $field) {
    $form = parent::buildConfigurationForm($form, $form_state, $entity_list, $filter, $field);
    $configuration = $this->getConfiguration();
    $form['reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset other filters'),
      '#description' => $this->t('When a link is clicked, unset all other filter values.'),
      '#default_value' => $configuration['reset'],
    ];
    $form['total'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show total in link'),
      '#default_value' => $configuration['total'],
    ];
    $form['all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show "All" option as first item'),
      '#default_value' => $configuration['all'],
    ];
    if ($filter instanceof ExoListFieldPropertyInterface) {
      $properties = ['' => $this->t('- None -')] + $filter->getPropertyOptions($field['definition']);
      if (count($properties) > 2) {
        if (empty($configuration['group'])) {
          $configuration['group'] = key($properties);
        }
        $form['group'] = [
          '#type' => 'radios',
          '#title' => $this->t('Group'),
          '#options' => $properties,
          '#default_value' => $configuration['group'],
        ];
        if (count($properties) > 5) {
          $form['group']['#type'] = 'select';
        }
      }
    }
    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Results'),
      '#default_value' => $configuration['limit'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function alterElement(array &$element, EntityListInterface $entity_list, ExoListFilterInterface $filter, array $field) {
    $configuration = $this->getConfiguration();
    $options = $filter->getFilteredValueOptions($entity_list, $field);
    $options = OptGroup::flattenOptions($options);
    $multiple = !empty($field['filter']['settings']['multiple']);
    $current = $entity_list->getHandler()->getOption(['filter', $field['id']]) ?? [];
    $total = $entity_list->getHandler()->getTotal();
    $current = is_array($current) ? $current : [$current];
    $items = [];
    if (!empty($configuration['group'])) {
      $groups = [];
      foreach ($options as $value => $group) {
        $groups[$group][$value] = $value;
      }
      ksort($groups);
      $groups = array_filter($groups);
      foreach ($groups as $group => $values) {
        asort($values);
        if ($groupItems = $this->buildLinks($entity_list, $filter, $field, $values, $current, $multiple, $total)) {
          $list = [
            '#theme' => 'item_list',
            '#title' => $group,
            '#items' => $groupItems,
          ];
          $items[] = $list;
        }
      }
    }
    else {
      $items = $this->buildLinks($entity_list, $filter, $field, array_keys($options), $current, $multiple, $total);
    }
    $element = [
      '#type' => 'item',
      '#title' => $element['#title'],
    ];
    $element[] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#access' => !empty($items),
      '#prefix' => '<div class="exo-form-element exo-form-element-type-links">',
      '#suffix' => '</div>',
    ];
  }

  /**
   * Build links.
   */
  protected function buildLinks(EntityListInterface $entity_list, ExoListFilterInterface $filter, array $field, $values, $current, $multiple, $total) {
    $items = [];
    $configuration = $this->getConfiguration();
    if ($configuration['all']) {
      $is_active_all = empty($current);
      $items[] = [
        '#type' => 'link',
        '#value' => $this->t('All'),
        '#title' => [
          '#type' => 'inline_template',
          '#template' => '<span class="value{% if active %} is-active{% endif %}">{{ value }}</span>',
          '#context' => [
            'value' => $this->t('All'),
            'active' => $is_active_all ? 'is-active' : NULL,
          ],
        ],
        '#url' => $entity_list->toFilteredUrl([]),
      ];
    }
    foreach ($values as $value) {
      $value_total = !empty($configuration['total']) ? $filter->getOptionTotal(array_unique(array_merge($current, [$value])), $entity_list, $field) : 0;
      $is_current = in_array($value, $current);
      $url_value = $multiple ? array_merge($current, [$value]) : $value;
      if ($is_current) {
        $url_value = $multiple ? array_filter($current, function ($item) use ($value) {
          return $item != $value;
        }) : '';
      }
      $filters = !empty($url_value) ? [
        $field['id'] => $url_value,
      ] : [];
      if (!$is_current && $value_total && $value_total === $total) {
        continue;
      }
      if (empty($configuration['reset'])) {
        $filters += $entity_list->getHandler()->getOption('filter') ?? [];
      }
      $items[] = [
        '#type' => 'link',
        '#value' => $value,
        '#title' => [
          '#type' => 'inline_template',
          '#template' => '<span class="value{% if remove %} is-active{% endif %}">{{ value }}{% if total %} <span class="total">({{ total }})</span>{% endif %}</span>{% if remove %} <span class="remove">{{ remove }}</span>{% endif %}',
          '#context' => [
            'value' => $value,
            'remove' => !$is_current ? NULL : $this->icon('Remove')->setIcon('regular-times')->setIconOnly(),
            'total' => $value_total,
          ],
        ],
        '#url' => $entity_list->toFilteredUrl($filters),
      ];
    }
    uasort($items, function ($a, $b) {
      return strnatcmp($a['#value'], $b['#value']);
    });
    return $items;
  }

}
