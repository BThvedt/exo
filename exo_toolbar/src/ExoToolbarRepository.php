<?php

namespace Drupal\exo_toolbar;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\exo_toolbar\Routing\ExoToolbarPathMatcherInterface;
use Drupal\exo_toolbar\Plugin\ExoToolbarRegionManagerInterface;
use Drupal\exo_toolbar\Plugin\ExoToolbarRegionCollection;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\exo_toolbar\Entity\ExoToolbarItemInterface;
use Drupal\exo_toolbar\Entity\ExoToolbarInterface;

/**
 * Provides a repository for the Exo toolbar.
 */
class ExoToolbarRepository implements ExoToolbarRepositoryInterface {

  /**
   * The eXo toolbar storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $exoToolbarStorage;

  /**
   * The eXo toolbar item storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $exoToolbarItemStorage;

  /**
   * The region manager.
   *
   * @var \Drupal\exo_toolbar\Plugin\ExoToolbarRegionManagerInterface
   */
  protected $exoToolbarRegionManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\exo_toolbar\Routing\ExoToolbarPathMatcherInterface
   */
  protected $exoToolbarPathMatcher;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $accountProxy;

  /**
   * Region instances.
   *
   * @var array
   */
  protected $regions;

  /**
   * The plugin collection that holds the region plugin for this entity.
   *
   * @var \Drupal\exo_toolbar\Plugin\ExoToolbarRegionCollection
   */
  protected $regionPluginCollection;

  /**
   * Toolbar item entities.
   *
   * @var array
   */
  protected $toolbarItems;

  /**
   * Toolbar visible item entities.
   *
   * @var array
   */
  protected $toolbarVisibleItems;

