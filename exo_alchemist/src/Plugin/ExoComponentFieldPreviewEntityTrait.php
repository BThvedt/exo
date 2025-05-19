<?php

namespace Drupal\exo_alchemist\Plugin;

/**
 * Provides methods for creating file entities.
 */
trait ExoComponentFieldPreviewEntityTrait {

  /**
   * Get an entity to preview.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function getPreviewEntity($entity_type_id, $bundle = NULL, $entity_id = NULL, array $exclude = []) {
    $entity = NULL;
    $entity_definition = $this->entityTypeManager()->getDefinition($entity_type_id);
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    if ($entity_id) {
      $entity = $storage->load($entity_id);
    }
    $bundle_key = $entity_definition->getKey('bundle');
    if (!$entity) {
      $query = $storage->getQuery();
      if ($key = $entity_definition->getKey('id')) {
        $query->sort($key);
        if (!empty($exclude)) {
          $query->condition($key, $exclude, 'NOT IN');
        }
      }
      if ($bundle && $bundle_key) {
        $query->condition($bundle_key, $bundle);
      }
      if ($key = $entity_definition->getKey('status')) {
        $query->condition($key, TRUE);
      }
      if ($entity_type_id === 'media') {
        $query->condition('alchemist_key', NULL, 'IS NULL');
      }
      $query->range(0, 1);
      $results = $query->accessCheck(FALSE)->execute();
      if (!empty($results)) {
        $entity = $storage->load(reset($results));
      }
    }
    if (is_a($this, '\Drupal\exo_alchemist\Plugin\ExoComponentField\EntityDisplay')) {
      $route = \Drupal::routeMatch();
      if ($entity_type_id == 'node') {
        // Set route match so that views and other modules can access the
        // current entity.
        $route->getParameters()->set($entity_type_id, $entity);
      }
    }
    return $entity;
  }

  /**
   * Get an entity to preview.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function getNewEntity($entity_type_id, $bundle = NULL) {
    $entity = NULL;
    $entity_definition = $this->entityTypeManager()->getDefinition($entity_type_id);
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    $bundle_key = $entity_definition->getKey('bundle');
    $data = [];
    if ($bundle && $bundle_key) {
      $data[$bundle_key] = $bundle;
    }
    $entity = $storage->create($data);
    if (is_a($this, '\Drupal\exo_alchemist\Plugin\ExoComponentField\EntityDisplay')) {
      $route = \Drupal::routeMatch();
      if ($entity_type_id == 'node') {
        // Set route match so that views and other modules can access the
        // current entity.
        $route->getParameters()->set($entity_type_id, $entity);
      }
    }
    return $entity;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    return $this->entityTypeManager ?? \Drupal::service('entity_type.manager');
  }

}
