<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\Plugin\ExoListFilterBase;
use Drupal\state_machine\WorkflowGroupManagerInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a eXo list element for rendering a content entity field.
 *
 * @ExoListFilter(
 *   id = "state_machine",
 *   label = @Translation("State"),
 *   description = @Translation("The state machine label."),
 *   weight = 0,
 *   field_type = {
 *     "state",
 *   },
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {},
 *   exclusive = false,
 *   provider = "state_machine",
 * )
 */
class StateMachine extends ExoListFilterBase implements ContainerFactoryPluginInterface {

  /**
   * The workflow group manager service.
   *
   * @var \Drupal\state_machine\WorkflowGroupManagerInterface
   */
  protected $workflowGroupManager;

  /**
   * The workflow manager service.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;

  /**
   * LogGeneratorBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\state_machine\WorkflowGroupManagerInterface $workflow_group_manager
   *   The workflow group information service.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow information service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, WorkflowGroupManagerInterface $workflow_group_manager, WorkflowManagerInterface $workflow_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->workflowGroupManager = $workflow_group_manager;
    $this->workflowManager = $workflow_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.workflow_group'),
      $container->get('plugin.manager.workflow')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'workflow_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, EntityListInterface $entity_list, array $field) {
    $form = parent::buildConfigurationForm($form, $form_state, $entity_list, $field);
    $configuration = $this->getConfiguration();
    $options = [];
    foreach ($this->workflowManager->getGroupedLabels($entity_list->getTargetEntityTypeId()) as $group_label => $workflows) {
      foreach ($workflows as $workflow_id => $workflow_label) {
        $options[$workflow_id] = $group_label . ': ' . $workflow_label;
      }
    }
    $form['workflow_id'] = [
      '#type' => 'radios',
      '#title' => $this->t('Workflow'),
      '#default_value' => $configuration['workflow_id'],
      '#required' => TRUE,
      '#options' => $options,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array $form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $value, EntityListInterface $entity_list, array $field) {
    $form = parent::buildForm($form, $form_state, $value, $entity_list, $field);
    $form['state'] = [
      '#type' => 'select',
      '#title' => $field['display_label'],
      '#options' => $this->getStateOptions($entity_list),
      '#empty_option' => $this->t('- Show All -'),
      '#default_value' => $value,
    ];
    return $form;
  }

  /**
   * Get workflow options.
   */
  protected function getStateOptions(EntityListInterface $entity_list) {
    $configuration = $this->getConfiguration();
    $options = [];
    if (!empty($configuration['workflow_id'])) {
      $instance = $this->workflowManager->createInstance($configuration['workflow_id']);
      foreach ($instance->getStates() as $state_id => $state) {
        $options[$state_id] = $state->getLabel();
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrlQuery(array $raw_value, EntityListInterface $entity_list, array $field) {
    return $raw_value['state'];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty($raw_value) {
    return $this->checkEmpty($raw_value['state']);
  }

  /**
   * {@inheritdoc}
   */
  public function toPreview($value, EntityListInterface $entity_list, array $field) {
    $options = $this->getStateOptions($entity_list);
    return $options[$value] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function queryAlter($query, $value, EntityListInterface $entity_list, array $field) {
    $query->condition($field['field_name'], $value, '=');
  }

}
