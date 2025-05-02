<?php

namespace Drupal\exo_asset\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\exo\Plugin\Field\FieldFormatter\ExoEntityReferenceSelectionTrait;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Plugin implementation of the 'eXo asset rendered entity' formatter.
 *
 * @FieldFormatter(
 *   id = "exo_asset",
 *   label = @Translation("eXo Asset"),
 *   description = @Translation("Display the referenced asset with magic."),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   }
 * )
 */
class ExoAsset extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

  use ExoEntityReferenceSelectionTrait;

  /**
   * The number of times this formatter allows rendering the same entity.
   *
   * @var int
   */
  const RECURSIVE_RENDER_LIMIT = 20;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManager
   */
  protected $fieldTypeManager;

  /**
   * The field formatter plugin manager.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $fieldFormatterManager;

  /**
   * An array of available formatters.
   *
   * @var array
   */
  protected $formatters;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * An array of counters for the recursive rendering protection.
   *
   * Each counter takes into account all the relevant information about the
   * field and the referenced entity that is being rendered.
   *
   * @var array
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter::viewElements()
   */
  protected static $recursiveRenderDepth = [];

  /**
   * Constructs a StringFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Field\FieldTypePluginManager $field_type_manager
   *   The formatter type manager.
   * @param \Drupal\Core\Field\FormatterPluginManager $field_formatter_manager
   *   The formatter plugin manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, FieldTypePluginManager $field_type_manager, FormatterPluginManager $field_formatter_manager, ModuleHandlerInterface $module_handler, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldTypeManager = $field_type_manager;
    $this->fieldFormatterManager = $field_formatter_manager;
    $this->moduleHandler = $module_handler;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('plugin.manager.field.formatter'),
      $container->get('module_handler'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'image' => [
        'formatter' => \Drupal::service('module_handler')->moduleExists('exo_image') ? 'exo_image' : 'image',
        'settings' => [],
        'modifiers' => [],
      ],
      'image_mobile' => [
        'formatter' => '',
        'settings' => [],
        'modifiers' => [],
      ],
      'video' => [
        'formatter' => '',
        'settings' => [],
        'modifiers' => [],
      ],
    ] + self::selectionDefaultSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array_merge($this->selectionSettingsSummary(), parent::settingsSummary());
    $settings = $this->fieldDefinition->getThirdPartySetting('exo_asset', 'field_settings');
    foreach ($this->getMediaFieldInfo() as $info) {
      $key = $info['key'];
      if (!empty($settings['allow_' . $key]) && !empty($info['settings']['formatter'])) {
        $formatter_instance = $this->getFormatterInstance($info['media_bundle'], $info['media_field_name'], $info['settings']['formatter'], $info['settings']['settings']);
        $summary[] = $this->t('Field %name', ['%name' => $info['label']]);
        $summary = array_merge($summary, $formatter_instance->settingsSummary());
        if (!empty($info['settings']['modifiers'])) {
          // $summary[] = $this->t('Modifiers: %modifiers', [
          //   '%modifiers' => implode(', ', array_intersect_key($this->getFieldOptions(), $info['settings']['modifiers'])),
          // ]);
        }
      }
    }
    return $summary;
  }

  /**
   * Get media field info.
   */
  protected function getMediaFieldInfo() {
    $info = [];
    $info[] = [
      'key' => 'image',
      'label' => $this->t('Image'),
      'settings' => $this->getSetting('image'),
      'field_type' => 'image',
      'field_name' => 'image',
      'media_bundle' => 'image',
      'media_field_name' => 'field_media_image',
      'required' => TRUE,
    ];
    $info[] = [
      'key' => 'image_mobile',
      'label' => $this->t('Mobile Image'),
      'settings' => $this->getSetting('image_mobile'),
      'field_type' => 'image',
      'field_name' => 'image_mobile',
      'media_bundle' => 'image',
      'media_field_name' => 'field_media_image',
      'required' => FALSE,
    ];
    $info[] = [
      'key' => 'video',
      'label' => $this->t('Video'),
      'settings' => $this->getSetting('video'),
      'field_type' => exo_asset_has_remote_video() ? 'string' : 'video_embed_field',
      'field_name' => 'video',
      'media_bundle' => exo_asset_has_remote_video() ? 'remote_video' : 'video',
      'media_field_name' => exo_asset_has_remote_video() ? 'field_media_oembed_video' : 'field_media_video_embed_field',
      'required' => FALSE,
    ];
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->fieldDefinition->getThirdPartySetting('exo_asset', 'field_settings');
    $elements = [];
    $elements += $this->selectionSettingsForm($form, $form_state);

    foreach ($this->getMediaFieldInfo() as $info) {
      $key = $info['key'];
      if (!empty($settings['allow_' . $key])) {
        $elements[$key] = [];
        $elements[$key] = $this->fieldForm($elements[$key], $form_state, $info);
      }
    }

    return $elements;
  }

  /**
   * Generates a nested field formatter form.
   */
  protected function fieldForm(array $form, FormStateInterface $form_state, $config) {
    $field_name = $this->fieldDefinition->getName();
    $settings = $config['settings'];
    $wrapper_id = 'exo-asset-' . str_replace('_', '-', $config['key']);
    $parents = [
      'fields',
      $field_name,
      'settings_edit_form',
      'settings',
      $config['key'],
    ];

    $form = $this->formatterForm($config, $config['settings'], $parents, $form_state);

    $form['modifiers'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Modifiers'),
      '#description' => $this->t('Modifiers can be used to change the formatter if the modifier has a value.'),
      '#element_validate' => [[$this, 'validateModifiers']],
    ];
    foreach ($this->getFieldOptions() as $option_field_name => $option_field) {
      $modifier_parents = array_merge($parents, ['modifiers', $option_field_name]);
      $modifier_settings = $form_state->getValue($modifier_parents);
      if (empty($modifier_settings)) {
        $modifier_settings = !empty($settings['modifiers'][$option_field_name]) ? $settings['modifiers'][$option_field_name] : [];
      }
      $wrapper_id = 'exo-asset-' . str_replace('_', '-', implode('-', $modifier_parents));
      $form['modifiers'][$option_field_name] = [
        '#type' => 'container',
      ];
      $form['modifiers'][$option_field_name]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $option_field->getLabel(),
        '#default_value' => !empty($modifier_settings['enabled']),
        '#ajax' => [
          'callback' => [get_class($this), 'settingsFormAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];

      $form['modifiers'][$option_field_name]['settings'] = [
        '#type' => 'container',
        '#id' => $wrapper_id,
        '#parents' => $modifier_parents,
      ];

      if (!empty($modifier_settings['enabled'])) {
        if ($option_field->getType() == 'exo_attribute') {
          foreach ($this->getExoAttributeOptions($option_field) as $key => $option) {
            $modifier_attribute_settings = $modifier_settings['options'][$key] ?? [];
            $modifier_parents = array_merge($modifier_parents, [
              'settings',
              'options',
              $key,
            ]);
            $form['modifiers'][$option_field_name]['settings']['options'][$key] = [
              '#type' => 'details',
              '#title' => $option,
              '#open' => FALSE,
            ];
            $form['modifiers'][$option_field_name]['settings']['options'][$key] += $this->formatterForm($config, $modifier_attribute_settings, $modifier_parents, $form_state);
          }
        }
        else {
          $modifier_parents = array_merge($modifier_parents, ['settings']);
          $form['modifiers'][$option_field_name]['settings'] += $this->formatterForm($config, $modifier_settings, $modifier_parents, $form_state);
        }
      }
    }

    return $form;
  }

  /**
   * Get eXo attribute options.
   */
  protected function getExoAttributeOptions(FieldConfigInterface $option_field) {
    $options = [];
    $display = EntityFormDisplay::load($option_field->getTargetEntityTypeId() . '.' . $option_field->getTargetBundle() . '.' . $this->viewMode);
    if ($display) {
      $component = $display->getComponent($option_field->getName());
      if ($component) {
        $component['field_definition'] = $option_field;
        $instance = \Drupal::service('plugin.manager.field.widget')->createInstance($component['type'], $component);
        $options = $instance->getDefaultOptions();
      }
    }
    return $options;
  }

  /**
   * Validation callback for the modifiers element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateModifiers(array &$element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    $values = array_filter($values, function ($value) {
      return !empty($value['enabled']);
    });
    $form_state->setValue($element['#parents'], $values);
  }

  /**
   * Generates a nested field formatter form.
   */
  protected function formatterForm($config, $settings, $parents, FormStateInterface $form_state) {
    $wrapper_id = 'exo-asset-' . str_replace('_', '-', implode('-', $parents)) . '-settings';

    $formatter_parents = array_merge($parents, [
      'formatter',
    ]);
    $formatter = $form_state->getValue($formatter_parents);
    if (empty($formatter)) {
      $formatter = (string) NestedArray::getValue($form_state->getUserInput(), $formatter_parents);
    }
    if (empty($formatter)) {
      $formatter = !empty($settings['formatter']) ? $settings['formatter'] : $config['settings']['formatter'];
    }
    $formatter_settings_parents = array_merge($parents, [
      'settings',
    ]);
    $formatter_settings = $form_state->getValue($formatter_settings_parents);
    if (empty($formatter_settings)) {
      $formatter_settings = NestedArray::getValue($form_state->getUserInput(), $formatter_settings_parents);
    }
    if (empty($formatter_settings)) {
      $formatter_settings = !empty($settings['settings']) ? $settings['settings'] : (!empty($config['settings']['settings']) ? $config['settings']['settings'] : []);
    }
    if (!is_array($formatter_settings)) {
      $formatter_settings = [];
    }

    $form = [
      '#type' => 'fieldset',
      '#title' => $config['label'],
    ];

    $formatter_options = array_map(function ($item) {
      return $item['label'];
    }, $this->getFieldFormatters($config['field_type']));
    if (empty($config['required'])) {
      $formatter_options = ['' => '- Hidden -'] + $formatter_options;
    }

    $form['formatter'] = [
      '#type' => 'select',
      '#title' => $this->t('Formatter'),
      '#options' => $formatter_options,
      '#default_value' => $formatter,
      '#ajax' => [
        'callback' => [get_class($this), 'settingsFormAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $form['settings'] = [
      '#type' => 'container',
      '#id' => $wrapper_id,
    ];
    if (!empty($formatter)) {
      $formatter_instance = $this->getFormatterInstance($config['media_bundle'], $config['media_field_name'], $formatter, $formatter_settings);
      if (!empty($formatter_instance)) {
        $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
        $settings_form = $formatter_instance->settingsForm($form['settings'], $subform_state);
        if (!empty($settings_form)) {
          $form['settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Settings'),
          ] + $settings_form + $form['settings'];
        }
      }
    }
    return $form;
  }

  /**
   * Ajax callback for the handler settings form.
   *
   * @see static::fieldSettingsForm()
   */
  public static function settingsFormAjax($form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    // Go one level up in the form, to the widgets container.
    $elements = NestedArray::getValue($form, array_slice($element['#array_parents'], 0, -1));
    return $elements['settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $settings = $this->fieldDefinition->getThirdPartySetting('exo_asset', 'field_settings');
    $elements = [];
    if ($items instanceof EntityReferenceFieldItemListInterface) {
      $entities = $entities = $this->getEntitiesToView($items, $langcode);
      foreach ($entities as $delta => $entity) {
        // Due to render caching and delayed calls, the viewElements() method
        // will be called later in the rendering process through a '#pre_render'
        // callback, so we need to generate a counter that takes into account
        // all the relevant information about this field and the referenced
        // entity that is being rendered.
        $recursive_render_id = $items->getFieldDefinition()->getTargetEntityTypeId()
          . $items->getFieldDefinition()->getTargetBundle()
          . $items->getName()
          // We include the referencing entity, so we can render default images
          // without hitting recursive protections.
          . $items->getEntity()->id()
          . $entity->getEntityTypeId()
          . $entity->id();

        if (isset(static::$recursiveRenderDepth[$recursive_render_id])) {
          static::$recursiveRenderDepth[$recursive_render_id]++;
        }
        else {
          static::$recursiveRenderDepth[$recursive_render_id] = 1;
        }

        // Protect ourselves from recursive rendering.
        if (static::$recursiveRenderDepth[$recursive_render_id] > static::RECURSIVE_RENDER_LIMIT) {
          $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity %entity_type: %entity_id, using the %field_name field on the %bundle_name bundle. Aborting rendering.', [
            '%entity_type' => $entity->getEntityTypeId(),
            '%entity_id' => $entity->id(),
            '%field_name' => $items->getName(),
            '%bundle_name' => $items->getFieldDefinition()->getTargetBundle(),
          ]);
          return $elements;
        }

        $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
        $build = $view_builder->view($entity, 'default', $entity->language()->getId());
        $build['#parent_entity'] = $items->getEntity();
        $build['#parent_field_name'] = $items->getName();
        $build['#pre_render'][] = [$this, 'preRender'];
        $build['#cache']['keys'][] = $build['#parent_entity']->getEntityTypeId();
        $build['#cache']['keys'][] = $build['#parent_entity']->id();
        $build['#cache']['keys'][] = $this->viewMode;

        if (!empty($settings['allow_link']) && $entity->hasField('link') && !$entity->link->isEmpty()) {
          $build['#tag'] = 'a';
          $build['#attributes']['href'] = $entity->link->first()->getUrl()->toString();
        }

        $elements[$delta] = $build;

        // Add a resource attribute to set the mapping property's value to the
        // entity's url. Since we don't know what the markup of the entity will
        // be, we shouldn't rely on it for structured data such as RDFa.
        if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
          $items[$delta]->_attributes += ['resource' => $entity->toUrl()->toString()];
        }
      }
    }
    return $elements;
  }

  /**
   * Pre-render the entity to override the formatters.
   */
  public function preRender($element) {
    $entity = $element['#exo_asset'];
    foreach ($this->getMediaFieldInfo() as $info) {
      if (
        !empty($info['settings']['formatter'])
        && $entity->hasField($info['field_name'])
        && !$entity->{$info['field_name']}->isEmpty()
        && $entity->{$info['field_name']}->entity
        && $entity->{$info['field_name']}->entity->hasField($info['media_field_name'])
        && !$entity->{$info['field_name']}->entity->{$info['media_field_name']}->isEmpty()
      ) {
        $formatter = $info['settings']['formatter'];
        $settings = $info['settings']['settings'];
        $attributes = !empty($element[$info['field_name']]['#attributes']) ? $element[$info['field_name']]['#attributes'] : [];
        $attached = !empty($element[$info['field_name']]['#attached']) ? $element[$info['field_name']]['#attached'] : [];
        $parent = $element['#parent_entity'];
        $parent_field_name = $element['#parent_field_name'];
        if (!empty($info['settings']['modifiers'])) {
          foreach ($info['settings']['modifiers'] as $field_name => $modifier_settings) {
            if ($parent->hasField($field_name) && !$parent->get($field_name)->isEmpty()) {
              $option_field = $parent->get($field_name)->getFieldDefinition();
              if ($option_field->getType() == 'boolean' && empty($parent->get($field_name)->value)) {
                continue;
              }
              if ($option_field->getType() == 'exo_attribute') {
                $value = $parent->get($field_name)->value;
                if (isset($modifier_settings['options'][$value])) {
                  $formatter = $modifier_settings['options'][$value]['formatter'];
                  $settings = $modifier_settings['options'][$value]['settings'];
                }
              }
              else {
                $formatter = $modifier_settings['formatter'];
                $settings = $modifier_settings['settings'];
              }
              break;
            }
          }
        }
        $element[$info['field_name']] = $entity->{$info['field_name']}->entity->{$info['media_field_name']}->view([
          'type' => $formatter,
          'label' => 'hidden',
          'settings' => $settings,
        ]) + [
          '#attributes' => [],
          '#attached' => [],
        ];
        $element[$info['field_name']]['#attributes'] = NestedArray::mergeDeep($element[$info['field_name']]['#attributes'], $attributes);
        $element[$info['field_name']]['#attached'] = NestedArray::mergeDeep($element[$info['field_name']]['#attached'], $attached);
      }
    }
    return $element;
  }

  /**
   * Gets a field formatter instance.
   */
  protected function getFormatterInstance($bundle, $field_name, $formatter, $settings) {
    $field_definition = $this->getFieldDefinitions($bundle, $field_name);
    $options = [
      'field_definition' => $field_definition,
      'view_mode' => 'full',
      'settings' => $settings,
      'configuration' => ['type' => $formatter, 'settings' => $settings],
    ];
    return $this->fieldFormatterManager->getInstance($options);
  }

  /**
   * Gets formatters for the given field type.
   *
   * @param string $bundle
   *   The media bundle name.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   A field definition.
   */
  protected function getFieldDefinitions($bundle, $field_name) {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', $bundle);
    return $field_definitions[$field_name] ?? NULL;
  }

  /**
   * Gets formatters for the given field type.
   *
   * @param string $field_type
   *   The field type id.
   *
   * @return array
   *   Formatters info array.
   */
  protected function getFieldFormatters($field_type) {
    if (!isset($this->formatters)) {
      $this->formatters = $this->fieldFormatterManager->getDefinitions();
    }

    $formatters = [];
    foreach ($this->formatters as $formatter => $formatter_info) {
      if (in_array($field_type, $formatter_info['field_types'])) {
        $formatters[$formatter] = $formatter_info;
      }
    }
    return $formatters;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $entities = parent::getEntitiesToView($items, $langcode);
    $entities = $this->filterSelectionEntities($entities);
    return $entities;
  }

  /**
   * Get the field options.
   */
  protected function getFieldOptions() {
    $entityTypeManager = \Drupal::service('entity_field.manager');
    $fields = $entityTypeManager->getFieldDefinitions($this->fieldDefinition->getTargetEntityTypeId(), $this->fieldDefinition->getTargetBundle());
    $options = [];
    foreach ($fields as $field) {
      if ($field->isDisplayConfigurable('form') && !in_array($field->getName(), [
        'uid',
        'status',
        'created',
      ])) {
        $options[$field->getName()] = $field;
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    foreach ($this->getMediaFieldInfo() as $info) {
      if (!empty($info['settings']['formatter'])) {
        $formatter_instance = $this->getFormatterInstance($info['media_bundle'], $info['media_field_name'], $info['settings']['formatter'], $info['settings']['settings']);
        $dependencies = NestedArray::mergeDeep($dependencies, $formatter_instance->calculateDependencies());
      }
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);
    foreach ($this->getMediaFieldInfo() as $info) {
      if (!empty($info['settings']['formatter'])) {
        $formatter_instance = $this->getFormatterInstance($info['media_bundle'], $info['media_field_name'], $info['settings']['formatter'], $info['settings']['settings']);
        $changed = $formatter_instance->onDependencyRemoval($dependencies) ? TRUE : $changed;
      }
    }
    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'exo_asset');
  }

}
