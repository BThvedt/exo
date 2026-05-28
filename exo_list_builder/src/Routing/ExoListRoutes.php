<?php

namespace Drupal\exo_list_builder\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class ExoListRoutes {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];

    $entity_type_manager = \Drupal::entityTypeManager();
    /** @var \Drupal\exo_list_builder\EntityListInterface[] $exo_entity_lists */
    $exo_entity_lists = $entity_type_manager->getStorage('exo_entity_list')->loadMultiple();

    foreach ($exo_entity_lists as $exo_entity_list) {
      if (!$exo_entity_list->isPublished()) {
        continue;
      }

      $list_routes = $exo_entity_list->getHandler()->routes($routes);
      if ($list_routes) {
        $routes += $list_routes;
      }
    }

    // Support entity exo_list_builder entity definitions.
    foreach ($entity_type_manager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($exo_list_builder = $entity_type->get('exo_list_builder')) {
        foreach ($exo_list_builder as $link_template => $data) {
          if (!is_array($data)) {
            continue;
          }
          $data += [
            'id' => '',
            'title_callback' => '\Drupal\exo_list_builder\Controller\ExoListController::listingTitle',
            'controller' => '\Drupal\exo_list_builder\Controller\ExoListController::listing',
          ];
          $exo_entity_list_id = $data['id'];
          if ($entity_type->hasLinkTemplate($link_template) && isset($exo_entity_lists[$exo_entity_list_id])) {
            $exo_entity_list = $exo_entity_lists[$exo_entity_list_id];
            if (!$exo_entity_list->isPublished()) {
              continue;
            }
            $route = new Route($entity_type->getLinkTemplate($link_template));
            $route
              ->addDefaults([
                '_controller' => $data['controller'],
                '_title_callback' => $data['title_callback'],
                'exo_entity_list' => $exo_entity_list->id(),
              ])
              ->setRequirement('_entity_access', 'exo_entity_list.view');
            $routes["entity.{$entity_type_id}.archive_collection"] = $route;
          }
        }
      }
    }

    return $routes;
  }

}
