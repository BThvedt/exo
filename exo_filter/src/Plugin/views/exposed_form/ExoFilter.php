<?php

namespace Drupal\exo_filter\Plugin\views\exposed_form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\exo_filter\Plugin\ExoFilterManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\Html;
use Drupal\exo_modal\ExoModalGeneratorInterface;
use Drupal\exo\ExoSettingsInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\Core\Form\SubformState;
use Drupal\views\Plugin\views\exposed_form\InputRequired;

/**
 * Exposed form plugin that provides a basic exposed form.
 *
 * @ingroup views_exposed_form_plugins
 *
 * @ViewsExposedForm(
 *   id = "exo_filter",
 *   title = @Translation("eXo | Filters"),
 *   help = @Translation("Provides additional options for exposed form elements.")
 * )
 */
class ExoFilter extends InputRequired {

  /**
   * The eXo filter plugin manager.
   *
   * @var \Drupal\exo_filter\Plugin\ExoFilterManager
   */
  protected $exoFilterManager;

  /**
   * The eXo sort plugin manager.
   *
   * @var \Drupal\exo_filter\Plugin\ExoFilterManager
   */
  protected $exoSortManager;

  /**
   * The eXo Modal options service.
   *
   * @var \Drupal\exo\ExoSettingsPluginInstanceInterface
   */
  protected $exoModalSettings;

  /**
   * The eXo modal generator.
   *
   * @var \Drupal\exo_modal\ExoModalGeneratorInterface
   */
  protected $exoModalGenerator;

  /**
   * Constructs a PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\exo_filter\Plugin\ExoFilterManager $exo_filter_manager
   *   The eXo filter manager.
   * @param \Drupal\exo\ExoSettingsInterface $exo_modal_settings
   *   The eXo options service.
   * @param \Drupal\exo_modal\ExoModalGeneratorInterface $exo_modal_generator
   *   The eXo modal generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExoFilterManager $exo_filter_manager, ExoFilterManager $exo_sort_manager, ExoSettingsInterface $exo_modal_settings, ExoModalGeneratorInterface $exo_modal_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->definition = $plugin_definition + $configuration;
    $this->exoFilterManager = $exo_filter_manager;
    $this->exoSortManager = $exo_sort_manager;
    $this->exoModalSettings = $exo_modal_settings;
    $this->exoModalGenerator = $exo_modal_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->exoModalSettings = $this->exoModalSettings->createInstance($this->options['modal']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.exo_filter'),
      $container->get('plugin.manager.exo_sort'),
      $container->get('exo_modal.settings'),
      $container->get('exo_modal.generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['input_required'] = ['default' => FALSE];
    $options['general'] = [
      'default' => [
        'actions_first' => FALSE,
        'autosubmit' => FALSE,
        'autosubmit_hide' => FALSE,
        'allow_secondary' => FALSE,
        'secondary_element' => 'details',
        'secondary_label' => $this->t('Advanced options'),
        'use_modal' => FALSE,
        'modal_clone' => FALSE,
      ],
    ];
    $options['modal'] = [
      'default' => [
        'exo_default' => TRUE,
      ],
    ];
    foreach ($this->view->display_handler->getHandlers('filter') as $id => $filter) {
      $options[$id] = [
        'default' => [
          'format' => '',
          'more' => [
            'is_secondary' => 0,
          ],
        ],
      ];
    }
    $options['sort']['default']['plugin_id'] = '';

    // Initialize options if any sort is exposed.
    // Iterate over each sort and determine if any sorts are exposed.
    $is_sort_exposed = FALSE;
    /** @var \Drupal\views\Plugin\views\HandlerBase $sort */
    foreach ($this->view->display_handler->getHandlers('sort') as $sort) {
      if ($sort->isExposed()) {
        $is_sort_exposed = TRUE;
        break;
      }
    }
    if ($is_sort_exposed) {
      $options['sort']['default']['plugin_id'] = 'default';
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // User raw user input for AJAX callbacks.
    $user_input = $form_state->getUserInput();
    $bef_input = $user_input['exposed_form_options']['general'] ?? NULL;

    $form['text_input_required']['#weight'] = 2;
    $form['text_input_required']['#states'] = [
      'visible' => [
        ':input[name="exposed_form_options[input_required]"]' => ['checked' => TRUE],
      ],
    ];
    $form['input_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require Input'),
      '#description' => $this->t('Do not show any results until a filter has been applied.'),
      '#default_value' => $this->options['input_required'],
      '#weight' => 1,
    ];

