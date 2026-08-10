<?php

namespace Drupal\exo_alchemist\Plugin\Field\FieldWidget;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormHelper;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Plugin implementation of the 'entity_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "entity_field_widget",
 *   label = @Translation("Entity Field Widget"),
 *   field_types = {
 *     "exo_alchemist_map"
 *   }
 * )
 */
class EntityFieldWidget extends WidgetBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The formatter manager.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $formatterManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $referencedEntityTypeId;

  /**
   * The referencedBundle ID.
   *
   * @var string
   */
  protected $referencedBundle;

  /**
   * The field name.
   *
   * @var string
   */
  protected $referencedFieldName;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FormatterPluginManager $formatter_manager
   *   The formatter manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityFieldManagerInterface $entity_field_manager, FormatterPluginManager $formatter_manager, ModuleHandlerInterface $module_handler, LoggerInterface $logger) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityFieldManager = $entity_field_manager;
    $this->formatterManager = $formatter_manager;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger;
    $this->referencedEntityTypeId = $this->getSetting('entity_type_id');
    $this->referencedBundle = $this->getSetting('bundle');
    $this->referencedFieldName = $this->getSetting('field_name');
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
      $configuration['third_party_settings'],
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.formatter'),
      $container->get('module_handler'),
      $container->get('logger.channel.exo_alchemist')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'entity_type_id' => '',
      'bundle' => '',
      'field_name' => '',
      'default_formatter' => '',
    ] + parent::defaultSettings();
  }

  /**
   * Set the bundle.
   *
   * @param string $bundle
   *   The bundle.
   */
  public function setBundle($bundle) {
    $this->referencedBundle = $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = !$items->isEmpty() ? $items->get($delta)->value : [];
    $this->setDefaultFormatterConfig($value);
    $config = $this->getSetting('formatter');

    $element['value'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Formatter'),
      '#process' => [
        [$this, 'formatterSettingsProcessCallback'],
      ],
    ] + $element;

    $element['value']['label'] = [
      '#type' => 'select',
      '#title' => $this->t('Label'),
      // @todo This is directly copied from
      //   \Drupal\field_ui\Form\EntityViewDisplayEditForm::getFieldLabelOptions(),
      //   resolve this in https://www.drupal.org/project/drupal/issues/2933924.
      '#options' => [
        'above' => $this->t('Above'),
        'inline' => $this->t('Inline'),
        'hidden' => '- ' . $this->t('Hidden') . ' -',
        'visually_hidden' => '- ' . $this->t('Visually Hidden') . ' -',
      ],
      '#default_value' => $config['label'],
    ];

    $element['value']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Formatter'),
      '#options' => $this->getApplicablePluginOptions($this->getReferencedFieldDefinition()),
      '#required' => TRUE,
      '#default_value' => $config['type'],
      '#ajax' => [
        'callback' => [static::class, 'formatterSettingsAjaxCallback'],
        'wrapper' => 'formatter-settings-wrapper',
      ],
    ];

    // Add the formatter settings to the form via AJAX.
    $element['value']['settings_wrapper'] = [
      '#prefix' => '<div id="formatter-settings-wrapper">',
      '#suffix' => '</div>',
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function flagErrors(FieldItemListInterface $items, ConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    // @todo We skip flagging for errors as there is a bug with map fields that
    // prevents this from working correctly.
    // @see https://www.drupal.org/project/drupal/issues/2563843
  }

  /**
   * Render API callback: builds the formatter settings elements.
   */
  public function formatterSettingsProcessCallback(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if ($formatter = $this->getFormatter($element['#parents'], $form_state)) {
      $element['settings_wrapper']['settings'] = $formatter->settingsForm($complete_form, $form_state);
      $element['settings_wrapper']['settings']['#parents'] = array_merge($element['#parents'], ['settings']);
      $element['settings_wrapper']['third_party_settings'] = $this->thirdPartySettingsForm($formatter, $this->getReferencedFieldDefinition(), $complete_form, $form_state);
      $element['settings_wrapper']['third_party_settings']['#parents'] = array_merge($element['#parents'], ['third_party_settings']);
      FormHelper::rewriteStatesSelector($element['settings_wrapper'], "fields[$this->referencedFieldName][settings_edit_form]", 'settings[formatter]');

      // Store the array parents for our element so that we can retrieve the
      // formatter settings in our AJAX callback.
      $form_state->set('field_block_array_parents', $element['#array_parents']);
    }
    return $element;
  }

  /**
   * Adds the formatter third party settings forms.
   *
   * @param \Drupal\Core\Field\FormatterInterface $plugin
   *   The formatter.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $form
   *   The (entire) configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The formatter third party settings form.
   */
  protected function thirdPartySettingsForm(FormatterInterface $plugin, FieldDefinitionInterface $field_definition, array $form, FormStateInterface $form_state) {
    $settings_form = [];
    // Invoke hook_field_formatter_third_party_settings_form(), keying resulting
    // subforms by module name.
    //
    // getImplementations() was removed in D11; this mirrors the invokeAllWith()
    // shape core's own EntityViewDisplayEditForm moved to, including its null
    // coalesce — an implementation returning nothing must not blank the key.
    $this->moduleHandler->invokeAllWith(
      'field_formatter_third_party_settings_form',
      function (callable $hook, string $module) use (&$settings_form, &$plugin, &$field_definition, &$form, &$form_state) {
        $settings_form[$module] = ($settings_form[$module] ?? []) + ($hook(
          $plugin,
          $field_definition,
          EntityDisplayBase::CUSTOM_MODE,
          $form,
          $form_state,
        ) ?? []);
      }
    );
    return $settings_form;
  }

  /**
   * Render API callback: gets the layout settings elements.
   */
  public static function formatterSettingsAjaxCallback(array $form, FormStateInterface $form_state) {
    $formatter_array_parents = $form_state->get('field_block_array_parents');
    return NestedArray::getValue($form, array_merge($formatter_array_parents, ['settings_wrapper']));
  }

  /**
   * Gets the field definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition.
   */
  protected function getReferencedFieldDefinition() {
    if (empty($this->referencedFieldDefinition)) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($this->referencedEntityTypeId, $this->referencedBundle);
      $this->referencedFieldDefinition = $field_definitions[$this->referencedFieldName];
    }
    return $this->referencedFieldDefinition;
  }

  /**
   * Returns an array of applicable formatter options for a field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return array
   *   An array of applicable formatter options.
   *
   * @see \Drupal\field_ui\Form\EntityDisplayFormBase::getApplicablePluginOptions()
   */
  protected function getApplicablePluginOptions(FieldDefinitionInterface $field_definition) {
    $options = $this->formatterManager->getOptions($field_definition->getType());
    $applicable_options = [];
    foreach ($options as $option => $label) {
      $plugin_class = DefaultFactory::getPluginClass($option, $this->formatterManager->getDefinition($option));
      if ($plugin_class::isApplicable($field_definition)) {
        $applicable_options[$option] = $label;
      }
    }
    return $applicable_options;
  }

  /**
   * Gets the formatter object.
   *
   * @param array $parents
   *   The #parents of the element representing the formatter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Field\FormatterInterface
   *   The formatter object.
   */
  protected function getFormatter(array $parents, FormStateInterface $form_state) {
    // Use the processed values, if available.
    $configuration = NestedArray::getValue($form_state->getValues(), $parents);
    if (!$configuration) {
      // Next check the raw user input.
      $configuration = NestedArray::getValue($form_state->getUserInput(), $parents);
      if (!$configuration) {
        // If no user input exists, use the default values.
        $configuration = $this->getSetting('formatter');
      }
    }

    return $this->formatterManager->getInstance([
      'configuration' => $configuration,
      'field_definition' => $this->getReferencedFieldDefinition(),
      'view_mode' => EntityDisplayBase::CUSTOM_MODE,
      'prepare' => TRUE,
    ]);
  }

  /**
   * Set the default formatter configuration.
   *
   * @return $this
   */
  protected function setDefaultFormatterConfig($config = []) {
    $this->setSetting('formatter', $config + [
      'label' => 'hidden',
      'type' => $this->getSetting('default_formatter'),
      'settings' => [],
      'third_party_settings' => [],
    ]);
    return $this;
  }

}
