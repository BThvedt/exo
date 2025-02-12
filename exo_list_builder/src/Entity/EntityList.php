<?php

namespace Drupal\exo_list_builder\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\Plugin\ExoListElementInterface;
use Drupal\exo_list_builder\Plugin\ExoListFilterInterface;

/**
 * Defines the eXo Entity List entity.
 *
 * @ConfigEntityType(
 *   id = "exo_entity_list",
 *   label = @Translation("eXo Entity List"),
 *   label_plural = @Translation("eXo Entity Lists"),
 *   label_collection = @Translation("eXo Entity Lists"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\exo_list_builder\EntityListListBuilder",
 *     "view_builder" = "Drupal\exo_list_builder\EntityListViewBuilder",
 *     "access" = "Drupal\exo_list_builder\EntityListAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\exo_list_builder\Form\EntityListAddForm",
 *       "edit" = "Drupal\exo_list_builder\Form\EntityListForm",
 *       "delete" = "Drupal\exo_list_builder\Form\EntityListDeleteForm",
 *       "duplicate" = "Drupal\exo_list_builder\Form\EntityListForm",
 *       "action_cancel" = "Drupal\exo_list_builder\Form\EntityListActionCancelForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\exo_list_builder\EntityListHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "exo_entity_list",
 *   admin_permission = "administer exo list builder",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "key",
 *     "target_entity_type",
 *     "target_bundles_include",
 *     "target_bundles_exclude",
 *     "override",
 *     "format",
 *     "url",
 *     "limit",
 *     "limit_options",
 *     "offset",
 *     "actions",
 *     "sorts",
 *     "sort",
 *     "references",
 *     "fields",
 *     "settings",
 *     "weight",
 *   },
 *   lookup_keys = {
 *     "target_entity_type",
 *     "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/exo/list/{exo_entity_list}",
 *     "add-form" = "/admin/config/exo/list/add",
 *     "edit-form" = "/admin/config/exo/list/{exo_entity_list}/edit",
 *     "duplicate-form" = "/admin/config/exo/list/{exo_entity_list}/duplicate",
 *     "action-cancel-form" = "/admin/config/exo/list/{exo_entity_list}/{exo_entity_list_action}/cancel",
 *     "delete-form" = "/admin/config/exo/list/{exo_entity_list}/delete",
 *     "collection" = "/admin/config/exo/list"
 *   }
 * )
 */
class EntityList extends ConfigEntityBase implements EntityListInterface {

  /**
   * The eXo Entity List ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The eXo Entity List label.
   *
   * @var string
   */
  protected $label;

  /**
   * The eXo Entity List status.
   *
   * @var bool
   */
  protected $status = TRUE;

  /**
   * The eXo Entity List label.
   *
   * @var string
   */
  protected $key = 'q';

  /**
   * The target entity type id.
   *
   * @var string
   */
  protected $target_entity_type;

  /**
   * The target bundle ids to include.
   *
   * @var array
   */
  protected $target_bundles_include = [];

  /**
   * The target bundle ids to exclude.
   *
   * @var array
   */
  protected $target_bundles_exclude = [];

  /**
   * The override entity list builder flag.
   *
   * @var bool
   */
  protected $override = FALSE;

  /**
   * The entity list format.
   *
   * @var string
   */
  protected $format = 'table';

  /**
   * The entity list URL.
   *
   * @var string
   */
  protected $url = '';

  /**
   * The default limit.
   *
   * @var int
   */
  protected $limit = 10;

  /**
   * The limit options.
   *
   * @var array
   */
  protected $limit_options = [10, 20, 50, 100];

  /**
   * The default offset.
   *
   * @var int
   */
  protected $offset = 0;

  /**
   * The action definitions.
   *
   * @var array
   */
  protected $actions = [];

  /**
   * The action plugin definitions.
   *
   * @var array
   */
  protected $actionDefinitions;

  /**
   * The sort definitions.
   *
   * @var array
   */
  protected $sorts = [];

  /**
   * The sort plugin definitions.
   *
   * @var array
   */
  protected $sortDefinitions;

  /**
   * The sort default.
   *
   * @var string
   */
  protected $sort = '';

  /**
   * The reference fields.
   *
   * @var array
   */
  protected $references = [];

  /**
   * Various settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * The field definitions.
   *
   * @var array
   */
  protected $fields = [];

  /**
   * The available field definitions.
   *
   * @var array
   */
  protected $fieldDefinitions;

  /**
   * The target bundle ids.
   *
   * @var array
   */
  protected $targetBundles;

