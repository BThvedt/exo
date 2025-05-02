<?php

namespace Drupal\exo_toolbar\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Plugin\Context\ContextAwarePluginManagerTrait;

/**
 * Provides the eXo Toolbar Item plugin manager.
 */
class ExoToolbarItemManager extends DefaultPluginManager implements ExoToolbarItemManagerInterface {

  use CategorizingPluginManagerTrait {
    getSortedDefinitions as traitGetSortedDefinitions;
  }
  use ContextAwarePluginManagerTrait;

  /**
   * Constructs a new ExoToolbarItemManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/ExoToolbarItem', $namespaces, $module_handler, 'Drupal\exo_toolbar\Plugin\ExoToolbarItemPluginInterface', 'Drupal\exo_toolbar\Annotation\ExoToolbarItem');
    $this->alterInfo('exo_toolbar_item_info');
    $this->setCacheBackend($cache_backend, 'exo_toolbar_item_plugins');
    $this->defaults = [
      'category' => $this->t('eXo'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);
    $this->processDefinitionCategory($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getSortedDefinitions(?array $definitions = NULL, $show_hidden = FALSE) {
    // Sort the plugins first by category, then by admin label.
    $definitions = $this->traitGetSortedDefinitions($definitions, 'admin_label');
    // Do not display the 'broken' plugin in the UI.
    unset($definitions['broken']);

    if (!$show_hidden) {
      // Filter out definitions that can not be configured in Field UI.
      $definitions = array_filter($definitions, function ($definition) {
        return empty($definition['no_ui']);
      });
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    return 'broken';
  }

}
