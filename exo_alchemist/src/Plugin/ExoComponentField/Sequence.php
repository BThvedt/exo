<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\exo_alchemist\ExoComponentFieldManager;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\exo_alchemist\ExoComponentValue;
use Drupal\exo_alchemist\ExoComponentValues;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;

/**
 * A 'sequence' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "sequence",
 *   label = @Translation("Sequence"),
 * )
 */
class Sequence extends EntityReferenceBase {
  use LayoutEntityHelperTrait;

  /**
   * {@inheritDoc}
   */
  protected $allowDefaultAsTrue = FALSE;

  /**
   * The entity type to reference.
   *
   * @var string
   */
  protected $entityType = ExoComponentManager::ENTITY_TYPE;

  /**
   * Component definition.
   *
   * @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   *   The component definition.
   */
  protected $componentDefinition;

  /**
   * The parent component's modifier attributes.
   *
   * @var array
   *   An array of attributes.
   */
  protected $parentModifierAttributes;

  /**
   * The component manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    parent::processDefinition();
    $field = $this->getFieldDefinition();
    if (!$field->hasAdditionalValue('sequence_fields')) {
      throw new PluginException(sprintf('eXo Component Field plugin (%s) requires [fields] be set.', $field->getType()));
    }

    $component_definition = $this->getComponentDefinition(TRUE);

    // Merge in base defaults.
    foreach ($field->getDefaults() as $default) {
      $temp_component_definition = clone $component_definition;
      foreach ($field->getAdditionalValue('sequence_fields') as $sequence_field_name => $sequence_field) {
        $default_value = $default->getValue($sequence_field_name);
        $sequence_default_value = $sequence_field['default'] ?? NULL;
        $temp_component_definition->getField($sequence_field_name)->setDefaults($default_value);
        // Make sure field is valid.
        if ($sequence_field_name === 'value') {
          // Support subfields named 'value'. The 'value' value is always set
          // and will be used by the sequence. As a result, we set the default
          // to _value which is used in getComponentDefinitionWithValue().
          $default->setValue('_value', $default_value !== TRUE ? $default_value : $sequence_default_value);
        }
        elseif (!empty($sequence_field['default']) && empty($default->getValue($sequence_field_name))) {
          $default->setValue($sequence_field_name, $sequence_field['default']);
        }
      }
      $temp_definition = $temp_component_definition->toArray();
      $this->exoComponentManager()->processDefinition($temp_definition, $temp_definition['id']);
    }

    $definition = $component_definition->toArray();
    $this->exoComponentManager()->processDefinition($definition, $definition['id']);
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetConfig() {
    // Sequence fields should never be able to be edited via a widget.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldChanges(array &$changes, ExoComponentFieldInterface $from_field, ?FieldStorageConfigInterface $from_storage = NULL, ?FieldConfigInterface $from_config = NULL) {
    /** @var \Drupal\exo_alchemist\Plugin\ExoComponentField\Sequence $from_field */
    $component_definition = $this->getComponentDefinition();
    $sequence_changes = $this->exoComponentManager()->getExoComponentFieldManager()->getEntityBundleFieldChanges($component_definition, $from_field->getComponentDefinition());
    if (!empty(array_filter($sequence_changes))) {
      $changes['update'][$component_definition->getName()] = $this->getFieldDefinition();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldInstall() {
    parent::onFieldInstall();
    $component = $this->getComponentDefinition();
    $component->addAdditional('alchemist_default', TRUE);
    $this->exoComponentManager()->installEntityType($component);
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldUpdate() {
    parent::onFieldUpdate();
    $component = $this->getComponentDefinition();
    $component->addAdditional('alchemist_default', TRUE);
    $this->exoComponentManager()->updateEntityType($component);
    // On update, we need to make sure we build the entity.
    $values = ExoComponentValues::fromFieldDefaults($this->getFieldDefinition());
    foreach ($values as $value) {
      $this->exoComponentManager()->buildEntity($this->getComponentDefinitionWithValue($value));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldUninstall() {
    parent::onFieldUninstall();
    $component = $this->getComponentDefinition();
    $component->addAdditional('alchemist_default', TRUE);
    $this->exoComponentManager()->uninstallEntityType($component);
  }

  /**
   * {@inheritdoc}
   */
  public function onDraftUpdateLayoutBuilderEntity(FieldItemListInterface $items) {
    if ($items->count()) {
      $this->deepSerializeEntity($items);
    }
  }

  /**
   * Workaround for deep serialization.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   */
  protected function deepSerializeEntity(FieldItemListInterface $items) {
    // This is an ugly workaround of the lack of deep serialization. Entities
    // nested more than 1 level are never serialized and we therefore set
    // these entities as "new" so that they are serialized and then we set
    // them back here.
    // @see \Drupal\exo_alchemist\ExoComponentGenerator::handleComponentEntityPreSave().
    // @see https://www.drupal.org/project/drupal/issues/2824097
    // @todo Remove when patch added to core.
    foreach ($items as $delta => $item) {
      if ($item->entity) {
        if (!$item->entity->isNew()) {
          $child_definition = $this->exoComponentManager()->getEntityComponentDefinition($item->entity);
          foreach ($child_definition->getFields() as $field) {
            $this->exoComponentManager()->onDraftUpdateLayoutBuilderEntity($child_definition, $item->entity);
          }
          $item->entity->enforceIsNew();
          $item->setValue([
            'target_id' => NULL,
            'entity' => $item->entity,
          ]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue($delta = 0) {
    $field = $this->getFieldDefinition();
    $value = new ExoComponentValue($field, [
      '_delta' => $delta,
    ]);
    if ($entity = $this->getValueEntity($value)) {
      $value = [
        'target_id' => $entity->id(),
      ];
      if ($entity instanceof RevisionableInterface) {
        $value['target_revision_id'] = $entity->getRevisionId();
      }
      return $value;
    }
    return NULL;
  }

  /**
   * Instances can use this method to match values.
   */
  protected function entityFieldValuesMatch($entity_field_match, ContentEntityInterface $parent, FieldItemListInterface $parent_items, FieldItemListInterface $items, array $contexts) {
    $parent_field_name = $parent_items->getName();
    $parent_field_type = $parent_items->getFieldDefinition()->getType();
    $settings = $items->getSettings();
    $values = [];
    foreach ($parent->get($parent_field_name) as $item) {
      $component_definition = $this->getComponentDefinition();
      $value = [
        'type' => $component_definition->safeId(),
      ];
      foreach ($entity_field_match as $to => $from) {
        if ($parent_field_type === 'entity_reference_revisions') {
          if ($item->entity->hasField($from)) {
            $val = $item->entity->get($from)->getValue() ?? NULL;
          }
          else {
            $val = $this->entityFieldValuesHelpers($from, $item->entity);
          }
          $value[$component_definition->getField($to)->getFieldName()] = $val;
        }
        else {
          $val = $parent_field_type === 'fieldception' ? $item->getFieldValue($from) : $item->{$from} ?? NULL;
          $value[$component_definition->getField($to)->getFieldName()] = $val;
        }
      }
      $values[] = \Drupal::entityTypeManager()->getStorage($settings['target_type'])->create($value);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    $value = parent::viewValue($item, $delta, $contexts);
    $entity = $this->getReferencedEntity($item, $contexts);
    if ($entity) {
      $field = $this->getFieldDefinition();
      $component = $this->getComponentDefinition();
      $component->addParentFieldDelta($field, $delta);
      $value += $this->exoComponentManager()->viewEntityValues($component, $item->entity, $contexts);
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function onCloneValue(FieldItemInterface $item, $all) {
    $component = $this->getComponentDefinition();
    $entity = $this->exoComponentManager()->cloneEntity($component, $item->entity, $all);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldRestore(ExoComponentValues $values, FieldItemListInterface $items, $force = FALSE) {
    $field_values = [];
    if ($items->isEmpty()) {
      $field_values = parent::onFieldRestore($values, $items);
    }
    elseif ($force) {
      $default_entities = $this->populateValues($values, $items);
      $component = $this->getComponentDefinition();
      $default_values = ExoComponentValues::fromFieldDefaults($this->getFieldDefinition());
      $entities = [];
      if ($component->isGlobal()) {
        $entities = $default_entities;
      }
      else {
        foreach ($default_entities as $delta => $default_entity) {
          if ($item = $items->get($delta)) {
            $entities[$delta] = $item->entity;
          }
          else {
            $entities[$delta] = $this->exoComponentManager()->cloneEntity($component, $default_entity);
          }
        }
      }
      $entities = array_filter($entities);
      foreach ($entities as $delta => $entity) {
        $item_component = isset($default_values[$delta]) ? $this->getComponentDefinitionWithValue($default_values[$delta]) : $component;
        $item_component->addParentFieldDelta($this->getFieldDefinition(), $delta);
        foreach ($item_component->getFields() as $field) {
          ExoComponentFieldManager::setVisibleFieldName($entity, $field->getName());
        }
        $field_values[] = $this->exoComponentManager()->restoreEntity($item_component, $entity, $force);
      }
    }
    else {
      // On update, we need to make sure we build the entity.
      $component = $this->getComponentDefinition();
      foreach ($items as $delta => $item) {
        $component->addParentFieldDelta($this->getFieldDefinition(), $delta);
        if ($item->entity) {
          foreach ($component->getFields() as $field) {
            ExoComponentFieldManager::setVisibleFieldName($item->entity, $field->getName());
          }
          $field_values[] = $this->exoComponentManager()->restoreEntity($component, $item->entity, $force);
        }
      }
    }
    return $field_values;
  }

  /**
   * {@inheritdoc}
   */
  public function onPreSaveLayoutBuilderEntity(FieldItemListInterface $items, EntityInterface $parent_entity) {
    parent::onPreSaveLayoutBuilderEntity($items, $parent_entity);
    $component = $this->getComponentDefinition();
    $target_type = $items->getFieldDefinition()->getFieldStorageDefinition()->getSetting('target_type');
    $storage = \Drupal::entityTypeManager()->getStorage($target_type);
    // Ensure every item has target_revision_id when target_id is set, so
    // EntityReferenceRevisionsItem::preSave() can load the entity and run.
    foreach ($items as $delta => $item) {
      if (!empty($item->target_id) && empty($item->target_revision_id)) {
        $entity = $item->entity ?? $storage->load($item->target_id);
        if ($entity && $entity instanceof RevisionableInterface) {
          $revision_id = $entity->getRevisionId();
          // If in-memory entity has no revision (e.g. setNewRevision() was called),
          // load from storage so we never write NULL.
          if (empty($revision_id) && $entity->id()) {
            $loaded = $storage->load($entity->id());
            $revision_id = $loaded ? $loaded->getRevisionId() : NULL;
          }
          if (!empty($revision_id)) {
            $item->set('target_revision_id', $revision_id);
          }
        }
      }
    }
    foreach ($items as $delta => $item) {
      $component->addParentFieldDelta($this->getFieldDefinition(), $delta);
      $entity = $item->entity;
      if ($entity) {
        if (!$entity->id()) {
          // Double check to make sure we do not have a duplicate UUID.
          $query = \Drupal::database()->select('block_content');
          $query->condition('uuid', $entity->uuid());
          $result = $query->countQuery()->execute()->fetchField();
          if ($result) {
            $entity->set('uuid', \Drupal::service('uuid')->generate());
          }
        }
        if ($entity instanceof RevisionableInterface) {
          // Only mark for a new revision when the child entity type
          // participates in ERR's automatic save propagation (i.e. has
          // entity_revision_parent_id_field, like paragraphs). Otherwise
          // calling setNewRevision() wipes the in-memory revision_id but
          // nothing saves the child, so EntityReferenceRevisionsItem::preSave()
          // later writes NULL into the parent's target_revision_id column.
          if ($entity->getEntityType()->get('entity_revision_parent_id_field')) {
            $entity->setNewRevision();
          }
        }
        $this->exoComponentManager()->getExoComponentFieldManager()->onPreSaveLayoutBuilderEntity($component, $entity, $parent_entity);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onPostSaveLayoutBuilderEntity(FieldItemListInterface $items, EntityInterface $parent_entity) {
    parent::onPostSaveLayoutBuilderEntity($items, $parent_entity);
    $sequence_entity = $items->getEntity();
    $component = $this->getComponentDefinition();
    foreach ($items as $delta => $item) {
      $component->addParentFieldDelta($this->getFieldDefinition(), $delta);
      $entity = $item->entity;
      if ($entity && $entity->id()) {
        $this->exoComponentManager()->getExoComponentFieldManager()->onPostSaveLayoutBuilderEntity($component, $entity, $parent_entity);
      }
      // Record inline block usage so non-reusable child blocks can resolve an
      // access dependency later (e.g. the media library opener). Deep-nested
      // sequence saves can drop the in-memory child object, so fall back to the
      // stored target_id rather than skipping the usage write entirely.
      $child_id = ($entity && $entity->id()) ? $entity->id() : $item->target_id;
      if ($child_id) {
        /** @var \Drupal\layout_builder\InlineBlockUsage $inline_block_usage */
        $inline_block_usage = \Drupal::service('inline_block.usage');
        $inline_block_usage->deleteUsage([$child_id]);
        $inline_block_usage->addUsage($child_id, $sequence_entity);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onPostDeleteLayoutBuilderEntity(FieldItemListInterface $items, EntityInterface $parent_entity) {
    parent::onPostDeleteLayoutBuilderEntity($items, $parent_entity);
    $sequence_entity = $items->getEntity();
    $component = $this->getComponentDefinition();
    foreach ($items as $delta => $item) {
      $component->addParentFieldDelta($this->getFieldDefinition(), $delta);
      $entity = $item->entity;
      if ($entity) {
        $this->exoComponentManager()->getExoComponentFieldManager()->onPostDeleteLayoutBuilderEntity($component, $entity, $parent_entity);
        // We need to remove usage.
        \Drupal::service('inline_block.usage')->removeByLayoutEntity($sequence_entity);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValues(ExoComponentValues $values, FieldItemListInterface $items) {
    $entity = $items->getEntity();
    $component = $this->getComponentDefinition();
    // When a new component is passed in, we make the assumption that we need
    // the whole sequence as a standalone entity.
    if ($entity->isNew()) {
      $field_values = [];
      $base = NULL;
      foreach ($values as $delta => $value) {
        $component = $this->getComponentDefinitionWithValue($value);
        if (!$base) {
          $base = $this->exoComponentManager()->loadEntity($component);
        }
        if ($base) {
          $child = $this->exoComponentManager()->cloneEntity($component, $base);
          $this->exoComponentManager()->populateEntity($component, $child);
          $field_values[$delta] = $child;
        }
      }
      if (!empty($field_values)) {
        return $field_values;
      }
    }
    return parent::getValues($values, $items);
  }

  /**
   * {@inheritdoc}
   */
  protected function getValueEntity(ExoComponentValue $value, ?FieldItemInterface $item = NULL) {
    $component = $this->getComponentDefinitionWithValue($value);
    $entity = $this->exoComponentManager()->loadEntity($component);
    if (!$entity) {
      $entity = $this->exoComponentManager()->buildEntity($component);
    }
    else {
      $this->exoComponentManager()->populateEntity($component, $entity);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $component = $this->getComponentDefinition();
    $info = $this->exoComponentManager()->getPropertyInfo($component);
    $properties = parent::propertyInfo();
    foreach ($info as $key => $data) {
      if ($key !== '_global') {
        $properties += $data['properties'];
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredPaths() {
    $paths = [];
    $field = $this->getFieldDefinition();
    $component = $this->getComponentDefinition();
    $count = $field->getCardinality() > 1 ? $field->getCardinality() : 1;
    for ($delta = 0; $delta < $count; $delta++) {
      $component->addParentFieldDelta($field, $delta);
      $paths = array_merge($paths, $this->exoComponentManager()->getExoComponentFieldManager()->getRequiredPaths($component));
    }
    return $paths;
  }

  /**
   * Get the component definition.
   *
   * @param bool $validate
   *   (optional) If TRUE, the definition is validated.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   *   The component definition.
   */
  public function getComponentDefinition($validate = FALSE) {
    if (!isset($this->componentDefinition)) {
      $field = $this->getFieldDefinition();
      $definition = [
        'id' => $field->id(),
        'label' => $field->getComponent()->getLabel() . ': ' . $field->getLabel(),
        'description' => 'A sequenced item.',
        'fields' => $field->getAdditionalValue('sequence_fields'),
        'modifier' => $field->getAdditionalValue('sequence_modifier'),
        'modifiers' => [],
        'modifier_globals' => FALSE,
        'enhancements' => [],
        'computed' => TRUE,
      ] + $field->toArray() + $field->getComponent()->toArray();
      // Sequence fields do not need to be inherited.
      unset($definition['additional']['sequence_fields']);
      unset($definition['additional']['sequence_modifier']);
      $this->exoComponentManager()->processDefinition($definition, $this->getPluginId(), $validate);
      /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition */
      $definition->addParentField($field);
      $this->componentDefinition = $definition;
    }
    return $this->componentDefinition;
  }

  /**
   * Get the component definition with set values.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValue $value
   *   The component value.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   *   The component definition.
   */
  protected function getComponentDefinitionWithValue(ExoComponentValue $value) {
    $field = $this->getFieldDefinition();
    $definition = clone $this->getComponentDefinition();
    $definition->addParentFieldDelta($field, $value->getDelta());
    // When passed with a value, we want to make sure the defaults are set
    // correctly.
    $definition->setAdditionalValue('_delta', $value->getDelta());
    foreach ($definition->getFields() as $subfield) {
      $name = $subfield->getName();
      // Support subfields named 'value'. The 'value' value is always set
      // and will be used by the sequence. As a result, we use the _value which
      // is set in processDefinition().
      if ($name === 'value') {
        $name = '_value';
      }
      $subfield->setDefaults($value->get($name));
      $subfield->setComponent($definition);
    }
    // Because we are dynamically settings the default values, we need to let
    // the field manager process these values to make sure they are correct.
    $this->exoComponentManager()->getExoComponentFieldManager()->processComponentDefinition($definition);
    return $definition;
  }

  /**
   * Get the eXo component manager.
   *
   * @return \Drupal\exo_alchemist\ExoComponentManager
   *   The eXo component manager.
   */
  public function exoComponentManager() {
    if (!isset($this->exoComponentManager)) {
      $this->exoComponentManager = \Drupal::service('plugin.manager.exo_component');
    }
    return $this->exoComponentManager;
  }

  /**
   * Get the entity type.
   */
  protected function getEntityTypeBundles() {
    return [$this->getFieldDefinition()->safeId()];
  }

  /**
   * Create a sequence entity.
   *
   * @param array $values
   *   Values should be an array of component field name => value.
   */
  public function createSequenceEntity(array $values) {
    $sequence_definition = $this->getComponentDefinition();
    $new_values = [
      'type' => $sequence_definition->safeId(),
    ];
    foreach ($values as $key => $value) {
      if ($field = $sequence_definition->getField($key)) {
        $new_values[$field->getFieldName()] = $value;
      }
    }
    return $this->entityTypeManager()->getStorage(ExoComponentManager::ENTITY_TYPE)->create($new_values);
  }

}
