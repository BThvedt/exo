<?php

namespace Drupal\exo_alchemist\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field_ui\Access\ViewModeAccessCheck;
use Symfony\Component\Routing\Route;

/**
 * Defines an access check for entity view mode routes.
 *
 * @see \Drupal\Core\Entity\Entity\EntityViewMode
 */
class ExoComponentViewModeAccessCheck extends ViewModeAccessCheck {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a new ExoComponentViewModeAccessCheck.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks access to the view mode.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param string $view_mode_name
   *   (optional) The view mode. Defaults to 'default'.
   * @param string $bundle
   *   (optional) The bundle. Different entity types can have different names
   *   for their bundle key, so if not specified on the route via a {bundle}
   *   parameter, the access checker determines the appropriate key name, and
   *   gets the value from the corresponding request attribute. For example,
   *   for nodes, the bundle key is "node_type", so the value would be
   *   available via the {node_type} parameter rather than a {bundle}
   *   parameter.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account, $view_mode_name = 'default', $bundle = NULL) {
    if ($entity_type_id = $route->getDefault('entity_type_id')) {
      if (substr($view_mode_name, 0, 10) === 'exo_field_') {
        return AccessResult::forbidden('This view mode is handled by Alchemist.');
      }
    }
    return parent::access($route, $route_match, $account, $view_mode_name, $bundle);
  }

}
