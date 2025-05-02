<?php

namespace Drupal\exo_alchemist\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_alchemist\Definition\ExoComponentDefinition;
use Drupal\exo_alchemist\ExoComponentManager;
use Symfony\Component\Routing\Route;

/**
 * Provides a generic access checker for entities.
 */
class ExoComponentAccessCheck implements AccessInterface {

  /**
   * The eXo component manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The layout manager.
   */
  public function __construct(ExoComponentManager $exo_component_manager) {
    $this->exoComponentManager = $exo_component_manager;
  }

  /**
   * Checks access to the entity operation on the given route.
   *
   * The route's '_entity_access' requirement must follow the pattern
   * 'entity_stub_name.operation', where available operations are:
   * 'view', 'update', 'create', and 'delete'.
   *
   * For example, this route configuration invokes a permissions check for
   * 'update' access to entities of type 'node':
   * @code
   * pattern: '/foo/{node}/bar'
   * requirements:
   *   _entity_access: 'node.update'
   * @endcode
   * And this will check 'delete' access to a dynamic entity type:
   * @code
   * example.route:
   *   path: foo/{parameter}/{example}
   *   requirements:
   *     _entity_access: example.delete
   *   options:
   *     parameters:
   *       example:
   *         type: entity:{parameter}
   * @endcode
   * The route match parameter corresponding to the stub name is checked to
   * see if it is entity-like i.e. implements EntityInterface.
   *
   * @see \Drupal\Core\ParamConverter\EntityConverter
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    // Split the entity type and the operation.
    $requirement = $route->getRequirement('_exo_component');
    $parts = explode('.', $requirement);
    [$parameter, $operation] = $parts;
    if ($operation === 'field') {
      array_shift($parts);
      $operation = implode('.', $parts);
    }
    // If $parameter parameter is a valid entity, call its own access check.
    $parameters = $route_match->getParameters();
    if ($parameters->has($parameter)) {
      $definition = $parameters->get($parameter);
      if ($definition instanceof ExoComponentDefinition) {
        return $this->exoComponentManager->accessDefinition($definition, $operation, $account);
      }
    }
    return AccessResult::neutral();
  }

}