  /**
   * Context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * Constructs a new ExoToolbarRepository.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\exo_toolbar\Plugin\ExoToolbarRegionManagerInterface $exo_toolbar_region_manager
   *   The eXo toolbar region manager.
   * @param \Drupal\exo_toolbar\Routing\ExoToolbarPathMatcherInterface $exo_toolbar_path_matcher
   *   The eXo toolbar patch matcher.
   * @param \Drupal\Core\Session\AccountProxyInterface $account_proxy
   *   Account proxy.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The plugin context handler.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ExoToolbarRegionManagerInterface $exo_toolbar_region_manager,
    ExoToolbarPathMatcherInterface $exo_toolbar_path_matcher,
    AccountProxyInterface $account_proxy,
    ContextHandlerInterface $context_handler,
  ) {
    $this->exoToolbarStorage = $entity_type_manager->getStorage('exo_toolbar');
    $this->exoToolbarItemStorage = $entity_type_manager->getStorage('exo_toolbar_item');
    $this->exoToolbarRegionManager = $exo_toolbar_region_manager;
    $this->exoToolbarPathMatcher = $exo_toolbar_path_matcher;
    $this->currentUser = $account_proxy;
    $this->contextHandler = $context_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveToolbar() {
    if ($this->exoToolbarPathMatcher->isAdmin()) {
      /** @var \Drupal\exo_toolbar\Entity\ExoToolbarInterface $toolbar */
      $toolbar = \Drupal::routeMatch()->getParameter('exo_toolbar');
      $toolbar = is_string($toolbar) ? $this->getToolbar($toolbar) : $toolbar;
      if ($toolbar instanceof ExoToolbarInterface && !$toolbar->isNew()) {
        // We do not check access if we are an admin route that contains an
        // exo_toolbar object as we check permission on the route itself.
        return $toolbar;
      }
      /** @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface $toolbar_item */
      $toolbar_item = \Drupal::routeMatch()->getParameter('exo_toolbar_item');
      if ($toolbar_item instanceof ExoToolbarItemInterface && !$toolbar_item->isNew()) {
        return $toolbar_item->getToolbar();
      }
    }
    $toolbars = $this->getToolbars();
    foreach ($toolbars as $toolbar) {
      /** @var \Drupal\exo_toolbar\Entity\ExoToolbarInterface $toolbar */
      $access = $toolbar->access('view', NULL, TRUE);
      if ($access->isAllowed()) {
        return $toolbar;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolbar($toolbar_id) {
    return $this->exoToolbarStorage->load($toolbar_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getToolbars() {
    $toolbars = $this->exoToolbarStorage->loadByProperties(['status' => 1]);
    uasort($toolbars, 'Drupal\exo_toolbar\Entity\ExoToolbar::sort');
    return $toolbars;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionCollection(?array $configurations = NULL) {
    if (!$this->regionPluginCollection) {
      $this->regionPluginCollection = new ExoToolbarRegionCollection(\Drupal::service('plugin.manager.exo_toolbar_region'));
    }
    if (!empty($configurations)) {
      $this->regionPluginCollection->setConfiguration($configurations);
    }
    return $this->regionPluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionDefinitions($show_hidden = TRUE) {
    return $this->exoToolbarRegionManager->getDefinitions($show_hidden);
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionLabels($show_hidden = FALSE) {
    $options = [];
    $definitions = $this->getRegionDefinitions($show_hidden);
    foreach ($definitions as $entity_type_id => $definition) {
      $options[$definition['id']] = $definition['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolbarItems($toolbar_id) {
    if (!isset($this->toolbarItems[$toolbar_id])) {
      $this->toolbarItems[$toolbar_id] = [];
      if ($toolbar = $this->getToolbar($toolbar_id)) {
        $this->toolbarItems[$toolbar_id] = $this->exoToolbarItemStorage->loadByProperties([
          'toolbar' => $toolbar_id,
        ]);
        uasort($this->toolbarItems[$toolbar_id], ['\Drupal\exo_toolbar\Entity\ExoToolbarItem', 'sort']);
      }
    }
    return $this->toolbarItems[$toolbar_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getToolbarItem($toolbar_id, $item_id) {
    $items = array_filter($this->getToolbarItems($toolbar_id), function ($item) use ($item_id) {
      return $item->id() == $item_id;
    });
    return !empty($items) ? reset($items) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolbarItemsOfType($toolbar_id, $plugin_type) {
    $items = &drupal_static(__FUNCTION__);
    if (!isset($items[$toolbar_id][$plugin_type])) {
      $items[$toolbar_id][$plugin_type] = [];
      foreach ($this->getVisibleToolbarItems($toolbar_id) as $key => $item) {
        if ($item->getPlugin()->getPluginId() == $plugin_type) {
          $items[$toolbar_id][$plugin_type][$key] = $item;
        }
      }
    }
    return $items[$toolbar_id][$plugin_type];
  }

  /**
   * {@inheritdoc}
   */
  public function hasToolbarItemOfType($toolbar_id, $plugin_type) {
    return !empty($this->getToolbarItemsOfType($toolbar_id, $plugin_type));
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibleToolbarItems($toolbar_id, ?CacheableMetadata $cacheable_metadata = NULL) {
    if (!isset($this->toolbarVisibleItems[$toolbar_id])) {
      $this->toolbarVisibleItems[$toolbar_id] = [];
      $toolbar = $this->getToolbar($toolbar_id);
      $all_items = $this->getToolbarItems($toolbar_id);
      $item_info = [];
      $require_items = [];
      foreach ($all_items as $item_id => $item) {
        /** @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface $item */
        $access = $item->access('view', NULL, TRUE);
        if ($cacheable_metadata) {
          $cacheable_metadata = $cacheable_metadata->merge(CacheableMetadata::createFromObject($access));
        }

        // Set the contexts on the block before checking access.
        if ($access->isAllowed() || $toolbar->isAdminMode()) {
          $this->toolbarVisibleItems[$toolbar_id][$item_id] = $item;
          // Check for item dependencies.
          if ($item->getPlugin()->isDependent() && !$toolbar->isAdminMode()) {
            $require_items[$item->getRegionId()] = $item->id();
          }
          else {
            $item_info[$item->getRegionId()][] = $item->id();
          }
        }
      }

      // Remove items if they are dependent and have no siblings.
      foreach ($require_items as $region_id => $item_id) {
        if (empty($item_info[$region_id])) {
          unset($this->toolbarVisibleItems[$toolbar_id][$item_id]);
        }
      }
    }
    return $this->toolbarVisibleItems[$toolbar_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getToolbarRegionItems($toolbar_id, $region_id) {
    $items = &drupal_static(__FUNCTION__);
    if (!isset($items[$toolbar_id][$region_id])) {
      $items[$toolbar_id][$region_id] = array_filter($this->getToolbarItems($toolbar_id), function ($item) use ($region_id) {
        /** @var /Drupal/exo_toolbar/Entity/ExoToolbarItemInterface $item */
        return $item->getRegionId() == $region_id;
      });
    }
    return $items[$toolbar_id][$region_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibleToolbarRegionItems($toolbar_id, $region_id, ?CacheableMetadata $cacheable_metadata = NULL) {
    $items = &drupal_static(__FUNCTION__);
    if (!isset($items[$toolbar_id][$region_id])) {
      $items[$toolbar_id][$region_id] = array_filter($this->getVisibleToolbarItems($toolbar_id, $cacheable_metadata), function ($item) use ($region_id) {
        /** @var /Drupal/exo_toolbar/Entity/ExoToolbarItemInterface $item */
        return $item->getRegionId() == $region_id;
      });
    }
    return $items[$toolbar_id][$region_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getToolbarRegionSectionItems($toolbar_id, $region_id, $section_id) {
    $items = &drupal_static(__FUNCTION__);
    if (!isset($items[$toolbar_id][$region_id][$section_id])) {
      $items[$toolbar_id][$region_id][$section_id] = array_filter($this->getToolbarRegionItems($toolbar_id, $region_id), function ($item) use ($section_id) {
        /** @var /Drupal/exo_toolbar/Entity/ExoToolbarItemInterface $item */
        return $item->getSectionId() == $section_id;
      });
    }
    return $items[$toolbar_id][$region_id][$section_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibleToolbarRegionSectionItems($toolbar_id, $region_id, $section_id, ?CacheableMetadata $cacheable_metadata = NULL) {
    $items = &drupal_static(__FUNCTION__);
    if (!isset($items[$toolbar_id][$region_id][$section_id])) {
      $items[$toolbar_id][$region_id][$section_id] = array_filter($this->getVisibleToolbarRegionItems($toolbar_id, $region_id, $cacheable_metadata), function ($item) use ($section_id) {
        /** @var /Drupal/exo_toolbar/Entity/ExoToolbarItemInterface $item */
        return $item->getSectionId() == $section_id;
      });
    }
    return $items[$toolbar_id][$region_id][$section_id];
  }

}
