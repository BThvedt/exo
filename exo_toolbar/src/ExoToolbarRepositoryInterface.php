<?php

namespace Drupal\exo_toolbar;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines an interface for eXo toolbar repository.
 */
interface ExoToolbarRepositoryInterface {

  /**
   * Returns the first accessible toolbar entity.
   *
   * @return \Drupal\exo_toolbar\Entity\ExoToolbarInterface|null
   *   An toolbar entity object. NULL if no matching entity is found.
   */
  public function getActiveToolbar();

  /**
   * Get a specific toolbar entity.
   *
   * @param string $toolbar_id
   *   The toolbar id to load.
   *
   * @return \Drupal\exo_toolbar\Entity\ExoToolbarInterface|null
   *   An toolbar entity object. NULL if no matching entity is found.
   */
  public function getToolbar($toolbar_id);

  /**
   * Get all enabled toolbar entities.
   *
   * @return \Drupal\exo_toolbar\Entity\ExoToolbarInterface[]
   *   An array of toolbar entity objects indexed by their IDs. Returns an empty
   *   array if no matching entities are found.
   */
  public function getToolbars();

  /**
   * Get all items attached to a toolbar.
   *
   * @param string $toolbar_id
   *   The toolbar id to load.
   *
   * @return \Drupal\exo_toolbar\Entity\ExoToolbarIteminterface[]
   *   An array of toolbar item entity objects indexed by their IDs.
   */
  public function getToolbarItems($toolbar_id);

  /**
   * Get a specific item attached to a toolbar.
   *
   * @param string $toolbar_id
   *   The toolbar id to load.
   * @param string $item_id
   *   The item id to load.
   *
   * @return \Drupal\exo_toolbar\Entity\ExoToolbarIteminterface|null
   *   A toolbar item entity object. NULL if no entity is found.
   */
  public function getToolbarItem($toolbar_id, $item_id);

  /**
   * Encapsulates the creation of the region's LazyPluginCollection.
   *
   * @param array $configurations
   *   (optional) An associative array containing the initial configuration for
   *   each plugin in the collection, keyed by plugin instance ID.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The region's plugin collection.
   */
  public function getRegionCollection(?array $configurations = NULL);

  /**
   * Gets the definition of all region plugins.
   *
   * @return mixed[]
   *   An array of plugin definitions (empty array if no definitions were
   *   found). Keys are plugin IDs.
   */
  public function getRegionDefinitions();

  /**
   * Builds a list of region labels suitable for a Form API options list.
   *
   * @return array
   *   An array of region labels, keyed by region id.
   */
  public function getRegionLabels();

  /**
   * Returns an array of toolbar item entities.
   *
   * @param string $toolbar_id
   *   The toolbar id.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   (optional) A CacheableMetadata object. This is used to pass this
   *   information back to the caller.
   *
   * @return array
   *   An array keyed by item ID, with item entities as the values.
   */
  public function getVisibleToolbarItems($toolbar_id, ?CacheableMetadata $cacheable_metadata = NULL);

  /**
   * Returns TRUE of a toolbar contains of item of a specific plugin type.
   *
   * @param string $toolbar_id
   *   The toolbar id.
   * @param string $plugin_type
   *   The plugin type id.
   *
   * @return bool
   *   Return TRUE if item of plugin type exists.
   */
  public function hasToolbarItemOfType($toolbar_id, $plugin_type);

}
