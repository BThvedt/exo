<?php

namespace Drupal\exo_toolbar\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\exo\Shared\ExoVisibilityEntityTrait;
use Drupal\exo\Shared\ExoVisibilityEntityInterface;
use Drupal\exo_toolbar\Plugin\ExoToolbarRegionCollection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Component\Utility\Html;

/**
 * Defines the eXo Toolbar entity.
 *
 * @ConfigEntityType(
 *   id = "exo_toolbar",
 *   label = @Translation("eXo Toolbar"),
 *   handlers = {
 *     "access" = "Drupal\exo_toolbar\ExoToolbarAccessControlHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\exo_toolbar\ExoToolbarListBuilder",
 *     "form" = {
 *       "add" = "Drupal\exo_toolbar\Form\ExoToolbarForm",
 *       "edit" = "Drupal\exo_toolbar\Form\ExoToolbarForm",
 *       "delete" = "Drupal\exo_toolbar\Form\ExoToolbarDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\exo_toolbar\ExoToolbarHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "exo_toolbar",
 *   admin_permission = "administer exo toolbar",
 *   static_cache = true,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "weight",
 *     "settings",
 *     "visibility",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/exo/toolbar/add",
 *     "edit-form" = "/admin/config/exo/toolbar/{exo_toolbar}",
 *     "delete-form" = "/admin/config/exo/toolbar/{exo_toolbar}/delete",
 *     "collection" = "/admin/config/exo/toolbar"
 *   }
 * )
 */
class ExoToolbar extends ConfigEntityBase implements ExoToolbarInterface, EntityWithPluginCollectionInterface, ExoVisibilityEntityInterface {
  use ExoVisibilityEntityTrait;

  /**
   * The eXo Toolbar ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The eXo Toolbar label.
   *
   * @var string
   */
  protected $label;

  /**
   * The plugin instance settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * The plugin instance settings with defaults as needed.
   *
   * @var \Drupal\exo\ExoSettingsInstanceInterface
   */
  protected $exoSettings;

  /**
   * The weight of this toolbar in relation to other toolbars.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * Is in edit mode.
   *
   * @var bool
   */
  protected $isAdminMode = FALSE;

  /**
   * The plugin collection that holds the block plugin for this entity.
   *
   * @var \Drupal\exo_toolbar\Plugin\ExoToolbarRegionCollection
   */
  protected $regionCollection;

  /**
   * The eXo toolbar repository.
   *
   * @var \Drupal\exo_toolbar\ExoToolbarRepositoryInterface
   */
  protected $exoToolbarRepository;

  /**
   * The eXo toolbar path matcher service.
   *
   * @var \Drupal\exo_toolbar\Routing\ExoToolbarPathMatcherInterface
   */
  protected $exoToolbarPathMatcher;

  /**
   * The eXo toolbar settings service.
   *
   * @var \Drupal\exo_toolbar\ExoToolbarSettings
   */
  protected $exoToolbarSettings;

  /**
   * Cache contexts.
   *
   * @var string[]
   */
  protected $itemCacheContexts = [];

  /**
   * Cache tags.
   *
   * @var string[]
   */
  protected $itemCacheTags = [];

  /**
   * Cache max-age.
   *
   * @var int
   */
  protected $itemCacheMaxAge = Cache::PERMANENT;

  /**
   * {@inheritdoc}
   */
  public function isAdminMode() {
    return $this->exoToolbarPathMatcher()->isAdmin();
  }

