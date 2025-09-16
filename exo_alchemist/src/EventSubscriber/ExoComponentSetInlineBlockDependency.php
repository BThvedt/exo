<?php

namespace Drupal\exo_alchemist\EventSubscriber;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Database\Connection;
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
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database, InlineBlockUsageInterface $usage, SectionStorageManagerInterface $section_storage_manager, ExoComponentManager $exo_component_manager) {
    parent::__construct($entity_type_manager, $database, $usage, $section_storage_manager);
    $this->exoComponentManager = $exo_component_manager;

  }

  /**
   * {@inheritdoc}
   */
  protected function getInlineBlockDependency(BlockContentInterface $block_content) {
    $layout_entity_info = $this->usage->getUsage($block_content->id());
    if (empty($layout_entity_info)) {
      // If the block does not have usage information then we cannot set a
      // dependency. It may be used by another module besides layout builder.
      return NULL;
    }
    $layout_entity_storage = $this->entityTypeManager->getStorage($layout_entity_info->layout_entity_type);
    $layout_entity = $layout_entity_storage->load($layout_entity_info->layout_entity_id);
    if ($this->exoComponentManager->getEntityComponentDefinition($block_content)) {
      return $layout_entity;
    }
    return NULL;
  }

}
