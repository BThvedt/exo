<?php

namespace Drupal\exo_reverse_entity_reference\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;

/**
 * Plugin implementation of the 'reverse_entity_reference' field type.
 *
 * @FieldType(
 *   id = "exo_reverse_entity_reference",
 *   label = @Translation("eXo Reverse Entity Reference"),
 *   description = @Translation("An entity field containing a reverse entity reference"),
 *   category = "reverse_reference",
 *   list_class = "\Drupal\exo_reverse_entity_reference\Plugin\Field\FieldType\ExoReverseReferenceList",
 *   default_formatter = "exo_reverse_entity_reference_label",
 *   no_ui = TRUE
 * )
 */
class ExoReverseEntityReferenceItem extends EntityReferenceItem {
}
