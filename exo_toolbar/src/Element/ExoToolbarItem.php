<?php

namespace Drupal\exo_toolbar\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a render element for a Drupal toolbar item.
 *
 * @RenderElement("exo_toolbar_item")
 */
class ExoToolbarItem extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#theme' => 'exo_toolbar_item',
      '#attributes' => [],
      '#weight' => 0,
      '#id' => NULL,
      '#exo_toolbar_item' => NULL,
      '#pre_render' => [
        [$class, 'preRenderItem'],
      ],
      'item' => NULL,
    ];
  }

  /**
   * Builds the Toolbar item as a structured array ready for drupal_render().
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   A renderable array.
   */
  public static function preRenderItem(array $element) {
    /** @var \Drupal\exo_toolbar\Entity\ExoToolbarInterface $exo_toolbar */
    $exo_toolbar_item = $element['#exo_toolbar_item'];
    $element['item'] = $exo_toolbar_item->getPlugin()->build();
    return $element;
  }

}
