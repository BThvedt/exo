<?php

namespace Drupal\exo_reverse_entity_reference\Plugin\Field\FieldType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Field\EntityReferenceFieldItemList;

/**
 * A computed field that provides reverse entity references.
 *
 * The definition of the Computed Field List is based on that
 * of content_moderation module.
 *
 * @package Drupal\reverse_entity_reference\Plugin\Field\FieldType
 */
class ExoReverseReferenceList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * The reverse entity manager.
   *
   * @var \Drupal\exo_reverse_entity_reference\ExoReverseEntityReferenceManager
   */
  protected $reverseEntityManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a BackReferenceProcessed object.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The data definition.
   * @param string $name
   *   (optional) The name of the created property, or NULL if it is the root
   *   of a typed data tree. Defaults to NULL.
   * @param \Drupal\Core\TypedData\TypedDataInterface $parent
   *   (optional) The parent object of the data property, or NULL if it is the
   *   root of a typed data tree. Defaults to NULL.
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    // Statically includes the managers, since DI isn't available.
    $this->reverseEntityManager = \Drupal::service('exo_reverse_entity_reference.manager');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->fieldTypeManager = \Drupal::service('plugin.manager.field.field_type');
    $this->logger = \Drupal::logger('exo_reverse_entity_reference');
  }

  /**
   * {@inheritdoc}
   */
  public function referencedEntities() {
    $this->ensureComputedValue();
    return parent::referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    // Compute the value of the moderation state.
    $index = 0;
    if (!isset($this->list[$index]) || $this->list[$index]->isEmpty()) {
      $reverse_references = $this->getReverseReferences();
      foreach ($reverse_references as $reference) {

        if (!empty($reference)) {

          $this->list[$index] = $this->createItem($index, [
            'target_id' => $reference['referring_entity_type_id'],
          ]);
          // Add virtual field to store field type.
          $this->list[$index]->field_name = $reference['field_name'];
        }
        $index++;
      }
    }
  }

  /**
   * Load all the reverse references for this entity.
   *
   * @return array
   *   A table of referring entities providing field name, entity type and
   *   entity id.
   */
  public function getReverseReferences() {
    $reference_map = [];

    $entity = $this->getEntity();
    $field_definitions = $this->reverseEntityManager->getReverseReferenceFieldDefinitions($entity->getEntityTypeId(), $this->getSetting('target_type'));

    // No fields found that reference this entity type.
    if (empty($field_definitions)) {
      return [];
    }

    foreach ($field_definitions as $field_name => $field_definition) {
      $has_custom_storage = $field_definition->hasCustomStorage();
      $reference_map = array_merge($reference_map, $this->getReferrers($entity, $field_definition->getTargetEntityTypeId(), $field_name, $has_custom_storage));
    }

    return $reference_map;
  }

  /**
   * Referrers getter.
   *
   * Get all the entities referring this entity given an entity type and field
   * name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $referenced
   *   The referenced entity.
   * @param string $referring_entity_type
   *   The referring entity type.
   * @param string $field_name
   *   The referring field on that entity type.
   * @param bool $has_custom_storage
   *   Whether to load reverse references from custom storage or regular entity
   *   query.
   * @param string[] $bundles
   *   (optional) The bundles that use the referring field. Defaults to
   *   array(NULL).
   *
   * @return array
   *   A table of referring entities providing field name, entity type and
   *   entity id
   */
  protected function getReferrers(EntityInterface $referenced, $referring_entity_type, $field_name, $has_custom_storage, array $bundles = [NULL]) {
    $referring_entities = [];
    $referring_storage = $this->entityTypeManager->getStorage($referring_entity_type);
    $result = NULL;
    foreach ($bundles as $referring_bundle) {
      unset($result);
      $result = $this->doGetReferrers($referring_storage, $field_name, $has_custom_storage, $referring_bundle);
      if (isset($result)) {
        foreach ($result as $referrer_id) {
          $referring_entities[] = [
            'referring_entity_type' => $referring_entity_type,
            'field_name' => $field_name,
            'referring_entity_type_id' => $referrer_id,
          ];
        }
      }
    }
    return $referring_entities;
  }

  /**
   * Referrers helper getter.
   *
   * Get all the entities referring this entity given an entity type and field
   * name.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $referring_storage
   *   The storage class for the referring entity type.
   * @param string $field_name
   *   The name of the field used to refer to this entity type.
   * @param bool $has_custom_storage
   *   Whether to load reverse references from custom storage or regular entity
   *   query.
   * @param string|null $referring_bundle
   *   (optional) The bundle that of the referring entity type that
   *   can reference the referred entity.
   *
   * @return int[]
   *   an array of entity ids. (on failure returns empty array)
   */
  protected function doGetReferrers(EntityStorageInterface $referring_storage, $field_name, $has_custom_storage, $referring_bundle = NULL) {
    $result = [];

    if ($has_custom_storage) {
      $referring_entities = $referring_storage->loadMultiple();
      $referring_entities = array_filter($referring_entities, function (ContentEntityInterface $entity) use ($field_name, $referring_bundle) {
        if (!isset($referring_bundle) || $entity->bundle() == $referring_bundle) {
          $refers_to = array_column($entity->get($field_name)
            ->getValue(), 'target_id');
          return in_array($this->getEntity()->id(), $refers_to);
        }
        return FALSE;
      });
      $result = array_map(function (EntityInterface $entity) {
        return $entity->id();
      }, $referring_entities);
    }
    else {
      try {
        $query = $referring_storage->getQuery();
        if (isset($referring_bundle)) {
          $query->condition('type', $referring_bundle)
            ->condition($field_name, $this->getEntity()->id());
        }
        else {
          $query->condition($field_name, $this->getEntity()->id());
        }
        $query_conditions = $this->getSetting('query_conditions');
        if (is_array($query_conditions)) {
          foreach ($query_conditions as $query_field_name => $query_field_value) {
            if ($query_field_name === 'moderation_state') {
              // @see exo_list_builder_query_exo_entity_list_moderation_state_alter().
              $query->addTag('exo_entity_list_moderation_state');
              $query->addMetaData('exo_entity_list_moderation_state', $query_field_value);
            }
            else {
              $query->condition($query_field_name, $query_field_value);
            }
          }
        }
        $result = $query->accessCheck(FALSE)->execute();
      }
      catch (QueryException $e) {
        $this->logger->error(
          "Something went wrong with querying the DB for reverse references. Probably an improperly reported field type (consider contacting the field type module creator). Field Type: @field_type Entity Type: @entity_type Bundle: @bundle PHP Exception: @exception",
          [
            "@field_type" => $field_name,
            "@entity_type" => $referring_storage->getEntityTypeId(),
            "@bundle" => ($referring_bundle ?: "all"),
            "@exception" => $e->getMessage(),
          ]
        );
      }
    }
    return $result;
  }

}