  /**
   * The field defaults.
   *
   * @var array
   */
  protected static $fieldDefaults = [
    'label' => 'Unnamed',
    'type' => 'custom',
    'view' => [
      'type' => '',
      'settings' => [],
      'toggle' => FALSE,
      'show' => FALSE,
      'wrapper' => 'small',
      'align' => 'left',
      'size' => 'compact',
      'group_by' => FALSE,
      'group_by_sort' => 'asc',
      'sort' => NULL,
      'sort_asc_label' => '@label: Up',
      'sort_desc_label' => '@label: Down',
      'sort_natsort' => FALSE,
      'sort_secondary' => FALSE,
    ],
    'filter' => [
      'type' => '',
      'settings' => [],
    ],
    'reference_field' => NULL,
    'alias_field' => NULL,
    'sort_field' => NULL,
    'weight' => 0,
  ];

  /**
   * The action defaults.
   *
   * @var array
   */
  protected $actionDefaults = [
    'settings' => [],
  ];

  /**
   * The setting defaults.
   *
   * @var array
   */
  protected $settingDefaults = [
    'node_access_op' => NULL,
    'render_status' => TRUE,
    'operations_status' => TRUE,
    'limit_status' => TRUE,
    'result_status' => TRUE,
    'sort_status' => TRUE,
    'filter_status' => TRUE,
    'filter_overview_status' => TRUE,
    'block_status' => FALSE,
    'first_page_only_status' => FALSE,
    'item_wrapper_status' => TRUE,
  ];

  /**
   * The weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The entity handler.
   *
   * @var \Drupal\exo_list_builder\ExoListBuilderInterface
   */
  protected $entityHandler;

