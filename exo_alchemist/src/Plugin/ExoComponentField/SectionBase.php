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
   * Component UUIDs merged during onPreSaveLayoutBuilderEntity() in the
   * current request, so onPostSaveLayoutBuilderEntity() can tell it was
   * already handled and skip reprocessing them.
   *
   * @var bool[]
   */
  protected static $preSaveMergedUuids = [];

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
      // Merge any pending nested-column content (added via the "add
      // component" route while this section's own component was still
      // unsaved, sitting in Layout Builder tempstore) directly into this
      // entity's own field now, before core's own inline-block save
      // (InlineBlockEntityOperations::handlePreSave(), invoked moments
      // later in this same presave phase) persists it for the first time.
      // This lets that single core save capture the complete component in
      // one pass. Previously this merge happened in
      // onPostSaveLayoutBuilderEntity() instead (after the parent entity's
      // own row was already written), which required resaving the parent
      // entity afterward to persist the updated reference - but that
      // resave re-triggered core's own block-duplication logic a second
      // time and collided on UUIDs with the component just saved moments
      // before.
      $section_storage = $this->getTemporarySectionStorage($entity, $parent_entity);
      if ($section_storage) {
        $entity->set(OverridesSectionStorage::FIELD_NAME, $section_storage->getSections());
        $this->layoutTempstoreRepository()->delete($section_storage);
      }
      // Record that this component's content was merged here, so
      // onPostSaveLayoutBuilderEntity() below knows to skip it once core's
      // own save completes. This is intentionally a static, request-scoped
      // marker rather than a persisted field: a plugin instance is
      // recreated for each field callback, so a plain instance property
      // would not survive between this call and the later postSave call,
      // and a persisted field would incorrectly keep suppressing this
      // entity's postSave handling on every future, separate edit once
      // written to the database.
      static::$preSaveMergedUuids[$entity->uuid()] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onPostSaveLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity) {
    if (!empty(static::$preSaveMergedUuids[$entity->uuid()])) {
      // Already merged and persisted in onPreSaveLayoutBuilderEntity()
      // above: core's own presave save already saved the complete entity
      // and updated $parent_entity's component configuration to reference
      // it. Nothing further to do.
      unset(static::$preSaveMergedUuids[$entity->uuid()]);
      return;
    }
    // This entity already existed before this save (it was not new at
    // presave time). Pull any newly pending nested-column content and save
    // it onto this same entity/revision. This updates the entity in place,
    // so $parent_entity's existing reference to it (by revision or UUID)
    // remains valid and $parent_entity does not need to be resaved.
    $section_storage = $this->getTemporarySectionStorage($entity, $parent_entity);
    if ($section_storage) {
      $entity->set(OverridesSectionStorage::FIELD_NAME, $section_storage->getSections());
      $entity->save();
      $this->layoutTempstoreRepository()->delete($section_storage);
    }
  }

  /**
   * {@inheritdoc}
   *
   * A section field's value lives in the entity's own
   * OverridesSectionStorage::FIELD_NAME field rather than in a fieldable
   * item, so unlike ExoComponentFieldFieldableBase subclasses, cloning must
   * walk the nested sections directly and re-clone any exo component
   * entities they reference. Without this, cloning a saved section
   * component (e.g. when duplicating a page that already has one) leaves
   * its nested column components pointing at the original entities instead
   * of getting their own clones.
   */
  public function onClone(ContentEntityInterface $entity, $all = FALSE) {
    if (!$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return;
    }
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getSections();
    foreach ($sections as $section) {
      foreach ($section->getComponents() as $component) {
        if (ExoComponentManager::isExoComponent($component)) {
          $configuration = $component->get('configuration');
          $component_entity = NULL;
          if (!empty($configuration['block_revision_id'])) {
            $component_entity = $this->exoComponentManager()->entityLoadByRevisionId($configuration['block_revision_id']);
          }
          if (!empty($configuration['block_uuid'])) {
            $component_entity = $this->exoComponentManager()->entityLoadByUuid($configuration['block_uuid']);
          }
          if ($component_entity) {
            $definition = $this->exoComponentManager()->getEntityComponentDefinition($component_entity);
            $component_entity = $this->exoComponentManager()->cloneEntity($definition, $component_entity, $all);
            $configuration['block_revision_id'] = $configuration['block_uuid'] = NULL;
            $configuration['block_serialized'] = serialize($component_entity);
            $component->setConfiguration($configuration);
          }
        }
      }
    }
    $entity->set(OverridesSectionStorage::FIELD_NAME, $sections);
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
    if ($this->isPreview($contexts) || $entity->isNew()) {
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
      // Calling the full entity view builder here would re-enter this
      // entity's own render pipeline, which asks this same field to render
      // this same entity again and recurses forever (e.g. when
      // isLayoutBuilder() above cannot detect that we are already inside
      // Layout Builder's own rendering for this entity). All we actually
      // need is this entity's own nested layout output, so build it
      // directly from its own Section objects instead - the same core API
      // Layout Builder itself uses to convert a Section into a render
      // array. This dispatches SECTION_COMPONENT_BUILD_RENDER_ARRAY for
      // each nested component (the same event
      // BlockComponentRenderArrayAfterCore already handles safely for
      // top-level page placements) without ever re-entering the entity's
      // own view pipeline.
      $entity->exoComponentSection = TRUE;
      $render = [];
      if ($entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
        foreach ($entity->get(OverridesSectionStorage::FIELD_NAME)->getSections() as $entity_section) {
          $section_render = $entity_section->toRenderArray($contexts);
          if (!empty($section_render)) {
            $render[] = $section_render;
          }
        }
      }
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

  /**
   * Gets the exo component manager.
   *
   * @return \Drupal\exo_alchemist\ExoComponentManager
   *   The exo component manager.
   */
  private function exoComponentManager() {
    return \Drupal::service('plugin.manager.exo_component');
  }

}
