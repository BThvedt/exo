<?php

namespace Drupal\exo_toolbar\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining eXo Toolbar entities.
 */
interface ExoToolbarInterface extends ConfigEntityInterface {

  /**
   * Returns the eXo settings instance.
   *
   * @return \Drupal\exo\ExoSettingsInstanceInterface
   *   The eXo settings instance.
   */
  public function getExoSettings();

  /**
   * Returns the settings.
   *
   * We use eXo settings and pull in the default settings as needed.
   *
   * @return array
   *   An array of settings.
   */
  public function getSettings();

  /**
   * Returns the weight of this toolbar (used for sorting).
   *
   * @return int
   *   The toolbar weight.
   */
  public function getWeight();

  /**
   * Returns TRUE if toolbar is in edit mode.
   *
   * @return bool
   *   The toolbar edit mode status.
   */
  public function isAdminMode();

  /**
   * Sets the toolbar weight.
   *
   * @param int $weight
   *   The desired weight.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Set the toolbar edit mode.
   *
   * @param bool $status
   *   TRUE if edit mode should be enabled.
   *
   * @return $this
   */
  public function setAdminMode($status = TRUE);

  /**
   * Get specific region plugin instance.
   *
   * @return \Drupal\exo_toolbar\Plugin\ExoToolbarRegionPluginInterface
   *   A plugin instance for this toolbar.
   */
  public function getRegion($exo_region_id);

  /**
   * Get region plugin instances.
   *
   * @return \Drupal\exo_toolbar\Plugin\ExoToolbarRegionPluginInterface[]
   *   An array of region instances for this toolbar.
   */
  public function getRegions();

  /**
   * Get all items attached to a toolbar.
   *
   * @param string $region_id
   *   The region id.
   * @param string $section_id
   *   The section id.
   *
   * @return \Drupal\exo_toolbar\Entity\ExoToolbarIteminterface[]
   *   An array of toolbar item entity objects indexed by their IDs.
   */
  public function getItems($region_id = NULL, $section_id = NULL);

  /**
   * Get a specific item attached to a toolbar.
   *
   * @param string $item_id
   *   The item id to load.
   *
   * @return \Drupal\exo_toolbar\Entity\ExoToolbarIteminterface|null
   *   A toolbar item entity object. NULL if no entity is found.
   */
  public function getItem($item_id);

  /**
   * Get visible items.
   */
  public function getVisibleItems($region_id = NULL, $section_id = NULL, ?CacheableMetadata $cacheable_metadata = NULL);

  /**
   * Encapsulates the creation of the region's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The region's plugin collection.
   */
  public function getRegionCollection();

  /**
   * Builds a list of region labels suitable for a Form API options list.
   *
   * @return array
   *   An array of region labels, keyed by region id.
   */
  public function getRegionLabels();

  /**
   * Get weight of next item given a region id and section id.
   *
   * @param string $region_id
   *   The region id.
   * @param string $section_id
   *   The section id.
   *
   * @return int
   *   The weight of the next item.
   */
  public function getNextWeight($region_id = NULL, $section_id = NULL);

}
