<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\exo_alchemist\Ajax\ExoComponentModifierAttributesCommand;
use Drupal\exo_alchemist\Definition\ExoComponentDefinition;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\exo_alchemist\ExoComponentPropertyManager;
use Drupal\exo_alchemist\Plugin\ExoComponentPropertyOptionsInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\exo_alchemist\Plugin\ExoComponentField\EntityField;

/**
 * Provides a form form removing a component.
 *
 * @internal
 */
class ExoComponentPreviewForm extends FormBase {
  use AjaxFormHelperTrait;
  use RedirectDestinationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The eXo component plugin manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * The eXo component plugin manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentPropertyManager
   */
  protected $exoComponentPropertyManager;

  /**
   * Preview contexts.
   *
   * @var \Drupal\Core\Plugin\Context\Context[]
   */
  protected $contexts;

  /**
   * Constructs a new ExoComponentAppearanceForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The eXo component manager.
   * @param \Drupal\exo_alchemist\ExoComponentPropertyManager $exo_component_property_manager
   *   The eXo component property manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ExoComponentManager $exo_component_manager, ExoComponentPropertyManager $exo_component_property_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->exoComponentManager = $exo_component_manager;
    $this->exoComponentPropertyManager = $exo_component_property_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.exo_component'),
      $container->get('plugin.manager.exo_component_property')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_component_preview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ExoComponentDefinition $definition = NULL) {
    /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition */
    $entity = $form_state->get('entity');
    if (empty($entity)) {
      $entity = $this->exoComponentManager->loadEntity($definition);
      $form_state->set('entity', $entity);
    }

