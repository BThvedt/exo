<?php

namespace Drupal\exo_list_builder\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element;
use Drupal\exo_list_builder\ExoListActionManagerInterface;
use Drupal\exo_list_builder\ExoListManagerInterface;
use Drupal\exo_list_builder\ExoListSortManagerInterface;
use Psr\Container\ContainerInterface;

/**
 * Class entity list form.
 */
class EntityListForm extends EntityForm {

  /**
   * The entity.
   *
   * @var \Drupal\exo_list_builder\EntityListInterface
   */
  protected $entity;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The element manager service.
   *
   * @var \Drupal\exo_list_builder\ExoListManagerInterface
   */
  protected $elementManager;

  /**
   * The filter manager service.
   *
   * @var \Drupal\exo_list_builder\ExoListManagerInterface
   */
  protected $filterManager;

  /**
   * The action manager service.
   *
   * @var \Drupal\exo_list_builder\ExoListActionManagerInterface
   */
  protected $actionManager;

  /**
   * The sort manager service.
   *
   * @var \Drupal\exo_list_builder\ExoListSortManagerInterface
   */
  protected $sortManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.exo_list_element'),
      $container->get('plugin.manager.exo_list_filter'),
      $container->get('plugin.manager.exo_list_action'),
      $container->get('plugin.manager.exo_list_sort')
    );
  }

  /**
   * Entity list form constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\exo_list_builder\ExoListManagerInterface $element_manager
   *   The element manager service.
   * @param \Drupal\exo_list_builder\ExoListManagerInterface $filter_manager
   *   The filter manager service.
   * @param \Drupal\exo_list_builder\ExoListActionManagerInterface $action_manager
   *   The action manager service.
   * @param \Drupal\exo_list_builder\ExoListSortManagerInterface $sort_manager
   *   The sort manager service.
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, ExoListManagerInterface $element_manager, ExoListManagerInterface $filter_manager, ExoListActionManagerInterface $action_manager, ExoListSortManagerInterface $sort_manager) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->elementManager = $element_manager;
    $this->filterManager = $filter_manager;
    $this->actionManager = $action_manager;
    $this->sortManager = $sort_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if ($this->operation == 'duplicate') {
      $form['#title'] = $this->t('<em>Duplicate</em> @label', ['@label' => $this->entity->label()]);
      $this->entity = $this->entity->createDuplicate();
    }

    $form = parent::form($form, $form_state);

    /** @var \Drupal\exo_list_builder\EntityListInterface $exo_entity_list */
    $exo_entity_list = $this->entity;
    $entity_type_id = $exo_entity_list->getTargetEntityTypeId();
    $form_state->set('exo_entity_list', $exo_entity_list);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $exo_entity_list->label(),
      '#description' => $this->t("Label for the eXo Entity List."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $exo_entity_list->id(),
      '#machine_name' => [
        'exists' => '\Drupal\exo_list_builder\Entity\EntityList::load',
      ],
      '#disabled' => !$exo_entity_list->isNew(),
    ];

    $form['entity_type'] = [
      '#type' => 'item',
      '#title' => $this->t('Entity type'),
      '#markup' => $exo_entity_list->getTargetEntityType()->getLabel() . ' (' . $exo_entity_list->getTargetEntityType()->id() . ')',
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $exo_entity_list->status(),
    ];

    $form['target_bundles_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Bundles'),
      '#open' => FALSE,
      '#access' => FALSE,
    ];

    $entity_type = $exo_entity_list->getTargetEntityType();
    $form['target_bundles_container']['target_bundles_include'] = [
      '#type' => 'value',
      '#value' => [$entity_type_id => $entity_type_id],
    ];
    if ($entity_type->hasKey('bundle') && $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type->id())) {
      $form['target_bundles_container']['#access'] = TRUE;
      $options = [];
      foreach ($bundles as $bundle_id => $bundle) {
        $options[$bundle_id] = $bundle['label'];
      }
      asort($options);
      $form['target_bundles_container']['target_bundles_include'] = [
        '#type' => 'select',
        '#title' => $this->t('Include Bundles'),
        '#description' => $this->t('If no bundles are select, all bundles will be included.'),
        '#default_value' => $exo_entity_list->getTargetBundleIncludeIds(),
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#multiple' => TRUE,
      ];
      $form['target_bundles_container']['target_bundles_exclude'] = [
        '#type' => 'select',
        '#title' => $this->t('Exclude Bundles'),
        '#default_value' => $exo_entity_list->getTargetBundleExcludeIds(),
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#multiple' => TRUE,
      ];
    }

    if ($exo_entity_list->allowOverride()) {
      $form['override'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use as Entity List Builder'),
        '#description' => $this->t('If checked, this entity list will be used as the entity list builder for the entity type.'),
        '#default_value' => $exo_entity_list->isOverride(),
      ];
    }

    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => [
        'table' => $this->t('Table'),
        'list' => $this->t('Unformatted List'),
      ],
      '#default_value' => $exo_entity_list->getFormat(),
    ];

    $form['list_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link List Item to Entity'),
      '#description' => $this->t('May cause issues if any field contains a link.'),
      '#default_value' => $exo_entity_list->getSetting('list_link'),
      '#parents' => ['settings', 'list_link'],
      '#states' => [
        'visible' => [
          ':input[name="format"]' => ['value' => 'list'],
        ],
      ],
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('A local URL to use for this entity list. Not needed if relying on the entity list builder.'),
      '#default_value' => $exo_entity_list->getUrl(),
    ];

    $form['pager'] = [
      '#type' => 'details',
      '#title' => $this->t('Pager'),
      '#open' => FALSE,
    ];

    $form['pager']['limit_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show All'),
      '#description' => $this->t('If checked, the list will show all results and not use a pager.'),
      '#default_value' => $exo_entity_list->getLimit() == 0,
    ];

    $states = [
      'visible' => [
        ':input[name="limit_all"]' => ['checked' => FALSE],
      ],
    ];

    $form['pager']['limit_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose Limit Selection'),
      '#description' => $this->t('If checked, the user will be able to change the number of results displayed.'),
      '#default_value' => $exo_entity_list->getSetting('limit_status'),
      '#states' => $states,
      '#parents' => ['settings', 'limit_status'],
    ];
    $limit_range_states = $states;
    $limit_range_states['visible'][':input[name="settings[limit_status]"]'] = ['checked' => FALSE];
    $form['pager']['limit_range'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit'),
      '#default_value' => $exo_entity_list->getLimit(),
      '#states' => $limit_range_states,
    ];

    $limit_options_states = $states;
    $limit_options_states['visible'][':input[name="settings[limit_status]"]'] = ['checked' => TRUE];
    $form['pager']['limit_options'] = [
      '#type' => 'table',
      '#header' => [
        'default' => $this->t('Default'),
        'amount' => $this->t('Amount'),
      ],
      '#states' => $limit_options_states,
    ];
    $delta = 0;
    foreach ($exo_entity_list->getLimitOptions() as $int) {
      $row = [];
      $row['default'] = [
        '#type' => 'radio',
        '#default_value' => $exo_entity_list->getLimit() == $int ? $delta : NULL,
        '#parents' => ['limit'],
        '#return_value' => $delta,
      ];
      $row['amount'] = [
        '#type' => 'number',
        '#default_value' => $int,
        '#parents' => ['limit_options', $delta],
      ];
      $form['pager']['limit_options'][$delta] = $row;
      $delta++;
    }

    $default_label = (string) $this->t('Default:');
    $options = [
      $default_label => [
        '' => $this->t('Drupal core pager'),
        '_hide' => $this->t('Hide pager'),
        '_show_all' => $this->t('Show All (ajax)'),
      ],
    ];
    if ($this->moduleHandler->moduleExists('pagerer')) {
      /** @var \Drupal\pagerer\PagererPresetListBuilder $pagerer_preset_list */
      $pagerer_preset_list = $this->entityTypeManager->getListBuilder('pagerer_preset');
      $replace_label = (string) $this->t('Replace with:');
      $options[$replace_label] = $pagerer_preset_list->listOptions();
    }
    $form['pager']['pagerer_header'] = [
      '#type' => 'select',
      '#title' => $this->t('Header pager'),
      '#description' => $this->t("Core pager theme requests can be overridden. Select whether they need to be fulfilled by Drupal core pager, or the Pagerer pager to use."),
      '#options' => $options,
      '#parents' => ['settings', 'pagerer_header'],
      '#default_value' => $exo_entity_list->getSetting('pagerer_header'),
      '#states' => $states,
    ];
    $form['pager']['pagerer_footer'] = [
      '#type' => 'select',
      '#title' => $this->t('Footer pager'),
      '#description' => $this->t("Core pager theme requests can be overridden. Select whether they need to be fulfilled by Drupal core pager, or the Pagerer pager to use."),
      '#options' => $options,
      '#parents' => ['settings', 'pagerer_footer'],
      '#default_value' => $exo_entity_list->getSetting('pagerer_footer'),
      '#states' => $states,
    ];
    $form['pager']['remember'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remember'),
      '#description' => $this->t("If enabled, the list will remember the user's last limit selection."),
      '#parents' => ['settings', 'remember_limit'],
      '#default_value' => $exo_entity_list->getSetting('remember_limit'),
      '#states' => $states,
    ];
    $form['pager']['offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Offset'),
      '#description' => $this->t("The number of results to skip."),
      '#parents' => ['offset'],
      '#default_value' => $exo_entity_list->getOffset(),
    ];

    $this->buildFormActions($form, $form_state);
    $this->buildFormSorts($form, $form_state);

    $form['references'] = [
      '#type' => 'details',
      '#title' => $this->t('References'),
      '#tree' => TRUE,
      '#access' => FALSE,
    ];

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#tree' => TRUE,
    ];

    $form['settings']['key'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Key'),
      '#maxlength' => 255,
      '#default_value' => $exo_entity_list->getKey(),
      '#description' => $this->t("The id used as the query filter identifier in the URL."),
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => '\Drupal\exo_list_builder\Form\EntityListForm::keyAllowed',
      ],
      '#parents' => ['key'],
    ];

    if ($exo_entity_list->getTargetEntityTypeId() === 'node') {
      $form['settings']['node_access_op'] = [
        '#type' => 'select',
        '#title' => $this->t('Node Access OP'),
        '#description' => $this->t('By default, node access is checked using the "view" operation. If you want to use a different operation, select it here.'),
        '#default_value' => $exo_entity_list->getSetting('node_access_op'),
        '#empty_option' => $this->t('View'),
        '#options' => [
          'update' => $this->t('Update'),
          'delete' => $this->t('Delete'),
        ],
      ];
    }

    $form['settings']['render_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Rendered Results'),
      '#description' => $this->t('If unchecked, the rendered rows of the list will not be shown.'),
      '#default_value' => $exo_entity_list->getSetting('render_status'),
    ];

    $form['settings']['operations_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Operations'),
      '#description' => $this->t('If checked, the entity operations will be shown.'),
      '#default_value' => $exo_entity_list->showOperations(),
    ];

    $form['settings']['result_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Result Information'),
      '#description' => $this->t('If checked, the user will be able to see how many results are available.'),
      '#default_value' => $exo_entity_list->getSetting('result_status'),
    ];

    $form['settings']['sort_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Sort Selector'),
      '#description' => $this->t('If checked, the user will be able to change the sort order via a dropdown.'),
      '#default_value' => $exo_entity_list->getSetting('sort_status'),
    ];

    $form['settings']['filter_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Filters'),
      '#description' => $this->t('If checked, the filter will be shown.'),
      '#default_value' => $exo_entity_list->getSetting('filter_status'),
    ];

    $form['settings']['filter_overview_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Filter Overview'),
      '#description' => $this->t('If checked, the filter overview will be shown.'),
      '#default_value' => $exo_entity_list->getSetting('filter_overview_status'),
    ];

    $form['settings']['block_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose Filters in Block'),
      '#description' => $this->t('If checked, a block will be made available that will show the list filters.'),
      '#default_value' => $exo_entity_list->getSetting('block_status'),
    ];

    $form['settings']['first_page_only_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only Show on First Page'),
      '#description' => $this->t('Useful when multiple lists are on the same page and you only want to show this list on the first page.'),
      '#default_value' => $exo_entity_list->getSetting('first_page_only_status'),
    ];

    $form['settings']['hide_no_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide if No Results'),
      '#description' => $this->t('If the list returns no results, do not display it.'),
      '#default_value' => $exo_entity_list->getSetting('hide_no_results'),
    ];

    $form['settings']['disable_url_encoding'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable URL Encoding'),
      '#description' => $this->t('If checked, the URL will show the raw filter query keys and values.'),
      '#default_value' => $exo_entity_list->getSetting('disable_url_encoding'),
    ];

    $form['settings']['cache_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enhanced Cache'),
      '#description' => $this->t('If checked, additional caching will be enabled which will cache the rendered result for all users. This should only be used when the results of the list are the same for all users.'),
      '#default_value' => $exo_entity_list->getSetting('cache_status'),
    ];

    $form['settings']['autosubmit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autosubmit'),
      '#description' => $this->t('If checked, the form will autosubmit when a filter is changed.'),
      '#default_value' => $exo_entity_list->getSetting('autosubmit'),
    ];

    $form['settings']['autosubmit_hide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide submit button'),
      '#description' => $this->t('Hide submit button if javascript is enabled.'),
      '#default_value' => $exo_entity_list->getSetting('autosubmit_hide'),
      '#states' => [
        'visible' => [
          ':input[name="settings[autosubmit]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['settings']['item_wrapper_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wrap each item in a DIV'),
      '#default_value' => $exo_entity_list->getSetting('item_wrapper_status'),
      '#states' => [
        'visible' => [
          ':input[name="format"]' => ['value' => 'list'],
        ],
      ],
    ];

    $form['settings']['expose_filter_values_to_data_layer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose Filter Values to Data Layer'),
      '#description' => $this->t('If checked, the filter values will be pushed to the data layer for tracking purposes.'),
      '#default_value' => $exo_entity_list->getSetting('expose_filter_values_to_data_layer'),
    ];

    $form['settings']['submit_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override Submit Button Label'),
      '#default_value' => $exo_entity_list->getSetting('submit_label'),
    ];

    $form['settings']['empty_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override Empty Message'),
      '#default_value' => $exo_entity_list->getSetting('empty_message'),
    ];

    $form['settings']['empty_filter_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override Empty Filtered Message'),
      '#default_value' => $exo_entity_list->getSetting('empty_filter_message'),
    ];

    $form['fields_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Fields'),
      '#open' => TRUE,
      '#prefix' => '<div id="fields-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['fields_container']['fields'] = [
      '#type' => 'table',
      '#header' => [
        'status' => $this->t('Status'),
        'name' => $this->t('Name'),
        'display_label' => $this->t('Label'),
        'view' => $this->t('View'),
        'filter' => $this->t('Filterable'),
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
      '#prefix' => '<div id="fields-wrapper">',
      '#suffix' => '</div>',
    ];

    $fields = NestedArray::mergeDeep($exo_entity_list->getFields(), $exo_entity_list->getAvailableFields(), $exo_entity_list->getFields());
    foreach ($fields as $field_id => $field) {
      $elements = $this->elementManager->getFieldOptions($field['alias_type'] ?? $field['type'], $exo_entity_list->getTargetEntityTypeId(), $exo_entity_list->getTargetBundleIds(), $field_id);
      $elements = array_filter($elements, function ($element) use ($field) {
        /** @var \Drupal\exo_list_builder\Plugin\ExoListElementInterface $instance */
        $instance = $this->elementManager->createInstance($element);
        return $instance->applies($field);
      }, ARRAY_FILTER_USE_KEY);
      $filters = $this->filterManager->getFieldOptions($field['alias_type'] ?? $field['type'], $exo_entity_list->getTargetEntityTypeId(), $exo_entity_list->getTargetBundleIds(), $field_id);
      $filters = array_filter($filters, function ($filter) use ($field) {
        /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
        $instance = $this->filterManager->createInstance($filter);
        return $instance->applies($field);
      }, ARRAY_FILTER_USE_KEY);
      $row = [];
      $enabled = $exo_entity_list->hasField($field_id);
      $weight = $enabled ? $field['weight'] : 0;
      $row['#attributes']['class'][] = 'draggable';
      $row['#weight'] = $weight;
      $row['status'] = [
        '#type' => 'checkbox',
        '#default_value' => $enabled,
      ];
      $states = [
        'visible' => [
          ':input[name="fields[' . $field_id . '][status]"]' => ['checked' => TRUE],
        ],
      ];
      $row['name'] = [
        '#markup' => '<strong>' . $field['label'] . '</strong><br><small>' . $this->t('Name: %value', [
          '%value' => $field_id,
        ]) . '</small>',
      ];
      if (!empty($field['definition'])) {
        $row['name']['#markup'] .= '<br><small>' . $this->t('Type: %value', [
          '%value' => $field['type'],
        ]) . '</small>';
      }
      $row['display_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $field['display_label'],
        '#states' => $states,
      ];

      $view_id = Html::getId('fields-wrapper-view-' . $field_id);
      $row['view'] = [
        '#type' => 'container',
        '#states' => $states,
        '#id' => $view_id,
      ];
      $view_type = $this->getElementPropertyValue([
        'fields',
        $field_id,
        'view',
        'type',
      ], $form_state, $field['view']['type']);
      $row['view']['type'] = [
        '#type' => 'select',
        '#options' => ['' => $this->t('- None -')] + $elements,
        '#default_value' => $view_type,
        '#ajax' => [
          'event' => 'change',
          'method' => 'replace',
          'wrapper' => $view_id,
          'callback' => [__CLASS__, 'ajaxReplaceFieldsViewCallback'],
        ],
      ];
      $row['view']['options'] = [
        '#type' => 'details',
        '#title' => $this->t('Options'),
        '#access' => !empty($view_type),
        '#states' => [
          '!visible' => [
            ':input[name="fields[' . $field_id . '][view][type]"]' => ['value' => ''],
          ],
        ],
      ];
      $row['view']['options']['toggle'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Allow show/hide'),
        '#default_value' => $field['view']['toggle'],
      ];
      $row['view']['options']['show'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show by default'),
        '#default_value' => $field['view']['show'],
        '#states' => [
          'visible' => [
            ':input[name="fields[' . $field_id . '][view][options][toggle]"]' => ['checked' => TRUE],
          ],
        ],
      ];
      if (!empty($field['sort_field'])) {
        $row['view']['options']['sort'] = [
          '#type' => 'details',
          '#title' => $this->t('Sort'),
        ];
        $row['view']['options']['sort']['sort'] = [
          '#type' => 'select',
          '#title' => $this->t('Sort'),
          '#parents' => [
            'fields',
            $field_id,
            'view',
            'options',
            'sort',
          ],
          '#options' => [
            '' => $this->t('- None -'),
            'asc' => $this->t('Ascending'),
            'desc' => $this->t('Descending'),
          ],
          '#empty_option' => $this->t('- None -'),
          '#default_value' => $field['view']['sort'],
        ];
        $row['view']['options']['sort']['sort_asc_label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Ascending label'),
          '#parents' => [
            'fields',
            $field_id,
            'view',
            'options',
            'sort_asc_label',
          ],
          '#default_value' => $field['view']['sort_asc_label'],
        ];
        $row['view']['options']['sort']['sort_desc_label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Descending label'),
          '#parents' => [
            'fields',
            $field_id,
            'view',
            'options',
            'sort_desc_label',
          ],
          '#default_value' => $field['view']['sort_desc_label'],
        ];
        $row['view']['options']['sort']['sort_natsort'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Attempt Natural Sort'),
          '#parents' => [
            'fields',
            $field_id,
            'view',
            'options',
            'sort_natsort',
          ],
          '#default_value' => $field['view']['sort_natsort'] ?? FALSE,
        ];
        $row['view']['options']['sort']['sort_secondary'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Secondary Sort'),
          '#description' => $this->t('If checked, this field will be used as a secondary sort and will be applied after the default sort.'),
          '#parents' => [
            'fields',
            $field_id,
            'view',
            'options',
            'sort_secondary',
          ],
          '#default_value' => $field['view']['sort_secondary'] ?? FALSE,
        ];
        $row['view']['options']['sort']['sort_default'] = [
          '#type' => 'radio',
          '#title' => $this->t('Default Sort'),
          '#default_value' => $exo_entity_list->getSort() === 'field:' . $field_id ? 'field:' . $field_id : FALSE,
          '#return_value' => 'field:' . $field_id,
          '#parents' => ['sort'],
        ];
      }
      $row['view']['options']['wrapper'] = [
        '#type' => 'select',
        '#title' => $this->t('Wrapper'),
        '#options' => [
          '' => $this->t('- None -'),
          'small' => '<small>',
          'strong' => '<strong>',
          'em' => '<em>',
          'badge' => '<badge>',
          'badge_success' => '<badge success>',
          'badge_warning' => '<badge warning>',
          'badge_alert' => '<badge alert>',
          'div' => '<div class>',
          'div_span' => '<div class><span>',
        ],
        '#default_value' => $field['view']['wrapper'],
      ];
      $row['view']['options']['align'] = [
        '#type' => 'select',
        '#title' => $this->t('Align'),
        '#options' => [
          'left' => $this->t('Left'),
          'right' => $this->t('Right'),
          'center' => $this->t('Center'),
        ],
        '#default_value' => $field['view']['align'],
      ];
      $row['view']['options']['size'] = [
        '#type' => 'select',
        '#title' => $this->t('Size'),
        '#options' => [
          '' => $this->t('Auto'),
          'compact' => $this->t('Compact'),
          'stretch' => $this->t('Stretch'),
          'nowrap' => $this->t('No Wrap'),
        ],
        '#default_value' => $field['view']['size'],
      ];
      $row['view']['options']['group_by'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Group by value'),
        '#default_value' => $field['view']['group_by'],
      ];
      $row['view']['options']['group_by_sort'] = [
        '#type' => 'radios',
        '#title' => $this->t('Group by sort'),
        '#options' => [
          'asc' => $this->t('Ascending'),
          'desc' => $this->t('Descending'),
        ],
        '#default_value' => $field['view']['group_by_sort'],
        '#states' => [
          'visible' => [
            ':input[name="fields[' . $field_id . '][view][options][group_by]"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $row['view']['settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Settings'),
        '#id' => Html::getId('entity-list-view-settings-' . $field_id),
        '#access' => !empty($view_type),
        '#states' => [
          '!visible' => [
            ':input[name="fields[' . $field_id . '][view][type]"]' => ['value' => ''],
          ],
        ],
      ];
      if (!empty($view_type) && $this->elementManager->hasDefinition($view_type)) {
        $element_definition = $this->elementManager->getDefinition($view_type);
        if (!empty($element_definition['sort_only'])) {
          $row['view']['options']['#type'] = 'container';
          foreach (Element::children($row['view']['options']) as $child) {
            if ($child !== 'sort') {
              $row['view']['options'][$child]['#access'] = FALSE;
            }
          }
        }
        else {
          /** @var \Drupal\exo_list_builder\Plugin\ExoListElementInterface $instance */
          $instance = $this->elementManager->createInstance($view_type, $field['view']['settings']);
          $subform_state = SubformState::createForSubform($row['view']['settings'], $form, $form_state);
          $row['view']['settings'] = $instance->buildConfigurationForm($row['view']['settings'], $subform_state, $exo_entity_list, $field);
        }
      }
      if (!Element::children($row['view']['settings'])) {
        $row['view']['settings']['#access'] = FALSE;
      }

      $filter_id = Html::getId('fields-wrapper-filter-' . $field_id);
      $row['filter'] = [
        '#type' => 'container',
        '#id' => $filter_id,
        '#states' => $states,
        '#access' => !empty($filters),
      ];
      $filter_type = $this->getElementPropertyValue([
        'fields',
        $field_id,
        'filter',
        'type',
      ], $form_state, $field['filter']['type']);

      $row['filter']['type'] = [
        '#type' => 'select',
        '#options' => ['' => $this->t('- None -')] + $filters,
        '#default_value' => $filter_type,
        '#ajax' => [
          'event' => 'change',
          'method' => 'replace',
          'wrapper' => $filter_id,
          'callback' => [__CLASS__, 'ajaxReplaceFieldsFilterCallback'],
        ],
      ];
      $row['filter']['settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Settings'),
        '#id' => Html::getId('entity-list-filter-settings-' . $field_id),
        '#access' => !empty($filter_type),
        '#states' => [
          '!visible' => [
            ':input[name="fields[' . $field_id . '][filter][type]"]' => ['value' => ''],
          ],
        ],
      ];
      if (!empty($filter_type) && $this->filterManager->hasDefinition($filter_type)) {
        /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
        $instance = $this->filterManager->createInstance($filter_type, $field['filter']['settings']);
        $subform_state = SubformState::createForSubform($row['filter']['settings'], $form, $form_state);
        $row['filter']['settings'] = $instance->buildConfigurationForm($row['filter']['settings'], $subform_state, $exo_entity_list, $field);
      }

      $row['weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for @title', [
          '@title' => $field['label'],
        ]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['weight']],
      ];
      $form['fields_container']['fields'][$field_id] = $row;

      if ($field['type'] === 'entity_reference' && !empty($field['definition'])) {
        $this->buildFormReferencesField($field, $form, $form_state);
      }
    }

    ksort($form['references']);

    return $form;
  }

  /**
   * Build references field.
   *
   * @param array $field
   *   The field.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildFormReferencesField(array $field, array &$form, FormStateInterface $form_state) {
    $field_id = $field['id'];
    $references = $this->entity->getReferences();
    $reference_fields = $this->entity->getReferenceFields($field);
    if (!empty($reference_fields)) {
      $form['references']['#access'] = TRUE;
      $id = Html::getId('fields-wrapper-references-' . $field_id);
      $form['references'][$field_id] = [
        '#type' => 'fieldset',
        '#title' => $field['label'],
        '#id' => $id,
      ];
      $form['references'][$field_id]['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => !empty($references[$field_id]['status']),
        '#ajax' => [
          'event' => 'change',
          'method' => 'replace',
          'wrapper' => $id,
          'callback' => [__CLASS__, 'ajaxReplaceReferencesCallback'],
        ],
      ];
      if (!empty($references[$field_id]['status'])) {
        $options = [];
        foreach ($reference_fields as $reference_field_id => $reference_field) {
          $options[$reference_field_id] = $reference_field['label'];
        }
        asort($options);
        $form['references'][$field_id]['fields'] = [
          '#type' => 'select',
          '#title' => $this->t('Fields'),
          '#multiple' => TRUE,
          '#options' => $options,
          '#default_value' => $references[$field_id]['fields'] ?? NULL,
        ];
      }
    }

  }

  /**
   * Build actions.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildFormActions(array &$form, FormStateInterface $form_state) {
    $exo_entity_list = $this->entity;

    $actions = $this->getElementPropertyValue(['actions'], $form_state, $exo_entity_list->getActions());
    $open = $exo_entity_list->getActions() !== $actions;

    $form['actions_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Actions'),
      '#open' => $open,
      '#prefix' => '<div id="actions-wrapper" class="exo-form-element">',
      '#suffix' => '</div>',
    ];
    $form['actions_container']['actions'] = [
      '#type' => 'table',
      '#header' => [
        'status' => $this->t('Status'),
        'name' => $this->t('Name'),
        'settings' => '',
      ],
      '#empty' => $this->t('No actions available.'),
    ];
    foreach ($exo_entity_list->getAvailableActions() as $action_id => $action) {
      /** @var \Drupal\exo_list_builder\Plugin\ExoListActionInterface $instance */
      $instance = $this->actionManager->createInstance($action_id, $actions[$action_id]['settings'] ?? $action['settings']);
      if ($instance->applies($exo_entity_list->getHandler())) {
        $enabled = isset($actions[$action_id]);
        $row = [];
        $row['status'] = [
          '#type' => 'checkbox',
          '#default_value' => $enabled,
          '#ajax' => [
            'event' => 'change',
            'method' => 'replace',
            'wrapper' => 'actions-wrapper',
            'callback' => [__CLASS__, 'ajaxReplaceActionsCallback'],
          ],
        ];
        $row['name'] = [
          '#markup' => '<strong>' . $action['label'] . '</strong>',
        ];
        $row['settings'] = [];
        if ($enabled) {
          $row['settings'] = [
            '#type' => 'container',
            '#tree' => TRUE,
          ];
          $subform_state = SubformState::createForSubform($row['settings'], $form, $form_state);
          $row['settings'] = $instance->buildConfigurationForm($row['settings'], $subform_state, $exo_entity_list, $action);
        }
        $form['actions_container']['actions'][$action_id] = $row;
      }
    }
  }

  /**
   * Build sorts.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildFormSorts(array &$form, FormStateInterface $form_state) {
    $exo_entity_list = $this->entity;
    $sorts = $this->getElementPropertyValue(['sorts'], $form_state, $exo_entity_list->getSorts());

    $form['sorts_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Sort'),
      '#description' => $this->t('Individual fields can be set as sortable via their view options.'),
      '#open' => FALSE,
      '#prefix' => '<div id="sorts-wrapper" class="exo-form-element">',
      '#suffix' => '</div>',
    ];
    $form['sorts_container']['sorts'] = [
      '#type' => 'table',
      '#header' => [
        'status' => $this->t('Status'),
        'name' => $this->t('Name'),
        'default' => $this->t('Default Sort'),
      ],
      '#empty' => $this->t('No sorts available.'),
    ];
    $sort_plugins = array_filter($exo_entity_list->getAvailableSorts(), function ($sort) use ($exo_entity_list) {
      /** @var \Drupal\exo_list_builder\Plugin\ExoListSortInterface $instance */
      $instance = $this->sortManager->createInstance($sort);
      return $instance->applies($exo_entity_list);
    }, ARRAY_FILTER_USE_KEY);
    foreach ($sort_plugins as $sort_id => $sort) {
      $enabled = isset($sorts[$sort_id]);
      $row = [];
      $row['status'] = [
        '#type' => 'checkbox',
        '#default_value' => $sort_id === 'default' ?: $enabled,
        '#disabled' => $sort_id === 'default',
      ];
      $row['name'] = [
        '#markup' => '<strong>' . $sort['label'] . '</strong>',
      ];
      $row['default'] = [
        '#type' => 'radio',
        '#default_value' => $exo_entity_list->getSort() === $sort_id ? $sort_id : FALSE,
        '#return_value' => $sort_id,
        '#parents' => ['sort'],
        '#states' => [
          'disabled' => [
            ':input[name="sorts[' . $sort_id . '][status]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $form['sorts_container']['sorts'][$sort_id] = $row;
    }

  }

  /**
   * Ajax replace callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The fields form.
   */
  public static function ajaxReplaceActionsCallback(array $form, FormStateInterface $form_state) {
    return $form['actions_container'];
  }

  /**
   * Ajax replace callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The fields form.
   */
  public static function ajaxReplaceReferencesCallback(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'];
    array_pop($parents);
    $view = NestedArray::getValue($form, $parents);
    return $view;
  }

  /**
   * Ajax replace callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The fields form.
   */
  public static function ajaxReplaceFieldsFilterCallback(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'];
    array_pop($parents);
    $view = NestedArray::getValue($form, $parents);
    return $view;
  }

  /**
   * Ajax replace callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The fields form.
   */
  public static function ajaxReplaceFieldsViewCallback(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'];
    array_pop($parents);
    $view = NestedArray::getValue($form, $parents);
    return $view;
  }

  /**
   * Get element property value.
   *
   * @param array|string $property
   *   The property.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param mixed $default
   *   The default value.
   *
   * @return array|mixed|null
   *   The property value.
   */
  protected function getElementPropertyValue($property, FormStateInterface $form_state, $default = '') {
    return $form_state->hasValue($property)
      ? $form_state->getValue($property)
      : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $sort = $form_state->getUserInput()['sort'] ?? NULL;
    $form_state->setValue('sort', $sort);

    if ($form_state->getValue('limit_all')) {
      $form_state->setValue('limit', 0);
    }
    elseif (!$form_state->getValue(['settings', 'limit_status'])) {
      $form_state->setValue('limit', $form_state->getValue('limit_range'));
    }
    else {
      $limit = (int) $form_state->getValue('limit');
      $limit_options = array_map(function ($int) {
        return (int) $int;
      }, $form_state->getValue('limit_options'));
      if (isset($limit_options[$limit])) {
        $form_state->setValue('limit', $limit_options[$limit]);
        $form_state->setValue('limit_options', $limit_options);
      }
      else {
        $form_state->unsetValue('limit');
        $form_state->unsetValue('limit_options');
      }
    }

    // Url validation.
    $url = $form_state->getValue('url');
    if ($url) {
      if (substr($url, 0, 1) !== '/') {
        $form_state->setError($form['url'], $this->t('The url must be a local URL and start with a slash.'));
      }
    }

    $fields = $form_state->getValue('fields');
    foreach ($fields as $field_id => &$field) {
      if (empty($field['status'])) {
        unset($fields[$field_id]);
      }
      else {
        unset($field['status']);
        $field['view'] += $field['view']['options'];
        unset($field['view']['options']);
        if (empty($field['view']['toggle'])) {
          $field['view']['show'] = TRUE;
        }
        if (!empty($field['view']['type']) && $this->elementManager->hasDefinition($field['view']['type'])) {
          $element_definition = $this->elementManager->getDefinition($field['view']['type']);
          if (empty($element_definition['sort_only'])) {
            /** @var \Drupal\exo_list_builder\Plugin\ExoListElementInterface $instance */
            $instance = $this->elementManager->createInstance($field['view']['type'], $field['view']['settings'] ?? []);
            $subform_state = SubformState::createForSubform($form['fields_container']['fields'][$field_id]['view']['settings'], $form, $form_state);
            $instance->validateConfigurationForm($form['fields_container']['fields'][$field_id]['view']['settings'], $subform_state);
            $field['view']['settings'] = $subform_state->getValues();
          }
        }
        if (!empty($field['filter']['type']) && $this->filterManager->hasDefinition($field['filter']['type'])) {
          /** @var \Drupal\exo_list_builder\Plugin\ExoListFilterInterface $instance */
          $instance = $this->filterManager->createInstance($field['filter']['type'], $field['filter']['settings'] ?? []);
          $subform_state = SubformState::createForSubform($form['fields_container']['fields'][$field_id]['filter']['settings'], $form, $form_state);
          $instance->validateConfigurationForm($form['fields_container']['fields'][$field_id]['filter']['settings'], $subform_state);
          $field['filter']['settings'] = $subform_state->getValues();
        }
      }
    }
    $form_state->setValue('fields', $fields);

    $actions = $form_state->getValue('actions');
    foreach ($actions as $action_id => &$action) {
      if (empty($action['status'])) {
        unset($actions[$action_id]);
      }
      else {
        unset($action['status']);
      }
    }
    $form_state->setValue('actions', $actions);

    $sorts = $form_state->getValue('sorts');
    foreach ($sorts as $sort_id => &$sort) {
      if (empty($sort['status']) || $sort_id === 'default') {
        unset($sorts[$sort_id]);
      }
      else {
        unset($sort['status']);
      }
    }
    $form_state->setValue('sorts', $sorts);

    $references = $form_state->getValue('references');
    if ($references) {
      foreach ($references as $reference_id => &$reference) {
        if (empty($reference['status'])) {
          unset($references[$reference_id]);
        }
        elseif (!empty($reference['fields'])) {
          $references[$reference_id]['fields'] = array_values($reference['fields']);
        }
      }
    }
    $form_state->setValue('references', $references);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $exo_entity_list = $this->entity;
    $status = $exo_entity_list->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label eXo Entity List.', [
          '%label' => $exo_entity_list->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label eXo Entity List.', [
          '%label' => $exo_entity_list->label(),
        ]));
    }

    $form_state->setRedirectUrl($exo_entity_list->toUrl('edit-form'));
  }

  /**
   * Check if the entity key is allowed.
   *
   * @return bool
   *   Returns TRUE if allowed.
   */
  public static function keyAllowed($key) {
    return FALSE;
  }

}
