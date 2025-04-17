<?php

namespace Drupal\exo_modal\Plugin;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\exo\ExoSettingsInterface;
use Drupal\Core\Form\SubformState;
use Drupal\exo_modal\ExoModalGeneratorInterface;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\exo_modal\ExoModalInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * Provides a base for eXo modal field formatters.
 */
abstract class ExoModalFieldFormatterBase extends FormatterBase implements ExoModalFieldFormatterPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

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
   * Constructs a new instance of the plugin.
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
   *   Third party settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\exo\ExoSettingsInterface $exo_modal_settings
   *   The eXo options service.
   * @param \Drupal\exo_modal\ExoModalGeneratorInterface $exo_modal_generator
   *   The eXo modal generator.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, ExoSettingsInterface $exo_modal_settings, ExoModalGeneratorInterface $exo_modal_generator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->loggerFactory = $logger_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->exoModalSettings = $exo_modal_settings->createInstance($this->getSetting('modal'));
    $this->exoModalGenerator = $exo_modal_generator;
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
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('exo_modal.settings'),
      $container->get('exo_modal.generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + [
      'ajax' => FALSE,
      'modal' => [
        'exo_default' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['ajax'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load in dynamically'),
      '#default_value' => $this->getSetting('ajax'),
    ];
    $form['modal'] = [];
    $form['modal'] = $this->exoModalSettings->buildForm($form['modal'], $form_state) + [
      '#type' => 'fieldset',
      '#title' => $this->t('Modal'),
    ];
    $form['#element_validate'] = [[get_class($this), 'settingsFormValidate']];
    return $form;
  }

  /**
   * Validate eXo modal.
   */
  public static function settingsFormValidate($form, FormStateInterface $form_state) {
    // Perform plugin validation AND submittion to make sure settings are fully
    // handled.
    $subform_state = SubformState::createForSubform($form['modal'], $form_state->getCompleteForm(), $form_state);
    $values = $subform_state->getValues();
    $exo_modal_settings = \Drupal::service('exo_modal.settings')->createInstance($values);
    $exo_modal_settings->validateForm($form['modal'], $subform_state);
    $exo_modal_settings->submitForm($form['modal'], $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $modal = $this->getSetting('modal');
    $summary[] = $this->t('eXo Modal settings: @type', [
      '@type' => empty($modal) || !empty($modal['exo_default']) ? $this->t('Default') : $this->t('Custom'),
    ]);
    if ($this->getSetting('ajax')) {
      $summary[] = $this->t('Load in dynamically');
    }
    return $summary;
  }

  /**
   * Get unique modal id.
   */
  protected function getUniqueId(FieldItemInterface $item, $delta) {
    $entity = $item->getEntity();
    $field_definition = $item->getFieldDefinition();
    return Html::getUniqueId(md5(implode('_', [
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $entity->id(),
      $this->viewMode,
      $field_definition->getName(),
      $delta,
    ])));
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $use_ajax = $this->getSetting('ajax');

    foreach ($items as $delta => $item) {
      if ($item instanceof FieldItemInterface) {
        /** @var \Drupal\Core\Field\FieldItemInterface $item */
        $modal = $this->generateModal($item, $delta);
        if ($use_ajax) {
          $entity = $items->getEntity();
          $field_definition = $items->getFieldDefinition();
          $view = $this->viewMode;
          $route_parameters = [
            'entity_type' => $entity->getEntityTypeId(),
            'entity' => $entity->id(),
            'field_name' => $field_definition->getName(),
            'delta' => $delta,
            'display_id' => $view,
            'langcode' => $langcode,
            'revision_id' => 'na',
          ];
          if ($entity instanceof RevisionableInterface && $entity->getRevisionId()) {
            $route_parameters['revision_id'] = $entity->getRevisionId();
          }
          if ($view == '_custom') {
            $settings = $this->getSettings();
            $route_parameters['display_settings'] = urlencode(json_encode([
              'type' => $this->getPluginId(),
              'label' => 'hidden',
              'settings' => $settings,
            ]));
          }
          $url = Url::fromRoute('exo_modal.api.field.view', $route_parameters)->getInternalPath();
          $modal->setSetting(['modal', 'ajax'], $url);
          $element[$delta] = $modal->toRenderableTrigger();
        }
        else {
          $content = $this->viewModalElement($modal, $item, $delta, $langcode);
          $modal->setContent($content);
          $element[$delta] = $modal->toRenderable();
        }
      }
    }
    return $element;
  }

  /**
   * Generate the modal.
   *
   * @return \Drupal\exo_modal\ExoModalInterface
   *   The modal.
   */
  protected function generateModal(FieldItemInterface $item, $delta, $settings = []) {
    return $this->exoModalGenerator->generate(
      $this->getUniqueId($item, $delta),
      !empty($settings) ? $settings : $this->getSetting('modal')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Creates the modal content. Individual modal block plugins can add elements
   * to this form by overriding ExoModalFieldFormatter::viewModalElements().
   * Most block plugins should not override this method unless they need to
   * alter the generic modal properties.
   *
   * @see \Drupal\exo_modal\Plugin\ExoModalFieldFormatter::viewModalElements()
   */
  public function buildModal(FieldItemInterface $item, $delta, $langcode) {
    $settings = $this->getSetting('modal');
    $settings['modal']['autoOpen'] = TRUE;
    $settings['modal']['destroyOnClose'] = TRUE;
    $modal = $this->generateModal($item, $delta, $settings);
    $content = $this->viewModalElement($modal, $item, $delta, $langcode);
    $modal->setContent($content);
    return $modal->toRenderableModal();
  }

  /**
   * Build modal content.
   *
   * Extending classes should implement this method to change the content that
   * is placed inside the modal.
   *
   * @param \Drupal\exo_modal\ExoModalInterface $modal
   *   The field value to be rendered.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field value to be rendered.
   * @param string $delta
   *   The delta of the rendered field.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array for $items, as an array of child elements keyed by
   *   consecutive numeric indexes starting from 0.
   */
  protected function viewModalElement(ExoModalInterface $modal, FieldItemInterface $item, $delta, $langcode) {
    return ['#markup' => 'eXo Modal'];
  }

}
