<?php

namespace Drupal\exo_aos\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Defines the 'string' entity field type.
 *
 * @FieldType(
 *   id = "exo_aos",
 *   label = @Translation("eXo Animate on Scroll"),
 *   description = @Translation("A field used to add AOS attributes to entity wrappers."),
 *   category = "eXo",
 *   list_class = "\Drupal\Core\Field\MapFieldItemList",
 *   default_widget = "exo_aos",
 *   default_formatter = "exo_aos"
 * )
 */
class ExoAosItem extends MapItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return [
      'value' => MapDataDefinition::create()->setLabel(t('Settings')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->value);
  }

}
