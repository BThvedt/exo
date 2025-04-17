<?php

namespace Drupal\exo_toolbar\Controller;

use Symfony\Component\HttpFoundation\Request;
use Drupal\exo_toolbar\Entity\ExoToolbarInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\exo_toolbar\Entity\ExoToolbarItemInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\exo_toolbar\Ajax\ExoToolbarRegionItemCommand;

/**
 * Defines a controller to list eXo toolbar items.
 */
class ExoToolbarItemController extends ControllerBase {

  /**
   * Shows the toolbar items administration page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\exo_toolbar\Entity\ExoToolbarInterface $exo_toolbar
   *   The toolbar to list items for.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function listing(Request $request, ?ExoToolbarInterface $exo_toolbar = NULL) {
    return $this->entityTypeManager()->getListBuilder('exo_toolbar_item')->render($request, $exo_toolbar);
  }

  /**
   * Shows the toolbar items for item that supports nested items.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface $exo_toolbar_item
   *   The toolbar item to list items for.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function items(Request $request, ?ExoToolbarItemInterface $exo_toolbar_item = NULL) {
    $response = new AjaxResponse();
    $response->addCommand(new ExoToolbarRegionItemCommand($exo_toolbar_item));
    return $response;
  }

}
