<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Element;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\exo_list_builder\Plugin\ExoListElementContentBase;

/**
 * Defines a eXo list element for rendering the moderation state.
 *
 * @ExoListElement(
 *   id = "state_machine",
 *   label = @Translation("State"),
 *   description = @Translation("The state machine label."),
 *   weight = 0,
 *   field_type = {
 *     "state",
 *   },
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {},
 *   exclusive = false,
 *   provider = "state_machine",
 * )
 */
class StateMachine extends ExoListElementContentBase {

  /**
   * {@inheritdoc}
   */
  protected function viewItem(EntityInterface $entity, FieldItemInterface $field_item, array $field) {
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $field_item */
    return $field_item->getLabel();
  }

}
