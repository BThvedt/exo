<?php

namespace Drupal\exo_alchemist\EventSubscriber;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\layout_builder\EventSubscriber\SetInlineBlockDependency;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;

/**
 * An event subscriber that returns an access dependency for inline blocks.
 *
 * When used within the layout builder the access dependency for inline blocks
 * will be explicitly set but if access is evaluated outside of the layout
 * builder then the dependency may not have been set.
 *
 * A known example of when the access dependency will not have been set is when
 * determining 'view' or 'download' access to a file entity that is attached
 * to a content block via a field that is using the private file system. The
 * file access handler will evaluate access on the content block without setting
 * the dependency.
 *
 * @internal
 *   Tagged services are internal.
 *
 * @see \Drupal\file\FileAccessControlHandler::checkAccess()
 * @see \Drupal\block_content\BlockContentAccessControlHandler::checkAccess()
 */
class ExoComponentSetInlineBlockDependency extends SetInlineBlockDependency {

  /**
   * The eXo component plugin manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Static cache of resolved parent dependencies, keyed by block id.
   *
   * @var array
   */
  protected $parentDependencyCache = [];

  /**
   * Constructs SetInlineBlockDependency object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\layout_builder\InlineBlockUsageInterface $usage
   *   The inline block usage service.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The eXo component manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database, InlineBlockUsageInterface $usage, SectionStorageManagerInterface $section_storage_manager, ExoComponentManager $exo_component_manager, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($entity_type_manager, $database, $usage, $section_storage_manager);
    $this->exoComponentManager = $exo_component_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInlineBlockDependency(BlockContentInterface $block_content) {
    // Only resolve dependencies for eXo component blocks.
    if (!$this->exoComponentManager->getEntityComponentDefinition($block_content)) {
      return NULL;
    }
    // Preferred path: the layout entity recorded in inline_block_usage. Skip
    // corrupt rows that never captured a layout entity (NULL type/id) so a bad
    // row can never call getStorage(NULL); fall through to the parent lookup.
    $layout_entity_info = $this->usage->getUsage($block_content->id());
    if (!empty($layout_entity_info) && !empty($layout_entity_info->layout_entity_type) && !empty($layout_entity_info->layout_entity_id)) {
      $layout_entity_storage = $this->entityTypeManager->getStorage($layout_entity_info->layout_entity_type);
      if ($layout_entity = $layout_entity_storage->load($layout_entity_info->layout_entity_id)) {
        return $layout_entity;
      }
    }
    // Fallback: nested sequence children frequently have no usage row (the
    // usage write is skipped when the in-memory child is lost during deep
    // serialization). Resolve the parent block that references this child via
    // an entity_reference_revisions field. Access is then evaluated recursively
    // against that parent, walking up the chain until an entity with a usage
    // row (ultimately the layout entity) is reached.
    return $this->getParentReferencingEntity($block_content);
  }

  /**
   * Finds the parent block that references a child via a revisions field.
   *
   * @param \Drupal\block_content\BlockContentInterface $block_content
   *   The child block content entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The parent block content entity, or NULL if none references it.
   */
  protected function getParentReferencingEntity(BlockContentInterface $block_content) {
    $child_id = $block_content->id();
    if (array_key_exists($child_id, $this->parentDependencyCache)) {
      return $this->parentDependencyCache[$child_id];
    }
    // Guard against cycles while a lookup is in progress.
    $this->parentDependencyCache[$child_id] = NULL;

    $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions('block_content');
    $field_map = $this->entityFieldManager->getFieldMapByFieldType('entity_reference_revisions');
    if (empty($field_map['block_content'])) {
      return NULL;
    }
    foreach (array_keys($field_map['block_content']) as $field_name) {
      if (!isset($storage_definitions[$field_name]) || $storage_definitions[$field_name]->getSetting('target_type') !== 'block_content') {
        continue;
      }
      $table = 'block_content__' . $field_name;
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }
      $parent_id = $this->database->select($table, 't')
        ->fields('t', ['entity_id'])
        ->condition($field_name . '_target_id', $child_id)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($parent_id) {
        $parent = $this->entityTypeManager->getStorage('block_content')->load($parent_id);
        if ($parent) {
          $this->parentDependencyCache[$child_id] = $parent;
          return $parent;
        }
      }
    }
    return NULL;
  }

}
