<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\exo_alchemist\Definition\ExoComponentDefinition;
use Drupal\exo_alchemist\ExoComponentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form form removing a component.
 *
 * @internal
 */
class ExoComponentUpdateForm extends ConfirmFormBase {

  /**
   * The eXo component plugin manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * The eXo component manager.
   *
   * @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   */
  protected $definitionFrom;

  /**
   * The eXo component manager.
   *
   * @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   */
  protected $definitionTo;

  /**
   * The changes being processed.
   *
   * @var array
   */
  protected $changes;

  /**
   * TRUE if component has changes.
   *
   * @var bool
   */
  protected $hasFieldChanges = FALSE;

  /**
   * Constructs a new DeleteMultiple object.
   *
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The eXo component manager.
   */
  public function __construct(ExoComponentManager $exo_component_manager) {
    $this->exoComponentManager = $exo_component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.exo_component')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_component_update_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ExoComponentDefinition $definition = NULL) {
    $this->definitionFrom = $definition;
    $this->definitionTo = $this->exoComponentManager->getDefinition($definition->id());
    $this->changes = $this->exoComponentManager->getEntityBundleFieldChanges($this->definitionTo, $this->definitionFrom);
    $form = parent::buildForm($form, $form_state);
    $form['description'] = $form['description']['#markup'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $build = [];
    $build['title']['#markup'] = $this->t('The component %title will be updated.', [
      '%title' => $this->definitionFrom->getLabel(),
    ]);
    foreach ($this->changes as $type => $fields) {
      if (!empty($fields)) {
        $this->hasFieldChanges = TRUE;
        $build[$type] = [
          '#theme' => 'item_list',
          '#title' => $this->t('@operation Field(s)', ['@operation' => ucfirst($type)]),
          '#items' => array_map(function ($field) {
            return $field->getLabel() . ' (' . $field->getName() . ')';
          }, $fields),
        ];
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Update');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to update %title?', [
      '%title' => $this->definitionFrom->getLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('exo_alchemist.component.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->exoComponentManager->updateInstalledDefinition($this->definitionFrom);
    $this->messenger()->addStatus($this->t('The component %title has been updated.', [
      '%title' => $this->definitionTo->getLabel(),
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
