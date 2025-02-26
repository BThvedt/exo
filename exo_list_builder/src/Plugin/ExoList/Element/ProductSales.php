<?php

declare(strict_types = 1);

namespace Drupal\exo_list_builder\Plugin\ExoList\Element;

use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\OrderItemTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\exo_list_builder\Plugin\ExoListElementBase;

/**
 * Defines a eXo list element for rendering the moderation state.
 *
 * @ExoListElement(
 *   id = "_commerce_product_sales",
 *   label = @Translation("Quantity Sold"),
 *   description = @Translation("The quantity of this product that has been sold."),
 *   weight = 0,
 *   field_type = {},
 *   entity_type = {
 *     "commerce_product",
 *   },
 *   bundle = {},
 *   field_name = {
 *     "_commerce_product_sales",
 *   },
 * )
 */
class ProductSales extends ExoListElementBase {

  /**
   * The order item types referencing product variations.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemTypeInterface[]
   */
  protected static $qualifyingOrderItemTypes;

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, array $field) {
    $database = \Drupal::database();
    $query = $database->select('commerce_order_item', 'coi');
    $query->addField('cpv', 'entity_id', 'product_id');
    $query->addExpression('SUM(coi.quantity)', 'count_sold');
    $query->innerJoin('commerce_order__order_items', 'commerce_order__order_items', 'commerce_order__order_items.order_items_target_id = coi.order_item_id');
    // Exclude canceled orders.
    $query->innerJoin('commerce_order', 'co', 'co.order_id = commerce_order__order_items.entity_id AND co.state != :state', [
      ':state' => 'canceled',
    ]);
    $query->innerJoin('commerce_product__variations', 'cpv', 'cpv.variations_target_id = coi.purchased_entity');
    $query->innerJoin('commerce_product_variation_field_data', 'cpvd', 'cpvd.variation_id = cpv.variations_target_id');
    $query
      ->condition('cpvd.product_id', $entity->id())
      ->condition('co.placed', NULL, 'IS NOT NULL')
      ->groupBy('cpvd.product_id')
      ->range(0, 1);

    if (isset($entity->exoEntityListBuilder)) {
      /** @var \Drupal\exo_list_builder\ExoListBuilderInterface $exoEntityList */
      $exoEntityListBuilder = $entity->exoEntityListBuilder;
      $filters = $exoEntityListBuilder->getOption('filter');
      if (!empty($filters['_commerce_product_sales'])) {
        $filter = $filters['_commerce_product_sales'];
        if (!empty($filter['s']) || !empty($filter['e'])) {
          $and = $query->andConditionGroup();
          if (!empty($filter['s'])) {
            $and->condition('co.placed', $filter['s'], '>=');
          }
          if (!empty($filter['e'])) {
            $and->condition('co.placed', $filter['e'], '<=');
          }
          $query->condition($and);
        }

      }
    }

    $results = $query->execute()->fetchAllKeyed();
    return (int) ($results[$entity->id()] ?? 0);
  }

}
