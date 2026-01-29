<?php

namespace Drupal\exo_alchemist\Controller;

use Drupal\block_content\Access\RefinableDependentAccessInterface;
use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\exo\Access\ExoRefinableDependentAccessTrait;
use Drupal\layout_builder\Plugin\Block\InlineBlock;

/**
 * Provides means of fetching target entity.
 */
trait ExoFieldParentsTrait {

  use ExoRefinableDependentAccessTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The eXo component plugin manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * The parent entity.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected $parentEntity;

  /**
   * Crawl path and return the child entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity.
   * @param array $parents
   *   The parents of the child entity.
   *
   * @return array
   *   An array of target information.
   */
  protected function getTarget(ContentEntityInterface $entity, array $parents) {
    $items = NULL;
    $item = NULL;
    foreach ($parents as $parent) {
      if (is_numeric($parent) && $items) {
        /** @var \Drupal\Core\Field\FieldItemListInterface $items */
        $current_item = $items->get((int) $parent);
        if ($current_item && $current_item->entity) {
          if ($current_item->entity instanceof BlockContentInterface) {
            $item = $current_item;
            $entity = $item->entity;
          }
        }
      }
      elseif ($entity->hasField($parent)) {
        $items = $entity->get($parent);
      }
    }
    return [
      'entity' => $entity,
      'items' => $items,
      'item' => $item,
    ];
  }

  /**
   * Crawl path and return the target previous to the target.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity.
   * @param array $parents
   *   The parents of the child entity.
   *
   * @return array
   *   An array of target information.
   */
  protected function getTargetParent(ContentEntityInterface $entity, array $parents) {
    $current_target = $this->getTarget($entity, $parents);
    $previous_target = NULL;
    foreach ($this->getTargets($entity, $parents) as $target) {
      if ($previous_target && $current_target['entity']->uuid() === $target['entity']->uuid()) {
        return $previous_target;
      }
      $previous_target = $target;
    }
    return NULL;
  }

  /**
   * Crawl path and return all entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity.
   * @param array $parents
   *   The parents of the child entity.
   *
   * @return array
   *   An array or targets information.
   */
  protected function getTargets(ContentEntityInterface $entity, array $parents = []) {
    $targets = [];
    while ($parents) {
      if (is_numeric(end($parents))) {
        $targets[] = $this->getTarget($entity, $parents);
      }
      array_pop($parents);
    }
    return array_reverse($targets);
  }

  /**
   * Crawl path and return the child entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity.
   * @param array $parents
   *   The parents of the child entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The child entity.
   */
  protected function getTargetEntity(ContentEntityInterface $entity, array $parents = []) {
    return $this->getTarget($entity, $parents)['entity'];
  }

  /**
   * Crawl path and set the child entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent_entity
   *   The parent entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $child_entity
   *   The child entity.
   * @param array $parents
   *   The parents of the child entity.
   *
   * @return $this
   */
  protected function setTargetEntity(ContentEntityInterface $parent_entity, ContentEntityInterface $child_entity, array $parents = []) {
    // No need to update if entities are the same.
    if ($parent_entity->uuid() === $child_entity->uuid()) {
      return $this;
    }
    $target = $this->getTarget($parent_entity, $parents);
    if (!empty($target['item']) && $target['items'] instanceof EntityReferenceFieldItemListInterface) {
      $target['item']->setValue([
        'target_id' => NULL,
        'target_revision_id' => NULL,
        'entity' => $child_entity,
      ]);
    }
    return $this;
  }

  /**
   * Crawl path and return the child entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity.
   * @param array $parents
   *   The parents of the child entity.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   Items.
   */
  protected function getTargetItems(ContentEntityInterface $entity, array $parents) {
    return $this->getTarget($entity, $parents)['items'];
  }

  /**
   * Loads or creates the block content entity of the block.
   *
   * @param \Drupal\layout_builder\Plugin\Block\InlineBlock $block_plugin
   *   The block plugin.
   *
   * @return \Drupal\block_content\BlockContentInterface
   *   The block content entity.
   */
  protected function extractBlockEntity(InlineBlock $block_plugin) {
    if (!isset($this->parentEntity)) {
      $configuration = $block_plugin->getConfiguration();
      if (!empty($configuration['block_serialized'])) {
        $this->parentEntity = unserialize($configuration['block_serialized']);
      }
      elseif (!empty($configuration['block_uuid'])) {
        $this->parentEntity = $this->exoComponentManager()->entityLoadByUuid($configuration['block_uuid']);
      }
      elseif (!empty($configuration['block_revision_id'])) {
        $this->parentEntity = $this->exoComponentManager()->entityLoadByRevisionId($configuration['block_revision_id']);
      }
      else {
        $this->parentEntity = \Drupal::entityTypeManager()->getStorage('block_content')->create([
          'type' => $block_plugin->getDerivativeId(),
          'reusable' => FALSE,
        ]);
      }
      if ($this->parentEntity instanceof RefinableDependentAccessInterface && $dependee = $this->getAccessDependency()) {
        $this->parentEntity->setAccessDependency($dependee);
      }
    }
    return $this->parentEntity;
  }

  /**
   * Get top level field name from parents.
   *
   * @param array $parents
   *   The parents of the child entity.
   *
   * @return string
   *   The field name.
   */
  protected function getFieldNameFromParents(array $parents) {
    $parents = array_reverse($parents);
    foreach ($parents as $parent) {
      if (substr($parent, 0, 10) === 'exo_field_') {
        return $parent;
      }
    }
    return NULL;
  }

  /**
   * Get top level key from parents.
   *
   * The key may be the field delta. It may also be a computed field name.
   *
   * @param array $parents
   *   The parents of the child entity.
   *
   * @return int
   *   The delta.
   */
  protected function getKeyFromParents(array $parents) {
    $parents = array_reverse($parents);
    foreach ($parents as $parent) {
      // Delta by not always be a number. For computed fields it may be a
      // string.
      if (substr($parent, 0, 10) !== 'exo_field_') {
        if (is_numeric($parent)) {
          $parent = (int) $parent;
        }
        return $parent;
      }
    }
    return NULL;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::service('entity_type.manager');
    }
    return $this->entityTypeManager;
  }

  /**
   * Retrieves the exo component manager.
   *
   * @return \Drupal\exo_alchemist\ExoComponentManager
   *   The exo component manager.
   */
  protected function exoComponentManager() {
    if (!isset($this->exoComponentManager)) {
      $this->exoComponentManager = \Drupal::service('plugin.manager.exo_component');
    }
    return $this->exoComponentManager;
  }

}