    $form['general']['#weight'] = 10;
    $form['general']['actions_first'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Actions First'),
      '#description' => $this->t('Will show all form actions before the form filters.'),
      '#default_value' => $this->options['general']['actions_first'],
    ];

    $form['general']['autosubmit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autosubmit'),
      '#description' => $this->t('Automatically submit the form once an element is changed.'),
      '#default_value' => $this->options['general']['autosubmit'],
    ];

    $form['general']['autosubmit_hide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide submit button'),
      '#description' => $this->t('Hide submit button if javascript is enabled.'),
      '#default_value' => $this->options['general']['autosubmit_hide'],
      '#states' => [
        'visible' => [
          ':input[name="exposed_form_options[general][autosubmit]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['general']['allow_secondary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable secondary exposed form options'),
      '#default_value' => $this->options['general']['allow_secondary'],
      '#description' => $this->t('Allows you to specify some exposed form elements as being secondary options and places those elements in a wrapper element. Use this option to place some exposed filters in an "Advanced Search" area of the form, for example.'),
    ];
    $form['general']['secondary_element'] = [
      '#type' => 'select',
      '#title' => $this->t('Secondary element'),
      '#default_value' => $this->options['general']['secondary_element'],
      '#options' => [
        'details' => $this->t('Details'),
        'fieldset' => $this->t('Fieldset'),
        'container' => $this->t('Container'),
      ],
      '#states' => [
        'required' => [
          ':input[name="exposed_form_options[general][allow_secondary]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="exposed_form_options[general][allow_secondary]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['general']['secondary_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secondary options label'),
      '#default_value' => $this->options['general']['secondary_label'],
      '#description' => $this->t(
        'The name of the wrapper element to hold secondary options. This cannot be left blank or there will be no way to show/hide these options.'
      ),
      '#states' => [
        'required' => [
          ':input[name="exposed_form_options[general][allow_secondary]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="exposed_form_options[general][allow_secondary]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $states = [
      'visible' => [
        ':input[name="exposed_form_options[general][use_modal]"]' => ['checked' => TRUE],
      ],
    ];
    $form['general']['use_modal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Modal'),
      '#default_value' => $this->options['general']['use_modal'],
      '#description' => $this->t('Allows you to specify some exposed form elements as being secondary options and places those elements in a collapsible "details" element. Use this option to place some exposed filters in an "Advanced Search" area of the form, for example.'),
    ];

    $form['general']['modal_clone'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clone Modal'),
      '#default_value' => $this->options['general']['modal_clone'],
      '#description' => $this->t('Allows the filter to be shown outside of a modal as well as within a modal. This is useful when you want a filter to be displayed normally for desktop but wrapped in a modal for mobile.'),
      '#states' => $states,
    ];

    $form['modal'] = [];
    $form['modal'] = $this->exoModalSettings->buildForm($form['modal'], $form_state) + [
      '#type' => 'details',
      '#title' => $this->t('Modal'),
    ] + [
      '#states' => $states,
      '#weight' => 15,
    ];

    /*
     * Add options for exposed sorts.
     */

    // Iterate over each sort and determine if any sorts are exposed.
    $is_sort_exposed = FALSE;
    /** @var \Drupal\views\Plugin\views\HandlerBase $sort */
    foreach ($this->view->display_handler->getHandlers('sort') as $sort) {
      if ($sort->isExposed()) {
        $is_sort_exposed = TRUE;
        break;
      }
    }

    if ($is_sort_exposed) {
      $options = [];
      foreach ($this->exoSortManager->getDefinitions() as $plugin_id => $definition) {
        if ($definition['class']::isApplicable()) {
          $options[$plugin_id] = $definition['label'];
        }
      }

      $form['sort'] = [
        '#prefix' => "<div id='bef-sort-configuration'>",
        '#suffix' => "</div>",
        '#type' => 'container',
      ];

      // Get selected plugin_id on AJAX callback directly from the form state.
      $selected_plugin_id = $bef_input['sort']['configuration']['plugin_id'] ??
        $this->options['sort']['plugin_id'];

      $form['sort']['plugin_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Display exposed sort options as'),
        '#default_value' => $selected_plugin_id,
        '#options' => $options,
        '#description' => $this->t('Select a format for the exposed sort options.'),
        '#ajax' => [
          'event' => 'change',
          'effect' => 'fade',
          'progress' => 'throbber',
          // Since views options forms are complex, they're built by
          // Drupal in a different way. To bypass this problem we need to
          // provide the full path to the Ajax callback.
          'callback' => __CLASS__ . '::ajaxCallback',
          'wrapper' => 'bef-sort-configuration',
        ],
      ];

      // // Move some existing form elements.
      // $form['sort']['exposed_sorts_label'] = $original_form['exposed_sorts_label'];
      // $form['sort']['expose_sort_order'] = $original_form['expose_sort_order'];
      // $form['sort']['sort_asc_label'] = $original_form['sort_asc_label'];
      // $form['sort']['sort_desc_label'] = $original_form['sort_desc_label'];
      if ($selected_plugin_id) {
        $plugin_configuration = $this->options['sort'] ?? [];
        /** @var \Drupal\exo_filter\Plugin\ExoFilterBase $plugin */
        $plugin = $this->exoSortManager->createInstance($selected_plugin_id, $plugin_configuration);
        $plugin->setView($this->view);

        $subform = &$form['sort'];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $subform += $plugin->buildConfigurationForm($subform, $subform_state);
      }
    }
    $form['sort']['empty'] = [
      '#type' => 'item',
      '#description' => $this->t('No sort elements have been exposed yet.'),
      '#access' => !$is_sort_exposed,
    ];

    // Go through each filter and add eXo options.
    foreach ($this->view->display_handler->getHandlers('filter') as $id => $filter) {
      if (!$filter->options['exposed']) {
        continue;
      }
      $type = $filter->getPluginId();
      $title = $filter->options['expose']['identifier'];
      $html_id = Html::getUniqueId('filter-' . $id);
      $identifier = '"' . $title . '"';
      $settings = $this->options[$id];

      $form[$id] = [
        '#type' => 'fieldset',
        '#title' => $title . ' (' . $type . ')',
        '#weight' => 20,
      ];

      $filter_options = $this->exoFilterManager->getOptions($type);
      $options = ['' => '- Default -'] + $filter_options;
      $form[$id]['format'] = [
        '#type' => 'select',
        '#id' => $html_id . '-format',
        '#title' => $this->t('Display @identifier exposed filter as', ['@identifier' => $identifier]),
        '#default_value' => $settings['format'],
        '#options' => $options,
      ];
      $form[$id]['settings'] = [
        '#type' => 'container',
      ];
      foreach ($filter_options as $format => $label) {
        $format_settings = $settings['settings'][$format] ?? [];
        $settings_element = [];
        $plugin = $this->exoFilterManager->createInstance($format, $format_settings);
        $plugin->setView($this->view);
        $plugin->exposedElementSettingsForm($settings_element);
        if (!empty($settings_element)) {
          $form[$id]['settings'][$format] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Settings'),
            '#states' => [
              'visible' => [
                '#' . $html_id . '-format' => [
                  'value' => $format,
                ],
              ],
            ],
          ] + $settings_element;
        }
      }
      // Details element to keep the UI from getting out of hand.
      $form[$id]['more'] = [
        '#type' => 'details',
        '#title' => $this->t('More options for @identifier', ['@identifier' => $identifier]),
      ];

      // Allow any filter to be moved into the secondary options element.
      $form[$id]['more']['is_secondary'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('This is a secondary option'),
        '#default_value' => $settings['more']['is_secondary'],
        '#states' => [
          'visible' => [
            ':input[name="exposed_form_options[general][allow_secondary]"]' => ['checked' => TRUE],
          ],
        ],
        '#description' => $this->t('Places this element in the secondary options portion of the exposed form.'),
      ];
    }

  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $values = $form_state->getValue(['exposed_form_options']);
    foreach ($this->view->display_handler->getHandlers('filter') as $id => $filter) {
      if (!empty($values[$id]['format'])) {
        $values[$id]['settings'] = [
          $values[$id]['format'] => $values[$id]['settings'][$values[$id]['format']],
        ];
      }
      else {
        unset($values[$id]['settings']);
      }
    }
    if (!empty($values['sort']['plugin_id'])) {
      $plugin_id = $values['sort']['plugin_id'];
      $plugin = $this->exoSortManager->createInstance($plugin_id);
      $subform = &$form['sort'];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $plugin->setView($this->view);
      $plugin->validateConfigurationForm($subform, $subform_state);
    }
    $form_state->setValue(['exposed_form_options'], $values);
    $subform_state = SubformState::createForSubform($form['modal'], $form_state->getCompleteForm(), $form_state);
    $this->exoModalSettings->validateForm($form['modal'], $subform_state);
    parent::validateOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    if (!empty($values['sort']['plugin_id'])) {
      $plugin_id = $values['sort']['plugin_id'];
      $plugin = $this->exoSortManager->createInstance($plugin_id);
      $subform = &$form['sort'];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $plugin->setView($this->view);
      $plugin->submitConfigurationForm($subform, $subform_state);
    }
    $subform_state = SubformState::createForSubform($form['modal'], $form_state->getCompleteForm(), $form_state);
    $this->exoModalSettings->submitForm($form['modal'], $subform_state);
    parent::submitOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function exposedFilterApplied() {
    if ($this->options['input_required']) {
      return parent::exposedFilterApplied();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(&$form, FormStateInterface $form_state) {
    parent::exposedFormAlter($form, $form_state);
    $settings = $this->options;
    $allow_secondary = $settings['general']['allow_secondary'];

    if ($this->view->ajaxEnabled()) {
      // Disable the cache for ajax requests.
      $form['#cache']['max-age'] = 0;
    }

    // Some elements may be placed in a secondary details element (eg: "Advanced
    // search options"). Place this after the exposed filters and before the
    // rest of the items in the exposed form.
    if ($allow_secondary) {
      $secondary = [
        '#type' => $this->options['general']['secondary_element'],
        '#title' => $this->options['general']['secondary_label'],
        '#attributes' => [
          'class' => [
            'exo-form-inline',
            'exo-filter-secondary',
          ],
        ],
        '#weight' => 1000,
      ];
      $form['actions']['#weight'] = 1001;
    }

    // Apply autosubmit values.
    if (!empty($settings['general']['autosubmit'])) {
      $form['#attributes']['data-exo-auto-submit-full-form'] = '';
      $form['actions']['submit']['#attributes']['data-exo-auto-submit-click'] = '';
      $form['#attached']['library'][] = 'exo/auto_submit';

      if (!empty($settings['general']['autosubmit_hide'])) {
        $form['actions']['submit']['#attributes']['class'][] = 'js-hide';
      }
    }

    if (!empty($this->options['general']['actions_first'])) {
      $form['actions']['#weight'] = -1000;
    }

    // Go through each filter and alter if necessary.
    foreach ($this->view->display_handler->getHandlers('filter') as $id => $filter) {
      if (!isset($form['#info']["filter-$id"]['value'])) {
        continue;
      }
      $identifier = $form['#info']["filter-$id"]['value'];
      $format = $this->options[$id]['format'];
      $context = [
        'filter_id' => $id,
        'expose' => $filter->options['expose'],
        'id' => $identifier,
        'plugin' => $this,
      ];
      $label = $context['expose']['placeholder'] ?? $context['expose']['label'];
      if ($label) {
        $form[$identifier]['#attributes']['aria-label'] = $label;
      }
      if ($format) {
        $format_settings = $this->options[$id]['settings'][$format] ?? [];
        $plugin = $this->exoFilterManager->createInstance($format, $format_settings);
        $plugin->setView($this->view);
        $plugin->exposedElementAlter($form[$identifier], $form_state, $context);
      }

      if ($allow_secondary && $this->options[$id]['more']['is_secondary']) {
        if (!empty($form[$identifier])) {
          // Move exposed operators with exposed filters.
          if (!empty($this->display->display_options['filters'][$identifier]['expose']['use_operator'])) {
            $op_id = $this->display->display_options['filters'][$identifier]['expose']['operator_id'];
            $secondary[$op_id] = $form[$op_id];
            unset($form[$op_id]);
          }
          $secondary[$identifier] = $form[$identifier];
          unset($form[$identifier]);
          $secondary[$identifier]['#title'] = $form['#info']["filter-$id"]['label'];
          unset($form['#info']["filter-$id"]);
        }
      }
    }

    // Check for secondary elements.
    if ($allow_secondary && !empty($secondary)) {
      // Add secondary elements after regular exposed filter elements.
      $remaining = array_splice($form, count($form['#info']) + 1);
      $form['secondary'] = $secondary;
      $form = array_merge($form, $remaining);
      $form['#info']['filter-secondary']['value'] = 'secondary';
    }

    if (empty($this->view->live_preview)) {
      $form['#after_build'][] = [$this, 'afterBuild'];
    }

    /*
     * Handle exposed sort elements.
     */
    if (isset($this->options['sort']['plugin_id']) && !empty($form['sort_by'])) {
      $plugin_id = $this->options['sort']['plugin_id'];
      $plugin_configuration = $this->options['sort'];

      $plugin = $this->exoSortManager->createInstance($plugin_id, $plugin_configuration);
      $plugin->setView($this->view);
      $plugin->exposedFormAlter($form, $form_state);
    }
  }

  /**
   * Act on form after build.
   */
  public function afterBuild(array $form) {
    $use_modal = $this->options['general']['use_modal'];
    if ($use_modal) {
      $modal_clone = $this->options['general']['modal_clone'];
      $modal_content_id = Html::getUniqueId('exo-filter-filters-' . $this->view->id() . '-' . $this->view->current_display);
      $modal_content = [
        '#type' => 'container',
        '#id' => $modal_content_id,
        '#attributes' => [
          'id' => $modal_content_id,
          'class' => ['exo-filter-filters'],
        ],
        '#weight' => 0,
      ];
      // Move all children into the modal container.
      foreach (Element::children($form) as $id) {
        $modal_content[$id] = $form[$id];
        unset($form[$id]);
      }
      // Allow each submit button to close the modal.
      foreach (Element::children($modal_content['actions']) as $id) {
        $element = &$modal_content['actions'][$id];
        if (isset($element['#type']) && $element['#type'] == 'submit' && $id != 'reset') {
          $element['#attributes']['data-exo-modal-close'] = '';
          $element['#attributes']['data-exo-modal-action-delay'] = 'closed';
        }
      }
      $modal_content['actions']['#attributes']['class'][] = 'exo-modal-actions';
      // Go through each filter and adjust as needed.
      foreach ($this->view->display_handler->getHandlers('filter') as $id => $filter) {
        if (!isset($form['#info']["filter-$id"]['value'])) {
          continue;
        }
        $identifier = $form['#info']["filter-$id"]['value'];
        $modal_content[$identifier]['#title'] = $form['#info']["filter-$id"]['label'];
      }

      $modal_options = $this->options['modal'] + ['modal' => []];
      $modal_options['modal'] += [
        'appendTo' => 'form',
        'appendToOverlay' => 'form',
        'appendToNavigate' => 'form',
        'appendToClosest' => TRUE,
        'class' => $this->view->ajaxEnabled() ? 'exo-auto-submit-disable' : '',
      ];
      $modal = \Drupal::service('exo_modal.generator')->generate($modal_content_id . '-modal', $modal_options)->addTriggerClass('button');
      if ($modal_clone) {
        $modal->setSetting(['modal', 'contentSelector'], '#' . $modal_content_id);
        $form['modal_content'] = $modal_content;
      }
      else {
        $modal->setContent($modal_content);
      }
      $form['modal'] = $modal->toRenderable();
    }
    return $form;
  }

}
