<?php

namespace Drupal\exo_alchemist;

use Drupal\block_content\Access\RefinableDependentAccessInterface;
use Drupal\block_content\Access\RefinableDependentAccessTrait;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\SectionComponent;

/**
 * The eXo component repository.
 */
class ExoComponentRepository {
  use RefinableDependentAccessTrait;

  /**
   * Drupal\exo_alchemist\ExoComponentManager definition.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * Cache of first component.
   *
   * @var array
   */
  protected $firstComponent = [];

  /**
   * Constructs a new ExoComponentRepository object.
   *
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The exo component manager.
   */
  public function __construct(ExoComponentManager $exo_component_manager) {
    $this->exoComponentManager = $exo_component_manager;
  }

  /**
   * Get all components attached to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param bool $use_tempstore
   *   Flag indicating if the tempstore storage should be used.
   *
   * @return \Drupal\block_content\Entity\BlockContent[]
   *   The component entities.
   */
  public function getComponents(EntityInterface $entity, $use_tempstore = FALSE) {
    $exo_components = [];
    $sections = $this->getEntitySections($entity, $use_tempstore);
    foreach ($sections as $section) {
      $components = $section->getComponents();
      uasort($components, [
        'Drupal\exo_alchemist\ExoComponentRepository',
        'sortComponents',
      ]);
      foreach ($components as $component) {
        if ($component instanceof InlineBlock || $component instanceof SectionComponent) {
          if ($exo_component = $this->extractBlockEntity($component->getPlugin())) {
            $exo_components[] = $exo_component;
          }
        }
      }
    }
    return $exo_components;
  }

  /**
   * Get first component attached to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param bool $use_tempstore
   *   Flag indicating if the tempstore storage should be used.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   The component entity.
   */
  public function getFirstComponent(EntityInterface $entity, $use_tempstore = FALSE) {
    if (!isset($this->firstComponent[$entity->uuid()])) {
      $sections = $this->getEntitySections($entity, $use_tempstore);
      if (!empty($sections)) {
        $this->firstComponent[$entity->uuid()] = NULL;
        /** @var \Drupal\layout_builder\Section $section */
        $section = reset($sections);
        $components = $section->getComponents();
        if (!empty($components)) {
          uasort($components, [
            'Drupal\exo_alchemist\ExoComponentRepository',
            'sortComponents',
          ]);
          $component = reset($components);
          $this->firstComponent[$entity->uuid()] = $this->extractBlockEntity($component->getPlugin());
        }
      }
    }
    return $this->firstComponent[$entity->uuid()];
  }

  /**
   * Get first component definition attached to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param bool $use_tempstore
   *   Flag indicating if the tempstore storage should be used.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   *   The component definition.
   */
  public function getFirstComponentDefinition(EntityInterface $entity, $use_tempstore = FALSE) {
    if ($component = $this->getFirstComponent($entity, $use_tempstore)) {
      return $this->exoComponentManager->getEntityComponentDefinition($component);
    }
    return NULL;
  }

  /**
   * Get all components attached to an entity that match a certain id.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $component_id
   *   The component id.
   * @param bool $use_tempstore
   *   Flag indicating if the tempstore storage should be used.
   *
   * @return \Drupal\block_content\Entity\BlockContent[]
   *   The component entities.
   */
  public function getComponentsById(EntityInterface $entity, $component_id, $use_tempstore = FALSE) {
    return array_filter($this->getComponents($entity, $use_tempstore), function ($component) use ($component_id) {
      $definition = $this->exoComponentManager->getEntityComponentDefinition($component);
      return $definition->id() === $component_id;
    });
  }

  /**
   * Get all field items within a component entity by type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $field_type
   *   The field type.
   * @param bool $use_tempstore
   *   Flag indicating if the tempstore storage should be used.
   *
   * @return \Drupal\block_content\Entity\BlockContent[]
   *   The component entities.
   */
  public function getComponentsWithFieldType(EntityInterface $entity, $field_type, $use_tempstore = FALSE) {
    return array_filter($this->getComponents($entity, $use_tempstore), function ($component) use ($field_type) {
      $definition = $this->exoComponentManager->getEntityComponentDefinition($component);
      return !empty($definition->getFieldsByType($field_type));
    });
  }

  /**
   * Get all field items within a component entity by type given an entity.
   *
   * Given an entity, it will get all components attached to the entity and
   * then crawl each one.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $field_type
   *   The field type.
   * @param bool $use_tempstore
   *   Flag indicating if the tempstore storage should be used.
   * @param bool $visible_only
   *   Flag indicating if we should only return visible fields.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The component entities.
   */
  public function getComponentItemsByFieldType(EntityInterface $entity, $field_type, $use_tempstore = FALSE, $visible_only = FALSE) {
    $items = NULL;
    foreach ($this->getComponentsWithFieldType($entity, $field_type, $use_tempstore) as $component) {
      $items = $this->getComponentItemsByEntityFieldType($component, $field_type, $items, $visible_only);
    }
    return $items;
  }

