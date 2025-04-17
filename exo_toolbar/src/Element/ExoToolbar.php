<?php

namespace Drupal\exo_toolbar\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a render element for the default Drupal toolbar.
 *
 * @RenderElement("exo_toolbar")
 */
class ExoToolbar extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#theme' => 'exo_toolbar',
      '#exo_toolbar' => NULL,
      '#pre_render' => [
        [$class, 'preRenderToolbar'],
      ],
      '#attached' => [
        'library' => [
          'exo_toolbar/theme',
        ],
      ],
      // Metadata for the toolbar wrapping element.
      '#attributes' => [
        'role' => 'group',
        'aria-label' => $this->t('Site toolbar'),
      ],
      '#heading' => $this->t('Toolbar regions'),
      'regions' => NULL,
    ];
  }

  /**
   * Builds the Toolbar regions as a structured array ready for drupal_render().
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   A renderable array.
   */
  public static function preRenderToolbar(array $element) {
    /** @var \Drupal\exo_toolbar\Entity\ExoToolbarInterface $exo_toolbar */
    $exo_toolbar = $element['#exo_toolbar'];
    /** @var \Drupal\exo_toolbar\Plugin\ExoToolbarRegionPluginInterface[] $exo_toolbar_regions */
    $exo_toolbar_regions = $exo_toolbar->getRegions();
    foreach ($exo_toolbar_regions as $exo_region_id => $exo_region) {
      if ($exo_toolbar->isAdminMode() || !$exo_toolbar->regionIsEmpty($exo_region_id)) {
        $element['regions'][$exo_region_id] = [
          '#type' => 'exo_toolbar_region',
          '#exo_toolbar' => $exo_toolbar,
          '#exo_toolbar_region_id' => $exo_region_id,
          '#cache' => [
            'keys' => ['exo_toolbar', $exo_toolbar->id(), $exo_region_id],
            'contexts' => $exo_toolbar->getItemCacheContexts($exo_region_id),
            'tags' => $exo_toolbar->getItemCacheTags($exo_region_id),
            'max-age' => $exo_toolbar->getItemCacheMaxAge($exo_region_id),
          ],
        ];
      }
    }

    $element['#attributes']['id'] = $exo_toolbar->getAttributeId();
    $element['#attached']['drupalSettings']['exoToolbar'] = [
      'isAdminMode' => $exo_toolbar->isAdminMode(),
    ];

    return $element;
  }

}
