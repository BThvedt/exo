<?php

namespace Drupal\exo_alchemist;

use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\exo_alchemist\Definition\ExoComponentDefinition;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextAwarePluginManagerInterface;
use Drupal\Core\Plugin\FilteredPluginManagerTrait;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\exo_alchemist\Definition\ExoComponentDefinitionField;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldComputedInterface;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldFieldableInterface;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Plugin\PluginBase;

/**
 * Provides the Component Field plugin manager.
 */
class ExoComponentFieldManager extends DefaultPluginManager implements ContextAwarePluginManagerInterface, ExoComponentContextInterface {

  use CategorizingPluginManagerTrait;
  use ExoIconTranslationTrait;
  use FilteredPluginManagerTrait;
  use ExoComponentContextTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * An array of instances.
   *
   * @var \Drupal\exo_alchemist\Plugin\ExoComponentFieldInterface[]
   */
  protected $instances = [];

  /**
   * An array of ops urls.
   *
   * @var string[]
   */
  protected $ops;

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'storage' => [],
    'field' => [],
    'widget' => [],
    'formatter' => [],
    'properties' => [],
  ];

  /**
   * Constructs a new Entity plugin manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity last installed schema repository.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, EntityTypeManagerInterface $entity_type_manager, CacheBackendInterface $cache, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/ExoComponentField', $namespaces, $module_handler, 'Drupal\exo_alchemist\Plugin\ExoComponentFieldInterface', 'Drupal\exo_alchemist\Annotation\ExoComponentField');
    $this->alterInfo('exo_component_field_info');
    $this->setCacheBackend($cache, 'exo_component_field_info', ['exo_component_field_info']);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'exo_component_field';
  }

  /**
   * Create a plugin instance given a field definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   A field definition.
   *
   * @return \Drupal\exo_alchemist\Plugin\ExoComponentFieldInterface
   *   A field plugin instance.
   */
  public function createFieldInstance(ExoComponentDefinitionField $field) {
    $configuration = [
      'fieldDefinition' => $field,
    ];
    return $this->createInstance($field->getType(), $configuration);
  }

  /**
   * Get an instance of a plugin.
   *
   * If an instance of a given plugin id has not yet been created, a new one
   * will be created.
   *
   * This works because our plugins do not support configuration at this time.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param bool $exception_on_invalid
   *   (optional) If TRUE, an invalid plugin ID will throw an exception.
   *
   * @return \Drupal\exo_alchemist\Plugin\ExoComponentFieldInterface
   *   The component field.
   */
  public function loadInstance($plugin_id, $exception_on_invalid = TRUE) {
    if (!isset($this->instances[$plugin_id])) {
      if ($exception_on_invalid || $this->hasDefinition($plugin_id)) {
        $this->instances[$plugin_id] = $this->createInstance($plugin_id);
      }
      else {
        $this->instances[$plugin_id] = NULL;
      }
    }
    return $this->instances[$plugin_id];
  }

  /**
   * Process component definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param bool $validate
   *   (optional) If TRUE, the definition is validated.
   */
  public function processComponentDefinition(ExoComponentDefinition $definition, $validate = TRUE) {
    foreach ($definition->getFields() as $plugin_id => $field) {
      if (!$this->hasDefinition($field->getType())) {
        $field->setType($this->getFallbackPluginId($plugin_id, []));
      }
      $instance = $this->createFieldInstance($field);
      $instance->processDefinition();
      if (
        !$definition->isInstalled() &&
        $field->isRequired() &&
        !$field->isEditable() &&
        empty($field->getDefaults()) &&
        empty($field->getEntityField()) &&
        !$field->isComputed()
      ) {
        throw new PluginException(sprintf('eXo Component Field plugin property (%s) in (%s) is required and not editable but does not supply a default.', $field->getType(), $definition['label']));
      }
      if ($validate && $instance instanceof ExoComponentFieldFieldableInterface) {
        // Validate previews.
        $values = ExoComponentValues::fromFieldDefaults($field);
        foreach ($values as $value) {
          $instance->validateValue($value);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    return 'broken';
  }

  /**
   * Check if installed definition is different than code definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $to_definition
   *   The component definition.
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition|null $from_definition
   *   The component definition.
   *
   * @return bool
   *   TRUE if installed definition is different than code definition.
   */
  public function installedDefinitionHasChanges(ExoComponentDefinition $to_definition, ExoComponentDefinition $from_definition) {
    $changes = $this->getEntityBundleFieldChanges($to_definition, $from_definition);
    return !empty($changes['add']) || !empty($changes['update']) || !empty($changes['remove']) || !empty($changes['orphan']);
  }

  /**
   * Get the field changes given a definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $to_definition
   *   The component definition.
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition|null $from_definition
   *   The component definition.
   *
   * @return array
   *   An array containing ['add' => [], 'update' => [], 'remove' => []].
   */
  public function getEntityBundleFieldChanges(ExoComponentDefinition $to_definition, ?ExoComponentDefinition $from_definition = NULL) {
    $changes = [
      'add' => [],
      'update' => [],
      'remove' => [],
      'orphan' => [],
    ];
    $entity_type = ExoComponentManager::ENTITY_TYPE;
    $bundle = $to_definition->safeId();
    $to_fields = $to_definition->getFields();
    if (!$from_definition) {
      $changes['add'] = $to_fields;
    }
    else {
      $from_fields = $from_definition->getFields();
      $changes['add'] = array_diff_key($to_fields, $from_fields);
      $changes['update'] = array_filter(array_intersect_key($to_fields, $from_fields), function ($field) use ($from_fields) {
        return $field->toArray() !== $from_fields[$field->getName()]->toArray();
      });
      $changes['remove'] = array_diff_key($from_fields, $to_fields);
      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
      $form_display = \Drupal::service('plugin.manager.exo_component')->getEntityTypeFormDisplay($to_definition);
      foreach ($to_fields as $to_name => $to_field) {
        if (!isset($from_fields[$to_field->getName()])) {
          continue;
        }
        $from_field = $from_fields[$to_field->getName()];
        $from_field_instance = $this->createFieldInstance($from_field);
        $to_field_instance = $this->createFieldInstance($to_field);
        $from_field_storage = FieldStorageConfig::loadByName($entity_type, $to_field->getFieldName());
        /** @var \Drupal\field\FieldConfigInterface $from_field_config */
        $from_field_config = FieldConfig::loadByName($entity_type, $bundle, $to_field->getFieldName());
        // Allow field instance to act on changes.
        $to_field_instance->onFieldChanges($changes, $from_field_instance, $from_field_storage, $from_field_config);

        if (!isset($changes['update'][$to_name]) && ($to_instance = $this->createFieldInstance($to_field)) && ($from_instance = $this->createFieldInstance($from_fields[$to_field->getName()]))) {
          if ($to_instance instanceof ExoComponentFieldFieldableInterface && $from_instance instanceof ExoComponentFieldFieldableInterface) {
            $widget_config = $to_instance->getWidgetConfig();
            if ($component = $form_display->getComponent($to_field->safeId())) {
              if (empty($widget_config['type']) || $widget_config['type'] !== $component['type']) {
                $changes['update'][$to_name] = $to_field;
              }
              elseif (isset($widget_config['settings'])) {
                // Only use settings specified in the component config.
                $config_overlap = array_intersect_key($component['settings'], $widget_config['settings']);
                if ($widget_config['settings'] !== $config_overlap) {
                  $changes['update'][$to_name] = $to_field;
                }
              }
            }
          }
        }
      }

      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
      $field_manager = \Drupal::service('entity_field.manager');
      $field_definitions = $field_manager->getFieldDefinitions($entity_type, $to_definition->safeId());
      foreach ($field_definitions as $id => $definition) {
        if (substr($id, 0, 10) == 'exo_field_') {
          $changes['orphan'][$id] = $definition;
        }
      }
      foreach ($to_fields as $id => $field) {
        unset($changes['orphan'][$field->safeId()]);
      }
    }
    return $changes;
  }

  /**
   * Install the config entity used as the entity type.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity used as the entity type.
   */
  public function installEntityType(ExoComponentDefinition $definition, ConfigEntityInterface $entity) {
    foreach ($definition->getFields() as $field) {
      $component_field = $this->createFieldInstance($field);
      $component_field->onInstall($entity);
    }
  }

  /**
   * Update the config entity used as the entity type.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity used as the entity type.
   */
  public function updateEntityType(ExoComponentDefinition $definition, ConfigEntityInterface $entity) {
    foreach ($definition->getFields() as $field) {
      $component_field = $this->createFieldInstance($field);
      $component_field->onUpdate($entity);
    }
  }

  /**
   * Delete the config entity used as the entity type.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity used as the entity type.
   */
  public function uninstallEntityType(ExoComponentDefinition $definition, ConfigEntityInterface $entity) {
    foreach ($definition->getFields() as $field) {
      $component_field = $this->createFieldInstance($field);
      $component_field->onUninstall($entity);
      $this->uninstallEntityField($field);
    }
  }

  /**
   * Build content type bundle fields as defined in definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display
   *   The form display for the entity type.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display
   *   The view display for the entity type.
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $original_definition
   *   The current component definition.
   */
  public function buildEntityType(ExoComponentDefinition $definition, EntityFormDisplayInterface $form_display, EntityViewDisplayInterface $view_display, ?ExoComponentDefinition $original_definition = NULL) {

    $changes = $this->getEntityBundleFieldChanges($definition, $original_definition);
    $entity_type = ExoComponentManager::ENTITY_TYPE;
    $bundle = $definition->safeId();
    $fields = $definition->getFields();

    foreach ($changes['remove'] as $key => $field) {
      /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field */
      $field_name = $field->getFieldName();

      $this->uninstallEntityField($field);
      // Ignore alchemist_key and load all matches. We fire cleanEntityField
      // here because we must use the original definition.
      foreach (\Drupal::service('plugin.manager.exo_component')->loadEntityMultiple($original_definition, TRUE) as $entity) {
        $this->cleanEntityField($field, $entity, FALSE);
      }

      $field_config = FieldConfig::loadByName($entity_type, $bundle, $field_name);
      if ($field_config) {
        $field_config->delete();
      }
    }

    foreach ($changes['orphan'] as $key => $definition) {
      // These are ghost fields that were created and never cleaned up.
      /** @var \Drupal\field\Entity\FieldConfig $definition */
      $definition->delete();
    }

    $changed = $changes['add'] + $changes['update'];
    if (!empty($changed)) {
      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
      foreach ($changed as $name => $field) {
        /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field */
        if ($this->hasDefinition($field->getType())) {
          $component_field = $this->createFieldInstance($field);
          if ($component_field instanceof ExoComponentFieldFieldableInterface) {
            $field_name = $field->getFieldName();
            $weight = array_search($field->getName(), array_keys($fields));

            // Storage config.
            if ($config = $component_field->getStorageConfig()) {
              $field_storage_config = FieldStorageConfig::loadByName($entity_type, $field_name);
              $config = [
                'field_name' => $field_name,
                'entity_type' => $entity_type,
                'cardinality' => $field->getCardinality(),
                'translatable' => TRUE,
                'locked' => TRUE,
              ] + $config;
              if (empty($field_storage_config)) {
                $field_storage_config = FieldStorageConfig::create($config);
              }
              /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage_config */
              foreach ($config as $key => $value) {
                $field_storage_config->set($key, $value);
              }
              $field_storage_config->save();

              // Field config.
              $field_config = FieldConfig::loadByName($entity_type, $bundle, $field_name);
              $config = [
                'field_storage' => $field_storage_config,
                'bundle' => $bundle,
                'label' => $field->getLabel(),
                'description' => $field->getDescription(),
                'required' => $field->isRequired(),
                'locked' => TRUE,
              ] + $component_field->getFieldConfig() + $config;
              if (empty($field_config)) {
                $field_config = FieldConfig::create($config);
              }
              /** @var \Drupal\field\Entity\FieldConfig $field_config */
              foreach ($config as $key => $value) {
                $field_config->set($key, $value);
              }
              $field_config->save();

              // Field widget.
              if ($config = $component_field->getWidgetConfig()) {
                $form_display->setComponent($field_name, $config + [
                  'weight' => $weight,
                ]);
              }
              else {
                $form_display->removeComponent($field_name);
              }
              $form_display->removeComponent('info');

              // Field formatter.
              if ($config = $component_field->getFormatterConfig()) {
                $view_display->setComponent($field_name, $config + [
                  'weight' => $weight,
                ]);
              }
            }
          }
          if (isset($changes['add'][$name])) {
            $this->installEntityField($field);
          }
          else {
            $this->updateEntityField($field);
          }
        }
      }
    }
  }

  /**
   * Get property info.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   */
  public function getPropertyInfo(ExoComponentDefinition $definition) {
    $info = [];
    foreach ($definition->getFields() as $key => $field) {

      $component_field = $this->createFieldInstance($field);
      $properties = [];
      $field_properties = [
        'attributes' => t('Field attributes.'),
      ] + $component_field->propertyInfo();
      if ($field->supportsMultiple()) {
        $properties[$field->getName() . '.attributes'] = t('Field group attributes.');
      }
      foreach ($field_properties as $property => $label) {
        $property_parts = [];
        $property_parts[] = $field->getName();
        if ($field->supportsMultiple()) {
          $property_parts[] = 'value.%';
        }
        $property_parts[] = $property;
        $properties[implode('.', $property_parts)] = $label;
      }
      $info[$key] = [
        'label' => $field->getLabel(),
        'properties' => $properties,
      ];
    }
    return $info;
  }

  /**
   * Get a list of paths to fields that require configuration pre create.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   *
   * @return string[]
   *   An array of field paths.
   */
  public function getRequiredPaths(ExoComponentDefinition $definition) {
    $paths = [];
    foreach ($definition->getFields() as $field) {
      $component_field = $this->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        $paths = array_merge($paths, $component_field->getRequiredPaths($field));
      }
    }
    return $paths;
  }

  /**
   * Populate content entity.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to populate.
   */
  public function populateEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity) {
    foreach ($definition->getFields() as $field) {
      $this->populateEntityField($field, $entity);
    }
    $this->populateEntityInstances($definition, $entity);
    return $entity;
  }

  /**
   * Populate entity with initial field values.
   *
   * This is used when installing and uninstalling a component. It should not
   * be used when programmatically settings component values.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The value to set to the field.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to populate.
   */
  public function populateEntityField(ExoComponentDefinitionField $field, ContentEntityInterface $entity) {
    $values = ExoComponentValues::fromFieldDefaults($field);
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        $field_name = $field->getFieldName();
        if ($entity->hasField($field_name)) {
          $value = $component_field->populateValues($values, $entity->get($field_name));
          $entity->get($field_name)->setValue($value);
        }
      }
      elseif ($component_field instanceof ExoComponentFieldComputedInterface) {
        $component_field->populateValues($values, $entity);
      }
    }
  }

  /**
   * Populate entity with initial field values.
   *
   * When a component has a new field, we iterate over all existing components
   * and set the default value of the field and hide it.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to populate.
   */
  public function populateEntityInstances(ExoComponentDefinition $definition, ContentEntityInterface $entity) {
    foreach ($this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadByProperties([
      'type' => $entity->bundle(),
    ]) as $entity_instance) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity_instance */
      if ($entity_instance->hasField('alchemist_default') && $entity_instance->get('alchemist_default')->value == 1) {
        continue;
      }
      $do_save = FALSE;
      foreach ($definition->getFields() as $field) {
        $field_name = $field->getFieldName();
        if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty() && $entity_instance->hasField($field_name) && $entity_instance->get($field_name)->isEmpty()) {
          if ($field->isHideable()) {
            static::setHiddenFieldName($entity_instance, $field->getName());
          }
          $entity_instance->get($field_name)->setValue($entity->get($field_name)->getValue());
          $do_save = TRUE;
        }
        elseif ($field->isRequired() && $entity_instance->hasField($field_name) && $entity_instance->get($field_name)->isEmpty()) {
          // If a field is required but the default entity does not have a
          // default value for this field, we need to set it to hidden. A
          // field's view() method should handle this condition.
          if ($field->isHideable()) {
            static::setHiddenFieldName($entity_instance, $field->getName());
          }
          $do_save = TRUE;
        }
      }
      if ($do_save) {
        $entity_instance->save();
      }
    }
  }

  /**
   * Set entity field value.
   *
   * This can be used when settings a component field value programmatically.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValues $values
   *   The value to set to the field.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to populate.
   */
  public function setEntityFieldValue(ExoComponentValues $values, ContentEntityInterface $entity) {
    $field = $values->getDefinition();
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        $field_name = $field->getFieldName();
        if ($entity->hasField($field_name)) {
          $entity->get($field_name)->setValue($component_field->getValues($values, $entity->get($field_name)));
        }
      }
    }
  }

  /**
   * Called on update while layout building.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to populate.
   */
  public function onDraftUpdateLayoutBuilderEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity) {
    foreach ($definition->getFields() as $field) {
      if ($this->hasDefinition($field->getType())) {
        $component_field = $this->createFieldInstance($field);
        if ($component_field instanceof ExoComponentFieldFieldableInterface) {
          $field_name = $field->getFieldName();
          if ($entity->hasField($field_name)) {
            $component_field->onDraftUpdateLayoutBuilderEntity($entity->get($field_name));
          }
        }
        elseif ($component_field instanceof ExoComponentFieldComputedInterface) {
          $component_field->onDraftUpdateLayoutBuilderEntity($entity);
        }
      }
    }
  }

  /**
   * Pre-save entity fields.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this entity.
   */
  public function onPreSaveLayoutBuilderEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity, EntityInterface $parent_entity) {
    foreach ($definition->getFields() as $field) {
      $this->onPreSaveLayoutBuilderEntityField($field, $entity, $parent_entity);
    }
  }

  /**
   * Pre-save field.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this entity.
   */
  public function onPreSaveLayoutBuilderEntityField(ExoComponentDefinitionField $field, ContentEntityInterface $entity, EntityInterface $parent_entity) {
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        $field_name = $field->getFieldName();
        if ($entity->hasField($field_name)) {
          $component_field->onPreSaveLayoutBuilderEntity($entity->get($field_name), $parent_entity);
        }
      }
      elseif ($component_field instanceof ExoComponentFieldComputedInterface) {
        $component_field->onPreSaveLayoutBuilderEntity($entity, $parent_entity);
      }
    }
  }

  /**
   * Post-save entity fields.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this entity.
   */
  public function onPostSaveLayoutBuilderEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity, EntityInterface $parent_entity) {
    foreach ($definition->getFields() as $field) {
      $this->onPostSaveLayoutBuilderEntityField($field, $entity, $parent_entity);
    }
  }

  /**
   * Post-save field.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this entity.
   */
  public function onPostSaveLayoutBuilderEntityField(ExoComponentDefinitionField $field, ContentEntityInterface $entity, EntityInterface $parent_entity) {
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        $field_name = $field->getFieldName();
        if ($entity->hasField($field_name)) {
          $component_field->onPostSaveLayoutBuilderEntity($entity->get($field_name), $parent_entity);
        }
      }
      elseif ($component_field instanceof ExoComponentFieldComputedInterface) {
        $component_field->onPostSaveLayoutBuilderEntity($entity, $parent_entity);
      }
    }
  }

  /**
   * Delete entity fields.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this entity.
   */
  public function onPostDeleteLayoutBuilderEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity, EntityInterface $parent_entity) {
    foreach ($definition->getFields() as $field) {
      $this->onPostDeleteLayoutBuilderEntityField($field, $entity, $parent_entity);
    }
  }

  /**
   * Delete entity field.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this entity.
   */
  public function onPostDeleteLayoutBuilderEntityField(ExoComponentDefinitionField $field, ContentEntityInterface $entity, EntityInterface $parent_entity) {
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        $field_name = $field->getFieldName();
        if ($entity->hasField($field_name)) {
          $component_field->onPostDeleteLayoutBuilderEntity($entity->get($field_name), $parent_entity);
        }
      }
      elseif ($component_field instanceof ExoComponentFieldComputedInterface) {
        $component_field->onPostDeleteLayoutBuilderEntity($entity, $parent_entity);
      }
    }
  }

  /**
   * Install entity fields.
   *
   * Called when fields are being added to a component.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   */
  public function installEntityFields(ExoComponentDefinition $definition, ContentEntityInterface $entity) {
    foreach ($definition->getFields() as $field) {
      $this->installEntityField($field, $entity);
    }
  }

  /**
   * Install field.
   *
   * Called with a field is being added to a component.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The component definition.
   */
  public function installEntityField(ExoComponentDefinitionField $field) {
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      $component_field->onFieldInstall();
    }
  }

  /**
   * Update entity fields.
   *
   * Called when fields are being updated within a component.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   */
  public function updateEntityFields(ExoComponentDefinition $definition) {
    foreach ($definition->getFields() as $field) {
      $this->updateEntityField($field);
    }
  }

  /**
   * Update field.
   *
   * Called when a field is being updated within a component.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The component definition.
   */
  public function updateEntityField(ExoComponentDefinitionField $field) {
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      $component_field->onFieldUpdate();
    }
  }

  /**
   * Uninstall entity fields.
   *
   * Called when fields are being removed from a component.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   */
  public function uninstallEntityFields(ExoComponentDefinition $definition) {
    foreach ($definition->getFields() as $field) {
      $this->uninstallEntityField($field);
    }
  }

  /**
   * Uninstall field.
   *
   * Called with a field is being removed from a component.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The component definition.
   */
  public function uninstallEntityField(ExoComponentDefinitionField $field) {
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      $component_field->onFieldUninstall();
    }
  }

  /**
   * Clean entity fields.
   *
   * Called when fields are being removed from a component.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param bool $update
   *   If this is a field update.
   */
  public function cleanEntityFields(ExoComponentDefinition $definition, ContentEntityInterface $entity, $update = TRUE) {
    foreach ($definition->getFields() as $field) {
      $this->cleanEntityField($field, $entity, $update);
    }
  }

  /**
   * Clean field.
   *
   * Called with a field is being removed from a component.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param bool $update
   *   If this is a field update.
   */
  public function cleanEntityField(ExoComponentDefinitionField $field, ContentEntityInterface $entity, $update = TRUE) {
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        $field_name = $field->getFieldName();
        if ($entity->hasField($field_name)) {
          $component_field->onFieldClean($entity->get($field_name), $update);
        }
      }
      elseif ($component_field instanceof ExoComponentFieldComputedInterface) {
        $component_field->onFieldClean($entity, $update);
      }
    }
  }

  /**
   * Clone content entity for fields.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param bool $all
   *   Flag that determines if this is a partial clone or full clone.
   */
  public function cloneEntityFields(ExoComponentDefinition $definition, ContentEntityInterface $entity, $all = FALSE) {
    foreach ($definition->getFields() as $field) {
      $this->cloneEntityField($field, $entity, $all);
    }
  }

  /**
   * Clone field.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param bool $all
   *   Flag that determines if this is a partial clone or full clone.
   */
  public function cloneEntityField(ExoComponentDefinitionField $field, ContentEntityInterface $entity, $all) {
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        $field_name = $field->getFieldName();
        if ($entity->hasField($field_name)) {
          $component_field->onClone($entity->get($field_name), $all);
        }
      }
      elseif ($component_field instanceof ExoComponentFieldComputedInterface) {
        $component_field->onClone($entity, $all);
      }
    }
  }

  /**
   * Restore content entity values for fields.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param bool $force
   *   If TRUE, will force restore.
   */
  public function restoreEntityFields(ExoComponentDefinition $definition, ContentEntityInterface $entity, $force = FALSE) {
    foreach ($definition->getFields() as $field) {
      $this->restoreEntityField($field, $entity, $force);
    }
  }

  /**
   * Restore field.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param bool $force
   *   If TRUE, will force restore.
   */
  public function restoreEntityField(ExoComponentDefinitionField $field, ContentEntityInterface $entity, $force = FALSE) {
    if ($this->hasDefinition($field->getType())) {
      $component_field = $this->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        $field_name = $field->getFieldName();
        if ($entity->hasField($field_name)) {
          $values = ExoComponentValues::fromFieldDefaults($field);
          $value = $component_field->onFieldRestore($values, $entity->get($field_name), $force);
          if ($value) {
            $entity->get($field_name)->setValue($value);
          }
        }
      }
    }
  }

  /**
   * Check if entity should be displayed.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   * @param bool $return_as_object
   *   If TRUE, will return access as object.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity, array $contexts, AccountInterface $account, $return_as_object) {
    $access = $this->entityFieldAccess($definition, $entity, $contexts, $account);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Check if entity should be displayed.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function entityFieldAccess(ExoComponentDefinition $definition, ContentEntityInterface $entity, array $contexts, AccountInterface $account) {
    foreach ($definition->getFields() as $field) {
      $component_field = $this->createFieldInstance($field);
      // Apply context.
      if ($component_field instanceof ContextAwarePluginInterface) {
        if (!empty($component_field->getContexts())) {
          \Drupal::service('context.handler')->applyContextMapping($component_field, $contexts);
        }
      }
      // If even a single field allows access.
      if ($component_field instanceof ExoComponentFieldFieldableInterface) {
        if ($component_field->access($entity->get($field->getFieldName()), $contexts, $account, FALSE)) {
          return AccessResult::allowed();
        }
      }
      elseif ($component_field instanceof ExoComponentFieldComputedInterface) {
        if ($component_field->access($contexts, $account, FALSE)) {
          return AccessResult::allowed();
        }
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * View content entity for definition as values.
   *
   * Values are broken out this way so sequence and other nested fields can
   * access the raw values before they are turned into attributes.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param array $values
   *   The values array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Plugin\Context\Context[] $default_contexts
   *   An array of current contexts.
   */
  public function viewEntityValues(ExoComponentDefinition $definition, array &$values, ContentEntityInterface $entity, array $default_contexts) {
    $is_layout_builder = $this->isLayoutBuilder($default_contexts);
    $is_preview = $this->isPreview($default_contexts);
    $is_component_locked = $is_layout_builder ? $definition->isLocked() : FALSE;
    $hidden_fields = self::getHiddenFieldNames($entity);
    foreach ($definition->getFields() as $field) {
      if ($this->hasDefinition($field->getType())) {
        $component_field = $this->createFieldInstance($field);
        $contexts = $default_contexts;
        $component_field->alterContexts($entity, $contexts);
        $component_field->addCacheableDependency($contexts, $entity);
        $is_locked = $is_component_locked ?: $component_field->isComputed($contexts) ?: $component_field->isLocked($contexts) && !$this->isDefaultStorage($contexts);
        $is_hidden = isset($hidden_fields[$field->getName()]);
        if ($is_preview && \Drupal::request()->query->get('show-hidden')) {
          if (!$field->isInvisible()) {
            $is_hidden = FALSE;
          }
        }
        if ($field->isFilter()) {
          $is_hidden = FALSE;
        }
        $field_name = $field->getFieldName();
        $attribute_type = $field->getType();
        $attribute_name = $field->getName();
        if ($alias = $field->getAlias()) {
          $alias_field = $definition->getField($alias);
          if ($alias_field) {
            $attribute_type = $alias_field->getType();
            $attribute_name = $alias_field->getName();
          }
        }
        $attributes = [
          'class' => [
            'type--' . Html::getClass(str_replace(PluginBase::DERIVATIVE_SEPARATOR, '-', $attribute_type)),
            'name--' . Html::getClass($attribute_name),
          ],
        ];

        // Apply context.
        if ($component_field instanceof ContextAwarePluginInterface) {
          if (!empty($component_field->getContexts())) {
            $component_field->setContextMapping(['entity' => 'layout_builder.entity']);
            if (isset($contexts['entity'])) {
              $component_field->setContextMapping(['entity' => 'entity']);
            }
            \Drupal::service('context.handler')->applyContextMapping($component_field, $contexts);
          }
        }
        $field_values = [];
        $is_fieldable = $component_field instanceof ExoComponentFieldFieldableInterface;
        $is_computed = $component_field instanceof ExoComponentFieldComputedInterface;
        if ($is_fieldable) {
          if ($entity->hasField($field_name)) {
            /** @var \Drupal\exo_alchemist\Plugin\ExoComponentFieldFieldableInterface $component_field */
            $field_values = $component_field->view($entity->get($field_name), $contexts);
          }
        }
        elseif ($is_computed) {
          /** @var \Drupal\exo_alchemist\Plugin\ExoComponentFieldComputedInterface $component_field */
          $field_values = $component_field->view($entity, $contexts);
        }

        $output = [];
        $ops = [];
        $ops_allow = [];
        $config = [
          'path' => $component_field->getParentsAsPath(),
        ];
        if ($is_layout_builder && !$is_locked) {
          $ops = $this->getOperations();
          $values['#attached']['drupalSettings']['exoAlchemist']['fieldOps'] = $ops;
          $description = $field->getLabel();
          if ($definition->isGlobal()) {
            $description = $this->icon($description ?? '')->setIcon('regular-globe');
          }
          $ops_allow = array_flip(array_keys($ops));
          $total_values = count($field_values);
          if ($field->getCardinality() === 1) {
            unset($ops_allow['clone']);
            unset($ops_allow['prev']);
            unset($ops_allow['next']);
          }
          elseif ($field->supportsUnlimited()) {
            if ($field->getMax() === $total_values) {
              unset($ops_allow['clone']);
            }
            if ($field->getMin() === $total_values) {
              unset($ops_allow['remove']);
            }
          }
          else {
            if ($field->getMin() === $total_values) {
              unset($ops_allow['remove']);
            }
            if ($field->getCardinality() == $total_values) {
              unset($ops_allow['clone']);
            }
          }
          if (!$component_field->isHideable($contexts)) {
            unset($ops_allow['hide']);
          }
          if (!$component_field->isEditable($contexts)) {
            unset($ops_allow['edit']);
            unset($ops_allow['clone']);
            unset($ops_allow['prev']);
            unset($ops_allow['next']);
            unset($ops_allow['remove']);
          }
          if ((!$component_field->isRemoveable($contexts) && $total_values === 1) || $total_values === 1) {
            unset($ops_allow['remove']);
          }
          $config = [
            'label' => $field->getLabel(),
            'description' => $description,
            'width' => $component_field->getFieldDefinition()->getModalWidth() ?? 700,
          ] + $config;
        }
        // When empty, make sure we have an empty field so that attributes
        // are generated.
        if (($is_layout_builder || $is_preview) && empty($field_values)) {
          $field_values[] = [];
        }
        foreach ($field_values as $delta => &$value) {
          $component_field->addCacheableDependency($contexts, $value);
          $is_empty = empty($value);
          $field_attributes = $attributes;
          // Properties can be sent through with the value.
          if (is_array($value)) {
            foreach (Element::properties($value) as $key) {
              if (isset($values[$key]) && is_array($values[$key])) {
                $values[$key] = NestedArray::mergeDeep($values[$key], $value[$key]);
              }
              if ($key === '#field_attributes') {
                $field_attributes = NestedArray::mergeDeep($field_attributes, $value[$key]);
              }
              // Allow components to control access to the component entirely.
              if ($key === '#component_access') {
                $values['#access'] = $values['#access'] ?? $value['#component_access'];
              }
              unset($value[$key]);
            }
          }
          // Properties can be sent through as a standalone item.
          elseif (Element::property($delta)) {
            if (isset($values[$delta]) && is_array($values[$delta])) {
              $values[$delta] = NestedArray::mergeDeep($values[$delta], $value);
            }
            continue;
          }
          if ($is_layout_builder && !$is_locked) {
            $ops_allow_delta = $ops_allow;
            if ($field->getCardinality() !== 1) {
              if ($delta === 0) {
                unset($ops_allow_delta['prev']);
              }
              if ($delta + 1 === $total_values) {
                unset($ops_allow_delta['next']);
              }
            }
            if (empty($ops_allow_delta)) {
              $description = $this->icon('This element cannot be changed.')->setIcon('regular-lock');
            }
            $delta_config = [
              'field_delta' => $delta,
              'ops' => array_keys($ops_allow_delta),
            ] + $config;
            if (!$field->isComputed()) {
              $delta_config['path'] = $component_field->getItemParentsAsPath($delta);
            }
            $field_attributes['id'] = Html::getId($delta_config['path'] . $entity->uuid());
            $field_attributes['data-exo-field'] = json_encode($delta_config);
          }
          $value['attributes'] = new ExoComponentAttribute($field_attributes);
          // If the field is locked, we need to make sure it is not editable.
          $value['attributes']->setAsLayoutBuilder(!$is_locked && $is_layout_builder);
          $is_editable = $is_layout_builder && empty($field->getGroup()) && !empty($ops_allow);
          $value['attributes']->editable($is_editable);
          // Expose attributes so that they can be used even when field is
          // empty.
          if ($is_layout_builder || $is_preview) {
            $values['#preview_field_attributes'][$field->getName()] = $value['attributes'];
          }
          if (!$is_empty && !$is_hidden) {
            $output[$delta] = $value;
          }
        }
        // If field support multiple values.
        if (!empty($output) && $field->supportsMultiple()) {
          $attributes = [
            'class' => [
              'group--' . Html::getClass($field->getName()),
              'exo-component-group',
            ],
          ];
          if ($is_layout_builder) {
            unset($ops_allow['clone']);
            unset($ops_allow['prev']);
            unset($ops_allow['next']);
            $attributes['id'] = Html::getId($config['path'] . $entity->uuid());
            $attributes['data-exo-field'] = json_encode([
              'ops' => array_keys($ops_allow),
            ] + $config);
          }
          $attributes = new ExoComponentAttribute($attributes);
          $attributes->setAsLayoutBuilder($is_layout_builder);
          if (!empty($ops) && $is_layout_builder && $component_field->isHideable($contexts)) {
            $attributes->addFieldOp('hide', $this->t('Hide All'), $ops['hide']['icon'], $ops['hide']['description'], $ops['hide']['url']);
          }
          $output = [
            'value' => $output,
            'attributes' => $attributes,
          ];
        }
        // If field only supports a single value.
        else {
          if (is_array($output) && count($output) == 1) {
            $output = reset($output);
          }
        }
        $values[$field->getName()] = $output;
      }
    }
  }

  /**
   * Get ops url placeholders.
   *
   * @return array
   *   An array of tokenized urls.
   */
  protected function getOperations() {
    if (!isset($this->ops)) {
      $this->ops = [];
      $ops = [
        'edit' => [
          'label' => $this->t('Edit'),
          'route' => 'layout_builder.field.update',
          'description' => $this->t('Make changes to the component element.'),
        ],
        'clone' => [
          'label' => $this->t('Clone'),
          'route' => 'layout_builder.field.clone',
        ],
        'prev' => [
          'label' => $this->t('Prev'),
          'route' => 'layout_builder.field.prev',
        ],
        'next' => [
          'label' => $this->t('Next'),
          'route' => 'layout_builder.field.next',
        ],
        'remove' => [
          'label' => $this->t('Remove'),
          'route' => 'layout_builder.field.remove',
          'description' => $this->t('Remove this component element.'),
        ],
        'hide' => [
          'label' => $this->t('Hide'),
          'route' => 'layout_builder.field.hide',
        ],
      ];
      $this->moduleHandler->alter('exo_component_field_ops', $ops);
      foreach ($ops as $key => $data) {
        $icon = $this->icon($data['label'])->match([
          'exo_alchemist',
          'local_task',
          'admin',
        ], $key);
        $this->ops[$key] = [
          'label' => $data['label'],
          'description' => !empty($data['description']) ? $data['description'] : '',
          'url' => Url::fromRoute($data['route'], [
            'section_storage_type' => '-section_storage_type-',
            'section_storage' => '-section_storage-',
            'delta' => '-delta-',
            'region' => '-region-',
            'uuid' => '-uuid-',
            'path' => '-path-',
          ])->toString(),
          'title' => $icon->toString(),
          'icon' => $icon->getIcon() ? $icon->getIcon()->getId() : '',
        ];
      }
    }
    return $this->ops;
  }

  /**
   * Get an array of field names that are set to be hidden.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component entity.
   *
   * @return array
   *   An array of field names.
   */
  public static function getHiddenFieldNames(ContentEntityInterface $entity) {
    $ids = [];
    $data = ExoComponentManager::getFieldData($entity);
    if (!empty($data['hidden'])) {
      $ids = array_combine($data['hidden'], $data['hidden']);
    }
    return $ids;
  }

  /**
   * Set fields as hidden.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component entity.
   * @param array $component_field_names
   *   An array of field names. Ids should be stored as the value of the array.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|false
   *   Will return the entity if value successfully set.
   */
  public static function setHiddenFieldNames(ContentEntityInterface $entity, array $component_field_names) {
    $data = ExoComponentManager::getFieldData($entity);
    if (!empty($component_field_names)) {
      $data['hidden'] = $component_field_names;
    }
    else {
      unset($data['hidden']);
    }
    return ExoComponentManager::setFieldData($entity, $data);
  }

  /**
   * Set fields as hidden.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component entity.
   * @param string $component_field_name
   *   The field name to hide.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|false
   *   Will return the entity if value successfully set.
   */
  public static function setHiddenFieldName(ContentEntityInterface $entity, $component_field_name) {
    $data = ExoComponentManager::getFieldData($entity);
    $data += ['hidden' => []];
    if (is_array($data['hidden']) && !in_array($component_field_name, $data['hidden'])) {
      $data['hidden'][] = $component_field_name;
    }
    return ExoComponentManager::setFieldData($entity, $data);
  }

  /**
   * Set fields as visible.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component entity.
   * @param string $component_field_name
   *   The field name to hide.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|false
   *   Will return the entity if value successfully set.
   */
  public static function setVisibleFieldName(ContentEntityInterface $entity, $component_field_name) {
    $data = ExoComponentManager::getFieldData($entity);
    $data += ['hidden' => []];
    if (is_array($data['hidden'])) {
      $data['hidden'] = array_filter($data['hidden'], function ($name) use ($component_field_name) {
        return $name !== $component_field_name;
      });
    }
    return ExoComponentManager::setFieldData($entity, $data);
  }

}
