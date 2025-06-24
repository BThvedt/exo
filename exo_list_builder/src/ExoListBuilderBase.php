<?php

namespace Drupal\exo_list_builder;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\exo_list_builder\Plugin\ExoListActionSettingsInterface;
use Drupal\exo_list_builder\Plugin\ExoListFilterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides a base class for exo list builder.
 */
abstract class ExoListBuilderBase extends EntityListBuilder implements ExoListBuilderInterface {

  use ExoIconTranslationTrait;
  use RedirectDestinationTrait;

  /**
   * The cacheable metadata.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  protected $cacheableMetadata;

  /**
   * The key to use for the form element containing the entities.
   *
   * @var string
   */
  protected $entitiesKey = 'entities';

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The list field manager service.
   *
   * @var \Drupal\exo_list_builder\ExoListFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The list element manager service.
   *
   * @var \Drupal\exo_list_builder\ExoListManagerInterface
   */
  protected $elementManager;

  /**
   * The list filter manager service.
   *
   * @var \Drupal\exo_list_builder\ExoListManagerInterface
   */
  protected $filterManager;

  /**
   * The sort manager service.
   *
   * @var \Drupal\exo_list_builder\ExoListSortManagerInterface
   */
  protected $sortManager;

  /**
   * The entity list.
   *
   * @var \Drupal\exo_list_builder\EntityListInterface
   */
  protected $entityList;

  /**
   * The list options.
   *
   * @var array
   */
  protected $options;

  /**
   * The query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $query;

  /**
   * The total number of results.
   *
   * @var int
   */
  protected $total;

  /**
   * The number of entities to list per page.
   *
   * @var int|false
   */
  protected $limit;

  /**
   * Use custom sort.
   *
   * If TRUE, the default sort will be handled by the list builder.
   *
   * @var bool
   */
  protected $customSort = FALSE;

  /**
   * The shown fields.
   *
   * @var array
   */
  protected $fields;

  /**
   * The fields with filters enabled.
   *
   * @var array
   */
  protected $filters;

  /**
   * The fields with expsoed filters.
   *
   * @var array
   */
  protected $exposedFilters;

  /**
   * An array of query conditions.
   *
   * @var array
   */
  protected $queryConditions = [];

  /**
   * An array of action instances.
   *
   * @var \Drupal\exo_list_builder\Plugin\ExoListActionInterface[]
   */
  protected $actions;

