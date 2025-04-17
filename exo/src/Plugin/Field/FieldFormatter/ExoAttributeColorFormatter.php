<?php

namespace Drupal\exo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'exo_attribute_color' formatter.
 *
 * @FieldFormatter(
 *   id = "exo_attribute_color",
 *   label = @Translation("eXo Attribute Color"),
 *   field_types = {
 *     "exo_attribute",
 *     "list_integer",
 *     "list_float",
 *     "list_string",
 *     "boolean",
 *   }
 * )
 */
class ExoAttributeColorFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $colors = exo_theme_colors();
    foreach ($items as $delta => $item) {
      $value = $item->value;
      if (isset($colors[$value])) {
        $elements[]['#plain_text'] = $colors[$value]['hex'];
      }
    }
    return $elements;
  }

}