    if ($entity) {
      $build = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'exo-component-preview',
          'class' => ['exo-component-preview'],
        ],
      ];
      $entity->exoAlchemistPreview = TRUE;

      if ($this->exoComponentManager->accessDefinition($definition, 'update')->isAllowed()) {
        \Drupal::messenger()->addWarning(t('The definition of this component has been changed. <a href="@url">Update this component</a>.', [
          '@url' => Url::fromRoute('exo_alchemist.component.update', [
            'definition' => $definition->id(),
          ], [
            'query' => $this->getDestinationArray(),
          ])->toString(),
        ]));
      }

      // Pass entity through standard layout builder render.
      /** @var \Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage $storage */
      $storage = \Drupal::service('plugin.manager.layout_builder.section_storage')->createInstance('defaults');
      $storage->setContext('layout_builder.entity', EntityContext::fromEntity($entity));
      $display = EntityViewDisplay::collectRenderDisplay($entity, 'default');
      $storage->setContext('display', EntityContext::fromEntity($display));
      $preview = new Context(new ContextDefinition('boolean'), TRUE);
      $storage->setContext('preview', $preview);
      $this->contexts = $storage->getContexts();

      $section = new Section('layout_onecol');
      $storage->insertSection(0, $section);
      $component = new SectionComponent($entity->uuid(), 'content', [
        'id' => 'inline_block:' . $entity->bundle(),
        'label_display' => FALSE,
        'block_serialized' => serialize($entity),
      ]);
      $section->appendComponent($component);
      $build['entity'] = $section->toRenderArray($storage->getContexts(), TRUE);

      $form['#prefix'] = \Drupal::service('renderer')->render($build);

      $form['#attributes']['data-exo-alchemist-refresh'] = '';
      $form['#attached']['library'][] = 'exo_alchemist/admin.preview';
      $form['#attributes']['class'][] = 'exo-form-wrap';
      $form['#attributes']['class'][] = 'exo-form-lock';
      $form['#exo_theme'] = 'black';
      // This is a very dirty way to force forms to use the intersect style.
      // All forms rendered after this request will use this style.
      exo_form_get_settings(['style' => 'intersect']);

      if ($messages = $this->messenger()->messagesByType('alchemist')) {
        $form['messages'] = [
          '#theme' => 'status_messages',
          '#message_list' => ['alchemist' => $messages],
          '#status_headings' => [
            'alchemist' => t('Alchemist message'),
          ],
        ];
        $this->messenger()->deleteByType('alchemist');
      }

      if (!empty($definition->getModifiers())) {
        $form['modifiers'] = [
          '#type' => 'exo_modal',
          '#title' => $this->t('Modify Appearance'),
          '#trigger_icon' => 'regular-pencil-paintbrush',
          '#trigger_attributes' => [
            'class' => ['exo-component-modify-button'],
          ],
          '#use_close' => FALSE,
          '#modal_attributes' => [
            'class' => ['exo-form-theme-black exo-form-lock'],
          ],
          '#modal_settings' => [
            'exo_preset' => 'aside_right',
            'modal' => [
              'title' => $this->t('Appearance'),
              'subtitle' => $this->t('Change component preview appearance.'),
              'theme' => 'black',
              'theme_content' => 'black',
              'icon' => 'regular-pencil-paintbrush',
              'padding' => 20,
              'width' => 500,
              'overlayColor' => 'transparent',
            ],
          ],
          '#tree' => TRUE,
        ];
      }

      if (\Drupal::request()->query->get('show-hidden')) {
        $form['all'] = [
          '#type' => 'link',
          '#title' => exo_icon('Hide Hidden Fields')->setIcon('regular-eye-slash'),
          '#url' => Url::fromRoute('<current>'),
          '#attributes' => ['class' => ['exo-component-modify-button']],
        ];
      }
      else {
        $form['all'] = [
          '#type' => 'link',
          '#title' => exo_icon('Show Hidden Fields')->setIcon('regular-eye'),
          '#url' => Url::fromRoute('<current>', [], [
            'query' => [
              'show-hidden' => TRUE,
            ],
          ]),
          '#attributes' => ['class' => ['exo-component-modify-button']],
        ];
      }

      $this->exoComponentPropertyManager->buildForm($form, $form_state, $definition, $entity);
      $form['modifiers']['#attributes']['data-exo-alchemist-refresh'] = '';

      $form['refresh'] = [
        '#type' => 'submit',
        '#value' => $this->t('Refresh'),
        '#id' => 'exo-alchemist-appearance-refresh',
        '#button_type' => 'primary',
        '#attributes' => [
          'class' => ['js-hide'],
        ],
        '#ajax' => [
          'callback' => '::ajaxSubmit',
        ],
      ];
    }

    $info = $this->exoComponentManager->getPropertyInfo($definition);
    if (!empty($info)) {
      $form['properties'] = $this->buildInfo($info, $this->t('Twig Properties'), '{{ ', ' }}');
      $form['properties']['#open'] = TRUE;
    }

    $info = $this->exoComponentManager->getExoComponentPropertyManager()->getAttributeInfo($definition);
    if (!empty($info)) {
      $form['attributes'] = $this->buildInfo($info, $this->t('Modifier: Attributes'), '.', '');
    }

    $info = $this->exoComponentManager->getExoComponentEnhancementManager()->getAttributeInfo($definition);
    if (!empty($info)) {
      $form['attributes'] = $this->buildInfo($info, $this->t('Enhancement: Attributes'), '.', '');
    }

    $info = $this->exoComponentManager->getExoComponentAnimationManager()->getAttributeInfo($definition);
    if (!empty($info)) {
      $form['animations'] = $this->buildInfo($info, $this->t('Animation: Options'), '', '');
    }

    $info = [];
    foreach (ExoComponentDefinition::getGlobalModifiers() as $key => $value) {
      $data = [
        'ID: ' . $key,
        'Type: ' . $value['type'],
      ];
      $info[$key] = [
        'label' => $value['label'],
        'properties' => [
          '<small>' . implode('<br>', $data) . '</small>' => !empty($value['status']) ? $this->t('Enabled') : '-',
        ],
      ];
    }
    if (!empty($info)) {
      uasort($info, [get_class($this), 'infoSortByLabel']);
      $form['globals'] = $this->buildInfo($info, $this->t('Modifier: Globals'), '', '', [
        $this->t('Property'),
        $this->t('Options'),
        $this->t('Status'),
      ]);
    }

    $info = [];
    $property_manager = $this->exoComponentManager->getExoComponentPropertyManager();
    foreach ($property_manager->getDefinitions() as $plugin_id => $property) {
      $instance = $property_manager->createInstance($plugin_id);
      $data = [];
      if ($instance instanceof ExoComponentPropertyOptionsInterface) {
        $options = $instance->getOptions();
        unset($options['_none']);
        $data[] = 'Options: ' . implode(', ', array_keys($options));
      }
      $info[$plugin_id] = [
        'label' => $property['label'],
        'properties' => [
          $plugin_id => implode('<br>', $data),
        ],
      ];
    }
    if (!empty($info)) {
      uasort($info, [get_class($this), 'infoSortByLabel']);
      $form['property_types'] = $this->buildInfo($info, $this->t('Modifier: Property Types'));
    }

    // Support entity field helpers.
    $entity_fields = $definition->getFieldsByType('field');
    if (!empty($entity_fields)) {
      $fields_wrapper_id = Html::getClass('exo-component-preview-entity-fields');
      $form['entity_fields'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Entity Field Helper'),
        '#tree' => TRUE,
        '#open' => TRUE,
        '#prefix' => '<div id="' . $fields_wrapper_id . '" class="exo-form-element exo-form-element-js">',
        '#suffix' => '</div>',
      ];
      /** @var \Drupal\Core\Field\FormatterPluginManager $plugin_manager */
      $plugin_manager = \Drupal::service('plugin.manager.field.formatter');
      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
      $field_manager = \Drupal::service('entity_field.manager');
      /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager */
      $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
      $field_map = $field_manager->getFieldMap();
      foreach ($entity_fields as $field) {
        $referenced_entity_type_id = EntityField::getEntityTypeIdFromPluginId($field->getType());
        $referenced_field_name = EntityField::getFieldNameFromPluginId($field->getType());
        if (isset($field_map[$referenced_entity_type_id][$referenced_field_name])) {
          $map = $field_map[$referenced_entity_type_id][$referenced_field_name];
          $referenced_entity_bundle = EntityField::getBundleFromPluginId($field->getType()) ?: reset($map['bundles']);
          $options = $plugin_manager->getOptions($map['type']);
          $definitions = $field_manager->getFieldDefinitions($referenced_entity_type_id, $referenced_entity_bundle);
          if (isset($definitions[$referenced_field_name])) {
            $field_definition = $definitions[$referenced_field_name];
            $display_options = $form_state->getValue([
              'entity_fields',
              'fields',
              $field->id(),
            ]);
            if (empty($display_options)) {
              $field_type_definition = $field_type_manager->getDefinition($map['type']);
              if (isset($field_type_definition['default_formatter'])) {
                $display_options['type'] = $field_type_definition['default_formatter'];
              }
              $display_options['label'] = ['hidden'];
              $display_options['settings'] = [];
              // Merge in field defaults if set.
              if ($defaults = $field->getDefaults()) {
                $default = reset($defaults);
                $display_options = $default->toArray() + $display_options;
              }
            }
            $plugin = $plugin_manager->getInstance([
              'field_definition' => $field_definition,
              'view_mode' => 'default',
              'configuration' => $display_options,
            ]);
            $applicable_options = [];
            foreach ($options as $option => $label) {
              $plugin_class = DefaultFactory::getPluginClass($option, $plugin_manager->getDefinition($option));
              if ($plugin_class::isApplicable($field_definition)) {
                $applicable_options[$option] = $label;
              }
            }
            $field_wrapper_id = Html::getClass('exo-component-preview-entity-field-', $field->id());
            $element = [
              '#type' => 'fieldset',
              '#title' => $field->getLabel(),
              '#prefix' => '<div id="' . $field_wrapper_id . '">',
              '#suffix' => '</div>',
            ];
            $element['label'] = [
              '#type' => 'select',
              '#title' => t('Label display for @title', ['@title' => $field_definition->getLabel()]),
              '#title_display' => 'invisible',
              '#options' => [
                'above' => t('Above'),
                'inline' => t('Inline'),
                'hidden' => '- ' . t('Hidden') . ' -',
                'visually_hidden' => '- ' . t('Visually Hidden') . ' -',
              ],
              '#default_value' => !empty($display_options['label']) ? $display_options['label'] : 'above',
            ];
            $element['type'] = [
              '#type' => 'select',
              '#title' => t('Plugin for @title', ['@title' => $field_definition->getLabel()]),
              '#title_display' => 'invisible',
              // '#options' => $applicable_options,
              '#options' => $options,
              '#default_value' => !empty($display_options['type']) ? $display_options['type'] : 'hidden',
              // '#parents' => ['fields', $key, 'type'],
              '#attributes' => ['class' => ['field-plugin-type']],
              '#ajax' => [
                'callback' => '::ajaxEntityFieldRefresh',
                'wrapper' => $field_wrapper_id,
              ],
            ];
            $element['settings'] = $plugin->settingsForm($form, $form_state);
            if (!empty($form_state->getTriggeringElement()['#show_yaml'])) {
              $yaml = [];
              $yaml[] = '    default:';
              $yaml[] = "      label: '" . $display_options['label'] . "'";
              $yaml[] = "      type: '" . $display_options['type'] . "'";
              if (!empty($display_options['settings'])) {
                $yaml[] = '      settings:';
                foreach ($display_options['settings'] as $key => $value) {
                  if (is_string($value)) {
                    $value = "'$value'";
                  }
                  $yaml[] = "        " . $key . ": " . $value . "";
                }
              }
              $element['yaml'] = [
                '#type' => 'html_tag',
                '#tag' => 'pre',
                // '#title' => $this->t('Component field YAML'),
                // '#autogrow' => TRUE,
                // '#rows' => count($yaml),
                '#value' => implode("\n", $yaml),
              ];
            }
            $form['entity_fields']['fields'][$field->id()] = $element;
          }
        }
      }
      $form['entity_fields']['actions'] = [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => t('Build YAML'),
          '#name' => 'entity_fields_submit',
          '#show_yaml' => TRUE,
          '#ajax' => [
            'callback' => '::ajaxEntityFieldBuildYaml',
            'wrapper' => $fields_wrapper_id,
          ],
          '#attributes' => [
            'class' => ['exo-component-modify-button'],
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * Submit form dialog #ajax callback.
   */
  public function ajaxEntityFieldRefresh(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    // Go one level up in the form, to the widgets container.
    return NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -1));
  }

  /**
   * Submit form dialog #ajax callback.
   */
  public function ajaxEntityFieldBuildYaml(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    // Go one level up in the form, to the widgets container.
    return NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -2));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $form_state->get('entity');
    $entity->get(ExoComponentPropertyManager::MODIFIERS_FIELD_NAME)->setValue(['value' => $form_state->getValue('modifiers')]);
    $form_state->set('entity', $entity);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $entity = $form_state->get('entity');
    $definition = $this->exoComponentManager->getEntityBundleComponentDefinition($entity->type->entity);
    $attributes = $this->exoComponentPropertyManager->getModifierAttributes($definition, $entity, $this->contexts, FALSE);
    $response = new AjaxResponse();
    $response->addCommand(new ExoComponentModifierAttributesCommand($attributes));
    return $response;
  }

  /**
   * Given info, build display.
   */
  protected function buildInfo(array $info, $label, $prefix = '', $suffix = '', $header = []) {
    $build = [
      '#type' => 'details',
      '#title' => $label,
      '#open' => FALSE,
    ];
    $build['table'] = [
      '#theme' => 'table',
      '#header' => $header + [
        0 => $this->t('Element'),
        1 => $this->t('Property'),
        2 => $this->t('Description'),
      ],
    ];
    $rows = [];
    foreach ($info as $key => $data) {
      if (empty($data['properties'])) {
        continue;
      }
      $count = 0;
      foreach ($data['properties'] as $property => $label) {
        $row = [];
        if ($count == 0) {
          $row[] = ['data' => ['#markup' => '<small><strong>' . ($data['label'] ?? 'Unnamed') . '</strong></small>']];
        }
        else {
          $row[] = '';
        }
        $row[] = ['data' => ['#markup' => '<small>' . $prefix . $property . $suffix . '</small>']];
        $row[] = ['data' => ['#markup' => '<small>' . $label . '</small>']];
        $rows[] = $row;
        $count++;
      }
    }
    $build['table']['#rows'] = $rows;
    return $build;
  }

  /**
   * Sorts a structured array by either a set 'label' property.
   *
   * @param array $a
   *   First item for comparison.
   * @param array $b
   *   Second item for comparison.
   *
   * @return int
   *   The comparison result for uasort().
   */
  public static function infoSortByLabel(array $a, array $b) {
    return SortArray::sortByKeyString($a, $b, 'label');
  }

}
