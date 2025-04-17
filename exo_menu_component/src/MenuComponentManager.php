<?php

namespace Drupal\exo_menu_component;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\exo_menu_component\Entity\MenuComponentInterface;
use Drupal\exo_menu_component\Entity\MenuComponentTypeInterface;

/**
 * Class MenuComponentManager.
 *
 * @package Drupal\exo_menu_component
 */
class MenuComponentManager implements MenuComponentManagerInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetMenus(MenuComponentTypeInterface $entity) {
    return array_filter($entity->getTargetMenu());
  }

  /**
   * {@inheritdoc}
   */
  public function getMegaMenuTypeWhichTargetMenu($menu_name) {
    $mega_menu_types = [];
    $menu_component_types = $this->entityTypeManager->getStorage('exo_menu_component_type')->loadMultiple();
    /** @var \Drupal\exo_menu_component\Entity\MenuComponentTypeInterface $entity */
    foreach ($menu_component_types as $id => $entity) {
      $target_menus = $this->getTargetMenus($entity);
      if (in_array($menu_name, $target_menus)) {
        $mega_menu_types[$id] = $entity->label();
      }
    }
    return $mega_menu_types;
  }

  /**
   * {@inheritdoc}
   */
  public function menuIsTargetedByMegaMenuType($menu_name) {
    $menu_component_types = $this->entityTypeManager->getStorage('exo_menu_component_type')->loadMultiple();
    /** @var \Drupal\exo_menu_component\Entity\MenuComponentTypeInterface $entity */
    foreach ($menu_component_types as $entity) {
      $target_menus = $this->getTargetMenus($entity);
      if (in_array($menu_name, $target_menus)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuComponentType($id) {
    $menu_component_type = $this->entityTypeManager->getStorage('exo_menu_component_type')->load($id);
    return $menu_component_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuComponent($id) {
    $menu_component = $this->entityTypeManager->getStorage('exo_menu_component')->load($id);
    return $menu_component;
  }

  /**
   * {@inheritdoc}
   */
  public function viewMenuComponent($id, $view_mode = 'default', ?MenuComponentInterface $exo_menu_component = NULL) {
    $build = [];
    $exo_menu_component = $exo_menu_component ?? $this->getMenuComponent($id);
    if ($exo_menu_component instanceof MenuComponentInterface) {
      if (!$exo_menu_component->access('view')) {
        return $build;
      }
      $viewBuilder = $this->entityTypeManager->getViewBuilder($exo_menu_component->getEntityTypeId());
      $build = $viewBuilder->view($exo_menu_component, $view_mode);
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function createMenuComponent($type) {
    return $this->entityTypeManager->getStorage('exo_menu_component')->create([
      'type' => $type,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormObject(MenuComponentInterface $menu_component) {
    return $this->entityTypeManager->getFormObject('exo_menu_component', 'default')->setEntity($menu_component);
  }

}
