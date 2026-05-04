<?php

namespace Drupal\exo\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;

/**
 * Defines the 'string' entity field type.
 *
 * @FieldType(
 *   id = "exo_attribute",
 *   label = @Translation("eXo Attribute"),
 *   description = @Translation("A field used to add attributes to entity wrappers."),
 *   category = "eXo",
 *   default_widget = "exo_theme_color",
 *   default_formatter = "exo_attribute_class"
 * )
 */
class ExoAttributeItem extends StringItem {
}
