<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldComputedBase;
use Drupal\layout_builder\Section;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Provides a section base class.
 */
abstract class SectionBase extends ExoComponentFieldComputedBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The layout id.
   *
   * @var string
   */
  protected $layoutId = 'layout_onecol';

  /**
   * The layout settings.
   *
   * @var array
   */
  protected $layoutSettings = [
    'column_widths' => 'dynamic',
  ];

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
  public function propertyInfo() {
    return [
      'render' => $this->t('The rendered section.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldInstall() {
    $field = $this->getFieldDefinition();
    $display = $this->getEntityViewDisplay(ExoComponentManager::ENTITY_TYPE, $field->getComponent()->safeId());
    $display->setOverridable();
    $section = $this->getSection();
    $display->removeAllSections();
    $display->appendSection($section);
    $display->setStatus(TRUE);
    $display->save();
  }

  /**
   * {@inheritdoc}
   */
  public function onPreSaveLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity) {
    if ($entity->isNew()) {
      // Layout builder will duplicate this component giving it a new UUID when
      // the parent layout is moving from default to override. We need to
      // preserve this until onPostSaveLayoutBuilderEntity so that we
      // can pull the pending sections and save them properly. Layout builder
      // does not like asigning sections to entities that have not yet been
      // saved.
      $data = ExoComponentManager::getFieldData($entity);
      $data['uuid'] = $entity->uuid();
      ExoComponentManager::setFieldData($entity, $data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onPostSaveLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity) {
    $data = ExoComponentManager::getFieldData($entity);
    if (!empty($data['uuid'])) {
      // If we have a saved UUID, we use it to fetch the proper storage.
      $temporary_entity = $entity->createDuplicate();
      $temporary_entity->set('uuid', $data['uuid']);
      $section_storage = $this->getTemporarySectionStorage($temporary_entity, $parent_entity);
      unset($data['uuid']);
    }
    else {
      $section_storage = $this->getTemporarySectionStorage($entity, $parent_entity);
    }
    if ($section_storage) {
      $entity->set(OverridesSectionStorage::FIELD_NAME, $section_storage->getSections());
      ExoComponentManager::setFieldData($entity, $data);
      $entity->save();
      $this->layoutTempstoreRepository()->delete($section_storage);

      // Since this entity is updated after the layout entity, we need to save
      // the layout entity again so it is aware of these changes.
      if ($parent_entity instanceof LayoutBuilderEntityViewDisplay && empty($parent_entity->exoComponentFieldSectionUpdated[$entity->uuid()])) {
        $parent_entity->exoComponentFieldSectionUpdated[$entity->uuid()] = TRUE;
        $parent_entity->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onDiscardLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity) {
    $this->layoutTempstoreRepository()->delete($this->getTemporarySectionStorage($entity, $parent_entity));
  }

  /**
   * {@inheritdoc}
   */
  public function isHideable(array $contexts) {
    return FALSE;
  }

  /**
   * The base section.
   *
   * @return \Drupal\layout_builder\Section
   *   The section.
   */
  protected function getSection() {
    return new Section($this->getLayoutId(), $this->getLayoutSettings() + [
      'column_sizes' => $this->getRegionSizes(),
    ]);
  }

  /**
   * An array of region sizes.
   *
   * This is used to determine which components can be used in a given region.
   *
   * @return array
   *   An array of region sizes.
   */
  protected function getRegionSizes() {
    return [
      'content' => 'full',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(ContentEntityInterface $entity, array $contexts) {
    if ($this->isPreview($contexts)) {
      $section = $this->getSection();
      $layout = $section->getLayout();
      $layout_definition = $layout->getPluginDefinition();
      $layout_settings = $section->getLayoutSettings();
      $render = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => [
            'layout',
            $layout_definition->getTemplate(),
          ],
        ],
      ];
      if (!empty($layout_settings['column_widths'])) {
        $render['#attributes']['class'][] = $layout_definition->getTemplate() . '--' . $layout_settings['column_widths'];
      }
      foreach ($layout_definition->getRegions() as $region => $info) {
        $render[$region] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'data-region' => $region,
            'class' => [
              'layout__region',
              'layout__region--' . $region,
            ],
          ],
        ];
        $render[$region]['placeholder'] = $this->componentPlaceholder($this->t('Region @region', [
          '@region' => $info['label'],
        ]));
      }
    }
    else {
      // When layout builder, we do not worry about rendering as the element
      // will handle rendering of this element.
      if ($this->isLayoutBuilder($contexts)) {
        return [];
      }
      $entity->exoComponentSection = TRUE;
      $render = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity);
    }
    $value = [
      'render' => $render,
    ];
    return $value;
  }

  /**
   * Get the entity view display.
   *
   * @return \Drupal\exo_alchemist\Entity\ExoLayoutBuilderEntityViewDisplay
   *   The entity view display.
   */
  public function getEntityViewDisplay($entity_type, $bundle, $view_mode = 'default') {
    $id = $entity_type . '.' . $bundle . '.' . $view_mode;
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    $display = $storage->load($id);
    if (!$display) {
      $display = $storage->create([
        'id' => $id,
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $view_mode,
      ]);
    }
    return $display;
  }

  /**
   * Gets the section storage for an entity.
   *
   * @return \Drupal\exo_alchemist\Plugin\SectionStorage\ExoComponentSectionStorage|null
   *   The section storage if found otherwise NULL.
   */
  public function getSectionStorage($entity, $layout_entity, $view_mode = 'default') {
    $contexts['entity'] = EntityContext::fromEntity($entity);
    $contexts['component_entity'] = EntityContext::fromEntity($entity);
    if ($layout_entity->getEntityTypeId() === 'entity_view_display') {
      $contexts['display_entity'] = EntityContext::fromEntity($layout_entity);
      $storage_type = 'component_defaults';
    }
    else {
      $contexts['entity'] = EntityContext::fromEntity($layout_entity);
      $storage_type = 'components';
    }
    $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
    return $this->sectionStorageManager()->load($storage_type, $contexts, new CacheableMetadata());
  }

  /**
   * Gets the section storage for an entity.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The section storage if found otherwise NULL.
   */
  public function getTemporarySectionStorage($entity, $layout_entity, $view_mode = 'default') {
    $section_storage = $this->getSectionStorage($entity, $layout_entity, $view_mode);
    if (!$this->layoutTempstoreRepository()->has($section_storage) && !$section_storage->isOverridden()) {
      $sections = $section_storage->getDefaultSectionStorage()->getSections();
      foreach ($sections as $section) {
        $section_storage->appendSection($section);
      }
      $this->layoutTempstoreRepository()->set($section_storage);
    }
    return $this->layoutTempstoreRepository()->get($section_storage);
  }

  /**
   * Get the layout id.
   */
  protected function getLayoutId() {
    return $this->layoutId;
  }

  /**
   * Get the layout settings.
   */
  protected function getLayoutSettings() {
    return $this->layoutSettings;
  }

  /**
   * Gets the section storage manager.
   *
   * @return \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   *   The section storage manager.
   */
  private function sectionStorageManager() {
    return $this->sectionStorageManager ?: \Drupal::service('plugin.manager.layout_builder.section_storage');
  }

  /**
   * Gets the layout builder tempstore repository.
   *
   * @return \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   *   The layout builder tempstore repository.
   */
  private function layoutTempstoreRepository() {
    return $this->layoutTempstoreRepository ?: \Drupal::service('layout_builder.tempstore_repository');
  }

}
