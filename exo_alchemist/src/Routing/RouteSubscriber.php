<?php

namespace Drupal\exo_alchemist\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldDisplayInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The eXo component manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The layout manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ExoComponentManager $exo_component_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->exoComponentManager = $exo_component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    foreach ([
      // 'entity.block_content.field_ui_fields',
      'entity.entity_form_display.block_content.default',
      'entity.entity_form_display.block_content.form_mode',
      'entity.entity_view_display.block_content.default',
      'entity.entity_view_display.block_content.view_mode',
    ] as $route_id) {
      if ($route = $collection->get($route_id)) {
        $route->setRequirement('_entity_access', 'block_content_type.update');
      }
    }

    foreach ($this->exoComponentManager->getInstalledDefinitions() as $definition) {
      foreach ($definition->getFields() as $field) {
        /** @var \Drupal\exo_alchemist\Plugin\ExoComponentField\EntityDisplay $component_field */
        $component_field = $this->exoComponentManager->getExoComponentFieldManager()->createFieldInstance($field);
        if (!$component_field instanceof ExoComponentFieldDisplayInterface) {
          continue;
        }
        if (!$component_field->useDisplay()) {
          continue;
        }
        $entity_type_id = $component_field->getDisplayedEntityTypeId();
        $bundle = $component_field->getDisplayedBundle();
        $view_mode = $field->safeId();
        $path = "/admin/config/exo/alchemist/library/{definition}";
        $route = new Route(
          "$path/$view_mode",
          [
            '_entity_form' => 'entity_view_display.edit',
            '_title' => 'Manage display',
            'entity_type_id' => $entity_type_id,
            'bundle' => $bundle,
            'view_mode_name' => $view_mode,
            'section_storage_type' => 'defaults',
            'section_storage' => '',
          ],
          [
            '_permission' => 'administer exo alchemist',
            '_exo_component' => 'definition.field.' . $field->safeId(),
          ],
          [
            '_admin_route' => TRUE,
            'parameters' => [
              'definition' => [
                'exo_component_plugin' => 'view',
              ],
              'section_storage' => [
                'layout_builder_tempstore' => TRUE,
              ],
            ],
          ]
        );
        $collection->add("exo_alchemist.component.display.{$view_mode}", $route);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -200];
    return $events;
  }

}
