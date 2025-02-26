<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Filter;

use Drupal\exo_list_builder\EntityListInterface;

/**
 * Defines a eXo list element for rendering a content entity field.
 *
 * @ExoListFilter(
 *   id = "_commerce_product_sales_timestamp_range",
 *   label = @Translation("Range"),
 *   description = @Translation("Filter timestamp between two dates."),
 *   weight = 0,
 *   field_type = {},
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {
 *     "_commerce_product_sales",
 *   },
 *   exclusive = TRUE,
 * )
 */
class ProductSalesTimestampRange extends TimestampRange {

  /**
   * {@inheritdoc}
   */
  public function queryAlter($query, $value, EntityListInterface $entity_list, array $field) {
    $query->addTag('exo_entity_list_commerce_product_sales_timestamp_range');
    $query->addMetaData('exo_entity_list_commerce_product_sales_timestamp_range', $value);
  }

}