  /**
   * The default field values.
   *
   * @return array
   *   The default field values.
   */
  public static function getFieldDefaults() {
    $defaults = static::$fieldDefaults;
    $defaults['view']['settings'] += ExoListElementInterface::DEFAULTS;
    $defaults['filter']['settings'] += ExoListFilterInterface::DEFAULTS;
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    \Drupal::service('router.builder')->rebuild();
    \Drupal::service('plugin.manager.queue_worker')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getKey() {
    return $this->key;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($rel === 'canonical' && $this->isPublished()) {
      $key = $this->getKey();
      if (!empty($options['query'][$key])) {
        $options['query'][$key] = $this->optionsEncode($options['query'][$key]);
      }
      return $this->getHandler()->toUrl($options);
    }
    // This is the frontend view available to the public.
    if ($rel === 'view' && $this->isPublished()) {
      $key = $this->getKey();
      if (!empty($options['query'][$key])) {
        $options['query'][$key] = $this->optionsEncode($options['query'][$key]);
      }
      return Url::fromRoute('entity.exo_entity_list.view', ['exo_entity_list' => $this->id()], $options);
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function toFilteredUrl(array $filters = [], array $options = []) {
    if (!empty($filters)) {
      $key = $this->getKey();
      $options['query'][$key]['filter'] = $filters;
    }
    return $this->toUrl('canonical', $options);
  }

  /**
   * {@inheritdoc}
   */
  public function optionsEncode($options) {
    if (!empty($this->getSetting('disable_url_encoding', FALSE))) {
      return $options;
    }
    return base64_encode(json_encode($options));
  }

  /**
   * {@inheritdoc}
   */
  public function optionsDecode($options) {
    if (!empty($this->getSetting('disable_url_encoding', FALSE))) {
      return $options;
    }
    return is_string($options) ? (@json_decode(base64_decode($options), TRUE) ?: []) : [];
  }

  /**
   * {@inheritDoc}
   */
  public function getTargetEntityTypeId() {
    return $this->target_entity_type;
  }

  /**
   * {@inheritDoc}
   */
  public function getTargetEntityType() {
    return $this->entityTypeManager()->getDefinition($this->getTargetEntityTypeId(), FALSE);
  }

  /**
   * {@inheritDoc}
   */
  public function getTargetBundleIds() {
    if (!isset($this->targetBundles)) {
      $include = $this->getTargetBundleIncludeIds();
      if (empty($include) && $target_entity_type = $this->getTargetEntityTypeId()) {
        $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($target_entity_type);
        foreach ($bundles as $bundle_id => $bundle) {
          $include[$bundle_id] = $bundle_id;
        }
      }
      $this->targetBundles = array_diff_key($include, $this->getTargetBundleExcludeIds());
    }
    return $this->targetBundles;
  }

  /**
   * {@inheritDoc}
   */
  public function getTargetBundleIncludeIds() {
    return $this->target_bundles_include;
  }

  /**
   * {@inheritDoc}
   */
  public function getTargetBundleExcludeIds() {
    return $this->target_bundles_exclude;
  }

  /**
   * {@inheritDoc}
   */
  public function isAllBundles() {
    return empty($this->getTargetBundleIncludeIds());
  }

  /**
   * {@inheritdoc}
   */
  public function allowOverride() {
    return $this->getHandler()->allowOverride();
  }

  /**
   * {@inheritdoc}
   */
  public function isOverride() {
    return $this->allowOverride() ? !empty($this->override) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormat() {
    return $this->format;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return $this->getHandler()->getRouteName();
  }

  /**
   * {@inheritdoc}
   */
  public function getLimit() {
    return $this->limit ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getLimitOptions() {
    return array_combine($this->limit_options, $this->limit_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getOffset() {
    return $this->offset ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function showOperations() {
    return !empty($this->getSetting('operations_status'));
  }

  /**
   * {@inheritdoc}
   */
  public function getSort() {
    return $this->sort;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortPluginId($sort = NULL) {
    $parts = explode(':', $sort ?: $this->getSort() ?? '');
    return $parts[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getSortPluginValue($sort = NULL) {
    $parts = explode(':', $sort ?: $this->getSort() ?? '');
    array_shift($parts);
    return $parts ? implode(':', $parts) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferences() {
    return $this->references ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getActions() {
    $actions = [];
    $definitions = $this->getAvailableActions();
    foreach ($definitions as $action_id => $definition) {
      if (isset($this->actions[$action_id])) {
        $actions[$action_id] = NestedArray::mergeDeep($this->actions[$action_id] + $definition);
      }
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableActions() {
    if (!isset($this->actionDefinitions)) {
      $this->actionDefinitions = [];
      /** @var \Drupal\exo_list_builder\ExoListActionManagerInterface $manager */
      $manager = \Drupal::service('plugin.manager.exo_list_action');
      $actions = [];
      foreach ($this->getTargetBundleIds() as $bundle_id) {
        $actions += $manager->getFieldOptions($this->getTargetEntityTypeId(), $bundle_id);
      }
      foreach ($actions as $action_id => $label) {
        $this->actionDefinitions[$action_id] = [
          'id' => $action_id,
          'label' => $label,
        ] + $this->actionDefaults;
      }
    }
    return $this->actionDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getSorts() {
    $sorts = [];
    $definitions = $this->getAvailableSorts();
    foreach ($definitions as $sort_id => $definition) {
      if (isset($this->sorts[$sort_id])) {
        $sorts[$sort_id] = NestedArray::mergeDeep($this->sorts[$sort_id] + $definition);
      }
    }
    return $sorts;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableSorts() {
    if (!isset($this->sortDefinitions)) {
      $this->sortDefinitions = [];
      /** @var \Drupal\exo_list_builder\ExoListSortManagerInterface $manager */
      $manager = \Drupal::service('plugin.manager.exo_list_sort');
      $sorts = [];
      foreach ($this->getTargetBundleIds() as $bundle_id) {
        $sorts += $manager->getFieldOptions($this->getTargetEntityTypeId(), $bundle_id);
      }
      foreach ($sorts as $action_id => $label) {
        $this->sortDefinitions[$action_id] = [
          'id' => $action_id,
          'label' => $label,
        ] + $this->actionDefaults;
      }
    }
    return $this->sortDefinitions;
  }

  /**
   * {@inheritDoc}
   */
  public function getFields() {
    $fields = [];
    $available_fields = $this->getAvailableFields();
    if (!is_array($this->fields)) {
      return [];
    }
    foreach ($this->fields as $field_id => $field) {
      if (!isset($available_fields[$field_id])) {
        continue;
      }
      // Merge all data.
      $field = NestedArray::mergeDeep($available_fields[$field_id], $field);
      // Always show if field cannot be toggled.
      if (empty($field['view']['toggle'])) {
        $field['view']['show'] = TRUE;
      }
      $fields[$field_id] = $field;
    }

    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public function setFields(array $fields) {
    $this->fields = $fields;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getAvailableFields() {
    if (!isset($this->fieldDefinitions)) {
      $this->fieldDefinitions = [];
      $fields = $this->getHandler()->loadFields();
      foreach ($fields as $field_id => &$field) {
        $defaults = static::getFieldDefaults();
        $field = NestedArray::mergeDeep($defaults, $field);
        $field['id'] = $field_id;
        $field['field_name'] = $field_id;
        if (empty($field['display_label'])) {
          $field['display_label'] = $field['label'];
        }
      }
      $fields = $this->alterAvailableFields($fields);
      // Support field references.
      foreach ($this->getReferences() as $reference_field_id => $reference) {
        if (isset($fields[$reference_field_id])) {
          $parent_field = $fields[$reference_field_id];
          $reference_fields = array_intersect_key($this->getReferenceFields($parent_field), array_flip($reference['fields'] ?? []));
          foreach ($reference_fields as $reference_field) {
            $reference_field['id'] = $reference_field_id . ':' . $reference_field['id'];
            $reference_field['label'] = $parent_field['label'] . ' (' . $reference_field['label'] . ')';
            $reference_field['reference_field'] = $reference_field_id;
            /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
            $definition = $reference_field['definition'];
            $parts = explode(':', $reference_field_id);
            if (count($parts) > 1) {
              // When nesting deep, we need to come up with magic that removes
              // the last part and adds the target entity type id.
              array_pop($parts);
              $reference_field['reference_field_query'] = implode(':', $parts) . '.entity:' . $definition->getTargetEntityTypeId();
            }
            $reference_field['sort_field'] = NULL;
            if (!empty($reference_field['definition']) && !$reference_field['definition']->isComputed()) {
              $field_storage = $reference_field['definition']->getFieldStorageDefinition();
              $property_name = $field_storage->getMainPropertyName();
              if (!$property_name) {
                // If we have no main property, default to first property.
                $property_names = $field_storage->getPropertyNames();
                $property_name = reset($property_names);
              }
              if (!empty($reference_field['reference_field_query'])) {
                // This is gross.
                $parts = explode(':', $reference_field['id']);
                $prop = end($parts);
                $reference_field['sort_field'] = $reference_field['reference_field_query'] . '.' . $prop. '.' . $property_name;
              }
              else {
                $reference_field['sort_field'] = str_replace(':', '.entity.', $reference_field['id']) . '.' . $property_name;
              }
            }
            $fields[$reference_field['id']] = $reference_field;
          }
        }
      }
      // Support field aliases.
      foreach ($fields as $field_id => &$field) {
        if (!empty($field['alias_field']) && isset($fields[$field['alias_field']])) {
          // Alias the field as if it were another field.
          $alias = $fields[$field['alias_field']];
          $field['type'] = $alias['type'];
          $field['field_name'] = $alias['field_name'];
          $field['sort_field'] = $alias['sort_field'];
          if (isset($alias['definition'])) {
            $field['definition'] = $alias['definition'];
          }
          $field['sort_field'] = $alias['sort_field'];
        }
      }
      $this->fieldDefinitions = $fields;
    }
    return $this->fieldDefinitions;
  }

  /**
   * {@inheritDoc}
   */
  public function getReferenceFields(array $field) {
    $fields = [];
    if (!empty($field['definition']) && $field['type'] == 'entity_reference') {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
      $definition = $field['definition'];
      $target_type = $definition->getSetting('target_type');
      $target_bundles = $definition->getSetting('handler_settings')['target_bundles'] ?? NULL;
      if (!$target_bundles) {
        /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_manager */
        $bundle_manager = \Drupal::service('entity_type.bundle.info');
        $target_bundles = array_keys($bundle_manager->getBundleInfo($target_type));
      }
      if (!$target_bundles) {
        $target_bundles = [$target_type];
      }

      /** @var \Drupal\exo_list_builder\EntityListInterface $temp_entity_list */
      $temp_entity_list = $this->entityTypeManager()->getStorage('exo_entity_list')->create([
        'target_entity_type' => $target_type,
        'target_bundles_include' => $target_bundles,
      ]);

      $fields = $temp_entity_list->getAvailableFields();
    }
    return $fields;
  }

  /**
   * Allow builder to modify field list.
   */
  protected function alterAvailableFields($fields) {
    // Set custom field default overrides.
    if (isset($fields['_label'])) {
      // Label field should not wrap in small tag by default.
      $fields['_label']['view']['wrapper'] = '';
      $fields['_label']['view']['size'] = 'stretch';
      $fields['_label']['view']['sort_asc_label'] = '@label A-Z';
      $fields['_label']['view']['sort_desc_label'] = '@label Z-A';
      $fields['_label']['filter']['settings']['position'] = 'header';
      $fields['_label']['filter']['settings']['match_operator'] = 'CONTAINS';
    }
    if (isset($fields['_view'])) {
      $fields['_view']['view']['wrapper'] = '';
    }
    foreach ($fields as &$field) {
      if (in_array($field['type'], ['boolean', 'image'])) {
        // Fields of this type should not wrap in small tag by default.
        $field['view']['wrapper'] = '';
        $field['view']['align'] = 'center';
      }
      if (in_array($field['type'], [
        'created',
        'changed',
        'timestamp',
        'datetime',
      ])) {
        // Fields of this type should not wrap in small tag by default.
        $field['view']['sort_asc_label'] = '@label: Oldest';
        $field['view']['sort_desc_label'] = '@label: Newest';
      }
      if (in_array($field['type'], ['string'])) {
        // Fields of this type should not wrap in small tag by default.
        $field['view']['sort_asc_label'] = '@label: A-Z';
        $field['view']['sort_desc_label'] = '@label: Z-A';
      }
    }
    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public function hasField($field_name) {
    return !empty($this->getField($field_name));
  }

  /**
   * {@inheritDoc}
   */
  public function getField($field_name) {
    $fields = $this->getFields();
    return $fields[$field_name] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldValue($field_name, $key) {
    $default = static::getFieldDefaults();
    $field = $this->getField($field_name);
    return $field[$key] ?? ($default[$key] ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHandlerClass() {
    return $this->getTargetEntityType()->getHandlerClass('exo_list_builder');
  }

  /**
   * {@inheritdoc}
   */
  public function getHandler() {
    if (!isset($this->entityHandler)) {
      $definition = $this->entityTypeManager()->getDefinition($this->getTargetEntityTypeId());
      if ($definition->hasHandlerClass('exo_list_builder_' . $this->id())) {
        // Allow handler per list.
        $class = $definition->getHandlerClass('exo_list_builder_' . $this->id());
      }
      else {
        $class = $definition->getHandlerClass('exo_list_builder');
      }
      $this->entityHandler = $this->entityTypeManager()->createHandlerInstance($class, $definition);
      $this->entityHandler->setEntityList($this);
    }
    return $this->entityHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function notifyEmail($email, $subject, $message, $link_text = NULL, $link_url = NULL, $attachments = []) {
    $module = 'exo_list_builder';
    $key = 'notify';
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $message = [
      '#theme' => 'exo_list_builder_notify',
      '#message' => is_array($message) ? $message : [
        '#markup' => '<p>' . $message . '</p>',
      ],
      '#link_text' => $link_text,
      '#link_url' => $link_url ? ($link_url instanceof Url ? $link_url->setAbsolute()->toString() : $link_url) : NULL,
    ];

    $params = [
      'subject' => $subject,
      'message' => $message,
      'attachments' => $attachments,
    ];

    try {
      /** @var \Drupal\Core\Mail\MailManagerInterface $mail_manager */
      $mail_manager = \Drupal::service('plugin.manager.mail');
      $result = $mail_manager->mail($module, $key, $email, $langcode, $params);
      $sent = (bool) $result['result'];
    }
    catch (\Exception $e) {
      $sent = FALSE;
    }

    return $sent;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return NestedArray::mergeDeep($this->settingDefaults, $this->settings);
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key = '', $default = NULL) {
    $settings = $this->getSettings();
    $key_exists = NULL;
    $value = &NestedArray::getValue($settings, (array) $key, $key_exists);
    return $value ?: $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($key, $value) {
    NestedArray::setValue($this->settings, (array) $key, $value);
    return $this;
  }

  /**
   * Method that is called when a private file is attached to this entity.
   *
   * Called when the private file is downloaded.
   */
  public function getFieldDefinitions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache = $this->getEntityType()->getListCacheTags();
    foreach ($this->getTargetBundleIds() as $bundle) {
      if ($this->getTargetEntityTypeId() === $bundle) {
        $cache = Cache::mergeTags($cache, [$this->getTargetEntityTypeId() . '_list']);
      }
      $cache = Cache::mergeTags($cache, [$this->getTargetEntityTypeId() . '_list:' . $bundle]);
    }
    return $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished() {
    $this->status = TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnpublished() {
    $this->status = FALSE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    $target_entity_type = $this->getTargetEntityType();
    if ($target_entity_type && !$this->isAllBundles()) {
      foreach ($this->getTargetBundleIds() as $bundle_id) {
        $dependency = $target_entity_type->getBundleConfigDependency($bundle_id);
        $this->addDependency($dependency['type'], $dependency['name']);
      }
    }

    return $this->getDependencies();
  }

}
