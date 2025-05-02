<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_alchemist\ExoComponentValue;
use Drupal\exo_alchemist\Form\ExoFieldUpdateForm;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldFieldableBase;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldPreviewEntityTrait;
use Drupal\exo_alchemist\Plugin\Field\FieldWidget\EntityFieldWidget;
use Drupal\exo_alchemist\Plugin\SectionStorage\ExoOverridesSectionStorage;
use Drupal\layout_builder\DefaultsSectionStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A 'field' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "field",
 *   deriver = "\Drupal\exo_alchemist\Plugin\Derivative\ExoComponentFieldEntityDeriver"
 * )
 */
class EntityField extends ExoComponentFieldFieldableBase implements ContextAwarePluginInterface, ContainerFactoryPluginInterface {

  use ExoComponentFieldPreviewEntityTrait;
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The bundle ID.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The field name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * Constructs a new FieldBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    $this->entityTypeId = static::getEntityTypeIdFromPluginId($plugin_id);
    $this->bundle = static::getBundleFromPluginId($plugin_id);
    $this->fieldName = static::getFieldNameFromPluginId($plugin_id);
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.channel.exo_alchemist')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageConfig() {
    return [
      'type' => 'exo_alchemist_map',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetConfig() {
    return [
      'type' => 'entity_field_widget',
      'settings' => [
        'entity_type_id' => $this->entityTypeId,
        'bundle' => $this->bundle,
        'field_name' => $this->fieldName,
        'default_formatter' => $this->pluginDefinition['default_formatter'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = [
      'value' => $this->t('Boolean indicating if entity has value.'),
      'render' => $this->t('The rendered field.'),
    ];
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatterSettings() {
    $field = $this->getFieldDefinition();
    return ($field->getAdditionalValue('formatter_settings') ?: []) + [
      'label' => 'hidden',
      'type' => $this->pluginDefinition['default_formatter'],
      'settings' => [],
      'third_party_settings' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEditable(array $contexts) {
    // Entity fields are attached to an entity and should be able to be edited
    // even when within locked sections.
    return $this->getFieldDefinition()->isEditable();
  }

  /**
   * Extending classes can use this method to set individual values.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValue $value
   *   The field value.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The current item.
   *
   * @return mixed
   *   A value suitable for setting to \Drupal\Core\Field\FieldItemInterface.
   */
  protected function getValue(ExoComponentValue $value, ?FieldItemInterface $item = NULL) {
    return ['value' => $value->toArray()];
  }

  /**
   * Get the entity of the display.
   *
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity.
   */
  protected function getReferencedEntity(array $contexts) {
    $entity = $this->getParentEntity();
    $entity_type_id = static::getEntityTypeIdFromPluginId($this->getPluginId());
    $bundle = static::getBundleFromPluginId($this->getPluginId());
    if ($this->isPreview($contexts) || $this->isDefaultStorage($contexts)) {
      // Always use plugin id for entity type id and bundle as these will be
      // the root entity.
      if ($entity = $this->getPreviewEntity($entity_type_id, $bundle)) {
        \Drupal::messenger()->addMessage($this->t('This component is being previewed using <a href="@url">@label</a>.', [
          '@url' => $entity->toUrl()->toString(),
          '@label' => $entity->getEntityType()->getLabel() . ': ' . $entity->label(),
        ]), 'alchemist');
      }
      else {
        \Drupal::messenger()->addWarning($this->t('Please create a @entity_type_id:@bundle entity to improve preview.', [
          '@entity_type_id' => $entity_type_id,
          '@bundle' => $bundle,
        ]));
      }
    }
    elseif ($entity && ($entity->getEntityTypeId() !== $entity_type_id || ($bundle && $entity->bundle() !== $bundle))) {
      return NULL;
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, array $contexts) {
    $field = $this->getFieldDefinition();
    $entity = $this->getReferencedEntity($contexts);
    $contexts['layout_builder.entity'] = EntityContext::fromEntity($entity);
    unset($contexts['entity']);
    if ($items->isEmpty()) {
      $items->appendItem();
    }
    $placeholder = [
      'value' => FALSE,
      'render' => $this->componentPlaceholderDefault($this->t('@label Placeholder', [
        '@label' => $field->getLabel(),
      ]), $this->t('This box will be replaced with the actual field content if it has been set.')),
    ];
    if ($entity && $entity->hasField($this->fieldName)) {
      if ($entity->get($this->fieldName)->isEmpty()) {
        if ($this->isLayoutBuilder($contexts)) {
          return [$placeholder];
        }
        return [
          [
            'value' => FALSE,
          ],
        ];
      }
      return parent::view($items, $contexts);
    }
    if ($this->isPreview($contexts)) {
      // When previewing, create a sample entity and inject our default values.
      $field_manager = \Drupal::service('entity_field.manager');
      $field_map = $field_manager->getFieldMap();
      if (isset($field_map[$this->entityTypeId][$this->fieldName])) {
        $bundle = reset($field_map[$this->entityTypeId][$this->fieldName]['bundles']);
        $sample_entity = \Drupal::service('layout_builder.sample_entity_generator')->get($this->entityTypeId, $bundle);
        $this->setContext('entity', EntityContext::fromEntity($sample_entity));
        $build = parent::view($items, $contexts);
        if (!empty($build[0]['value'])) {
          return $build;
        }
      }
      return [$placeholder];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    $display_settings = !empty($item->getValue()['value']) ? $item->getValue()['value'] : $this->getFormatterSettings();
    try {
      $parent_entity = $this->getParentEntity();
      if (!empty($parent_entity->in_preview)) {
        if ($sample = $this->getFieldDefinition()->getAdditionalValue('sample')) {
          $parent_entity->get($this->fieldName)->setValue([$sample]);
        }
      }
      $field = $parent_entity->get($this->fieldName);
      if ($field->isEmpty()) {
        return [
          'value' => FALSE,
          'render' => NULL,
        ];
      }
      $build = $field->view($display_settings);
    }
    // @todo Remove in https://www.drupal.org/project/drupal/issues/2367555.
    catch (EnforcedResponseException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $build = [];
      $message = 'The field "%field" failed to render with the error of "%error".';
      $args = ['%field' => $this->fieldName, '%error' => $e->getMessage()];
      $this->logger->warning($message, $args);
      if ($this->isPreview($contexts)) {
        \Drupal::messenger()->addWarning($this->t($message, $args));
      }
    }
    CacheableMetadata::createFromRenderArray($build)->addCacheableDependency($this)->applyTo($build);
    return [
      'value' => TRUE,
      'render' => $build,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function widgetAlter(WidgetInterface $widget, FormStateInterface $form_state) {
    // The bundle is not always provided. We need to add it if possible.
    if (empty($this->bundle)) {
      $form_object = $form_state->getFormObject();
      if ($form_object instanceof ExoFieldUpdateForm && $widget instanceof EntityFieldWidget) {
        $section_storage = $form_object->getSectionStorage();
        if ($section_storage instanceof DefaultsSectionStorageInterface) {
          $widget->setBundle($section_storage->getContext('display')->getContextValue()->getTargetBundle());
        }
        if ($section_storage instanceof ExoOverridesSectionStorage) {
          $widget->setBundle($section_storage->getEntity()->bundle());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ExoFieldUpdateForm) {
      $section_storage = $form_object->getSectionStorage();
      if ($section_storage instanceof ExoOverridesSectionStorage && ($entity = $section_storage->getEntity())) {
        $field = $this->getFieldDefinition();
        $form['widget']['#access'] = $field->getAdditionalValue('allow_per_item_format');
        $form_state->set('entity', $entity);
        /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
        $form_display = $this->entityTypeManager->getStorage('entity_form_display')->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $section_storage->getViewMode());
        if ($form_display) {
          $form_state->set('form_display', $form_display);
          /** @var \Drupal\Core\Field\WidgetInterface $widget */
          $widget = $form_display->getRenderer($this->fieldName);
          if ($widget) {
            $form['entity'] = [
              '#type' => 'fieldset',
              '#title' => $this->t('Element Value'),
              '#tree' => TRUE,
              '#weight' => -10,
              '#field_parents' => ['settings', 'block_form'],
              '#parents' => ['settings', 'block_form'],
            ];
            $items = $entity->get($this->fieldName);
            $items->filterEmptyItems();
            $form['entity'][$this->fieldName] = $widget->form($items, $form['entity'], $form_state);
            $form['entity'][$this->fieldName]['#access'] = $items->access('edit');
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formSubmit(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $form_state->get('form_display');
    $entity = $form_state->get('entity');
    if ($entity) {
      $form_display->extractFormValues($entity, $form['entity'], $form_state);
      $entity->_exoComponentEntityFieldSave[$this->getFieldName] = $this->getFieldName;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function componentAccess(FieldItemListInterface $items, array $contexts, AccountInterface $account) {
    $parent_entity = $this->getParentEntity();

    $access = parent::componentAccess($items, $contexts, $account);

    // Check that the entity in question has this field.
    if (!$parent_entity instanceof FieldableEntityInterface || !$parent_entity->hasField($this->fieldName)) {
      return $access->andIf(AccessResult::forbidden());
    }

    // Check field access.
    $field = $parent_entity->get($this->fieldName);
    $access = $access->andIf($field->access('view', $account, TRUE));
    if (!$access->isAllowed()) {
      return $access;
    }

    // Check to see if the field has any values.
    if ($field->isEmpty()) {
      return $access->andIf(AccessResult::forbidden());
    }
    return $access;
  }

  /**
   * Get entity type id from plugin id.
   *
   * @param string $plugin_id
   *   The plugin id.
   *
   * @return string
   *   The entity type id.
   */
  public static function getEntityTypeIdFromPluginId($plugin_id) {
    $parts = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 4);
    return $parts[1];
  }

  /**
   * Get bundle id from plugin id.
   *
   * @param string $plugin_id
   *   The plugin id.
   *
   * @return string
   *   The bundle.
   */
  public static function getBundleFromPluginId($plugin_id) {
    $parts = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 4);
    if (isset($parts[3])) {
      return $parts[2];
    }
    return NULL;
  }

  /**
   * Get field name from plugin id.
   *
   * @param string $plugin_id
   *   The plugin id.
   *
   * @return string
   *   The field name.
   */
  public static function getFieldNameFromPluginId($plugin_id) {
    $parts = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 4);
    if (isset($parts[3])) {
      return $parts[3];
    }
    return $parts[2];
  }

}
