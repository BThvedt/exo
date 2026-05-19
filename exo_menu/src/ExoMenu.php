<?php

namespace Drupal\exo_menu;

use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\exo\ExoSettingsInstanceInterface;
use Drupal\Core\Cache\Cache;
use Drupal\exo_menu\Plugin\ExoMenuPluginManagerInterface;
use Drupal\exo_menu\Plugin\ExoMenuCollection;
use Drupal\Component\Utility\Html;

/**
 * Defines an eXo menu.
 */
class ExoMenu implements ExoMenuInterface {
  use RefinableCacheableDependencyTrait;
  use ExoIconTranslationTrait;

  /**
   * The unique menu id.
   *
   * @var string
   */
  protected $id;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The eXo Menu options service.
   *
   * @var \Drupal\ux\UxOptionsInterface
   */
  protected $exoSettings;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * The eXo menu plugin manager.
   *
   * @var \Drupal\exo_menu\Plugin\ExoMenuPluginManagerInterface
   */
  protected $exoMenuManager;

  /**
   * Determines the style plugin to use.
   *
   * @var string
   */
  protected $style = 'tree';

  /**
   * The plugin.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin collection that holds the style plugin for this entity.
   *
   * @var \Drupal\exo_menu\Plugin\ExoMenuCollection
   */
  protected $pluginCollection;

  /**
   * The menu ids to use in the eXo menu.
   *
   * @var array
   */
  protected $menuIds = [];

  /**
   * Determines if menu should be expand.
   *
   * @var bool
   */
  protected $expand = TRUE;

  /**
   * Determines the initial visibility level.
   *
   * @var int
   */
  protected $level = 1;

  /**
   * Determines the child visibility level.
   *
   * @var int
   */
  protected $childLevel = 0;

  /**
   * Determines the menu max depth.
   *
   * @var int
   */
  protected $depth = 3;

  /**
   * The menu tree.
   *
   * @var array
   */
  protected $tree;

  /**
   * The wrapping tag.
   *
   * @var string
   */
  protected $tag = 'div';

  /**
   * Constructs a new ExoMenu object.
   *
   * @param string $id
   *   The unique menu id.
   * @param string $style
   *   The menu style plugin id.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\exo\ExoSettingsInstanceInterface $exo_settings
   *   The eXo options service.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   * @param \Drupal\exo_menu\Plugin\ExoMenuPluginManagerInterface $exo_menu_manager
   *   The eXo menu plugin manager.
   * @param array $menu_names
   *   The menu ids to generate a menu for.
   */
  public function __construct($id, $style, EntityTypeManagerInterface $entity_type_manager, ExoSettingsInstanceInterface $exo_settings, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, ExoMenuPluginManagerInterface $exo_menu_manager, array $menu_names) {
    $this->id = Html::getId($id);
    $this->style = $style;
    $this->entityTypeManager = $entity_type_manager;
    $this->exoSettings = $exo_settings;
    $this->menuTree = $menu_tree;
    $this->menuActiveTrail = $menu_active_trail;
    $this->exoMenuManager = $exo_menu_manager;
    $this->menuIds = $menu_names;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    $collection = $this->getPluginCollection();
    if ($collection) {
      return $collection->get($this->style);
    }
    return NULL;
  }

  /**
   * Encapsulates the creation of the item's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The item's plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      if ($this->exoMenuManager->hasDefinition($this->style)) {
        $this->pluginCollection = new ExoMenuCollection($this->exoMenuManager, $this->style, $this->exoSettings->getSettings());
      }
      else {
        $this->pluginCollection = NULL;
      }
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'style' => $this->getPluginCollection(),
    ];
  }

  /**
   * Set the ExoMenu style plugin to use.
   *
   * @param string $style
   *   The eXo menu display plugin id.
   *
   * @return $this
   */
  public function setStyle($style) {
    $this->plugin = $style;
    return $this;
  }

  /**
   * Set the menu as expanded.
   *
   * All menu links that have children will "Show as expanded".
   *
   * @param bool $expand
   *   Set as expanded.
   *
   * @return $this
   */
  public function setAsExpanded($expand = TRUE) {
    $this->expand = $expand == TRUE;
    return $this;
  }

  /**
   * Check if menu is set as expanded.
   *
   * @return int
   *   Returns TRUE if menu is set as expanded.
   */
  public function isExpanded() {
    return (bool) $this->exoSettings->getSetting('expand') != NULL ? $this->exoSettings->getSetting('expand') : $this->expand;
  }

  /**
   * Set the initial menu level.
   *
   * The menu is only visible if the menu item for the current page is at this
   * level or below it. Use level 1 to always display this menu.
   *
   * @param int $level
   *   The initial menu level.
   *
   * @return $this
   */
  public function setLevel($level = 1) {
    $this->level = $level;
    return $this;
  }

  /**
   * Get the initial menu level.
   *
   * @return int
   *   The initial menu level.
   */
  public function getLevel() {
    return $this->exoSettings->getSetting('level') != NULL ? $this->exoSettings->getSetting('level') : $this->level;
  }

