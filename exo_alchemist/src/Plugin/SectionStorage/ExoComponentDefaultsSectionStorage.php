<?php

namespace Drupal\exo_alchemist\Plugin\SectionStorage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\exo_alchemist\ExoComponentSectionNestedStorageInterface;
use Drupal\field_ui\FieldUI;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Entity\SampleEntityGeneratorInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines the 'components' section storage type.
 *
 * @SectionStorage(
 *   id = "component_defaults",
 *   weight = 10,
 *   context_definitions = {
 *     "component_entity" = @ContextDefinition("entity", constraints = {
 *       "EntityHasField" = \Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage::FIELD_NAME,
 *     }),
 *     "display_entity" = @ContextDefinition("entity", constraints = {
 *       "EntityHasField" = \Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage::FIELD_NAME,
 *     }),
 *     "view_mode" = @ContextDefinition("string", default_value = "default"),
 *   }
 * )
 *
 * @internal
 *   Plugin classes are internal.
 */
class ExoComponentDefaultsSectionStorage extends ExoDefaultsSectionStorage implements ExoComponentSectionNestedStorageInterface {

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, SampleEntityGeneratorInterface $sample_entity_generator, SectionStorageManagerInterface $section_storage_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_type_bundle_info, $sample_entity_generator);
    $this->sectionStorageManager = $section_storage_manager;
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
      $container->get('entity_type.bundle.info'),
      $container->get('layout_builder.sample_entity_generator'),
      $container->get('plugin.manager.layout_builder.section_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageId() {
    $entity = $this->getEntity();
    $display_entity = $this->getDisplayEntity();
    $id = $display_entity->getEntityTypeId() . '.' . ($display_entity->isNew() ? $display_entity->uuid() : $display_entity->id()) . '.' . $entity->getEntityTypeId() . '.' . $entity->uuid();
    return $id;
  }

  /**
   * Gets the component entity storing the overrides.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity storing the overrides.
   */
  public function getEntity() {
    return $this->getContextValue('component_entity');
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntity() {
    // Parent entity is same as entity.
    return $this->getEntity();
  }

  /**
   * Gets the layout entity storing the overrides.
   *
   * @return \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface
   *   The entity storing the overrides.
   */
  public function getDisplayEntity() {
    return $this->getContextValue('display_entity');
  }

  /**
   * {@inheritdoc}
   */
  protected function getSectionList() {
    return $this->getEntity()->get(OverridesSectionStorage::FIELD_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function isLayoutBuilderEnabled() {
    return !empty($this->getEntity()->layoutBuilderEnabled);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSectionStorage() {
    $display = LayoutBuilderEntityViewDisplay::collectRenderDisplay($this->getEntity(), $this->getContextValue('view_mode'));
    return $this->sectionStorageManager->load('defaults', ['display' => EntityContext::fromEntity($display)]);
  }

  /**
   * {@inheritdoc}
   */
  public function isOverridden() {
    // If there are any sections at all, including a blank one, this section
    // storage has been overridden. Do not use count() as it does not include
    // blank sections.
    return !empty($this->getSections());
  }

  /**
   * Get the parent overrides storage.
   *
   * @return \Drupal\layout_builder\OverridesSectionStorageInterface
   *   The overrides storage of the layout.
   */
  public function getParentEntityStorage() {
    $display_entity = $this->getDisplayEntity();
    /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display_entity */
    $contexts['display'] = EntityContext::fromEntity($display_entity);
    $contexts['view_mode'] = new Context(new ContextDefinition('string'), $display_entity->getMode());
    return $this->sectionStorageManager->load('defaults', $contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function buildRoutes(RouteCollection $collection) {}

  /**
   * {@inheritdoc}
   */
  public function buildLocalTasks($base_plugin_definition) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutBuilderUrl($rel = 'view') {
    $entity = $this->getDisplayEntity();
    return Url::fromRoute("layout_builder.defaults.{$entity->getTargetEntityTypeId()}.$rel", $this->getRouteParameters());
  }

  /**
   * Provides the route parameters needed to generate a URL for this object.
   *
   * @return mixed[]
   *   An associative array of parameter names and values.
   */
  protected function getRouteParameters() {
    $entity = $this->getDisplayEntity();
    $entity_type = $this->entityTypeManager->getDefinition($entity->getTargetEntityTypeId());
    $route_parameters = FieldUI::getRouteBundleParameter($entity_type, $entity->getTargetBundle());
    $route_parameters['view_mode_name'] = $entity->getMode();
    return $route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function deriveContextsFromRoute($value, $definition, $name, array $defaults) {
    $contexts = [];

    $display_entity = $this->extractEntityFromRoute($value, $defaults);
    $component_entity = NULL;
    if ($display_entity) {
      $contexts['display_entity'] = EntityContext::fromEntity($display_entity);
      $component_entity = $this->extractComponentEntityFromLayoutEntity($value, $display_entity);
    }

    if ($component_entity) {
      $contexts['component_entity'] = EntityContext::fromEntity($component_entity);
      // @todo Expand to work for all view modes in
      //   https://www.drupal.org/node/2907413.
      $view_mode = 'full';
      // Retrieve the actual view mode from the returned view display as the
      // requested view mode may not exist and a fallback will be used.
      $view_mode = LayoutBuilderEntityViewDisplay::collectRenderDisplay($component_entity, $view_mode)->getMode();
      $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  private function extractEntityFromRoute($value, array $defaults) {
    [, $entity_type_id, $bundle, $view_mode] = explode('.', $value, 6);
    $value = $entity_type_id . '.' . $bundle . '.' . $view_mode;
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    // If the display does not exist, create a new one.
    $entity = $storage->load($value);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  private function extractComponentEntityFromLayoutEntity($value, EntityInterface $display_entity) {
    [,,,,, $entity_id] = explode('.', $value, 6);
    /** @var \Drupal\exo_alchemist\ExoComponentRepository $repository */
    $repository = \Drupal::service('exo_alchemist.repository');
    $components_with_section = $repository->getComponents($display_entity, TRUE);
    foreach ($components_with_section as $component_with_section) {
      if ($component_with_section->uuid() === $entity_id) {
        return $component_with_section;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $this->getParentEntityStorage()->access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function getContextsDuringPreview() {
    $contexts = $this->getContexts();

    // view_mode is a required context, but SectionStorage plugins are not
    // required to return it (for example, the layout_library plugin provided
    // in the Layout Library module. In these instances, explicitly create a
    // view_mode context with the value "default".
    if (!isset($contexts['view_mode']) || $contexts['view_mode']->validate()->count() || !$contexts['view_mode']->getContextValue()) {
      $contexts['view_mode'] = new Context(new ContextDefinition('string'), 'default');
    }
    $display = $this->getDisplayEntity();
    $entity = $this->sampleEntityGenerator->get($display->getTargetEntityTypeId(), $display->getTargetBundle());

    $contexts['layout_builder.entity'] = EntityContext::fromEntity($entity);
    return $contexts;
  }

}
