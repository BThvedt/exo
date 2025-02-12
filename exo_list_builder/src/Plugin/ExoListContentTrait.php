<?php

namespace Drupal\exo_list_builder\Plugin;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Provides a trait for handling content entities.
 */
trait ExoListContentTrait {

  /**
   * Properties.
   *
   * @var array
   */
  protected $property = [];

  /**
   * Get item from entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array $field
   *   The field definition.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|null
   *   The field item list.
   */
  protected function getItems(ContentEntityInterface $entity, array $field) {
    $field_name = $field['field_name'];
    if (!$entity->hasField($field_name)) {
      return NULL;
    }
    $field_items = $entity->get($field_name);
    if ($field_items->isEmpty()) {
      return NULL;
    }
    return $field_items;
  }

  /**
   * Get item from entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array $field
   *   The field definition.
   *
   * @return \Drupal\Core\Field\FieldItemInterface|null
   *   The field item.
   */
  protected function getItem(ContentEntityInterface $entity, array $field) {
    $items = $this->getItems($entity, $field);
    if (!$items) {
      return NULL;
    }
    return $items->first();
  }

  /**
   * Get the entity type bundles from the definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   An array of bundles.
   */
  protected function getFieldBundles(FieldDefinitionInterface $field_definition, EntityTypeInterface $entity_type) {
    $handler = $field_definition->getSetting('handler_settings');
    if ($entity_type->hasKey('bundle')) {
      $bundles = $handler['target_bundles'] ?? array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type->id()));
    }
    else {
      $bundles = $handler['target_bundles'] ?? [$entity_type->id()];
    }
    return $bundles;
  }

  /**
   * Get the property options to export.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param bool $allow_computed
   *   Allow computed properties.
   *
   * @return array
   *   An array of property.
   */
  public function getPropertyOptions(FieldDefinitionInterface $field_definition, $allow_computed = FALSE) {
    $property = $this->getFieldProperties($field_definition, $allow_computed);
    $options = [];
    foreach ($property as $property_name => $property) {
      $options[$property_name] = $property->getLabel();
    }
    return $options;
  }

  /**
   * Get the property options to export.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param bool $allow_computed
   *   Allow computed properties.
   *
   * @return array
   *   An array of property.
   */
  protected function getPropertyReferenceOptions(FieldDefinitionInterface $field_definition, $allow_computed = FALSE) {
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $entity_type_id = $field_definition->getSetting('target_type');
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $bundles = $this->getFieldBundles($field_definition, $entity_type);
    $fields = [];
    foreach ($bundles as $bundle) {
      $fields += $entity_field_manager->getFieldDefinitions($entity_type_id, $bundle);
    }
    $options = [];
    foreach ($fields as $field_name => $referenced_field_definition) {
      $property = $this->getFieldProperties($referenced_field_definition, $allow_computed);
      foreach ($property as $property_name => $property) {
        $options[$field_name . '.' . $property_name] = $referenced_field_definition->getLabel() . ': ' . $property->getLabel();
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldProperties(FieldDefinitionInterface $definition, $allow_computed = FALSE) {
    $key = $definition->getTargetEntityTypeId() . '.' . $definition->getName();
    if (empty($this->property[$key])) {
      $storage = $definition->getFieldStorageDefinition();
      $property = $storage->getPropertyDefinitions();
      // Filter out all computed property, these cannot be set.
      if (!$allow_computed) {
        $property = array_filter($property, function (DataDefinitionInterface $definition) {
          return !$definition->isComputed();
        });
      }

      if ($definition->getType() === 'image') {
        unset($property['width'], $property['height']);
      }
      $this->property[$key] = $property;
    }
    return $this->property[$key];
  }

  /**
   * Get available field values.
   */
  public function getAvailableFieldValues(EntityListInterface $entity_list, array $field, $property, $condition, $limit = NULL) {
    $field_id = $field['id'];
    $cid = 'exo_list_buider:filter:' . $entity_list->id() . ':' . $field_id . ':' . $property;
    // Field can enable facet support.
    $faceted = !empty($field['filter']['settings']['widget_settings']['facet']);
    $group_property = $field['filter']['settings']['widget_settings']['group'] ?? NULL;
    $limit = $limit ?? $field['filter']['settings']['widget_settings']['limit'] ?? 50;
    $do_cache = empty($faceted) && empty($condition);
    if ($do_cache && ($cache = \Drupal::cache()->get($cid))) {
      return $cache->data;
    }
    else {
      $values = [];
      $cacheable_metadata = new CacheableMetadata();
      $cacheable_metadata->addCacheTags($entity_list->getCacheTags());
      if ($computed_filter = $this->getComputedFilterClass($field['definition'])) {
        $values = $computed_filter::getExoListAvailableFieldValues($entity_list, $field_id, $property, $condition);
      }
      elseif ($field['definition']->isComputed()) {
        // No support for computed fields. You can use the
        // ExoListComputedFilterInterface if you have control of the field item
        // class. See getComputedFilterClass().
      }
      else {
        $query = $this->getAvailableFieldValuesQuery($entity_list, $field, $property, $condition, $cacheable_metadata, $limit);
        if ($query) {
          $handler = $entity_list->getHandler();

          // Experimental support for query conditions.
          $query_conditions = $handler->getQueryConditions();
          if (isset($query_conditions[$field['field_name']])) {
            /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
            $condition = $query_conditions[$field['field_name']];
            if (!empty($condition['value'])) {
              $field_alias = $query->getMetaData('field_alias') ?: $query->getMetaData('base_alias');
              $field_id_key = $query->getMetaData('field_id_key') ?: $query->getMetaData('base_id_key');
              $key = is_array($condition['value']) ? implode('.', $condition['value']) : $condition['value'];
              $cid .= ':' . $field['field_name'] . ':' . $key;
              if ($do_cache && ($cache = \Drupal::cache()->get($cid))) {
                return $cache->data;
              }
              $operator = $condition['operator'] ?? '=';
              $query->condition($field_alias . '.' . $field_id_key, $condition['value'], $operator);
            }
          }

          // Facet support.
          if ($faceted && ($base_alias = $query->getMetaData('base_alias')) && ($base_id_key = $query->getMetaData('base_id_key'))) {
            $ids = $handler->getQuery('options')->execute();
            if (empty($ids)) {
              return [];
            }
            $query->condition($base_alias . '.' . $base_id_key, $ids, 'IN');
          }

          $results = $query->execute();
          if ($group_property) {
            $values = $results->fetchAllKeyed();
            ksort($values);
          }
          else {
            $values = $results->fetchCol();
            $parts = explode('.', $property);
            $column = $parts[1] ?? NULL;
            $entities = [];
            // When referencing a target entity, fetch the entity labels.
            if ($column === 'target_id') {
              $field_definition = $field['definition'];
              if ($reference_field_definition = $this->getReferenceFieldDefinition($field_definition)) {
                $nested_reference_entity_type_id = $reference_field_definition->getSetting('target_type');
                $entities = $this->entityTypeManager()->getStorage($nested_reference_entity_type_id)->loadMultiple($values);
              }
            }
            elseif ($property === 'target_id') {
              $entities = $this->entityTypeManager()->getStorage($field['definition']->getSetting('target_type'))->loadMultiple($values);
            }
            // When referencing the id, fetch the entity labels.
            elseif ($property === $entity_list->getTargetEntityType()->getKey('id')) {
              $entities = $this->entityTypeManager()->getStorage($entity_list->getTargetEntityTypeId())->loadMultiple($values);
            }
            else {
              $values = array_combine($values, $values);
            }
            // Reference fields load the actual entities and we want to use
            // the label and order of those entities.
            if (!empty($entities)) {
              uasort($entities, function (EntityInterface $a, EntityInterface $b) {
                if ($a instanceof ConfigEntityInterface && $b instanceof ConfigEntityInterface) {
                  if ($a->get('weight') && $b->get('weight')) {
                    return $a->get('weight') <=> $b->get('weight');
                  }
                }
                elseif ($a instanceof TermInterface && $b instanceof TermInterface) {
                  if ($a->getWeight() === $b->getWeight()) {
                    return strnatcasecmp($a->label(), $b->label());
                  }
                  return $a->getWeight() <=> $b->getWeight();
                }
                return strnatcasecmp($a->label(), $b->label());
              });
              $values = [];
              foreach ($entities as $entity) {
                if ($entity->getEntityType()->hasKey('bundle')) {
                  $cacheable_metadata->addCacheTags([$entity->getEntityTypeId() . '_list:' . $entity->bundle()]);
                }
                $cacheable_metadata->addCacheableDependency($entity);
                if ($entity instanceof TermInterface) {
                  // Support for single-level term parents.
                  /** @var \Drupal\taxonomy\TermInterface[] $parents */
                  $parents = $this->entityTypeManager()->getStorage('taxonomy_term')->loadParents($entity->id());
                  if (count($parents) === 1) {
                    $parent = reset($parents);
                    $values[$parent->label()][$entity->id()] = '- ' . $entity->label();
                    continue;
                  }
                }
                $values[$entity->id()] = $entity->label();
              }
            }
          }
        }
      }
      if ($do_cache) {
        \Drupal::cache()->set($cid, $values, Cache::PERMANENT, $cacheable_metadata->getCacheTags());
      }
    }
    return $values;
  }

  /**
   * Get available field values query.
   */
  protected function getAvailableFieldValuesQuery(EntityListInterface $entity_list, array $field, $property, $condition, CacheableMetadata $cacheable_metadata, $limit = NULL) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
    $definition = $field['definition'];
    $entity_type_id = $definition->getTargetEntityTypeId();
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    if ($storage instanceof SqlEntityStorageInterface) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
      $entity_type = $this->entityTypeManager()->getDefinition($entity_type_id);
      $base_id_key = $entity_type->getKey('id');
      if ($definition->isComputed()) {
        return NULL;
      }
      $field_name = $field['field_name'];
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = $storage->getTableMapping();
      $base_table = $table_mapping->getDataTable() ?: $table_mapping->getBaseTable();
      $field_table = $table_mapping->getFieldTableName($field_name);
      $field_storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
      $field_storage_definition = $field_storage_definitions[$field_name];
      $field_column = $table_mapping->getFieldColumnName($field_storage_definition, $property);
      $fields = [$field_column];
      $group_property = $field['filter']['settings']['widget_settings']['group'] ?? NULL;
      if ($group_property) {
        $field_group_column = $table_mapping->getFieldColumnName($field_storage_definition, $group_property);
        $fields[] = $field_group_column;
      }
      $connection = \Drupal::database();
      $query = $connection->select($field_table, 'f')
        ->fields('f', $fields)
        ->distinct(TRUE)
        ->range(0, $limit);
      if (!empty($condition)) {
        $query->condition($field_column, '%' . $connection->escapeLike($condition) . '%', 'LIKE');
      }
      $base_alias = 'f';
      if ($field_table !== $base_table) {
        // There is likely a better way to pull this off. We need the "id"
        // column of the field so that it can be joined. We are assuming it is
        // the first column but this may not always be the case.
        $field_columns = $table_mapping->getAllColumns($field_table);
        if (in_array('id', $field_columns)) {
          $field_id_key = 'id';
        }
        elseif (in_array('entity_id', $field_columns)) {
          $field_id_key = 'entity_id';
        }
        else {
          $field_id_key = reset($field_columns);
        }
        // If we are fetching from a non-base table, we need to join the base.
        $query->join($base_table, 'b', 'b.' . $base_id_key . ' = f.' . $field_id_key);
        $base_alias = 'b';
      }
      if ($label_key = $entity_type->getKey('label')) {
        $properties = $field_storage_definitions[$label_key]->getPropertyDefinitions();
        if (count($properties) > 1) {
          $query->orderBy($base_alias . '.' . $label_key . '__' . key($properties));
        }
        else {
          $query->orderBy($base_alias . '.' . $label_key);
        }
      }
      $query->addMetaData('base_alias', $base_alias);
      $query->addMetaData('base_id_key', $base_id_key);
      if (!empty($field['reference_field'])) {
        $this->alterAvailableFieldValuesQueryByReference($query, $entity_list, $field['reference_field'], $cacheable_metadata);
      }
      elseif (($target_bundle_ids = $entity_list->getTargetBundleIds()) && ($bundle_key = $entity_list->getTargetEntityType()->getKey('bundle'))) {
        $query->condition($query->getMetaData('base_alias') . '.' . $bundle_key, $entity_list->getTargetBundleIds(), 'IN');
      }
      return $query;
    }
    return NULL;
  }

  /**
   * Alter available field values query by reference.
   */
  protected function alterAvailableFieldValuesQueryByReference(SelectInterface $query, EntityListInterface $entity_list, $reference_field, CacheableMetadata $cacheable_metadata) {
    $field = $entity_list->getField($reference_field) ?: $entity_list->getAvailableFields()[$reference_field] ?? NULL;
    if (empty($field)) {
      return;
    }
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
    $definition = $field['definition'];
    $entity_type_id = $definition->getTargetEntityTypeId();
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    if ($storage instanceof SqlEntityStorageInterface) {
      $property = 'target_id';
      $entity_type = $this->entityTypeManager()->getDefinition($entity_type_id);
      $base_id_key = $entity_type->getKey('id');
      if ($definition->isComputed()) {
        return NULL;
      }
      $base_alias = NULL;
      $field_name = $field['field_name'];
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = $storage->getTableMapping();
      $base_table = $table_mapping->getDataTable() ?: $table_mapping->getBaseTable();
      $field_table = $table_mapping->getFieldTableName($field_name);
      $ref_field_id_key = NULL;
      if ($field_table !== $base_table) {
        // There is likely a better way to pull this off. We need the "id"
        // column of the field so that it can be joined. We are assuming it is
        // the first column but this may not always be the case.
        $field_columns = $table_mapping->getAllColumns($field_table);
        if (in_array('id', $field_columns)) {
          $ref_field_id_key = 'id';
        }
        elseif (in_array('entity_id', $field_columns)) {
          $ref_field_id_key = 'entity_id';
        }
        else {
          $ref_field_id_key = reset($field_columns);
        }
      }
      $field_storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id)[$field_name];
      $field_column = $table_mapping->getFieldColumnName($field_storage_definitions, $property);
      $field_alias = str_replace(':', '_', $reference_field);
      $query->join($field_table, $field_alias, $field_alias . '.' . $field_column . ' = ' . $query->getMetaData('base_alias') . '.' . $query->getMetaData('base_id_key'));
      if (!empty($field['reference_field'])) {
        // We have made it to the base query level.
        $query->addMetaData('base_alias', $field_alias);
        $query->addMetaData('base_id_key', $ref_field_id_key ?? $base_id_key);
        $this->alterAvailableFieldValuesQueryByReference($query, $entity_list, $field['reference_field'], $cacheable_metadata);
      }
      else {
        if ($ref_field_id_key) {
          // If we are fetching from a non-base table, we need to join the base.
          $base_alias = 'base_' . $field_alias;
          $query->join($base_table, $base_alias, $base_alias . '.' . $base_id_key . ' = ' . $field_alias . '.' . $ref_field_id_key);
        }
        // We have made it to the base query level.
        $query->addMetaData('base_alias', $base_alias);
        $query->addMetaData('base_id_key', $base_id_key);
      }
    }
  }

  /**
   * Get referenced available field values query.
   */
  protected function getReferencedAvailableFieldValuesQuery(EntityListInterface $entity_list, array $field, $property, $condition, CacheableMetadata $cacheable_metadata, $limit = NULL) {
    $query = NULL;
    $limit = $limit ?? $field['filter']['settings']['widget_settings']['limit'] ?? 50;
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $field['definition'];
    /** @var \Drupal\Core\Entity\EntityFieldManager $entity_field_manager */
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $reference_entity_type_id = $field_definition->getSetting('target_type');
    if (empty($reference_entity_type_id)) {
      return $query;
    }
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    if (!$storage instanceof SqlEntityStorageInterface) {
      return $query;
    }
    if ($reference_table_mapping = $this->getReferencedTableMapping($reference_entity_type_id)) {
      $reference_entity_type = $this->entityTypeManager()->getDefinition($reference_entity_type_id);
      $cacheable_metadata->addCacheTags([$reference_entity_type->id() . '_list']);
      $reference_bundles = $this->getFieldBundles($field_definition, $reference_entity_type);
      @[$reference_field_name, $reference_property] = explode('.', $property);
      $reference_field_definitions = [];
      foreach ($reference_bundles as $reference_bundle) {
        $cacheable_metadata->addCacheTags([$reference_entity_type->id() . '_list:' . $reference_bundle]);
        $reference_field_definitions += $entity_field_manager->getFieldDefinitions($reference_entity_type_id, $reference_bundle);
      };
      /** @var \Drupal\Core\Field\FieldConfigInterface $reference_field_definition */
      $reference_field_definition = $reference_field_definitions[$reference_field_name];
      $reference_field_storage_definition = $reference_field_definition->getFieldStorageDefinition();
      $reference_id_key = $reference_entity_type->getKey('id');
      $reference_data_table = $reference_table_mapping->getDataTable() ?: $reference_table_mapping->getBaseTable();
      $reference_field_table = $reference_table_mapping->getFieldTableName($reference_field_name);
      $reference_field_column = $reference_table_mapping->getFieldColumnName($reference_field_storage_definition, $reference_property);
      $reference_bundle_key = $reference_entity_type->getKey('bundle');
      $connection = \Drupal::database();
      // We are searching against a field's table.
      if ($reference_data_table && $reference_data_table !== $reference_field_table) {
        $query = $connection->select($reference_data_table, 'd');
        $query->join($reference_field_table, 'f', 'd.' . $reference_id_key . ' = f.entity_id');
        if (!empty($reference_bundles)) {
          $query->condition('f.bundle', $reference_bundles, 'IN');
        }
        $query->addMetaData('base_alias', 'd');
        $query->addMetaData('base_id_key', $reference_id_key);
      }
      else {
        $query = $connection->select($reference_field_table, 'f');
        if (!empty($reference_bundles) && $reference_bundle_key) {
          $query->condition('f.' . $reference_bundle_key, $reference_bundles, 'IN');
        }
        $query->addMetaData('base_alias', 'f');
        $query->addMetaData('base_id_key', $reference_id_key);
      }
      $query->fields('f', [$reference_field_column])
        ->distinct(TRUE);
      $query->range(0, $limit);
      $query->addMetaData('field_alias', $reference_field_table);
      $query->addMetaData('field_id_key', $reference_id_key);

      if (!empty($condition)) {
        if ($reference_property === 'target_id') {
          $target_entity_type = $this->entityTypeManager()->getDefinition($reference_field_definition->getSetting('target_type'));
          $cacheable_metadata->addCacheTags([$target_entity_type->id() . '_list']);
          $target_table_mapping = $this->getReferencedTableMapping($target_entity_type->id());
          $label_key = $target_entity_type->getKey('label');
          if ($label_key && $target_table_mapping) {
            $target_data_table = $target_table_mapping->getDataTable() ?: $target_table_mapping->getBaseTable();
            $target_id_key = $target_entity_type->getKey('id');
            $target_field_column = $reference_table_mapping->getFieldColumnName($reference_field_storage_definition, 'target_id');
            $query->join($target_data_table, 't', 't.' . $target_id_key . ' = f.' . $target_field_column);
            $query->condition('t.' . $label_key, '%' . $connection->escapeLike($condition) . '%', 'LIKE');
          }
        }
        else {
          $query->condition('f.' . $reference_field_column, '%' . $connection->escapeLike($condition) . '%', 'LIKE');
        }
      }
      else {
        // Use weight field if available.
        if (isset($reference_field_definitions['weight'])) {
          // Currently only supports weight fields on the data table.
          // @todo Support weights set in other fields.
          if ($reference_table_mapping->getFieldTableName('weight') === $reference_data_table) {
            $query->fields($query->getMetaData('base_alias'), ['weight']);
            $query->orderBy($query->getMetaData('base_alias') . '.weight');
          }
        }
        $query->orderBy('f.' . $reference_field_column);
        // We removed the below as reference fields should be sorted by their
        // values and not the title of the parent entity. This may cause
        // unexpected side effects.
        // if ($label_key = $reference_entity_type->getKey('label')) {
        //   ksm($reference_entity_type->id(), $query);
        //   $query->fields($query->getMetaData('base_alias'), [$label_key]);
        //   $query->orderBy($query->getMetaData('base_alias') . '.' . $label_key);
        // }
        if ($status_key = $reference_entity_type->getKey('status')) {
          // Take into account a status filter that is not exposed.
          if ($entity_list->hasField($status_key)) {
            $status_field = $entity_list->getField($status_key);
            if (empty($status_field['filter']['settings']['expose']) && !empty($status_field['filter']['settings']['default']['status'])) {
              $filter_value = $entity_list->getHandler()->getFilterValue($status_key);
              if (!is_null($filter_value)) {
                $query->condition($query->getMetaData('base_alias') . '.' . $status_key, !empty($filter_value));
              }
            }
          }
        }
      }

      $storage = $this->entityTypeManager()->getStorage($entity_list->getTargetEntityTypeId());
      if ($storage instanceof SqlEntityStorageInterface) {
        // Here is some insane logic to get the query to join to the parent
        // entity table.
        $field_name = $field['field_name'];
        /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
        $table_mapping = $storage->getTableMapping();
        $table = $table_mapping->getFieldTableName($field_name);
        $column = $table_mapping->getFieldColumnName($field['definition']->getFieldStorageDefinition(), 'target_id');
        $query->join($table, 'ff', 'ff.' . $column . ' = ' . $query->getMetaData('base_alias') . '.' . $query->getMetaData('base_id_key'));
        $query->addMetaData('base_alias', 'ff');
        $query->addMetaData('base_id_key', $column);

        $entity_type = $entity_list->getTargetEntityType();
        $base_table = $table_mapping->getDataTable() ?? $table_mapping->getBaseTable();
        $base_id_key = $entity_type->getKey('id');
        if ($table !== $base_table) {
          // If we are not on the actual base table, we need to join to it.
          $query->join($base_table, 'b', 'b.' . $base_id_key . ' = ff.entity_id');
          $query->addMetaData('base_alias', 'b');
          $query->addMetaData('base_id_key', $base_id_key);
        }
      }
    }
    return $query;
  }

  /**
   * Get referenced table mapping.
   *
   * @return \Drupal\Core\Entity\Sql\DefaultTableMapping
   *   The mapping.
   */
  protected function getReferencedTableMapping($entity_type_id) {
    $mapping = NULL;
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    if ($storage instanceof SqlEntityStorageInterface) {
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $reference_table_mapping */
      $mapping = $storage->getTableMapping();
    }
    return $mapping;
  }

  /**
   * Get the entity type bundles from the definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   A field definition.
   */
  protected function getReferenceFieldDefinition(FieldDefinitionInterface $field_definition) {
    $configuration = $this->getConfiguration();
    [$field_name, $property] = explode('.', $configuration['property']);
    $reference_entity_type_id = $field_definition->getSetting('target_type');
    $reference_entity_type = $this->entityTypeManager()->getDefinition($reference_entity_type_id);
    $reference_bundles = $this->getFieldBundles($field_definition, $reference_entity_type);
    /** @var \Drupal\Core\Entity\EntityFieldManager $entity_field_manager */
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $fields = [];
    foreach ($reference_bundles as $bundle_id) {
      $fields += $entity_field_manager->getFieldDefinitions($field_definition->getTargetEntityTypeId(), $bundle_id);
    }
    return $fields[$field_name] ?? NULL;
  }

  /**
   * Check if field supports computed filtering.
   *
   * @return bool
   *   Returns TRUE if the field supports computed filtering.
   */
  protected function isComputedFilter(FieldDefinitionInterface $field_definition) {
    return isset(class_implements($field_definition->getClass())['Drupal\exo_list_builder\ExoListComputedFilterInterface']);
  }

  /**
   * Get the computed class.
   *
   * @return \Drupal\exo_list_builder\ExoListComputedFilterInterface
   *   Returns TRUE if the field supports computed filtering.
   */
  protected function getComputedFilterClass(FieldDefinitionInterface $field_definition) {
    return $this->isComputedFilter($field_definition) ? $field_definition->getClass() : '';
  }

}
