<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Sort;

use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\Plugin\ExoListSortBase;

/**
 * Defines a eXo list action for batch operations.
 *
 * @ExoListSort(
 *   id = "commerce_product_sales",
 *   label = @Translation("Quantity Sold"),
 *   description = @Translation("Sort by quantity of items sold."),
 *   weight = 0,
 *   entity_type = {
 *     "commerce_product",
 *   },
 *   bundle = {},
 * )
 */
class ProductSales extends ExoListSortBase {

  /**
   * {@inheritdoc}
   */
  protected $supportsDirectionChange = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $defaultDirection = 'desc';

  /**
   * {@inheritdoc}
   */
  public function getAscLabel() {
    return $this->label() . ': ' . $this->t('Least');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescLabel() {
    return $this->label() . ': ' . $this->t('Most');
  }

  /**
   * {@inheritdoc}
   */
  public function sort($query, EntityListInterface $entity_list, &$direction = NULL) {
    foreach ($entity_list->getFields() as $field) {
      if ($field['id'] === '_commerce_product_sales') {
        $direction = $direction ?: $this->getDefaultDirection();
        $query->addTag('exo_entity_list_commerce_product_sales_sort');
        $query->addMetaData('exo_entity_list_commerce_product_sales_sort_field', $field['field_name']);
        $query->addMetaData('exo_entity_list_commerce_product_sales_sort_direction', $direction);
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityListInterface $exo_list) {
    $fields = $exo_list->getFields();
    return isset($fields['_commerce_product_sales']);
  }

}