  /**
   * The job queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface[]
   */
  protected $queues;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('form_builder'),
      $container->get('module_handler'),
      $container->get('plugin.manager.exo_list_field'),
      $container->get('plugin.manager.exo_list_element'),
      $container->get('plugin.manager.exo_list_filter'),
      $container->get('plugin.manager.exo_list_sort')
    );
  }

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\exo_list_builder\ExoListFieldManagerInterface $field_manager
   *   The field manager service.
   * @param \Drupal\exo_list_builder\ExoListManagerInterface $element_manager
   *   The element manager service.
   * @param \Drupal\exo_list_builder\ExoListManagerInterface $filter_manager
   *   The filter manager service.
   * @param \Drupal\exo_list_builder\ExoListSortManagerInterface $sort_manager
   *   The sort manager service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, FormBuilderInterface $form_builder, ModuleHandlerInterface $module_handler, ExoListFieldManagerInterface $field_manager, ExoListManagerInterface $element_manager, ExoListManagerInterface $filter_manager, ExoListSortManagerInterface $sort_manager) {
    $this->entityTypeId = $entity_type->id();
    $this->storage = $storage;
    $this->entityType = $entity_type;
    $this->formBuilder = $form_builder;
    $this->moduleHandler = $module_handler;
    $this->fieldManager = $field_manager;
    $this->elementManager = $element_manager;
    $this->filterManager = $filter_manager;
    $this->sortManager = $sort_manager;
  }

  /**
   * {@inheritDoc}
   */
  public function loadFields() {
    if (!isset($this->fields)) {
      $use_cache = !$this->entityList->isNew();
      if ($use_cache) {
        $cid = 'exo_list_builder:fields:' . $this->entityList->id();
        if ($cache = \Drupal::cache()->get($cid)) {
          $fields = $cache->data;
        }
      }
      if (!isset($fields)) {
        $entity_list = $this->getEntityList();
        $fields = [];
        foreach ($entity_list->getTargetBundleIds() as $bundle) {
          $fields += $this->fieldManager->getFields($entity_list->getTargetEntityTypeId(), $bundle);
        }
        $fields += $this->discoverFields();
        $this->alterFields($fields);
        $this->moduleHandler->alter('exo_list_builder_fields', $fields, $this->entityTypeId);
        $this->moduleHandler->alter('exo_list_builder_fields_' . $entity_list->id(), $fields, $this->entityTypeId);
        if ($use_cache) {
          \Drupal::cache()->set($cid, $fields, Cache::PERMANENT, ['entity_field_info']);
        }
      }
      $this->fields = $fields;
    }
    return $this->fields;
  }

  /**
   * Allow builder to modify field list.
   */
  protected function alterFields(&$fields) {
  }

  /**
   * {@inheritDoc}
   */
  protected function discoverFields() {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldEntity(EntityInterface $entity, array $field) {
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_entity_list_' . $this->entityList->id() . '_form';
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityList() {
    return $this->entityList;
  }

  /**
   * {@inheritDoc}
   */
  public function setEntityList(EntityListInterface $entity_list) {
    $this->entityList = $entity_list;
    $this->buildOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl(array $options = []) {
    $entity_list = $this->getEntityList();
    if ($entity_list->getUrl()) {
      return Url::fromRoute($entity_list->getRouteName(), [], $options);
    }
    if ($entity_list->isOverride()) {
      if ($entity_list->getTargetEntityType()->getLinkTemplate('collection')) {
        $target_entity_type = $entity_list->getTargetEntityType();
        return Url::fromRoute("entity.{$target_entity_type->id()}.collection", [], $options);
      }
    }
    return Url::fromRoute('<current>', [], $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    $entity_list = $this->getEntityList();
    $route_name = 'entity.exo_entity_list.canonical';
    $override = $entity_list->isOverride();
    if ($override) {
      $route_name = 'entity.' . $entity_list->getTargetEntityTypeId() . '.collection';
    }
    elseif ($entity_list->getUrl()) {
      $route_name = 'exo_list_builder.' . $entity_list->id();
    }
    return $route_name;
  }

  /**
   * {@inheritdoc}
   */
  public function allowOverride() {
    $entity_list = $this->getEntityList();
    $bundle_includes = $entity_list->getTargetBundleIncludeIds();
    $bundle_excludes = $entity_list->getTargetBundleExcludeIds();
    $bundles = $entity_list->getTargetBundleIds();
    if (
      // An entity type without bundles.
      (count($bundles) === 1 && key($bundles) === $entity_list->getTargetEntityTypeId()) ||
      // An entity type with all bundles.
      (empty($bundle_includes) && empty($bundle_excludes))
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get options defaults.
   *
   * @return array
   *   The defaults.
   */
  protected function getOptionDefaults() {
    $entity_list = $this->getEntityList();
    return [
      'order' => NULL,
      'sort' => NULL,
      'page' => 0,
      // Flag indicating if list has been changed by the user.
      'm' => NULL,
      'limit' => $entity_list->getLimit(),
      'show' => [],
      'filter' => [],
    ];
  }

  /**
   * Build the query options.
   */
  protected function buildOptions() {
    $query = \Drupal::request()->query->all() ?? [];
    $key = $this->getEntityList()->getKey();
    if (!empty($query[$key])) {
      $options = $this->getEntityList()->optionsDecode($query[$key]);
      if (is_array($options)) {
        $query += $options;
      }
    }
    $this->setOptions($query);
  }

  /**
   * Get the query options.
   *
   * @return array
   *   The query options.
   */
  protected function getOptions() {
    return $this->options;
  }

  /**
   * Get a query option.
   *
   * @return mixed
   *   The query options.
   */
  public function getOption($key, $default_value = NULL) {
    $exists = NULL;
    $options = $this->getOptions();
    if (!empty($options)) {
      $option = NestedArray::getValue($options, (array) $key, $exists);
    }
    return $exists ? $option : $default_value;
  }

  /**
   * Set a query option.
   *
   * @return $this
   */
  protected function setOptions(array $options) {
    $defaults = $this->getOptionDefaults();
    $this->options = array_intersect_key($options + $defaults, $defaults);
    return $this;
  }

  /**
   * Set a query option.
   *
   * @return $this
   */
  public function setOption($key, $value) {
    NestedArray::setValue($this->options, (array) $key, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsUrl(array $exclude_options = [], array $exclude_filters = [], array $query = []) {
    $entity_list = $this->getEntityList();
    $options = $this->getOptions();
    $defaults = $this->getOptionDefaults();
    $options_query = \Drupal::request()->query->all();
    $options_query = array_diff_key($options_query, $defaults);
    $query = NestedArray::mergeDeep($options_query, $query);
    $key = $entity_list->getKey();
    unset($query[$key]);
    unset($query['check_logged_in']);
    unset($options['order']);
    unset($options['sort']);
    unset($options['page']);
    if (!empty($options['limit']) && (int) $options['limit'] !== $entity_list->getLimit()) {
      $query['limit'] = $options['limit'];
    }
    unset($options['limit']);
    foreach ($options as $id => $value) {
      if (!empty($value) && isset($defaults[$id]) && !in_array($id, $exclude_options)) {
        if ($id === 'filter') {
          $value = array_diff_key($value, array_flip($exclude_filters));
          if (empty($value)) {
            continue;
          }
        }
        $query[$key][$id] = $value;
      }
    }
    $url = Url::fromRoute('<current>');
    if (!empty($query[$key])) {
      $query[$key] = $this->getEntityList()->optionsEncode($query[$key]);
    }
    $query['m'] = 1;
    $url->setOption('query', $query);
    return $url;
  }

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $query = $this->getQuery();

    // Only add the pager if a limit is specified.
    if ($limit = $this->getLimit()) {
      $query = clone $query;
      $element = \Drupal::service('pager.manager')->getMaxPagerElementId() + 1;
      $page = \Drupal::service('pager.parameters')->findPage($element);
      $total = $this->getTotal();
      $start = ($page * $limit) + $this->entityList->getOffset();
      \Drupal::service('pager.manager')->createPager($total, $limit, $element);
      $query->range($start, $limit);
    }
    elseif ($offset = $this->entityList->getOffset()) {
      $query->range($offset);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_list = $this->entityList;
    $entity_ids = $this->getEntityIds();
    $entities = $this->storage->loadMultiple($entity_ids);
    $this->moduleHandler->alter('exo_list_builder_entities', $entities, $this->entityList);
    $this->moduleHandler->alter('exo_list_builder_entities_' . $entity_list->id(), $entities, $entity_list);
    return $entities;
  }

  /**
   * {@inheritDoc}
   */
  public function getQuery($context = 'default') {
    if (!isset($this->query)) {
      $this->query = $this->buildQuery($context);
    }
    return $this->query;
  }

  /**
   * Get the entity query object.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query object.
   */
  protected function getEntityQuery($context = 'default') {
    return $this->getStorage()->getQuery();
  }

  /**
   * {@inheritDoc}
   */
  protected function buildQuery($context = 'default') {
    $entity_list = $this->getEntityList();
    $query = $this->getEntityQuery($context);
    $query->accessCheck(TRUE);
    if ($op = $entity_list->getSetting('node_access_op')) {
      $query->addMetaData('op', $op);
    }
    $query->addTag('exo_list_query');
    $query->addMetaData('exo_list_builder', $this);

    $this->addQuerySort($query, $context);

    if ($entity_list->getTargetEntityType()->hasKey('bundle')) {
      $query->condition($entity_list->getTargetEntityType()->getKey('bundle'), $entity_list->getTargetBundleIds(), 'IN');
    }

    // Use any set query conditions.
    $this->buildQueryConditions($query, $context);

    // Filter.
    $filter_values = [];
    foreach ($this->getFilters() as $field_id => $field) {
      if (!$field['filter']['instance']) {
        continue;
      }
      /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
      $instance = $field['filter']['instance'];
      $filter_value = $this->getFilterValue($field_id);
      if ($this->isModified() && !empty($field['filter']['settings']['remember'])) {
        $key = $this->entityList->id() . '_' . $field_id . '_remember';
        \Drupal::service('session')->set($key, $filter_value);
      }
      if ($instance->allowQueryAlter($filter_value, $entity_list, $field)) {
        if (is_array($filter_value) && $instance->supportsMultiple()) {
          $group = NULL;
          switch ($instance->getMultipleJoin($field)) {
            case 'and':
              foreach ($filter_value as $filter_val) {
                $group = $query->andConditionGroup();
                $instance->queryAlter($group, $filter_val, $entity_list, $field);
                $query->condition($group);
              }
              break;

            default:
              $group = $query->orConditionGroup();
              foreach ($filter_value as $filter_val) {
                $instance->queryAlter($group, $filter_val, $entity_list, $field);
              }
              $query->condition($group);
              break;
          }
        }
        else {
          $instance->queryAlter($query, $filter_value, $entity_list, $field);
        }
        $filter_values[$field_id] = $filter_value;
      }
    }
    $query->addMetaData('exo_list_filter_values', $filter_values);
    $this->moduleHandler->alter('exo_list_builder_query', $query, $entity_list);
    $this->moduleHandler->alter('exo_list_builder_query_' . $entity_list->id(), $query, $entity_list);
    return $query;
  }

  /**
   * {@inheritDoc}
   */
  public function getFilterValue($field_id) {
    $filter_value = NULL;
    $field = $this->getFilters()[$field_id] ?? NULL;
    if ($field && $field['filter']['instance']) {
      $entity_list = $this->getEntityList();
      /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
      $instance = $field['filter']['instance'];
      $default_value = $instance->getDefaultValue($entity_list, $field);
      // Non-exposed fields that have a default value set.
      if (empty($field['filter']['settings']['expose']) && !is_null($default_value)) {
        $filter_value = $this->getOption(['filter', $field_id]) ?? $default_value;
      }
      // Exposed fields.
      else {
        $filter_value = $this->getOption(['filter', $field_id]);
        // Provide default filters when filter value is empty, list has not been
        // modified and field provides a default.
        if (empty($filter_value) && !$this->isModified() && !is_null($default_value)) {
          $filter_value = $default_value;
        }
        if ($default_value && $instance->isDefaultValueLocked($field)) {
          if (is_array($filter_value)) {
            if (is_array($default_value)) {
              $filter_value += $default_value;
            }
            else {
              $filter_value[$default_value] = $default_value;
            }
          }
          else {
            $filter_value = $default_value;
          }
        }
      }
    }
    return $filter_value;
  }

  /**
   * Add the sort query.
   *
   * This only impacts non-table lists.
   */
  protected function addQuerySort(QueryInterface $query, $context = 'default') {
    $entity_list = $this->entityList;
    $order = $this->getOption('order') ?: $entity_list->getSort();
    $sort = $this->getOption('sort');
    $fields = $entity_list->getFields();
    if ($order) {
      $instance = NULL;
      $sort_plugin_id = $entity_list->getSortPluginId($order);
      $sort_plugin_value = $entity_list->getSortPluginValue($order);
      if ($sort_plugin_id && $this->sortManager->hasDefinition($sort_plugin_id)) {
        $instance = $this->sortManager->createInstance($sort_plugin_id);
      }
      if (!$instance && $entity_list->getFormat() === 'table') {
        // When using table-sorting, the order is actually the field label.
        foreach ($fields as $field) {
          if (!empty($field['sort_field']) && (string) $field['display_label'] === $sort_plugin_id) {
            $instance = $this->sortManager->createInstance('field');
            $sort_plugin_value = $field['id'];
            break;
          }
        }
      }

      if ($instance) {
        if ($this->cacheableMetadata) {
          $this->cacheableMetadata->addCacheableDependency($instance);
        }
        $instance->sort($query, $entity_list, $sort, $sort_plugin_value);
        if ($context === 'default') {
          $this->setOption('order', $order);
          $this->setOption('sort', $sort);
        }
      }
    }
    foreach ($fields as $field) {
      if (!empty($field['view']['sort_secondary'])) {
        $secondary_sort = strtolower($field['view']['sort'] ?: 'asc');
        if (!empty($fields[$sort_plugin_value]['view']['sort']) && $fields[$sort_plugin_value]['view']['sort'] !== $sort) {
          $secondary_sort = $secondary_sort === 'asc' ? 'desc' : 'asc';
        }
        $query->sort($field['sort_field'], $secondary_sort);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addQueryCondition($field, $value = NULL, $operator = NULL, $langcode = NULL) {
    $this->queryConditions[$field] = [
      'field' => $field,
      'value' => $value,
      'operator' => $operator,
      'langcode' => $langcode,
    ];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryConditions() {
    return $this->queryConditions;
  }

  /**
   * Build query conditions.
   */
  protected function buildQueryConditions(QueryInterface $query, $context = 'default') {
    foreach ($this->getQueryConditions() as $condition) {
      if ($condition['field'] === 'moderation_state') {
        $query->addTag('exo_entity_list_moderation_state');
        // @see exo_list_builder_query_exo_entity_list_moderation_state_alter().
        $query->addMetaData('exo_entity_list_moderation_state', $condition['value']);
      }
      else {
        $query->condition($condition['field'], $condition['value'], $condition['operator'], $condition['langcode']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTotal() {
    if (!isset($this->total)) {
      $query = clone $this->buildQuery('all');
      $query->addTag('exo_list_total');
      $this->total = $query->count()->execute();
    }
    return (int) $this->total;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawTotal($ignoreFilters = FALSE, $context = 'all') {
    $query = clone $this->buildQuery($context);
    $query->addTag('exo_list_raw_total');
    if ($ignoreFilters) {
      $options = $this->getOption(['filter']);
      $this->setOption(['filter'], []);
      $total = $query->count()->execute();
      $this->setOption(['filter'], $options);
    }
    else {
      $total = $query->count()->execute();
    }
    return $total;
  }

  /**
   * Check if list should be constructed as a form.
   *
   * @return bool
   *   Returns TRUE if list should be constructed as a form.
   */
  protected function isForm() {
    return !empty($this->getExposedFilters()) || !empty($this->getActions());
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entity_list = $this->getEntityList();
    $this->cacheableMetadata = new CacheableMetadata();

    if ($entity_list->getSetting('first_page_only_status') && $this->getOption('page') > 0) {
      return [
        '#cache' => [
          'contexts' => $this->getCacheContexts(),
          'tags' => $this->getCacheTags(),
        ],
      ];
    }

    if ($entity_list->getFormat() === 'table' || $this->isForm()) {
      $build = $this->formBuilder->getForm($this);
    }
    else {
      $build = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['exo-reset'],
        ],
      ];
      $build = $this->buildList($build);
    }

    if (isset($build['top'])) {
      if (!Element::children($build['top'])) {
        $build['top']['#access'] = FALSE;
      }
      else {
        $build['top']['shadow'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => 'exo-list-states--shadow',
          ],
          '#weight' => 100,
        ];
      }
    }
    if (isset($build['header']['first']) && !Element::getVisibleChildren($build['header']['first'])) {
      $build['header']['first']['#access'] = FALSE;
    }
    if (isset($build['header']['second']) && !Element::getVisibleChildren($build['header']['second'])) {
      $build['header']['second']['#access'] = FALSE;
    }
    if (isset($build['header']) && !Element::getVisibleChildren($build['header'])) {
      $build['header']['#access'] = FALSE;
    }
    if (isset($build['footer']) && !Element::getVisibleChildren($build['footer'])) {
      $build['footer']['#access'] = FALSE;
    }
    if (isset($build['sidebar'])) {
      if (!Element::getVisibleChildren($build['sidebar'])) {
        $build['sidebar']['#access'] = FALSE;
      }
      else {
        $build['#attributes']['class'][] = 'has-sidebar';
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function isFiltered($include_defaults = FALSE) {
    foreach ($this->getFilters() as $field_id => $field) {
      // If a filter is exposed AND contains a default value, then consider it
      // as filtered.
      if (!empty($field['filter']['settings']['expose']) && !empty($field['filter']['settings']['default']['status'])) {
        return TRUE;
      }
      if ($include_defaults && !empty($field['filter']['settings']['default']['status'])) {
        return TRUE;
      }
    }
    return !empty($this->getOption('filter'));
  }

  /**
   * {@inheritdoc}
   */
  public function isModified() {
    return !empty($this->getOption('m'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildList(array $build) {
    $entity_list = $this->getEntityList();
    $render_status = $entity_list->getSetting('render_status');
    // Only load the entities when we want to render the results.
    $entities = $render_status ? $this->load() : [];

    // Advanced cache. Needs to run after load() so that the pager will properly
    // render.
    $enhancedCache = $entity_list->getSetting('cache_status', FALSE);
    if ($enhancedCache) {
      $cid = ['exo_list_builder', $this->entityList->id()];
      foreach ($this->getOptions() as $option => $value) {
        if (empty($value)) {
          continue;
        }
        $cid[$option] = is_array($value) ? base64_encode(json_encode($value)) : $value;
      }
      $cid = implode(':', $cid);
      if ($cache = \Drupal::cache()->get($cid)) {
        return $cache->data;
      }
    }

    $total = $this->getTotal();
    // We are not modified and we only have a single entity..
    $hide_extras = !$this->isFiltered() && $total <= 1;

    $id = str_replace('_', '-', $entity_list->id());
    $build['#id'] = 'exo-list-' . $id;
    $build['#exo_list_id'] = $entity_list->id();
    $build['#exo_hide_extras'] = $hide_extras;
    $build['#attributes']['class'][] = 'exo-list';
    $build['#attributes']['class'][] = 'exo-list-' . $id;
    $build['#attached']['library'][] = 'exo_list_builder/list';

    $build['top'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => -110,
      '#attributes' => ['class' => ['exo-list-top']],
    ];

    $build['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => -100,
      '#attributes' => ['class' => ['exo-list-header']],
    ];

    $build['header']['first'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => -100,
      '#attributes' => ['class' => ['exo-list-header-first']],
    ];

    $build['header']['second'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => -100,
      '#attributes' => ['class' => ['exo-list-header-second']],
    ];

    $build['sidebar'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => -50,
      '#attributes' => ['class' => ['exo-list-sidebar']],
    ];

    $format = $this->entityList->getFormat();
    $format_build = [];
    $this->cacheableMetadata->addCacheContexts($this->getCacheContexts());
    $this->cacheableMetadata->addCacheTags($this->getCacheTags());
    if ($render_status) {
      $format_build = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => [
            'exo-list-content',
            'exo-list-' . str_replace('_', '-', $format),
          ],
        ],
      ];
      switch ($format) {
        case 'table':
          $format_build['#type'] = 'table';
          $format_build['#header'] = $this->buildHeader();
          $format_build['#title'] = $this->getTitle();
          break;
      }
    }
    $build[$this->entitiesKey] = $format_build;

    $build['#draggable'] = FALSE;
    if ($entities) {
      $build_rows = [];
      foreach ($entities as $key => $target_entity) {
        if ($row = $this->buildRow($target_entity)) {
          switch ($format) {
            case 'table':
              $build_rows[$key] = $row;
              $build['#draggable'] = !empty($row['#draggable']);
              break;

            default:
              $build_rows[$key] = [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => [
                  'class' => [
                    'exo-list-item',
                  ],
                ],
                '#group_by' => $row['#group_by'] ?? NULL,
                'row' => $row,
              ];
              if ($entity_list->getSetting('list_link')) {
                $build_rows[$key]['#tag'] = 'a';
                $build_rows[$key]['#attributes']['href'] = $target_entity->toUrl()->toString();
              }
              if (!$entity_list->getSetting('item_wrapper_status')) {
                $build_rows[$key]['#type'] = NULL;
              }
              break;
          }
        }
      }

      if ($build_rows) {
        // Hide ops as necessary.
        $found_ops = FALSE;
        foreach ($build_rows as $row) {
          if (!empty($row['operations']['data']['#links'])) {
            $found_ops = TRUE;
            break;
          }
        }

        if (!$found_ops) {
          unset($build[$this->entitiesKey]['#header']['operations']);
          foreach ($build_rows as $key => $row) {
            unset($build_rows[$key]['operations']);
          }
        }
      }

      $group_by = [];
      foreach ($build_rows as $row) {
        if (!empty($row['#group_by'])) {
          foreach ($row['#group_by'] as $field_name => $rendered) {
            unset($build[$this->entitiesKey]['#header'][$field_name]);
            $key = md5((string) $rendered);
            $group_by[$field_name]['sort'] = $row['#group_by_sort'][$field_name] ?? $row['row']['#group_by_sort'][$field_name];
            $group_by[$field_name]['data'][$key]['rendered'] = $rendered;
            if (isset($row['#entity_id'])) {
              $group_by[$field_name]['data'][$key]['rows'][$row['#entity_id']] = $row;
            }
            else {
              $group_by[$field_name]['data'][$key]['rows'][$row['row']['#entity_id']] = $row;
            }
          }
        }
      }
      if ($group_by) {
        $build['#group_by'] = TRUE;
        $build['#draggable'] = FALSE;
        $template = $build[$this->entitiesKey];
        $build[$this->entitiesKey] = [];
        $build[$this->entitiesKey] += $this->buildGroups($template, $group_by);
      }
      else {
        $build[$this->entitiesKey] += $build_rows;
        if (!$hide_extras && ($subform = $this->buildSort($build))) {
          $build['header']['second']['sort'] = $subform + [
            '#weight' => -10,
          ];
        }
      }
    }
    elseif ($render_status) {
      if (!$this->isModified() && $this->entityList->getSetting('hide_no_results')) {
        $build['#access'] = FALSE;
      }
      else {
        $build[$this->entitiesKey] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => [
              'exo-list-content',
              'exo-list-' . str_replace('_', '-', $format),
            ],
          ],
          'message' => $this->buildEmpty($build),
        ];
      }
    }
    $build[$this->entitiesKey]['#entities'] = $entities;

    $build['footer'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => 100,
      '#attributes' => ['class' => ['exo-list-footer']],
    ];

    if (!$hide_extras) {
      $pager = $this->buildPager($build, $build);
      $build['header']['second']['pager'] = $pager;
      // Remove pages from header.
      unset($build['header']['second']['pager']['pages']);
      unset($build['header']['second']['pager']['pager_footer']);
      if (!Element::getVisibleChildren($build['header']['second']['pager'])) {
        $build['header']['second']['pager']['#access'] = FALSE;
      }

      $build['footer']['pager'] = $pager;
      // Remove limit from footer.
      unset($build['footer']['pager']['limit']);
      unset($build['footer']['pager']['pager_header']);
    }
    $this->cacheableMetadata->applyTo($build);

    if ($enhancedCache) {
      \Drupal::cache()->set($cid, $build, Cache::PERMANENT, $this->cacheableMetadata->getCacheTags());
    }

    if ($this->getEntityList()->getSetting('autosubmit')) {
      $build['#attributes']['data-exo-auto-submit-full-form'] = '';
      $build['#attached']['library'][] = 'exo/auto_submit';
    }

    return $build;
  }

  /**
   * {@inheritDoc}
   */
  protected function buildGroups($template, $groups, $ids = [], $h = 2) {
    $group = array_shift($groups);
    $build = [];
    foreach ($group['data'] as $key => $data) {
      $segment = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['exo-list-group']],
      ];
      $segment['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h' . $h,
        '#weight' => -100,
        '#attributes' => ['class' => ['exo-list-group-title']],
        'value' => ['#markup' => $data['rendered']],
      ];
      if (!empty($groups)) {
        $segment['children'] = $this->buildGroups($template, $groups, array_merge($ids, array_keys($data['rows'])), $h + 1);
      }
      else {
        $rows = !empty($ids) ? array_intersect_key($data['rows'], array_flip($ids)) : $data['rows'];
        if (!empty($rows)) {
          $segment['children'] = $template + $rows;
        }
        else {
          $segment['#access'] = FALSE;
        }
      }
      $build[strip_tags($data['rendered'])] = $segment;
    }
    ksort($build);
    if ($group['sort'] == 'desc') {
      $build = array_reverse($build);
    }
    return $build;
  }

  /**
   * {@inheritDoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    $row['#entity_id'] = $entity->id();
    $row['#group_by'] = [];
    foreach ($this->getShownFields() as $field_id => $field) {
      $row[$field_id]['data'] = $this->renderField($entity, $field);
      $row[$field_id]['#wrapper_attributes']['class'][] = Html::getClass('exo-list-builder-field-id--' . $field_id);
      $row[$field_id]['#wrapper_attributes']['class'][] = Html::getClass('exo-list-builder-field-type--' . $field['view']['type']);
      if (!empty($field['view']['align'])) {
        $row[$field_id]['#wrapper_attributes']['class'][] = Html::getClass('exo-list-builder-align--' . $field['view']['align']);
      }
      if (!empty($field['view']['size'])) {
        $row[$field_id]['#wrapper_attributes']['class'][] = Html::getClass('exo-list-builder-size--' . $field['view']['size']);
      }
      if (isset($row[$field_id]['data']['#list_weight'])) {
        $row[$field_id]['data']['#parents'] = ['weight', $entity->id()];
        $row['#attributes']['class'][] = 'draggable';
        $row['#draggable'] = TRUE;
      }
      if (!empty($field['view']['group_by'])) {
        $row['#group_by'][$field_id] = \Drupal::service('renderer')->render($row[$field_id]['data']);
        $row['#group_by_sort'][$field_id] = $field['view']['group_by_sort'];
        unset($row[$field_id]);
      }
    }
    if ($this->entityList->showOperations()) {
      $row['operations']['data'] = $this->buildOperations($entity);
      $row['operations']['#wrapper_attributes']['class'][] = 'exo-list-builder-field-id--operations';
      $row['operations']['#wrapper_attributes']['class'][] = 'exo-list-builder-size--compact';
    }
    if ($entity instanceof EntityPublishedInterface) {
      if ($entity->isPublished()) {
        $row['#attributes']['class'][] = 'exo-list-builder--published';
      }
      else {
        $row['#attributes']['class'][] = 'exo-list-builder--unpublished';
      }
    }
    return $row;
  }

  /**
   * Build an individual field's output.
   *
   * @return array
   *   A render array.
   */
  protected function renderField(EntityInterface $entity, array $field) {
    /** @var \Drupal\exo_list_builder\Plugin\ExoListElementInterface $instance */
    $instance = $this->elementManager->createInstance($field['view']['type'], $field['view']['settings']);
    $field_entity = $this->getFieldEntity($entity, $field);
    if (!$field_entity) {
      return [
        '#markup' => $instance->getConfiguration()['empty'],
      ];
    }
    $field_entity->exoEntityList = $this->getEntityList();
    $field_entity->exoEntityListField = $field;
    $field_entity->exoEntityListBuilder = $this;
    $build = $instance->buildView($field_entity, $field);
    if (!is_array($build)) {
      if (!is_null($build)) {
        $build = [
          '#markup' => $build,
        ];
      }
      else {
        $build = [];
      }
    }
    if (!empty($build) && !empty($field['view']['wrapper'])) {
      $prefix = $field['view']['wrapper'];
      $suffix = $field['view']['wrapper'];
      switch ($field['view']['wrapper']) {
        case 'div':
          $prefix .= ' class="' . Html::getClass('exo-list-field--' . $field['id']) . '"';
          break;

        case 'div_span':
          $prefix = 'div class="' . Html::getClass('exo-list-field--' . $field['id']) . '"><span';
          $suffix = 'span></div';
          break;

        case 'badge':
          $prefix = 'div class="badge"><span';
          $suffix = 'span></div';
          break;

        case 'badge_success':
          $prefix = 'div class="badge badge--success"><span';
          $suffix = 'span></div';
          break;

        case 'badge_warning':
          $prefix = 'div class="badge badge--warning"><span';
          $suffix = 'span></div';
          break;

        case 'badge_alert':
          $prefix = 'div class="badge badge--alert"><span';
          $suffix = 'span></div';
          break;
      }
      $build['#prefix'] = '<' . $prefix . '>';
      $build['#suffix'] = '</' . $suffix . '>';
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_list = $this->getEntityList();

    // Skip reloading everything. This makes things faster.
    if ($user_input = $form_state->getUserInput()) {
      if (isset($user_input['exo_filter_submit']) || isset($user_input['exo_filter_modal_submit']) || isset($user_input['exo_filter_sidebar_submit'])) {
        $entity_list->setSetting('render_status', FALSE);
      }
    }

    $render_status = $entity_list->getSetting('render_status');
    $filter_status = $entity_list->getSetting('filter_status');
    $actions = $this->getActions();
    $action_settings_action = $form_state->get('action_settings_action');
    $form = $this->buildList($form);
    $hide_extras = !empty($form['#exo_hide_extras']);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh'),
      '#weight' => -200,
      '#attributes' => ['class' => ['hidden']],
    ];

    $format = $entity_list->getFormat();
    switch ($format) {
      case 'table':
        $form[$this->entitiesKey]['#tableselect'] = !empty($actions);
        break;
    }

    $entities = $form[$this->entitiesKey]['#entities'];

    if (!$hide_extras && $filter_status && ($entities || !$render_status || $this->isFiltered())) {
      // Filter.
      if ($subform = $this->buildFormFilters($form, $form_state)) {
        if (!empty($subform['sidebar'])) {
          $form['sidebar']['filters'] = $subform['sidebar'];
          unset($subform['sidebar']);
        }
        $form['header']['first']['filters'] = [
          '#access' => !empty(Element::getVisibleChildren($subform)),
        ] + $subform;
      }
    }

    if ($entities) {
      // Columns.
      if ($subform = $this->buildFormColumns($form, $form_state)) {
        $form['header']['first']['columns'] = $subform;
      }
    }

    if ($entities || !$render_status) {
      // Ensure a consistent container for filters/operations in the view
      // header.
      if ($subform = $this->buildFormBatch($form, $form_state)) {
        $form['header']['second']['batch'] = $subform + [
          '#weight' => -100,
        ];
      }

      if (!empty($actions)) {
        $form = $this->buildFormActionsQueue($form, $form_state);
      }
    }

    if ($entities || $this->isFiltered()) {
      // Filter overview.
      if ($entity_list->getSetting('filter_overview_status')) {
        $filter_overview = $this->buildFormFilterOverview($form, $form_state);
        if (!empty($filter_overview)) {
          if (empty($form['header']['second']['batch'])) {
            $form['header']['second']['filter_overview'] = [
              '#weight' => -1000,
            ] + $filter_overview;
          }
          else {
            $form['header']['filter_overview'] = $filter_overview;
          }
        }
      }
    }

    if (!empty($form['#draggable']) && $form[$this->entitiesKey]['#type'] === 'table') {
      $form[$this->entitiesKey]['#tabledrag'] = [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'list-weight',
        ],
      ];

      $form['draggable'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => [
            'exo-list-draggable',
          ],
        ],
      ];
      $form['draggable']['draggable_save'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save Order'),
        '#op' => 'action',
        '#submit' => ['::submitDraggable'],
        '#button_type' => 'primary',
        '#attributes' => [
          'style' => 'display:none',
          'class' => ['exo-list-draggable-submit'],
        ],
      ];

      $weight_field = $this->getWeightField();
      if ($weight_field && !empty($weight_field['view']['settings']['allow_reset'])) {
        $form['draggable']['draggable_reset'] = [
          '#type' => 'submit',
          '#value' => $this->t('Reset to alphabetical'),
          '#weight_field' => $weight_field['id'],
          '#op' => 'action',
          '#submit' => ['::submitDraggableReset'],
        ];
      }
    }
    if ($action_settings_action && isset($actions[$action_settings_action])) {
      foreach (Element::children($form) as $key) {
        $form[$key]['#access'] = FALSE;
      }
      if ($subform = $this->buildBatchForm($form, $form_state)) {
        $form['action_settings'] = $subform;
      }
    }

    return $form;
  }

  /**
   * Build form actions queue.
   */
  protected function buildFormActionsQueue(array $form, FormStateInterface $form_state) {
    $show_action_status_column = FALSE;
    $entity_list = $this->getEntityList();
    $actions = $this->getActions();
    $entities = $form[$this->entitiesKey]['#entities'];
    $actions_status_list = [];
    foreach ($actions as $action_id => $action) {
      if ($action->supportsJobQueue()) {
        /** @var \Drupal\exo_list_builder\QueueWorker\ExoListActionProcess $queue_worker */
        $queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('exo_list_action:' . $entity_list->id() . ':' . $action_id);
        $context = $queue_worker->getContext();
        if (!empty($context['results']['entity_list_id'])) {
          $date_formatter = \Drupal::service('date.formatter');
          $actions_status_list[$action_id] = [
            '#type' => 'inline_template',
            '#template' => '<strong>{{ title }}</strong> | <em>Started:</em> {{ started }}{% if finished %} | <em>Finished:</em> {{ finished }} | <em>Processed:</em> {{ processed }} | <a href="{{ cancel_url }}">{{ clear }}</a>{% else %} | <em>Processed:</em> {{ processed }} | <em>Remaining:</em> {{ remaining }} | <a href="{{ cancel_url }}">{{ cancel }}</a>{% endif %}{% if overview %} | {{ overview }}{% endif %}',
            '#context' => [
              'title' => $action->label(),
              'started' => $date_formatter->format($context['job_start'], 'medium'),
              'finished' => !empty($context['job_finish']) ? $date_formatter->format($context['job_finish'], 'medium') : NULL,
              'processed' => count($context['results']['entity_ids_complete']),
              'remaining' => count($context['results']['entity_ids']) - count($context['results']['entity_ids_complete']),
              'cancel' => $this->icon('Cancel')->setIcon('regular-times-circle'),
              'clear' => $this->icon('Clear')->setIcon('regular-times-circle'),
              'cancel_url' => $entity_list->toUrl('action-cancel-form', [
                'query' => \Drupal::destination()->getAsArray() + ['op' => 'clear'],
              ])->setRouteParameter('exo_entity_list_action', $action_id)->toString(),
              'overview' => $action->overview($context),
            ],
          ];
          if (empty($context['job_finish'])) {
            $show_action_status_column = TRUE;
            foreach ($entities as $entity_id => $entity) {
              $form[$this->entitiesKey][$entity_id]['_action_status']['#wrapper_attributes']['class'][] = 'exo-list-builder-size--compact';
              if (!isset($form[$this->entitiesKey][$entity_id]['_action_status']['data'][$action_id])) {
                $form[$this->entitiesKey][$entity_id]['_action_status']['data'][$action_id] = [
                  '#theme' => 'item_list',
                ];
              }
              if (isset($context['results']['entity_ids_complete'][$entity_id])) {
                $date = $date_formatter->format($context['results']['entity_ids_complete'][$entity_id], 'medium');
                $form[$this->entitiesKey][$entity_id]['_action_status']['data'][$action_id]['#items'][]['#markup'] = '<small>' . $action->label() . ': <em>' . $this->icon($date)->setIcon('regular-check-circle') . '</em></small>';
              }
              elseif (isset($context['results']['entity_ids'][$entity_id])) {
                $form[$this->entitiesKey][$entity_id]['_action_status']['data'][$action_id]['#items'][]['#markup'] = '<small>' . $action->label() . ': <em>' . $this->icon('Pending')->setIcon('regular-clock') . '</em></small>';
              }
              if (isset($form[$this->entitiesKey][$entity_id]['operations'])) {
                $operations = $form[$this->entitiesKey][$entity_id]['operations'];
                unset($form[$this->entitiesKey][$entity_id]['operations']);
                $form[$this->entitiesKey][$entity_id]['operations'] = $operations;
              }
            }
          }
        }
      }
    }
    if (!empty($actions_status_list)) {
      $form['action_status'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['exo-list-action-status'],
        ],
        '#weight' => -99,
      ];
      $form['action_status']['list'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Actions Overview'),
        '#items' => $actions_status_list,
      ];
    }

    if ($show_action_status_column) {
      $form[$this->entitiesKey]['#header']['_action_status'] = [
        'data' => $this->t('Action Status'),
        'class' => ['exo-list-action-status'],
      ];
      // Move operations to last column.
      if (isset($form[$this->entitiesKey]['#header']['operations'])) {
        $operations = $form[$this->entitiesKey]['#header']['operations'];
        unset($form[$this->entitiesKey]['#header']['operations']);
        $form[$this->entitiesKey]['#header']['operations'] = $operations;
      }
    }
    return $form;
  }

  /**
   * Build form pager.
   */
  protected function buildEmpty(array $build) {
    $message = $this->isFiltered() ? $this->getEmptyFilterMessage() : $this->getEmptyMessage();
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'messages',
          'messages--warning',
          'warning',
        ],
      ],
      'message' => [
        '#markup' => $message,
      ],
    ];
  }

  /**
   * Get the empty message.
   *
   * @return string
   *   The message.
   */
  protected function getEmptyMessage() {
    return $this->getEntityList()->getSetting('empty_message', $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]));
  }

  /**
   * Get the empty message when no filtered results are found.
   *
   * @return string
   *   The message.
   */
  protected function getEmptyFilterMessage() {
    return $this->getEntityList()->getSetting('empty_filter_message', $this->t('There are no @label matching the provided conditions.', ['@label' => $this->entityType->getPluralLabel()]));
  }

  /**
   * Build form pager.
   */
  protected function buildSort(array $build) {
    $build = [];
    $entity_list = $this->entityList;
    if ($entity_list->getSetting('sort_status')) {
      $links = [];
      $order = $this->getOption('order') ?: $this->entityList->getSort();
      $sort = $this->getOption('sort');
      $sort_fields = $this->getSortFields();
      $sort_plugin_id = $entity_list->getSortPluginId($order);
      $is_table_format = $entity_list->getFormat() === 'table';
      $active = NULL;
      if ($sort_plugin_id === 'field' && $is_table_format) {
        $field_name = $entity_list->getSortPluginValue($order);
        if (isset($sort_fields[$field_name])) {
          $order = $sort_fields[$field_name]['display_label'];
          $sort = $sort ?: $sort_fields[$field_name]['view']['sort'];
        }
      }
      foreach ($entity_list->getSorts() as $sort_id => $sort_plugin) {
        if ($sort_plugin['id'] === 'default' || !$this->sortManager->hasDefinition($sort_id)) {
          continue;
        }
        $sort_instance = $this->sortManager->createInstance($sort_id, $sort_plugin['settings']);
        // Plugins that support direction change.
        if ($sort_instance->supportsDirectionChange()) {
          $plugin_sort = $sort ?: 'desc';
          foreach (['desc', 'asc'] as $direction) {
            $url = $this->getOptionsUrl([], [], [
              'order' => $sort_id,
              'sort' => $direction,
            ]);
            $label = $direction === 'desc' ? $sort_instance->getDescLabel() : $sort_instance->getAscLabel();
            $icon = $direction === 'desc' ? 'regular-sort-amount-down' : 'regular-sort-amount-up';
            $links[$sort_id . '_' . $direction] = [
              'title' => $this->icon($label)->setIcon($icon)->toMarkup(),
              'url' => $url,
            ];
            if ($sort_plugin_id === $sort_id && $plugin_sort === $direction) {
              $active = [
                'title' => $this->icon('Sorted by @label', [
                  '@label' => $label,
                ])->setIcon($icon)->toMarkup(),
                'url' => $url,
              ];
            }
          }
        }
        // Plugins that do not support direction change.
        else {
          $url = $this->getOptionsUrl([], [], [
            'order' => $sort_id,
          ]);
          $links[$sort_id] = [
            'title' => $this->icon($sort_plugin['label'])->setIcon('regular-sort')->toMarkup(),
            'url' => $url,
          ];
          if ($sort_plugin_id === $sort_id) {
            $active = [
              'title' => $this->icon('Sorted by @label', [
                '@label' => $sort_plugin['label'],
              ])->setIcon('regular-sort')->toMarkup(),
              'url' => $url,
            ];
          }
        }
      }
      foreach ($sort_fields as $field_id => $field) {
        if (!empty($field['view']['sort'])) {
          $new_order = $is_table_format ? $field['display_label'] : 'field:' . $field['id'];
          $asc_url = $this->getOptionsUrl([], [], [
            'order' => $new_order,
            'sort' => 'asc',
          ]);
          $desc_url = $this->getOptionsUrl([], [], [
            'order' => $new_order,
            'sort' => 'desc',
          ]);
          $directions = ['asc', 'desc'];
          if (in_array($field['type'], ['changed', 'created', 'timestamp'])) {
            $directions = array_reverse($directions);
          }
          foreach ($directions as $direction) {
            $icon = $direction === 'desc' ? 'regular-sort-amount-down' : 'regular-sort-amount-up';
            $url = $direction === 'asc' ? $asc_url : $desc_url;
            $links[$field['id'] . '_' . $direction] = [
              'title' => $this->icon($field['view']['sort_' . $direction . '_label'], [
                '@label' => $field['display_label'],
              ])->setIcon($icon)->toMarkup(),
              'url' => $url,
            ];
            if ($order === $new_order && $sort === $direction) {
              $active = [
                'title' => $this->icon('Sorted by ' . $field['view']['sort_' . $direction . '_label'], [
                  '@label' => $field['display_label'],
                ])->setIcon($icon)->toMarkup(),
                'url' => $url,
              ];
            }
          }
        }
      }
      if (count($links) > 1) {
        if (!$active) {
          $active = [
            'title' => $this->icon('Set sort order')->setIcon('regular-sort')->toMarkup(),
            'url' => $this->getOptionsUrl([], [], []),
          ];
        }
        $links = [$active] + $links;
        $build = [
          '#type' => 'container',
          '#attributes' => ['class' => ['exo-list-sort']],
        ];
        $build['list'] = [
          '#type' => 'dropbutton',
          '#links' => $links,
        ];
      }
    }

    return $build;
  }

  /**
   * Return TRUE to use custom search.
   */
  protected function useCustomSort() {
    return $this->customSort;
  }

  /**
   * Build form pager.
   */
  protected function buildFormBatch(array $form, FormStateInterface $form_state) {
    $form = [];
    if ($actions = $this->getActions()) {
      $entity_list = $this->getEntityList();
      $render_status = $entity_list->getSetting('render_status');

      $form = [
        '#type' => 'container',
        '#attributes' => ['class' => ['exo-list-batch']],
        '#attached' => [
          'library' => ['exo_list_builder/download'],
        ],
      ];
      $options = [];
      foreach ($actions as $action_id => $action) {
        $options[$action_id] = $action->label();
      }
      if (empty($options)) {
        return [];
      }
      $form['action'] = [
        '#type' => 'select',
        '#options' => ['' => $this->t('- Bulk Actions -')] + $options,
        '#exo_form_default' => TRUE,
        '#name' => 'action',
      ];
      $form['actions'] = [
        '#type' => 'actions',
        '#states' => [
          '!visible' => [
            ':input[name="action"]' => ['value' => ''],
          ],
        ],
      ];
      $form['actions']['selected'] = [
        '#type' => 'submit',
        '#value' => $this->t('Apply to selected items'),
        '#exo_form_default' => TRUE,
        '#op' => 'action',
        '#name' => 'actions_selected',
        '#submit' => ['::submitBatchForm'],
        '#attributes' => [
          'style' => 'display:none',
          'class' => ['exo-form-button-disable-on-click'],
          'data-exo-form-button-disable-message' => 'Please wait...',
        ],
        '#states' => [
          'visible' => [
            ':input[name^="entities["]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['actions']['all'] = [
        '#type' => 'submit',
        '#value' => $this->t('Apply to all items'),
        '#exo_form_default' => TRUE,
        '#op' => 'action',
        '#name' => 'actions_all',
        '#submit' => ['::submitBatchForm'],
        '#attributes' => [
          'style' => 'display:none',
          'class' => ['exo-form-button-disable-on-click'],
          'data-exo-form-button-disable-message' => 'Please wait...',
        ],
        '#states' => [
          '!visible' => [
            ':input[name^="entities["]' => ['checked' => TRUE],
          ],
        ],
      ];
      if (!$render_status) {
        unset($form['actions']['selected']);
        $form['actions']['all']['#value'] = $this->t('Apply');
        $form['actions']['all']['#states'] = [
          '!visible' => [
            ':input[name="action"]' => ['value' => ''],
          ],
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  /**
   * Get the limit.
   *
   * @return int
   *   The limit.
   */
  public function getLimit() {
    if (!isset($this->limit)) {
      $limit = $this->getOption('limit');
      // Use stored session limit.
      if ($this->entityList->getSetting('limit_status') && $this->entityList->getSetting('remember_limit')) {
        $key = $this->entityList->id() . '_remember_limit';
        $limit = (int) \Drupal::service('session')->get($key) ?: $limit;
      }
      if ($limit || $limit === '0') {
        $options = $this->entityList->getLimitOptions();
        if (!isset($options[$limit])) {
          $limit = $this->entityList->getLimit();
        }
      }
      $this->limit = $limit;
    }
    return $this->limit;
  }

  /**
   * Build form pager.
   */
  protected function buildPager(array $form, &$build) {
    $entity_list = $this->getEntityList();
    $form = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['exo-list-pager']],
    ];
    $limit = $this->getLimit() ?: 0;
    $total = (int) $this->getTotal();
    $page = (int) $this->getOption('page') + 1;
    $pages = $limit > 0 && $total > 0 ? ceil($total / (int) $limit) : 1;

    if ($pages > 1 && $limit && $entity_list->getSetting('limit_status')) {
      $form['limit'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['exo-list-pager-limit']],
      ];

      $form['limit']['limit'] = [
        '#type' => 'select',
        '#title' => $this->t('Showing'),
        '#default_value' => $limit,
        '#exo_form_default' => TRUE,
        '#options' => $this->entityList->getLimitOptions(),
      ];

      $form['limit']['limit_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Go'),
        '#states' => [
          '!visible' => [
            ':input[name="limit"]' => ['value' => $limit],
          ],
        ],
      ];

      if ($pages && $pages != 1) {
        $form['pages']['#markup'] = '<div class="exo-list-pages">' . $this->t('Page @page of @pages', [
          '@page' => $page,
          '@pages' => $pages,
        ]) . '</div>';
      }
    }

    if ($entity_list->getSetting('result_status')) {
      $form['total']['#markup'] = '<div class="exo-list-total">' . $this->t('@total items', [
        '@total' => $this->getTotal(),
      ]) . '</div>';
    }

    if ($limit && $pages && $pages != 1) {
      $form['pager_header'] = $form['pager_footer'] = [
        '#type' => 'pager',
        '#quantity' => 3,
      ];
      $pagerer_header = $this->getEntityList()->getSetting('pagerer_header');
      $pagerer_footer = $this->getEntityList()->getSetting('pagerer_footer');
      if (($pagerer_header || $pagerer_footer) && $this->moduleHandler()->moduleExists('pagerer')) {
        if ($pagerer_header) {
          $form['pager_header'] = [
            '#type' => 'pager',
            '#theme' => 'pagerer',
            '#config' => [
              'preset' => $pagerer_header,
            ],
          ];
        }
        if ($pagerer_footer) {
          $form['pager_footer'] = [
            '#type' => 'pager',
            '#theme' => 'pagerer',
            '#config' => [
              'preset' => $pagerer_footer,
            ],
          ];
        }
      }
      if ($pagerer_header === '_hide') {
        unset($form['pager_header']);
      }
      if ($pagerer_footer === '_hide') {
        unset($form['pager_footer']);
      }
      if ($pagerer_header === '_show_all' || $pagerer_footer === '_show_all') {
        $id = Html::getUniqueId('list');
        $build['#attributes']['data-list-key'] = $id;
        $query = [];
        $query['limit'] = 0;
        $query['lkey'] = $id;
        if ($query_conditions = $this->getQueryConditions()) {
          $query['lqc'] = $entity_list->optionsEncode($query_conditions);
        }
        $url = $entity_list->toUrl('view', [
          'query' => $query,
        ]);
        if ($pagerer_header === '_show_all') {
          unset($form['pager_header']);
        }
        if ($pagerer_footer === '_show_all') {
          $form['pager_footer'] = [
            '#type' => 'link',
            '#title' => $this->t('Show More'),
            '#url' => $url,
            '#attributes' => [
              'class' => ['exo-list-show-more', 'use-ajax'],
            ],
            '#attached' => [
              'library' => ['core/drupal.ajax'],
            ],
          ];
        }
      }
    }

    $form['#access'] = !empty(Element::getVisibleChildren($form));

    return $form;
  }

  /**
   * Build form columns.
   */
  protected function buildFormColumns(array $form, FormStateInterface $form_state) {
    $all_fields = $this->getFields();
    if (empty($all_fields)) {
      return [];
    }
    $form = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['exo-list-columns']],
    ];
    $form['show'] = [
      '#type' => 'table',
      '#header' => [
        'status' => $this->t('Status'),
        'name' => $this->t('Name'),
        'weight' => $this->t('Weight'),
      ],
      '#empty' => $this->t('No fields available.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
    ];
    $shown_fields = $this->getShownFields();
    $weight = 0;
    $fields = array_replace($all_fields, $shown_fields + $all_fields);
    $show = FALSE;
    foreach ($fields as $field_id => $field) {
      if (empty($field['view']['type'])) {
        continue;
      }
      $row = [];
      if (!empty($field['view']['toggle'])) {
        $show = TRUE;
      }
      $enabled = isset($shown_fields[$field_id]);
      $row['#attributes']['class'][] = 'draggable';
      $row['#weight'] = $weight;
      $row['status'] = [
        '#type' => 'checkbox',
        '#default_value' => $enabled,
        '#disabled' => empty($field['view']['toggle']),
      ];
      $row['name'] = [
        '#markup' => '<strong>' . (!empty($field['display_label']) ? $field['display_label'] : $field['label']) . '</strong>',
      ];
      $row['weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for @title', ['@title' => $field['display_label']]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['weight']],
      ];
      $form['show'][$field_id] = $row;
      $weight++;
    }

    // We have no toggleable fields. No reason to show this.
    if (!$show) {
      return [];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->getEntityList()->getSetting('update_label', $this->t('Update')),
    ];
    $form['actions']['close'] = [
      '#type' => 'exo_modal_close',
      '#value' => $this->getEntityList()->getSetting('cancel_label', $this->t('Cancel')),
      '#name' => 'exo_filter_modal_cancel',
      '#attributes' => [
        'class' => ['exo-form-element', 'button', 'reset'],
      ],
    ];
    if ($this->getOption('show', FALSE)) {
      $form['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->getEntityList()->getSetting('update_label', $this->t('Reset')),
        '#url' => $this->getOptionsUrl(['show']),
        '#attributes' => [
          'class' => ['button', 'reset'],
        ],
        '#prefix' => '<div class="exo-form-element">',
        '#suffix' => '</div>',
      ];
    }

    return [
      '#type' => 'exo_modal',
      '#title' => $this->icon('Columns'),
      '#trigger_icon' => 'regular-line-columns',
      '#attributes' => ['class' => ['form-actions']],
      '#trigger_as_button' => TRUE,
      '#modal_settings' => [
        'modal' => [
          'title' => '',
          'right' => 0,
          'openTall' => TRUE,
          'smartActions' => FALSE,
          'closeButton' => FALSE,
          'transitionIn' => 'fadeInRight',
          'transitionOut' => 'fadeOutRight',
        ],
      ],
      '#use_close' => FALSE,
    ] + $form;
  }

  /**
   * Build modal columns.
   */
  protected function buildFormFilters(array $form, FormStateInterface $form_state, ?array $filters = NULL) {
    $filters = $filters ?: $this->getExposedFilters();
    if (empty($filters)) {
      return [];
    }

    foreach ($filters as $field_id => $filter) {
      if (!empty($filter['filter']['settings']['remember'])) {
        $key = $this->entityList->id() . '_' . $field_id . '_remember';
        if ($value = \Drupal::service('session')->get($key)) {
          $this->setOption(['filter', $field_id], $value);
        }
      }
    }
    $inline = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'exo-list-filters-inline',
          'exo-form-inline',
        ],
      ],
      '#parents' => ['filters'],
    ];
    $sidebar = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'exo-list-filters-sidebar',
        ],
      ],
      '#parents' => ['filters'],
      '#tree' => TRUE,
    ];
    $modal = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['exo-list-filters-modal']],
      '#parents' => ['filters'],
    ];

    $show_modal = FALSE;
    $show_inline = FALSE;
    $show_sidebar = FALSE;
    foreach ($this->buildFormFilterFields($filters, $form_state) as $field_id => $filter_form) {
      $settings = $filters[$field_id]['filter']['settings'];
      if (empty($settings['expose'])) {
        $filter_form['#access'] = FALSE;
      }
      if (!empty($settings['position'])) {
        switch ($settings['position']) {
          case 'header':
            $show_inline = TRUE;
            $inline[$field_id] = $filter_form;
            break;

          case 'sidebar':
            $show_sidebar = TRUE;
            $sidebar[$field_id] = $filter_form;
            break;

          default:
            $show_modal = TRUE;
            $modal[$field_id] = $filter_form;
            break;
        }
      }
      else {
        $show_modal = TRUE;
        $modal[$field_id] = $filter_form;
      }
    }

    $modal['actions']['#type'] = 'actions';
    $modal['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->getEntityList()->getSetting('submit_label', $this->t('Apply')),
      '#button_type' => 'primary',
      '#name' => 'exo_filter_modal_submit',
    ];
    $modal['actions']['cancel'] = [
      '#type' => 'exo_modal_close',
      '#value' => $this->getEntityList()->getSetting('cancel_label', $this->t('Cancel')),
      '#name' => 'exo_filter_modal_cancel',
    ];

    $sidebar['actions']['#type'] = 'actions';
    $sidebar['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->getEntityList()->getSetting('submit_label', $this->t('Apply')),
      '#name' => 'exo_filter_sidebar_submit',
    ];

    if ($this->getOption('filter', FALSE)) {
      $modal['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => $this->getOptionsUrl(['filter']),
        '#attributes' => [
          'class' => ['button', 'reset'],
        ],
      ];
      $sidebar['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => $this->getOptionsUrl(['filter']),
        '#attributes' => [
          'class' => ['button', 'reset'],
        ],
      ];
    }

    $form = [];
    if ($show_inline) {
      $form['inline'] = [
        '#access' => !empty(Element::getVisibleChildren($inline)),
      ] + $inline;
      $form['inline']['actions'] = [
        '#type' => 'actions',
        '#attributes' => [
          'class' => ['js-hide'],
        ],
      ];
      $form['inline']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->getEntityList()->getSetting('submit_label', $this->t('Apply')),
        '#name' => 'exo_filter_submit',
      ];
      if ($this->getEntityList()->getSetting('autosubmit')) {
        $form['inline']['actions']['submit']['#attributes']['data-exo-auto-submit-click'] = '';

        if ($this->getEntityList()->getSetting('autosubmit_hide')) {
          $form['inline']['actions']['submit']['#attributes']['class'][] = 'js-hide';
        }
      }
    }
    if ($show_sidebar) {
      $form['sidebar'] = [
        '#access' => !empty(Element::getVisibleChildren($sidebar)),
      ] + $sidebar;
    }
    if ($show_modal) {
      $form['modal'] = [
        '#type' => 'exo_modal',
        '#title' => $this->t('Filters'),
        '#trigger_icon' => 'regular-filter',
        '#attributes' => ['class' => ['form-actions']],
        '#trigger_as_button' => TRUE,
        '#modal_settings' => [
          'modal' => [
            'title' => '',
            'right' => 0,
            'openTall' => TRUE,
            'smartActions' => FALSE,
            'closeButton' => FALSE,
            'transitionIn' => 'fadeInRight',
            'transitionOut' => 'fadeOutRight',
          ],
        ],
        '#use_close' => FALSE,
        '#access' => !empty(Element::getVisibleChildren($modal)),
      ] + $modal;
    }
    if (!empty($form)) {
      return [
        '#tree' => TRUE,
        '#access' => !empty(Element::getVisibleChildren($form)),
      ] + $form;
    }
  }

  /**
   * Build filter fields.
   */
  public function buildFormFilterFields(array $filters, FormStateInterface $form_state) {
    $form = [];
    foreach ($filters as $field_id => $field) {
      if ($field['filter']['instance']) {
        /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
        $instance = $field['filter']['instance'];
        $form[$field_id] = $this->buildFormFilterField($field, $instance, $form_state);
      }
    }
    return $form;
  }

  /**
   * Build filter field.
   */
  protected function buildFormFilterField(array $field, ExoListFilterInterface $instance, FormStateInterface $form_state) {
    $form = [];
    $default = $instance->getDefaultValue($this->entityList, $field);
    $value = $this->getOption([
      'filter',
      $field['id'],
    ], ($this->isModified() ? NULL : $default ?? $instance->defaultValue()));
    $form = $instance->buildForm($form, $form_state, $value, $this->entityList, $field);
    $form = $instance->buildFormAfter($form, $form_state, $value, $this->entityList, $field);
    $form['#access'] = !empty(Element::getVisibleChildren($form));
    return $form;
  }

  /**
   * Build modal columns.
   */
  protected function buildFormFilterOverview(array $form, FormStateInterface $form_state) {
    $form = [];
    $filter_values = $this->getFormFilterOverviewValues($form, $form_state);

    if ($filter_values) {
      $items = [];
      foreach ($filter_values as $filter_id => $filter_value) {
        if ($item = $this->buildFormFilterItem($filter_id, $filter_value)) {
          $items[] = $item;
        }
      }

      if (!empty($items)) {
        $items[] = [
          '#type' => 'link',
          '#title' => $this->t('Reset Filters'),
          '#url' => $this->getOptionsUrl(['filter']),
        ];

        $form['list'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('Filtered By'),
          '#items' => $items,
          '#access' => !empty($items),
          '#prefix' => '<div class="exo-list-filter-overview">',
          '#suffix' => '</div>',
        ];

        if ($this->entityList->getSetting('expose_filter_values_to_data_layer')) {

          $data_layer_data = [
            'event' => 'filterApplied',
            'filters' => [],
          ];

          foreach ($filter_values as $filter_id => $filter_value) {

            if (is_array($filter_value)) {
              $filter_value = implode(',', $filter_value);
            }

            $data_layer_data['filters'][$filter_id] = $filter_value;
          }

          $data_layer_json = json_encode($data_layer_data);

          $form['#attached']['html_head'][] = [
            [
              '#type' => 'html_tag',
              '#tag' => 'script',
              '#value' => "window.dataLayer = window.dataLayer || []; window.dataLayer.push({$data_layer_json});",
            ],
            'data_layer_push',
          ];
        }
      }
    }

    return $form;
  }

  /**
   * Build filter overview values.
   */
  protected function getFormFilterOverviewValues(array $form, FormStateInterface $form_state) {
    $filter_values = $this->getOption('filter');
    $filters = $this->getExposedFilters();
    $values = [];
    foreach ($filters as $field_id => $field) {
      if (isset($filter_values[$field_id])) {
        $values[$field_id] = $filter_values[$field_id];
      }
    }
    if (!$this->isModified()) {
      foreach ($filters as $field_id => $field) {
        if (!isset($values[$field_id])) {
          if ($value = $field['filter']['instance']->getOverviewValue($this->entityList, $field)) {
            $values[$field_id] = $value;
          }
        }
      }
    }
    return $values;
  }

  /**
   * Build filter item.
   */
  protected function buildFormFilterItem($filter_id, $filter_value) {
    $entity_list = $this->getEntityList();
    $field = $this->getExposedFilter($filter_id);
    $value = $filter_value;
    if ($field) {
      $title = $field['display_label'];
      if ($field['filter']['instance']) {
        /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
        $instance = $field['filter']['instance'];
        if (is_array($filter_value) && $instance->supportsMultiple()) {
          $value = [];
          foreach ($filter_value as $filter_val) {
            $value[] = $instance->toPreview($filter_val, $entity_list, $field);
          }
          $value = implode(', ', $value);
        }
        else {
          $value = $instance->toPreview($filter_value, $entity_list, $field);
        }
        $url = $this->getOptionsUrl([], [$filter_id]);
      }
    }
    else {
      $title = ucwords(str_replace('_', ' ', $filter_id));
      $url = $this->getOptionsUrl();
      $options = $url->getOption('query');
      unset($options[$filter_id]);
      $url->setOption('query', $options);
    }
    return [
      '#type' => 'link',
      '#title' => [
        '#type' => 'inline_template',
        '#template' => '<span class="remove">{{ remove }}</span> <span class="title">{{ title }}</span>{% if value %}: <span class="value">{{ value }}</span>{% endif %}',
        '#context' => [
          'title' => $title,
          'value' => $value,
          'remove' => $this->icon('Remove')->setIcon('regular-times')->setIconOnly(),
        ],
      ],
      '#url' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $filters = $this->getExposedFilters();
    if (empty($filters)) {
      return;
    }
    foreach ($filters as $field) {
      $form_field = $form['header']['first']['filters']['inline'][$field['id']] ?? $form['header']['first']['filters']['modal'][$field['id']] ?? $form['sidebar']['filters'][$field['id']] ?? NULL;
      if ($form_field && $field['filter']['instance']) {
        /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
        $instance = $field['filter']['instance'];
        $subform_state = SubformState::createForSubform($form_field, $form, $form_state);
        $instance->validateForm($form_field, $subform_state, $this->getEntityList(), $field);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_list = $this->getEntityList();

    if ($form_state->getRedirect()) {
      // If a redirect has already been set, do not override it.
      return;
    }

    // Reset options.
    $this->setOptions([]);
    // Limit.
    $limit = $form_state->getValue('limit');
    $this->setOption('limit', $limit);
    if ($entity_list->getSetting('remember_limit') && $limit) {
      // Store session limit if enabled.
      $key = $entity_list->id() . '_remember_limit';
      \Drupal::service('session')->set($key, $limit);
    }

    // Show.
    if ($show = $form_state->getValue('show')) {
      $fields = $this->getShownFields();
      $show = array_filter($show, function ($item) {
        return !empty($item['status']);
      });
      uasort($show, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightProperty',
      ]);
      if (array_keys($show) !== array_keys($fields)) {
        $this->setOption('show', array_keys($show));
      }
    }
    // Filters.
    $filters = [];
    foreach ($this->getFilters() as $field_id => $field) {
      if ($field['filter']['instance']) {
        $filter_value = $form_state->getValue(['filters', $field_id]);
        /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
        $instance = $field['filter']['instance'];
        if (!$instance->isEmpty($filter_value)) {
          $filters[$field_id] = $instance->toUrlQuery($filter_value, $entity_list, $field);
        }
      }
    }
    if (!empty($filters)) {
      $this->setOption('filter', $filters);
    }

    $url = $this->getOptionsUrl();
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildBatchForm(array $form, FormStateInterface $form_state) {
    $form = [];
    $entity_list = $this->getEntityList();
    $action = $entity_list->getAvailableActions()[$form_state->get('action_settings_action')];
    /** @var \Drupal\exo_list_builder\Plugin\ExoListActionSettingsInterface $instance */
    $instance = $this->getActions()[$action['id']];
    $form['action_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('@label Settings', [
        '@label' => $action['label'],
      ]),
      '#element_validate' => ['::validateBatchSettingsForm'],
      '#tree' => TRUE,
    ];
    $subform_state = SubformState::createForSubform($form['action_settings'], $form, $form_state);
    $form['action_settings'] = $instance->buildSettingsForm($form['action_settings'], $subform_state, $entity_list, $action);
    $form['action_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process'),
      '#weight' => 1000,
      '#op' => 'action',
      '#submit' => ['::submitBatchSettingsForm'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateBatchSettingsForm(array &$form, FormStateInterface $form_state) {
    $entity_list = $this->getEntityList();
    $action = $entity_list->getAvailableActions()[$form_state->get('action_settings_action')];
    /** @var \Drupal\exo_list_builder\Plugin\ExoListActionSettingsInterface $instance */
    $instance = $this->getActions()[$action['id']];
    $subform_state = SubformState::createForSubform($form, $form_state->getCompleteForm(), $form_state);
    $instance->validateSettingsForm($form, $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitBatchSettingsForm(array &$form, FormStateInterface $form_state) {
    $form_state->setValue('action', $form_state->get('action_settings_action'));
    $form_state->set('action_settings_status', TRUE);
    $form_state->setValue($this->entitiesKey, $form_state->get('action_settings_selected'));
    $this->submitBatchForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitBatchForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (empty($trigger['#op']) || $trigger['#op'] !== 'action') {
      return;
    }
    $entity_list = $this->getEntityList();
    $action_id = $form_state->getValue('action');
    $actions = $entity_list->getAvailableActions();
    if (!isset($actions[$action_id])) {
      return;
    }
    $action = $actions[$action_id];
    $selected = [];
    $selected_keys = array_filter($form_state->getValue($this->entitiesKey) ?? [], function ($item) {
      return $item !== 0;
    });
    foreach ($selected_keys as $key) {
      if (isset($form[$this->entitiesKey][$key]['#entity_id'])) {
        $selected[$form[$this->entitiesKey][$key]['#entity_id']] = $form[$this->entitiesKey][$key]['#entity_id'];
      }
    }
    /** @var \Drupal\exo_list_builder\Plugin\ExoListActionInterface $instance */
    $instance = $this->getActions()[$action['id']];
    if ($instance instanceof ExoListActionSettingsInterface && empty($form_state->get('action_settings_status'))) {
      $form_state->set('action_settings_action', $action_id);
      $form_state->set('action_settings_selected', $selected);
      $form_state->setRebuild();
      return;
    }

    $action['settings'] = $instance->getConfiguration();
    $settings = $form_state->getValue(['action_settings']) ?? [];
    $ids = $instance->getEntityIds($selected, $this);
    $ids = array_combine($ids, $ids);
    $shown_field_ids = array_keys($this->getShownFields());
    if ($instance->runAsJobQueue(count($ids))) {
      if ($data = $this->buildQueue($action_id, $selected, $settings)) {
        $queue = $data['queue'];
        $queue_worker = $data['queue_worker'];
        try {
          while ($item = $queue->claimItem()) {
            // Allow to run for set amount of time. After that, cron will
            // take over.
            $queue_worker->processItem([
              'email_send' => FALSE,
              'timeout' => strtotime('+5 seconds'),
            ] + $item->data);
            $queue->deleteItem($item);
          }
        }
        catch (SuspendQueueException $e) {
          $context = $queue_worker->getContext();
          $emails = $context['results']['emails'] ?? [];
          if ($emails) {
            $this->messenger()->addMessage($this->t('Started action "@action". This process will continue in the background. When finished, a notification email will be sent to %email.', [
              '@action' => $instance->label(),
              '%email' => implode(', ', $emails),
            ]));
          }
          else {
            $this->messenger()->addMessage($this->t('Started action "@action". This process will continue in the background and you can view the status of the action in the "active actions overview" section of this page.', [
              '@action' => $instance->label(),
            ]));
          }
        }
      }
    }
    else {
      $batch_builder = (new BatchBuilder())
        ->setTitle($this->t('Processing Items'))
        ->setFinishCallback([ExoListActionManager::class, 'batchFinish'])
        ->setInitMessage($this->t('Starting item processing.'))
        ->setProgressMessage($this->t('Processed @current out of @total.'))
        ->setErrorMessage($this->t('Item processing has encountered an error.'));
      $do_batch = FALSE;
      $batch_builder->addOperation([ExoListActionManager::class, 'batchStart'], [
        $action,
        $entity_list->id(),
        $shown_field_ids,
        $ids,
        $settings,
      ]);
      foreach ($ids as $entity_id) {
        $do_batch = TRUE;
        $batch_builder->addOperation([ExoListActionManager::class, 'batch'], [
          $action,
          $entity_list->id(),
          $shown_field_ids,
          $entity_id,
          isset($selected[$entity_id]),
        ]);
      }
      if ($do_batch) {
        batch_set($batch_builder->toArray());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildQueue($action_id, array $selected = [], array $settings = [], array $emails = []) {
    $actions = $this->getActions();
    if (!isset($actions[$action_id])) {
      return;
    }
    $entity_list = $this->getEntityList();
    /** @var \Drupal\exo_list_builder\Plugin\ExoListActionInterface $instance */
    $instance = $this->getActions()[$action_id];
    $action = $entity_list->getAvailableActions()[$action_id];
    $action['settings'] = $instance->getConfiguration();
    $ids = $instance->getEntityIds($selected, $this);
    $ids = array_combine($ids, $ids);
    $shown_field_ids = array_keys($this->getShownFields());

    $queue = $this->getQueue($instance->getPluginId());
    $queue->deleteQueue();
    /** @var \Drupal\exo_list_builder\QueueWorker\ExoListActionProcess $queue_worker */
    $queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('exo_list_action:' . $entity_list->id() . ':' . $instance->getPluginId());
    $context = $queue_worker->getContext();
    if (!empty($context['results']['entity_list_id'])) {
      // If we have an existing list that never finished. Append selected
      // ids to unprocessed ids.
      if (!empty($selected)) {
        $ids = array_diff_key($context['results']['entity_ids'], $context['results']['entity_ids_complete']) + $ids;
      }
    }
    if (empty($emails)) {
      if ($email = $instance->getConfiguration()['queue_email'] ?? NULL) {
        $emails[] = $email;
      }
      elseif ($email = \Drupal::currentUser()->getEmail()) {
        $emails[] = $email;
      }
    }
    $emails = array_unique(array_map('trim', $emails));
    $queue_worker->processItem([
      'op' => 'start',
      'action' => $action,
      'list_id' => $this->getEntityList()->id(),
      'field_ids' => $shown_field_ids,
      'entity_ids' => $ids,
      'settings' => $settings,
      'total' => count($ids),
      'emails' => $emails,
    ]);
    $queue->createItem([
      'op' => 'run',
      'action' => $action,
      'list_id' => $this->getEntityList()->id(),
      'field_ids' => $shown_field_ids,
      'settings' => $settings,
      'selected' => $selected,
    ]);

    return [
      'queue' => $queue,
      'queue_worker' => $queue_worker,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitDraggable(array &$form, FormStateInterface $form_state) {
    if ($weights = $form_state->getValue(['weight'])) {
      $weight_field = NULL;
      foreach ($this->getShownFields() as $field_id => $field) {
        if ($field['view']['type'] === 'weight') {
          $weight_field = $field;
          break;
        }
      }
      if ($weight_field) {
        foreach ($this->load() as $entity) {
          $weight = $weights[$entity->id()];
          if ($entity instanceof ContentEntityInterface || $entity instanceof ConfigEntityInterface) {
            $entity->set($weight_field['field_name'], $weight);
            $entity->save();
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitDraggableReset(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $field = $this->entityList->getField($trigger['#weight_field']);

    /** @var \Drupal\exo_list_builder\Plugin\ExoList\Element\Weight $instance */
    $instance = $this->elementManager->createInstance($field['view']['type'], $field['view']['settings']);
    $instance->resetWeights($this->entityList, $field);
  }

  /**
   * {@inheritDoc}
   */
  public function buildHeader() {
    $order = $this->getOption('order') ?: $this->entityList->getSort();
    $initialize_table_sort = !$this->sortManager->hasDefinition($order);
    $fields = $this->getShownFields();
    $order = $this->getOption('order');

    if (!$order && $this->entityList->getSortPluginId() === 'field') {
      $sort_default = $this->entityList->getSortPluginValue();
      if (!isset($fields[$sort_default])) {
        $initialize_table_sort = FALSE;
      }
      else {
        \Drupal::request()->query->set('order', $fields[$sort_default]['display_label']);
      }
    }

    foreach ($fields as $field_id => $field) {
      $row[$field_id]['data'] = $field['display_label'];
      $row[$field_id]['class'][] = Html::getClass('exo-list-builder-field-id--' . $field_id);
      $row[$field_id]['class'][] = Html::getClass('exo-list-builder-field-type--' . $field['view']['type']);
      if (!empty($field['view']['sort']) && !empty($field['sort_field'])) {
        $row[$field_id] += [
          'specifier' => $field['sort_field'],
          'field' => $field['sort_field'],
          'sort' => $initialize_table_sort ? $field['view']['sort'] : NULL,
          'initial_click_sort' => $field['view']['sort'],
        ];
      }
    }
    $row['operations'] = [
      'data' => $this->t('Operations'),
      'class' => [
        'exo-list-builder-field-id--operations',
        'exo-list-builder--compact',
      ],
    ];

    return $row;
  }

  /**
   * Gets this list's default operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the operations are for.
   *
   * @return array
   *   The array structure is identical to the return value of
   *   self::getOperations().
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if ($entity->hasLinkTemplate('duplicate-form') && $entity->access('duplicate')) {
      $operations['duplicate'] = [
        'title' => $this->t('Duplicate'),
        'weight' => 99,
        'url' => $this->ensureDestination($entity->toUrl('duplicate-form')),
      ];
    }
    if ($entity->hasLinkTemplate('restore-form') && $entity->access('restore')) {
      $operations['restore'] = [
        'title' => $this->t('Restore'),
        'weight' => 98,
        'url' => $this->ensureDestination($entity->toUrl('restore-form')),
      ];
    }
    if ($entity->hasLinkTemplate('archive-form') && $entity->access('archive')) {
      $operations['archive'] = [
        'title' => $this->t('Archive'),
        'weight' => 99,
        'url' => $this->ensureDestination($entity->toUrl('archive-form')),
      ];
    }
    foreach ($operations as &$operation) {
      $operation['title'] = $this->icon($operation['title'])->match(['local_task'], $operation['title']);
    }
    return $operations;
  }

  /**
   * Get fields from entity list.
   *
   * @return array
   *   The fields.
   */
  protected function getFields() {
    return $this->getEntityList()->getFields();
  }

  /**
   * Get fields accounting for shown/hidden.
   *
   * @return array
   *   The fields.
   */
  protected function getShownFields() {
    $fields = $this->getFields();
    $show = $this->getOption('show');
    if (!empty($show)) {
      $fields = array_replace(array_flip($show), array_intersect_key($fields, array_flip($show)));
    }
    else {
      $fields = array_filter($fields, function ($field) {
        return !empty($field['view']['type']) && !empty($field['view']['show']);
      });
    }
    return $fields;
  }

  /**
   * Get fields accounting for shown/hidden.
   *
   * @return array
   *   The fields.
   */
  protected function getSortFields() {
    $fields = $this->getShownFields();
    $fields = array_filter($fields, function ($field) {
      return !empty($field['view']['sort']);
    });
    $sort_default = $this->entityList->getSortPluginValue();
    if (!isset($fields[$sort_default])) {
      if ($field = $this->getEntityList()->getField($sort_default)) {
        $fields = [$sort_default => $field] + $fields;
      }
    }
    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public function getWeightField() {
    foreach ($this->getShownFields() as $field) {
      if (!empty($field['view']['type']) && $field['view']['type'] === 'weight') {
        return $field;
      }
    }
    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getFilters() {
    if (!isset($this->filters)) {
      $filters = $this->getFields();
      $filters = array_filter($filters, function ($field) {
        return !empty($field['filter']['type']);
      });
      foreach ($filters as &$field) {
        $field['filter']['instance'] = NULL;
        if ($this->filterManager->hasDefinition($field['filter']['type'])) {
          $field['filter']['instance'] = $this->filterManager->createInstance($field['filter']['type'], $field['filter']['settings']);
        }
      }
      $this->filters = $filters;
    }
    return $this->filters;
  }

  /**
   * Get exposed filter fields.
   *
   * @return array
   *   The exposed filters.
   */
  protected function getExposedFilters() {
    if (!isset($this->exposedFilters)) {
      $filters = [];
      foreach ($this->getFilters() as $field_id => $field) {
        if (empty($field['filter']['settings']['expose']) && empty($field['filter']['settings']['expose_block'])) {
          continue;
        }
        $filters[$field_id] = $field;
      }
      $this->exposedFilters = $filters;
    }
    return $this->exposedFilters;
  }

  /**
   * Get exposed filter field.
   *
   * @return array
   *   The exposed filters.
   */
  protected function getExposedFilter($filter_id) {
    $filters = $this->getExposedFilters();
    return $filters[$filter_id] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getActions() {
    if (!isset($this->actions)) {
      $this->actions = [];
      foreach ($this->entityList->getActions() as $action_id => $action) {
        /** @var \Drupal\exo_list_builder\Plugin\ExoListActionInterface $instance */
        $instance = \Drupal::service('plugin.manager.exo_list_action')->createInstance($action['id'], $action['settings']);
        if ($instance->applies($this)) {
          $this->actions[$action_id] = $instance;
        }
      }
    }
    return $this->actions;
  }

  /**
   * Get queue.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue.
   */
  public function getQueue($action_id) {
    if (!isset($this->queues[$action_id])) {
      /** @var \Drupal\Core\Queue\QueueFactory $queue_factory */
      $queue_factory = \Drupal::service('queue');
      $this->queues[$action_id] = $queue_factory->get('exo_list_action:' . $this->getEntityList()->id() . ':' . $action_id, TRUE);
    }
    return $this->queues[$action_id];
  }

  /**
   * {@inheritDoc}
   */
  public function routes(array $current_routes) {
    $routes = [];
    $entity_list = $this->entityList;
    if (!$entity_list->isOverride() && ($url = $entity_list->getUrl())) {
      $routes['exo_list_builder.' . $entity_list->id()] = new Route($url, [
        '_controller' => '\Drupal\exo_list_builder\Controller\ExoListController::listing',
        '_title_callback' => '\Drupal\exo_list_builder\Controller\ExoListController::listingTitle',
        'exo_entity_list' => $entity_list->id(),
      ], [
        '_entity_access'  => 'exo_entity_list.view',
      ]);
    }
    return $routes;
  }

  /**
   * {@inheritDoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $entity_list = $this->entityList;
    if ($entity_list->isOverride()) {
      $route_name = 'entity.' . $entity_list->getTargetEntityTypeId() . '.collection';
      if ($entity_list->getTargetEntityTypeId() === 'node') {
        $route_name = 'system.admin_content';
      }
      $route = $collection->get($route_name);
      if ($entity_list->getTargetEntityTypeId() === 'media') {
        $route->setRequirement('_permission', 'access media overview');
      }
      if ($route) {
        $this->overrideRoute($route);
      }
    }
  }

  /**
   * Override a route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   */
  protected function overrideRoute(Route $route) {
    $entity_list = $this->entityList;
    if ($url = $entity_list->getUrl()) {
      $route->setPath($url);
    }
    $defaults = $route->getDefaults();
    $defaults['_controller'] = '\Drupal\exo_list_builder\Controller\ExoListController::listing';
    $defaults['_title_callback'] = '\Drupal\exo_list_builder\Controller\ExoListController::listingTitle';
    $defaults['exo_entity_list'] = $entity_list->id();
    unset($defaults['_entity_list']);
    unset($defaults['title']);
    $route->setDefaults($defaults);
    $options = $route->getOptions();
    $options['parameters']['exo_entity_list']['type'] = 'entity:exo_entity_list';
    $route->setOptions($options);
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(
      $this->getEntityList()->getEntityType()->getListCacheContexts(),
      $this->entityType->getListCacheContexts(),
      [
        'url.query_args:sort',
        'url.query_args:order',
        'url.query_args:' . $this->getEntityList()->getKey(),
      ]
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags() {
    return $this->getEntityList()->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    $vars = parent::__sleep();
    $vars = array_combine($vars, $vars);
    // Query contains a database reference and needs to be ignored on sleep.
    unset($vars['query']);
    return array_keys($vars);
  }

}