  /**
   * Get all field items within a component entity by type.
   *
   * @param \Drupal\block_content\Entity\BlockContent $component
   *   The entity.
   * @param string $field_type
   *   The field type.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The items.
   * @param bool $visible_only
   *   Flag indicating if we should only return visible fields.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The component entities.
   */
  public function getComponentItemsByEntityFieldType(BlockContent $component, $field_type, ?FieldItemListInterface $items = NULL, $visible_only = FALSE) {
    $definition = $this->exoComponentManager->getEntityComponentDefinition($component);
    $hidden = [];
    if ($visible_only) {
      $hidden = ExoComponentFieldManager::getHiddenFieldNames($component);
    }
    foreach ($definition->getFieldsByType($field_type) as $field) {
      if (isset($hidden[$field->getName()])) {
        continue;
      }
      $field_name = $field->safeId();
      if ($component->hasField($field_name)) {
        if ($items) {
          foreach ($component->get($field_name) as $item) {
            $items->appendItem($item->getValue());
          }
        }
        else {
          $items = clone $component->get($field_name);
        }
      }
    }
    return $items;
  }

  /**
   * Loads the component entity of the block.
   *
   * @param \Drupal\layout_builder\Plugin\Block\InlineBlock|\Drupal\layout_builder\SectionComponent $block_plugin
   *   The block plugin.
   *
   * @return \Drupal\block_content\BlockContentInterface
   *   The block content entity.
   */
  protected function extractBlockEntity($block_plugin) {
    $entity = NULL;
    $configuration = $block_plugin->getConfiguration();
    if (!empty($configuration['block_serialized'])) {
      $entity = unserialize($configuration['block_serialized']);
    }
    elseif (!empty($configuration['block_uuid'])) {
      $entity = $this->exoComponentManager->entityLoadByUuid($configuration['block_uuid']);
    }
    elseif (!empty($configuration['block_revision_id'])) {
      $entity = $this->exoComponentManager->entityLoadByRevisionId($configuration['block_revision_id']);
    }
    if ($entity instanceof RefinableDependentAccessInterface && $dependee = $this->getAccessDependency()) {
      $entity->setAccessDependency($dependee);
    }
    return $entity;
  }

  /**
   * Gets the sections for an entity if any.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param bool $use_tempstore
   *   Flag indicating if the tempstore storage should be used.
   *
   * @return \Drupal\layout_builder\Section[]
   *   The entity layout sections if available.
   */
  protected function getEntitySections(EntityInterface $entity, $use_tempstore = FALSE) {
    $section_storage = $this->getSectionStorageForEntity($entity, $use_tempstore);
    return $section_storage ? $section_storage->getSections() : [];
  }

  /**
   * Gets the section storage for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param bool $use_tempstore
   *   Flag indicating if the tempstore storage should be used.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The section storage if found otherwise NULL.
   */
  protected function getSectionStorageForEntity(EntityInterface $entity, $use_tempstore = FALSE) {
    // @todo Take into account other view modes in
    //   https://www.drupal.org/node/3008924.
    $view_mode = 'full';
    if ($entity instanceof LayoutEntityDisplayInterface) {
      $contexts['display'] = EntityContext::fromEntity($entity);
      $contexts['view_mode'] = new Context(new ContextDefinition('string'), $entity->getMode());
    }
    else {
      $contexts['entity'] = EntityContext::fromEntity($entity);
      if ($entity instanceof FieldableEntityInterface) {
        $view_mode = LayoutBuilderEntityViewDisplay::collectRenderDisplay($entity, $view_mode)->getMode();
        $display = EntityViewDisplay::collectRenderDisplay($entity, $view_mode);
        if ($display instanceof LayoutEntityDisplayInterface) {
          $contexts['display'] = EntityContext::fromEntity($display);
        }
        $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
      }
    }

    $section_storage_manager = $this->sectionStorageManager();
    $cacheability = new CacheableMetadata();
    $section_storage = $section_storage_manager->findByContext($contexts, $cacheability);
    // Always load the override if possible.
    if ($section_storage instanceof ExoComponentSectionDefaultStorageInterface && $section_storage->isOverridable()) {
      $section_storage = $section_storage_manager->load('overrides', $contexts, new CacheableMetadata());
    }
    if ($section_storage && $use_tempstore && $this->layoutTempstoreRepository()->has($section_storage)) {
      $section_storage = $this->layoutTempstoreRepository()->get($section_storage);
    }
    return $section_storage;
  }

  /**
   * Gets the section storage manager.
   *
   * @return \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   *   The section storage manager.
   */
  private function sectionStorageManager() {
    return $this->sectionStorageManager ?: \Drupal::service('plugin.manager.layout_builder.section_storage');
  }

  /**
   * Gets the layout builder tempstore repository.
   *
   * @return \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   *   The layout builder tempstore repository.
   */
  private function layoutTempstoreRepository() {
    return $this->layoutTempstoreRepository ?: \Drupal::service('layout_builder.tempstore_repository');
  }

  /**
   * Sorts by weight.
   */
  public static function sortComponents(SectionComponent $a, SectionComponent $b) {
    return $a->getWeight() - $b->getWeight();
  }

}
