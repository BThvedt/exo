<?php

namespace Drupal\exo_list_builder;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for exo list builder.
 */
abstract class ExoListBuilderBase extends EntityListBuilder implements ExoListBuilderInterface {

  use ExoIconTranslationTrait;
  use RedirectDestinationTrait;

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
   * The total number of results.
   *
   * @var int
   */
  protected $total;

  /**
   * The number of entities to list per page, or FALSE to list all entities.
   *
   * For example, set this to FALSE if the list uses client-side filters that
   * require all entities to be listed (like the views overview).
   *
   * @var int|false
   */
  protected $limit = 20;

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
   * An array of query conditions.
   *
   * @var array
   */
  protected $queryConditions = [];

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
      $container->get('plugin.manager.exo_list_filter')
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
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, FormBuilderInterface $form_builder, ModuleHandlerInterface $module_handler, ExoListFieldManagerInterface $field_manager, ExoListManagerInterface $element_manager, ExoListManagerInterface $filter_manager) {
    $this->entityTypeId = $entity_type->id();
    $this->storage = $storage;
    $this->entityType = $entity_type;
    $this->formBuilder = $form_builder;
    $this->moduleHandler = $module_handler;
    $this->fieldManager = $field_manager;
    $this->elementManager = $element_manager;
    $this->filterManager = $filter_manager;
  }

  /**
   * {@inheritDoc}
   */
  public function loadFields() {
    if (!isset($this->fields)) {
      $entity_list = $this->getEntityList();
      $fields = [];
      foreach ($entity_list->getTargetBundleIds() as $bundle) {
        $fields += $this->fieldManager->getFields($entity_list->getTargetEntityTypeId(), $bundle);
      }
      $fields += $this->discoverFields();
      $this->alterFields($fields);
      $this->moduleHandler->alter('exo_list_builder_fields', $fields, $this->entityTypeId);
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->entityTypeId . '_list';
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
    $query = \Drupal::request()->query->all();
    if (!empty($query['exo'])) {
      $query += json_decode(base64_decode($query['exo']), TRUE);
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
   * @return array
   *   The query options.
   */
  protected function getOption($key, $default_value = NULL) {
    $exists = NULL;
    $options = $this->getOptions();
    $option = NestedArray::getValue($options, (array) $key, $exists);
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
  protected function setOption($key, $value) {
    NestedArray::setValue($this->options, (array) $key, $value);
    return $this;
  }

  /**
   * Get options url.
   *
   * @param array $exclude_options
   *   An array of query options to exclude.
   * @param array $exclude_filters
   *   An array of query filters to exclude.
   *
   * @return \Drupal\Core\Url
   *   The url.
   */
  protected function getOptionsUrl(array $exclude_options = [], array $exclude_filters = []) {
    $entity_list = $this->getEntityList();
    $options = $this->getOptions();
    $defaults = $this->getOptionDefaults();
    $query = \Drupal::request()->query->all();
    $query = array_diff_key($query, $defaults);
    unset($query['exo']);
    $query['m'] = 1;
    unset($options['order']);
    unset($options['sort']);
    unset($options['page']);
    if (!empty($options['limit']) && (int) $options['limit'] !== $entity_list->getLimit()) {
      $query['limit'] = $options['limit'];
    }
    unset($options['limit']);
    foreach ($options as $key => $value) {
      if (!empty($value) && isset($defaults[$key]) && !in_array($key, $exclude_options)) {
        if ($key === 'filter') {
          $value = array_diff_key($value, array_flip($exclude_filters));
          if (empty($value)) {
            continue;
          }
        }
        $query['exo'][$key] = $value;
      }
    }
    $url = Url::fromRoute('<current>');
    if (!empty($query['exo'])) {
      $query['exo'] = base64_encode(json_encode($query['exo']));
    }
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
    $limit = $this->getOption('limit');
    if ($limit) {
      $query->pager($limit);
    }

    return $query->execute();
  }

  /**
   * Get the query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query.
   */
  protected function getQuery() {
    $entity_list = $this->getEntityList();
    $query = $this->getStorage()->getQuery()->accessCheck(TRUE);

    $header = $this->buildHeader();
    $query->tableSort($header);

    // Use an set query conditions.
    foreach ($this->queryConditions as $condition) {
      if ($condition['field'] === 'moderation_state') {
        $query->addTag('exo_entity_list_moderation_state');
        // @see exo_list_builder_query_exo_entity_list_moderation_state_alter().
        $query->addMetaData('exo_entity_list_moderation_state', $condition['value']);
      }
      else {
        $query->condition($condition['field'], $condition['value'], $condition['operator'], $condition['langcode']);
      }
    }

    // Filter.
    foreach ($this->getFilters() as $field_id => $field) {
      if (!$field['filter']['instance']) {
        continue;
      }
      // Non-exposed fields that have a default value set.
      if (empty($field['filter']['settings']['expose']) && !empty($field['filter']['settings']['default'])) {
        $filter_value = $field['filter']['settings']['default'];
      }
      // Exposed fields.
      else {
        $filter_value = $this->getOption(['filter', $field_id]);
        // Provide default filters when filter value is empty, list has not been
        // modified and field provides a default.
        if (empty($filter_value) && !$this->isModified() && !empty($field['filter']['settings']['default'])) {
          $filter_value = $field['filter']['settings']['default'];
        }
      }
      /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
      $instance = $field['filter']['instance'];
      if (!is_null($filter_value)) {
        $instance->queryAlter($query, $filter_value, $entity_list, $field);
      }
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function addQueryCondition($field, $value = NULL, $operator = NULL, $langcode = NULL) {
    $this->queryConditions[] = [
      'field' => $field,
      'value' => $value,
      'operator' => $operator,
      'langcode' => $langcode,
    ];
    return $this;
  }

  /**
   * Get the total.
   *
   * @return int
   *   The total results.
   */
  protected function getTotal() {
    if (!isset($this->total)) {
      $this->total = $this->getQuery()->count()->execute();
    }
    return $this->total;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $this->buildOptions();
    return $this->formBuilder->getForm($this);
  }

  /**
   * Check if the entity list is filtered.
   *
   * @return bool
   *   Returns TRUE if filtered.
   */
  protected function isFiltered() {
    return !empty($this->getOption('filter'));
  }

  /**
   * Check if the entity list has been modified by the user.
   *
   * This can happen any time the list is submitted.
   *
   * @return bool
   *   Returns TRUE if modified.
   */
  protected function isModified() {
    return !empty($this->getOption('m'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_list = $this->getEntityList();
    $actions = $entity_list->getActions();

    $form['#attributes']['class'][] = 'exo-list';
    $form['#attached']['library'][] = 'exo_list_builder/list';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh'),
      '#weight' => -200,
      '#attributes' => ['class' => ['hidden']],
    ];

    $form['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => -100,
      '#attributes' => ['class' => ['exo-list-header']],
    ];

    $form['header']['first'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => -100,
      '#attributes' => ['class' => ['exo-list-header-first']],
    ];

    $form['header']['second'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => -100,
      '#attributes' => ['class' => ['exo-list-header-second']],
    ];

    $form[$this->entitiesKey] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#tableselect' => !empty($actions),
      '#attributes' => [
        'class' => ['exo-list-table'],
      ],
      '#cache' => [
        'contexts' => array_merge($entity_list->getEntityType()->getListCacheContexts(), $this->entityType->getListCacheContexts()),
        'tags' => array_merge($entity_list->getEntityType()->getListCacheTags(), $this->entityType->getListCacheTags()),
      ],
    ];

    $form['footer'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#weight' => 100,
      '#attributes' => ['class' => ['exo-list-footer']],
    ];

    $entities = $this->load();
    if ($entities) {
      foreach ($entities as $target_entity) {
        if ($row = $this->buildRow($target_entity)) {
          $form[$this->entitiesKey][$target_entity->id()] = $row;
        }
      }

      // Filter.
      if ($subform = $this->buildFormFilters($form, $form_state)) {
        $form['header']['first']['filters'] = $subform;
      }

      // Columns.
      if ($subform = $this->buildFormColumns($form, $form_state)) {
        $form['header']['first']['columns'] = $subform;
      }

      // Ensure a consistent container for filters/operations in the view header.
      if ($subform = $this->buildFormBatch($form, $form_state)) {
        $form['header']['second']['batch'] = $subform;
        $form['header']['second']['batch']['#attached']['library'][] = 'exo_list_builder/download';
      }

      $form['header']['second']['pager'] = $this->buildFormPager($form, $form_state);
      $form['footer']['pager'] = $form['header']['second']['pager'];
      // Remove pages from header.
      unset($form['header']['second']['pager']['pages']);
      // Remove limit from footer.
      unset($form['footer']['pager']['limit']);
    }
    else {
      $form[$this->entitiesKey] = $this->buildEmpty($form, $form_state);
    }

    // Filter overview.
    $form['header']['filter_overview'] = $this->buildFormFilterOverview($form, $form_state);

    if (empty(Element::getVisibleChildren($form['header']['first']))) {
      unset($form['header']['first']);
    }
    if (empty(Element::getVisibleChildren($form['header']['second']))) {
      unset($form['header']['second']);
    }

    $found_ops = FALSE;
    $entity_keys = Element::children($form['entities']);
    foreach ($entity_keys as $id) {
      if (!empty($form['entities'][$id]['operations']['data']['#links'])) {
        $found_ops = TRUE;
        break;
      }
    }

    if (!$found_ops) {
      unset($form['entities']['#header']['operations']);
      foreach ($entity_keys as $id) {
        unset($form['entities'][$id]['operations']);
      }
    }

    return $form;
  }

  /**
   * Build form pager.
   */
  protected function buildEmpty(array $form, FormStateInterface $form_state) {
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
    return $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]);
  }

  /**
   * Get the empty message when no filtered results are found.
   *
   * @return string
   *   The message.
   */
  protected function getEmptyFilterMessage() {
    return $this->t('There are no filtered results matching the provided conditions.');
  }

  /**
   * Build form pager.
   */
  protected function buildFormBatch(array $form, FormStateInterface $form_state) {
    $form = [];
    $entity_list = $this->getEntityList();
    if ($actions = $entity_list->getActions()) {
      $form = [
        '#type' => 'container',
        '#attributes' => ['class' => ['exo-list-batch']],
      ];
      $options = [];
      foreach ($actions as $action) {
        $options[$action['id']] = $action['label'];
      }
      if (empty($options)) {
        return [];
      }
      $form['action'] = [
        '#type' => 'select',
        '#options' => ['' => $this->t('- Bulk Actions -')] + $options,
        '#exo_form_default' => TRUE,
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
        '#submit' => ['::submitBatchForm'],
        '#attributes' => [
          'style' => 'display:none',
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
        '#submit' => ['::submitBatchForm'],
        '#attributes' => [
          'style' => 'display:none',
        ],
        '#states' => [
          '!visible' => [
            ':input[name^="entities["]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * Build form pager.
   */
  protected function buildFormPager(array $form, FormStateInterface $form_state) {
    $form = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['exo-list-pager']],
    ];
    $limit = $this->getOption('limit');
    $total = $this->getTotal();

    if ($limit) {
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
        '#options' => [
          10 => 10,
          20 => 20,
          50 => 50,
          100 => 100,
        ],
      ];

      $form['limit']['limit_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Go'),
        '#states' => [
          '!visible' => [
            ':input[name="limit"]' => ['value' => $this->getOption('limit')],
          ],
        ],
      ];

      $page = (int) $this->getOption('page') + 1;
      if ($pages = ceil((int) $total / (int) $limit)) {
        $form['pages']['#markup'] = '<div class="exo-list-pages">' . $this->t('Page @page of @pages', [
          '@page' => $page,
          '@pages' => $pages,
        ]) . '</div>';
      }
    }

    $form['total']['#markup'] = '<div class="exo-list-total">' . $this->t('@total items', [
      '@total' => $total,
    ]) . '</div>';

    if ($limit) {
      $form['pager'] = [
        '#type' => 'pager',
        '#quantity' => 3,
      ];
    }

    return $form;
  }

  /**
   * Build form columns.
   */
  protected function buildFormColumns(array $form, FormStateInterface $form_state) {
    $entity_list = $this->getEntityList();
    $all_fields = $entity_list->getFields();
    if (empty($all_fields)) {
      return [];
    }
    $form = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['exo-list-columns']],
    ];
    $form['close'] = [
      '#type' => 'exo_modal_close',
      '#label' => exo_icon()->setIcon('regular-times'),
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
    if ($this->getOption('show', FALSE)) {
      $form['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => $this->getOptionsUrl(['show']),
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Changes'),
    ];

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
  protected function buildFormFilters(array $form, FormStateInterface $form_state) {
    $entity_list = $this->getEntityList();
    $filters = $this->getExposedFilters();
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
    $modal = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['exo-list-filters-modal']],
      '#parents' => ['filters'],
    ];
    $modal['close'] = [
      '#type' => 'exo_modal_close',
      '#label' => exo_icon()->setIcon('regular-times'),
    ];

    $show_modal = FALSE;
    $show_inline = FALSE;
    foreach ($filters as $field_id => $field) {
      if ($field['filter']['instance']) {
        /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
        $settings = $field['filter']['settings'];
        $instance = $field['filter']['instance'];
        $value = $this->getOption([
          'filter',
          $field_id,
        ], ($this->isModified() ? NULL : $field['filter']['settings']['default'] ?? $instance->defaultValue()));
        $filter_form = [];
        $filter_form = $instance->buildForm($filter_form, $form_state, $value, $entity_list, $field);
        if (!empty($settings['position'])) {
          if ($settings['position'] === 'header') {
            $show_inline = TRUE;
            $inline[$field_id] = $filter_form;
          }
        }
        else {
          $show_modal = TRUE;
          $modal[$field_id] = $filter_form;
        }
      }
    }

    $modal['actions']['#type'] = 'actions';
    if ($this->getOption('filter', FALSE)) {
      $modal['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => $this->getOptionsUrl(['filter']),
      ];
    }

    $modal['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Changes'),
    ];

    $form = [];
    if ($show_inline) {
      $form['inline'] = $inline;
      $form['inline']['actions'] = [
        '#type' => 'actions',
        '#attributes' => [
          'class' => ['js-hide'],
        ],
      ];
      $form['inline']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Apply'),
      ];
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
      ] + $modal;
    }
    if (!empty($form)) {
      return [
        '#tree' => TRUE,
      ] + $form;
    }
  }

  /**
   * Build modal columns.
   */
  protected function getFormFilterOverviewValues(array $form, FormStateInterface $form_state) {
    $filter_values = $this->getOption('filter');
    $filters = $this->getFilters();

    if (!$this->isModified()) {
      foreach ($filters as $field_id => $field) {
        if (!isset($filter_values[$field_id]) && !empty($field['filter']['settings']['default'])) {
          $filter_values[$field_id] = $field['filter']['settings']['default'];
        }
      }
    }
    return $filter_values;
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
      $form['list'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Filtered By'),
        '#items' => $items,
        '#access' => !empty($items),
        '#prefix' => '<div class="exo-list-filter-overview">',
        '#suffix' => '</div>',
      ];
    }
    return $form;
  }

  /**
   * Build modal columns.
   */
  protected function buildFormFilterItem($filter_id, $filter_value) {
    $entity_list = $this->getEntityList();
    $filters = $this->getFilters();
    $value = $filter_value;
    if (isset($filters[$filter_id])) {
      $field = $filters[$filter_id];
      $title = $field['display_label'];
      if (empty($field['filter']['settings']['expose'])) {
        return;
      }
      if ($field['filter']['instance']) {
        /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
        $instance = $field['filter']['instance'];
        $value = $instance->toPreview($filter_value, $entity_list, $field);
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_list = $this->getEntityList();

    // Reset options.
    $this->setOptions([]);
    // Limit.
    $this->setOption('limit', $form_state->getValue('limit'));
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
  public function submitBatchForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (empty($trigger['#op']) || $trigger['#op'] !== 'action') {
      return;
    }
    $entity_list = $this->getEntityList();
    $action = $entity_list->getAvailableActions()[$form_state->getValue('action')];
    $selected = array_filter($form_state->getValue($this->entitiesKey));
    if (empty($selected)) {
      $selected = $this->getQuery()->execute();
    }
    // $action['total'] = count($selected);
    $operations = [];
    foreach ($selected as $entity_id) {
      $operations[] = [
        '\Drupal\exo_list_builder\ExoListActionManager::batch',
        [
          $action,
          $entity_id,
          $entity_list->id(),
          array_keys($this->getShownFields()),
        ],
      ];
    }
    if (!empty($operations)) {
      $batch = [
        'operations' => $operations,
        'finished' => '\Drupal\exo_list_builder\ExoListActionManager::batchFinish',
        'title' => t('Processing Example Batch'),
        'init_message' => t('Example Batch is starting.'),
        'progress_message' => t('Processed @current out of @total.'),
        'error_message' => t('Example Batch has encountered an error.'),
      ];
      batch_set($batch);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function buildHeader() {
    foreach ($this->getShownFields() as $field_id => $field) {
      $row[$field_id] = $field['display_label'];
      if (!empty($field['view']['sort']) && !empty($field['sort_field'])) {
        $row[$field_id] = [
          'data' => $row[$field_id],
          'specifier' => $field['sort_field'],
          'field' => $field['sort_field'],
          'sort' => $field['view']['sort'],
        ];
      }
    }
    $row['operations'] = $this->t('Operations');

    if (!$this->getOption('order')) {
      $sort_default = $this->entityList->getSort();
      if (isset($row[$sort_default]['data'])) {
        \Drupal::request()->query->set('order', $row[$sort_default]['data']);
      }
    }

    return $row;
  }

  /**
   * {@inheritDoc}
   */
  public function buildRow(EntityInterface $entity) {
    foreach ($this->getShownFields() as $field_id => $field) {
      $row[$field_id]['data'] = $this->renderField($entity, $field);
      $row[$field_id]['#wrapper_attributes']['class'][] = Html::getClass('exo-list-builder-field-id--' . $field_id);
      $row[$field_id]['#wrapper_attributes']['class'][] = Html::getClass('exo-list-builder-field-type--' . $field['view']['type']);
    }
    $row['operations']['data'] = $this->buildOperations($entity);
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
    $build = $instance->buildView($entity, $field);
    if (!is_array($build)) {
      $build = [
        '#markup' => $build,
      ];
    }
    if (!empty($field['view']['wrapper'])) {
      $build['#prefix'] = '<' . $field['view']['wrapper'] . '>';
      $build['#suffix'] = '</' . $field['view']['wrapper'] . '>';
    }
    return $build;
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
    if ($entity->access('restore') && $entity->hasLinkTemplate('restore-form')) {
      $operations['restore'] = [
        'title' => $this->t('Restore'),
        'weight' => 98,
        'url' => $this->ensureDestination($entity->toUrl('restore-form')),
      ];
    }
    if ($entity->access('archive') && $entity->hasLinkTemplate('archive-form')) {
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
   * Get fields accounting for shown/hidden.
   *
   * @return array
   *   The fields.
   */
  protected function getShownFields() {
    $fields = $this->getEntityList()->getFields();
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
   * Get filters.
   *
   * @return array
   *   The fields.
   */
  protected function getFilters() {
    if (!isset($this->filters)) {
      $this->filters = $this->getEntityList()->getFields();
      $this->filters = array_filter($this->filters, function ($field) {
        return !empty($field['filter']['type']);
      });
      foreach ($this->filters as &$field) {
        $field['filter']['instance'] = NULL;
        if ($this->filterManager->hasDefinition($field['filter']['type'])) {
          $field['filter']['instance'] = $this->filterManager->createInstance($field['filter']['type'], $field['filter']['settings']);
        }
      }
    }
    return $this->filters;
  }

  /**
   * Get filters.
   *
   * @return array
   *   The fields.
   */
  protected function getExposedFilters() {
    $fields = $this->getFilters();
    $fields = array_filter($fields, function ($field) {
      return !empty($field['filter']['settings']['expose']);
    });
    return $fields;
  }

}
