<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\exo_alchemist\ExoComponentValue;
use Drupal\exo_alchemist\ExoComponentValues;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldDisplayInterface;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldDisplayTrait;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldFieldableBase;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldInterface;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldPreviewEntityTrait;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base component for entity reference fields.
 */
class EntityReferenceBase extends ExoComponentFieldFieldableBase implements ContainerFactoryPluginInterface, ExoComponentFieldDisplayInterface {

  use ExoComponentFieldPreviewEntityTrait;
  use ExoComponentFieldDisplayTrait {
    useDisplay as traitUseDisplay;
    getViewMode as traitGetViewMode;
  }

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type to reference.
   *
   * @var string
   */
  protected $entityType;

  /**
   * Constructs a LocalActionDefault object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field */
    $field = $this->getFieldDefinition();
    $entity_type = $this->getEntityType();
    if (!$entity_type) {
      throw new PluginException(sprintf('eXo Component Field plugin (%s) must define an entity type.', $field->getType()));
    }
    if (empty($this->getEntityTypeBundles())) {
      // Default bundle same as entity type.
      $field->setAdditionalValue('bundles', [$entity_type => $entity_type]);
    }
    foreach ($field->getDefaults() as $default) {
      if ($value = $default->getValue('value')) {
        $default->setValue('target_id', $value);
      }
    }
    parent::processDefinition();
  }

  /**
   * {@inheritdoc}
   */
  public function validateValue(ExoComponentValue $value) {
    $bundles = $this->getEntityTypeBundles();
    $value->setIfUnset('bundle', reset($bundles));
    $value->setIfUnset('view_mode', 'default');
    $value->setIfUnset('custom_view_mode', FALSE);
    if ($value->has('value') && !$value->has('target_id')) {
      // We do not unset the 'value' as other fields may use this differently.
      $value->set('target_id', $value->get('value'));
    }
    parent::validateValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageConfig() {
    $type = 'entity_reference';
    $entity_types = \Drupal::entityTypeManager()->getDefinitions();
    if ($entity_types[$this->getEntityType()]->isRevisionable()) {
      $type = 'entity_reference_revisions';
    }
    return [
      'type' => $type,
      'settings' => [
        'target_type' => $this->getEntityType(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldConfig() {
    $config = [
      'settings' => [
        'handler' => 'default',
      ],
    ];
    $entity_definition = $this->entityTypeManager()->getDefinition($this->getEntityType());
    if ($entity_definition->hasKey('bundle')) {
      $config['settings']['handler_settings']['target_bundles'] = array_combine($this->getEntityTypeBundles(), $this->getEntityTypeBundles());
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetConfig() {
    if ($widget_type = $this->getFieldDefinition()->getAdditionalValue('widget_type')) {
      switch ($widget_type) {
        case 'select':
          $widget_type = 'options_select';
          break;

        case 'buttons':
          $widget_type = 'options_buttons';
          break;

        case 'exo_buttons':
          $widget_type = 'exo_options_buttons';
          break;

        case 'autocomplete':
          $widget_type = 'exo_autocomplete';
          break;
      }
      $widget_settings = $this->getFieldDefinition()->getAdditionalValue('widget_settings') ?: [];
      return [
        'type' => $widget_type,
        'settings' => $widget_settings,
      ];
    }
    else {
      return [
        'type' => 'exo_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'limit' => 10,
          'size' => 60,
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function useDisplay() {
    return $this->traitUseDisplay() && !empty($this->getFieldDefinition()->getAdditionalValue('custom_view_mode'));
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldInstall() {
    parent::onFieldInstall();
    if ($this->useDisplay()) {
      $this->onFieldInstallFieldDisplay();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldUpdate() {
    parent::onFieldUpdate();
    if ($this->useDisplay()) {
      $this->onFieldUpdateFieldDisplay();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldUninstall() {
    parent::onFieldUninstall();
    if ($this->useDisplay()) {
      $this->onFieldUninstallFieldDisplay();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldChanges(array &$changes, ExoComponentFieldInterface $from_field, ?FieldStorageConfigInterface $from_storage = NULL, ?FieldConfigInterface $from_config = NULL) {
    $field_definition = $this->getFieldDefinition();
    // Some fields, such as webform, do not have target bundles.
    if ($from_config) {
      if (!empty($target_bundles) && array_diff($this->getEntityTypeBundles(), $target_bundles)) {
        $target_bundles = $from_config->getSetting('handler_settings')['target_bundles'] ?? NULL;
        $changes['update'][$field_definition->getName()] = $field_definition;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function hasDefault() {
    foreach ($this->getFieldDefinition()->getDefaults() as $default) {
      // Components can pass in default as a boolean. When this happens, we
      // treat the component as if it has no defaults.
      if (is_bool($default->getValue('value'))) {
        return FALSE;
      }
    }
    return $this->getFieldDefinition()->hasDefault();
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, array $contexts) {
    $is_preview = $this->isPreview($contexts);
    $is_empty = $items->isEmpty();
    $is_editable = $this->isEditable($contexts);
    $is_required = $this->isRequired();
    $is_invisible = $this->isInvisible();
    if (
      // When previewing and required but empty.
      ($is_preview && $is_empty && $is_required) ||
      // When previewing, empty and invisible.
      ($is_preview && $is_empty && $is_invisible) ||
      // When empty, required and not editable.
      ($is_empty && $is_required && !$is_editable)
    ) {
      $values = [];
      $bundles = $this->getEntityTypeBundles();
      $bundle = !empty($bundles) ? reset($bundles) : NULL;
      if ($defaults = $this->getFieldDefinition()->getDefaults()) {
        $previous = [];
        foreach ($defaults as $default) {
          $entity_id = $default['target_id'] !== TRUE ? $default['target_id'] : NULL;
          $preview = $this->getPreviewEntity($this->getEntityType(), $bundle, $entity_id, $previous);
          if (!empty($previous) && !$preview) {
            $previous = [];
            $preview = $this->getPreviewEntity($this->getEntityType(), $bundle, $entity_id, $previous);
          }
          if ($preview) {
            $previous[] = $preview->id();
            $value = [
              'target_id' => $preview->id(),
            ];
            if ($preview instanceof RevisionableInterface) {
              $value['target_revision_id'] = $preview->getRevisionId();
            }
            $values[] = $value;
          }
        }
      }
      else {
        $entity = $this->getPreviewEntity($this->getEntityType(), $bundle);
        if ($entity) {
          $value = [
            'target_id' => $entity->id(),
          ];
          if ($entity instanceof RevisionableInterface) {
            $value['target_revision_id'] = $entity->getRevisionId();
          }
          $values[] = $value;
          if ($is_preview) {
            $op = $this->getFieldDefinition()->isFilter() ? 'filtered' : 'previewed';
            \Drupal::messenger()->addMessage($this->t('This component is being @op as @name using <a href="@url">@label</a>.', [
              '@name' => $this->getFieldDefinition()->getLabel(),
              '@op' => $op,
              '@url' => $entity->toUrl()->toString(),
              '@label' => $entity->getEntityType()->getLabel() . ': ' . $entity->label(),
            ]), 'alchemist');
          }
        }
      }
      $items->setValue($values);
    }
    return parent::view($items, $contexts);
  }

  /**
   * {@inheritdoc}
   */
  protected function getValue(ExoComponentValue $value, ?FieldItemInterface $item = NULL) {
    return $this->getValueEntity($value, $item);
  }

  /**
   * Extending classes can return an entity that will be set as the value.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValue $value
   *   The field value.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The current item.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An entity that will be used to set the value of the field.
   */
  protected function getValueEntity(ExoComponentValue $value, ?FieldItemInterface $item = NULL) {
    if ($value->has('target_id') && !is_bool($value->get('target_id'))) {
      return [
        'target_id' => $value->get('target_id'),
      ];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = [
      'entity' => $this->t('The entity object.'),
      'entity_id' => $this->t('The entity id.'),
      'entity_uuid' => $this->t('The entity uuid.'),
      'entity_label' => $this->t('The entity label.'),
      'entity_type_id' => $this->t('The entity type id.'),
      'entity_view_url' => $this->t('The entity canonical url.'),
      'entity_edit_url' => $this->t('The entity edit url.'),
    ];
    if ($this->useDisplay()) {
      $properties += $this->propertyInfoFieldDisplay();
    }
    elseif ($this->getViewMode()) {
      $properties['render'] = $this->t('The entity renderable.');
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    $entity = $this->getReferencedEntity($item, $contexts);
    $value = [];
    if ($entity) {
      if ($entity->getEntityTypeId() !== ExoComponentManager::ENTITY_TYPE) {
        // When not acting on a component entity, we need the most recent
        // version of the entity.
        $entity = $this->entityTypeManager()->getStorage($entity->getEntityTypeId())->load($entity->id());
      }
      $contexts['cacheable_metadata']->addCacheableDependency($entity);
      $has_canonical = $entity->hasLinkTemplate('canonical');
      // Check view access when not a component entity.
      if (!in_array($entity->getEntityTypeId(), [
        ExoComponentManager::ENTITY_TYPE,
      ]) && ($has_canonical && !$entity->access('view') && !$this->getFieldDefinition()->getAdditionalValue('skip_access_check'))) {
        return $value;
      }
      // Check status when publishable.
      if ($entity instanceof EntityPublishedInterface && !$this->getFieldDefinition()->getAdditionalValue('allow_unpublished')) {
        if (!$entity->isPublished()) {
          return $value;
        }
      }
      $value += [
        'entity' => $entity,
        'entity_id' => $entity->id() ?: $entity->uuid(),
        'entity_uuid' => $entity->uuid(),
        'entity_label' => $entity->label(),
        'entity_type_id' => $entity->getEntityTypeId(),
        'entity_view_url' => NULL,
        'entity_edit_url' => NULL,
      ];
      if (!$entity->isNew()) {
        if ($has_canonical) {
          $value['entity_view_url'] = $entity->toUrl()->toString();
        }
        if ($entity->hasLinkTemplate('edit-form')) {
          $value['entity_edit_url'] = $entity->toUrl('edit-form')->toString();
        }
      }
      if ($this->useDisplay()) {
        $contexts['layout_builder.entity'] = EntityContext::fromEntity($entity);
        unset($contexts['entity']);
        $value += $this->viewValueFieldDisplay($entity, $contexts);
      }
      elseif ($view_mode = $this->getViewMode()) {
        $value['render'] = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, $view_mode);
      }
    }
    return $value;
  }

  /**
   * Get the entity type.
   *
   * @return string
   *   The entity type.
   */
  protected function getEntityType() {
    return $this->getFieldDefinition()->getAdditionalValue('entity_type') ?: $this->entityType;
  }

  /**
   * Get the entity type.
   *
   * @return array
   *   An array of support bundles.
   */
  protected function getEntityTypeBundles() {
    $bundles = $this->getFieldDefinition()->getAdditionalValue('bundles');
    if (empty($bundles)) {
      $bundles = $this->getFieldDefinition()->getAdditionalValue('bundle');
    }
    if (empty($bundles)) {
      return [];
    }
    return is_array($bundles) ? $bundles : [$bundles => $bundles];
  }

  /**
   * Get the entity view mode.
   *
   * @return string
   *   The entity view mode.
   */
  protected function getViewMode() {
    if ($this->useDisplay()) {
      return $this->traitGetViewMode();
    }
    return $this->getFieldDefinition()->getAdditionalValue('view_mode');
  }

  /**
   * {@inheritdoc}
   *
   * Required when using a display.
   */
  public function getDisplayedEntityTypeId() {
    return $this->getEntityType();
  }

  /**
   * {@inheritdoc}
   *
   * Required when using a display.
   */
  public function getDisplayedBundle() {
    $bundles = $this->getEntityTypeBundles();
    if (!empty($bundles) && count($bundles) === 1) {
      return reset($bundles);
    }
    return NULL;
  }

  /**
   * Get the referenced entity.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param array $contexts
   *   An array of contexts.
   *
   * @return \Drupal\core\Entity\EntityInterface
   *   The entity.
   */
  protected function getReferencedEntity(FieldItemInterface $item, array $contexts) {
    $entity = $item->entity;
    if ($this->isPreview($contexts)) {
      $bundles = $this->getEntityTypeBundles();
      $bundle = !empty($bundles) ? reset($bundles) : NULL;
      if ($entity && $entity->isNew()) {
        // Do nothing. Entity has been programmatically prepared.
      }
      else {
        $entity = $this->getPreviewEntity($this->getEntityType(), $bundle, $entity ? $entity->id() : NULL);
      }
    }
    $this->addCacheableDependency($contexts, $entity);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldRestore(ExoComponentValues $values, FieldItemListInterface $items, $force = FALSE) {
    $field_values = parent::onFieldRestore($values, $items);
    if (empty($field_values) && !$items->isEmpty()) {
      $restore = FALSE;
      foreach ($items as $delta => $item) {
        if (!$item->entity) {
          // We have an entity reference but no entity. This means the entity
          // no longer exists and we need to replace it.
          $restore = TRUE;
        }
      }
      if ($restore) {
        $field_values = $this->populateValues($values, $items);
      }
    }
    return $field_values;
  }

  /**
   * Returns the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  protected function moduleHandler() {
    if (!$this->moduleHandler) {
      $this->moduleHandler = \Drupal::service('module_handler');
    }
    return $this->moduleHandler;
  }

}