  /**
   * Set the child menu level.
   *
   * The menu items displayed will be at this level or below it. This level is
   * based on the active trail level.
   *
   * @param int $level
   *   The initial child menu level.
   *
   * @return $this
   */
  public function setChildLevel($level = 1) {
    $this->childLevel = $level;
    return $this;
  }

  /**
   * Get the child menu level.
   *
   * @return int
   *   The initial child menu level.
   */
  public function getChildLevel() {
    return $this->exoSettings->getSetting('child_level') != NULL ? $this->exoSettings->getSetting('child_level') : $this->childLevel;
  }

  /**
   * Set the wrapping tag.
   *
   * @param string $tag
   *   The wrapping tag.
   *
   * @return $this
   */
  public function setTag($tag = 'div') {
    $this->tag = $tag;
    return $this;
  }

  /**
   * Set the max menu depth.
   *
   * The menu is only visible if the menu item for the current page is at this
   * level or below it. Use level 1 to always display this menu.
   *
   * @param int $depth
   *   The max menu depth.
   *
   * @return $this
   */
  public function setDepth($depth = 1) {
    $this->depth = $this->depth;
    return $this;
  }

  /**
   * Get the max menu depth.
   *
   * @return int
   *   The the max menu depth.
   */
  public function getDepth() {
    return $this->exoSettings->getSetting('depth') != NULL ? $this->exoSettings->getSetting('depth') : $this->depth;
  }

  /**
   * Set the menu tree.
   */
  public function setMenuTree(array $tree) {
    $this->tree = $tree;
    return $this;
  }

  /**
   * Get the menu tree.
   */
  public function getMenuTree() {
    if (!isset($this->tree)) {
      $this->tree = [];
      foreach ($this->menuIds as $menu_name) {
        $this->tree += $this->buildMenu($menu_name);
      }
    }
    return $this->tree;
  }

  /**
   * Build and combine all menus into a single menu render array.
   *
   * @return array
   *   The menu build array.
   */
  protected function buildMenus() {
    $tree = $this->getMenuTree();
    if ($this->getPlugin()->renderAsLevels()) {
      $build = $this->menuTree->buildAsLevels($tree);
    }
    else {
      $build = $this->menuTree->build($tree);
    }
    $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'], $this->getCacheTags());
    $build['#cache']['contexts'] = Cache::mergeContexts($build['#cache']['contexts'], $this->getCacheContexts());
    $build['#cache']['max-age'] = Cache::mergeMaxAges($build['#cache']['max-age'], $this->getCacheMaxAge());
    return $build;
  }

  /**
   * Build out a menu tree for a given menu.
   *
   * @param string $menu_name
   *   The menu id.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   */
  protected function buildMenu($menu_name) {
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);
    // If expandParents is empty, the whole menu tree is built.
    if ($this->isExpanded()) {
      $parameters->expandedParents = [];
    }

    $active_trail = $this->menuActiveTrail->getActiveTrailIds($menu_name);
    $active_trail = array_values(array_reverse(array_filter($active_trail)));

    // If we have set a child level we want to only show the menu based on this
    // level and only show menu items in the current menu trail.
    if ($this->getChildLevel()) {
      if (count($active_trail) + 1 >= $this->getLevel()) {

        // We need to get the active trail two levels above the currently
        // requested level.
        if ($menu_link_id = array_slice($active_trail, $this->getChildLevel() - 2, 1)) {
          $menu_link_id = reset($menu_link_id);
          $parameters->setRoot($menu_link_id);
          $parameters->setMinDepth(1);
        }

        if ($this->getDepth() > 0) {
          $parameters->setMaxDepth(min($this->getChildLevel() + $this->getDepth() - 1, $this->menuTree->maxDepth()));
        }
      }
      else {
        return [];
      }
    }
    else {
      $parameters->setMinDepth($this->getLevel());
      if ($this->getDepth() > 0) {
        $parameters->setMaxDepth(min($this->getLevel() + $this->getDepth() - 1, $this->menuTree->maxDepth()));
      }
    }

    $tree = $this->menuTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    return $this->menuTree->transform($tree, $manipulators);
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable() {
    $style = $this->getPlugin();
    if ($style) {
      $build = $this->buildMenus();
      $id = Html::getUniqueId('exo-menu-' . $this->id);
      $build['#attributes']['id'] = $id;
      $build['#attached']['drupalSettings']['exoMenu']['defaults'][$this->style] = $style->prepareSettings($this->exoSettings->getSiteSettingsDiff(), 'site');
      $build['#attached']['drupalSettings']['exoMenu']['menus'][$id] = $style->prepareSettings($this->exoSettings->getLocalSettingsDiff(), 'local') + [
        'style' => $style->getPluginId(),
        'selector' => '#' . $id,
      ];
      $build['#wrap_children'] = $this->exoSettings->getSetting('wrap_children');
      $build['#tag'] = $this->tag;
      return $style->prepareBuild($build);
    }
    return [];
  }

}
