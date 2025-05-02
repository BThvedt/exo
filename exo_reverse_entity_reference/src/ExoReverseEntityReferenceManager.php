<?php

namespace Drupal\exo_reverse_entity_reference;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * Class reverse entity reference manager.
 */
class ExoReverseEntityReferenceManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * Definition cache.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected $definitionCache;

  /**
   * Definitions.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected $definitions = [];

  /**
   * Constructs a new ExoReverseEntityReferenceManager object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->fieldTypeManager = $field_type_manager;
  }

  /**
   * Load all the reverse references for this entity.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $target_entity_type_id
   *   The target entity type id.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions that are a reverse reference.
   */
  public function getReverseReferenceFieldDefinitions($entity_type_id, $target_entity_type_id) {
    $key = $entity_type_id . ':' . $target_entity_type_id;
    if (!isset($this->definitions[$key])) {
      $field_definitions = [];
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $reference_field_definitions = $this->fieldTypeManager->getDefinitions();
      $reference_field_definitions = array_filter($reference_field_definitions, function ($definition) use ($reference_field_definitions) {
        $er_extension = is_subclass_of($definition['class'], $reference_field_definitions['entity_reference']['class']);
        $is_er = $definition['class'] === $reference_field_definitions['entity_reference']['class'];
        return ($er_extension || $is_er);
      });
      $target_entity_type_id = $this->entityTypeManager->getDefinition($target_entity_type_id);
      if ($target_entity_type_id->getKey('bundle')) {
        $target_bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($target_entity_type_id->id());
      }
      else {
        $target_bundles = [$target_entity_type_id->id()];
      }

      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface[] $field_definitions */
      $field_definitions = [];
      foreach ($target_bundles as $target_bundle) {
        $definitions = $this->entityFieldManager->getFieldDefinitions($target_entity_type_id->id(), $target_bundle);
        $field_definitions += array_filter($definitions, function ($field_definition) use ($reference_field_definitions, $entity_type) {
          /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $definition */
          if (isset($reference_field_definitions[$field_definition->getType()])) {
            return $field_definition->getSetting('target_type') === $entity_type->id();
          }
          return FALSE;
        });
      }
      $this->definitions[$key] = $field_definitions;
    }
    return $this->definitions[$key];
  }

}