  /**
   * {@inheritdoc}
   */
  public function setAdminMode($status = TRUE) {
    $this->isAdminMode = $status === TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExoSettings() {
    if (!isset($this->exoSettings)) {
      $settings = $this->get('settings');
      $this->exoSettings = $this->exoToolbarSettings()->createInstance($settings, $this->id());
      // An eXo settings instance will merge default, site and local settings
      // together. However, we do not want the enabled regions to be merged, so
      // we overwrite those settings.
      if (empty($settings['exo_default']) && !empty($settings['enabled'])) {
        $this->exoSettings->setSetting('enabled', $settings['enabled']);
      }
    }
    return $this->exoSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributeId() {
    return Html::getId('exo-toolbar-' . $this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->getExoSettings()->getSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function isDebugMode() {
    $settings = $this->getSettings();
    return !empty($settings['debug']);
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegion($region_id) {
    return $this->getRegionCollection()->get($region_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionIds() {
    $regions = [];
    foreach ($this->getRegionCollection() as $region) {
      $regions[] = $region->getPluginId();
    }
    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegions() {
    $regions = [];
    foreach ($this->getRegionCollection() as $region) {
      // This method should not return any regions not set for UI display.
      // This allows 'region' plugins to add regions without having them
      // automatically added to the toolbar.
      if ($region->isRenderedOnInit()) {
        $regions[$region->getPluginId()] = $region;
      }
    }
    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionCollection() {
    if (!$this->regionCollection) {
      $settings = $this->getSettings();
      $regions = !empty($settings['regions']) ? $settings['regions'] : [];
      $regions = array_intersect_key($regions, array_flip($settings['enabled']));
      if (!$this->isNew()) {
        foreach ($this->exoToolbarRepository()->getToolbarItemsOfType($this->id(), 'region') as $item) {
          $settings = $item->getSettings();
          $regions['item:' . $item->id()] = [
            'id' => 'item:' . $item->id(),
          ] + $settings['region'];
        }
      }
      $this->regionCollection = new ExoToolbarRegionCollection(\Drupal::service('plugin.manager.exo_toolbar_region'), $regions);
    }
    return $this->regionCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function regionIsEmpty($region_id) {
    return empty($this->getVisibleItems($region_id));
  }

  /**
   * {@inheritdoc}
   */
  public function sectionIsEmpty($region_id, $section_id) {
    return empty($this->getVisibleItems($region_id, $section_id));
  }

  /**
   * {@inheritdoc}
   */
  public function getItems($region_id = NULL, $section_id = NULL) {
    if ($region_id && $section_id) {
      return $this->exoToolbarRepository()->getToolbarRegionSectionItems($this->id(), $region_id, $section_id);
    }
    elseif ($region_id) {
      return $this->exoToolbarRepository()->getToolbarRegionItems($this->id(), $region_id);
    }
    else {
      return $this->exoToolbarRepository()->getToolbarItems($this->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getItem($item_id) {
    return $this->exoToolbarRepository()->getToolbarItem($this->id(), $item_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibleItems($region_id = NULL, $section_id = NULL, ?CacheableMetadata $cacheable_metadata = NULL) {
    if ($region_id && $section_id) {
      return $this->exoToolbarRepository()->getVisibleToolbarRegionSectionItems($this->id(), $region_id, $section_id, $cacheable_metadata);
    }
    elseif ($region_id) {
      return $this->exoToolbarRepository()->getVisibleToolbarRegionItems($this->id(), $region_id, $cacheable_metadata);
    }
    else {
      return $this->exoToolbarRepository()->getVisibleToolbarItems($this->id(), $cacheable_metadata);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNextWeight($region_id = NULL, $section_id = NULL) {
    $weight = 0;
    $items = $this->getItems($region_id, $section_id);
    if (!empty($items)) {
      $max = 0;
      $min = 0;
      foreach ($items as $item) {
        $item_weight = $item->getWeight();
        $max = $weight > $max ? $weight : $max;
        $min = $weight < $min ? $weight : $min;
      }
      $section = $this->getRegion($region_id)->getSection($section_id);
      $weight = $section->getSort() == 'asc' ? $max + 1 : $min - 1;
    }
    return $weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionLabels() {
    $options = [];
    $collection = $this->getRegionCollection();
    foreach ($collection as $region) {
      /** @var /Drupal/exo_toolbar/Plugin/ExoToolbarRegionInterface $region */
      $options[$region->getPluginId()] = $region->label();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'regions' => $this->getRegionCollection(),
      'visibility' => $this->getVisibilityConditions(),
    ];
  }

  /**
   * Sorts active toolbars by weight; sorts inactive toolbars by name.
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    // Separate enabled from disabled.
    $status = (int) $b->status() - (int) $a->status();
    if ($status !== 0) {
      return $status;
    }

    // Sort by weight.
    $weight = $a->getWeight() - $b->getWeight();
    if ($weight) {
      return $weight;
    }

    // Sort by label.
    return strcmp($a->label(), $b->label());
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (!$this->isNew()) {
      $original = $storage->loadUnchanged($this->getOriginalId());
      // When a region is removed we remove all items within that region.
      $removed_regions = array_diff($original->getRegionIds(), $this->getRegionIds());
      foreach ($removed_regions as $region_id) {
        foreach ($this->getItems($region_id) as $item) {
          /** @var /Drupal/exo_toolbar/Entity/ExoToolbarItemInterface $item */
          $item->delete();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->getItemCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCacheTags($region_id = NULL, $section_id = NULL) {
    if (!isset($this->itemCacheTags)) {
      $this->itemCacheTags = parent::getCacheTags();
      foreach ($this->getVisibleItems($region_id, $section_id) as $item) {
        /** @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface.php $item */
        $this->itemCacheTags = Cache::mergeTags($this->itemCacheTags, $item->getCacheTags());
      }
    }
    return $this->itemCacheTags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->getItemCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCacheContexts($region_id = NULL, $section_id = NULL) {
    if (!isset($this->itemCacheContexts)) {
      $this->itemCacheContexts = parent::getCacheContexts();
      foreach ($this->getVisibleItems($region_id, $section_id) as $item) {
        /** @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface.php $item */
        $this->itemCacheContexts = Cache::mergeContexts($this->itemCacheContexts, $item->getCacheContexts());
      }
    }
    return $this->itemCacheContexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->getItemCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCacheMaxAge($region_id = NULL, $section_id = NULL) {
    if (!isset($this->itemCacheMaxAge)) {
      $this->itemCacheMaxAge = parent::getCacheMaxAge();
      foreach ($this->getVisibleItems($region_id, $section_id) as $item) {
        /** @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface.php $item */
        $this->itemCacheMaxAge = Cache::mergeMaxAges($this->itemCacheMaxAge, $item->getCacheMaxAge());
      }
    }
    return $this->itemCacheMaxAge;
  }

  /**
   * Gets the eXo toolbar repository.
   *
   * @return \Drupal\exo_toolbar\ExoToolbarRepositoryInterface
   *   The eXo toolbar repository.
   */
  protected function exoToolbarRepository() {
    if (!isset($this->exoToolbarRepository)) {
      $this->exoToolbarRepository = \Drupal::service('exo_toolbar.repository');
    }
    return $this->exoToolbarRepository;
  }

  /**
   * Gets the eXo toolbar settings service.
   *
   * @return \Drupal\exo_toolbar\ExoToolbarRepositoryInterface
   *   The eXo toolbar repository.
   */
  protected function exoToolbarPathMatcher() {
    if (!isset($this->exoToolbarPathMatcher)) {
      $this->exoToolbarPathMatcher = \Drupal::service('exo_toolbar.path.matcher');
    }
    return $this->exoToolbarPathMatcher;
  }

  /**
   * Gets the eXo toolbar settings service.
   *
   * @return \Drupal\exo_toolbar\ExoToolbarRepositoryInterface
   *   The eXo toolbar repository.
   */
  protected function exoToolbarSettings() {
    if (!isset($this->exoToolbarSettings)) {
      $this->exoToolbarSettings = \Drupal::service('exo_toolbar.settings');
    }
    return $this->exoToolbarSettings;
  }

}
