<?php

namespace Drupal\exo_list_builder\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides an entities deletion confirmation form.
 */
class EntityListActionCancelForm extends ConfirmFormBase {

  /**
   * The name of the current operation.
   *
   * Subclasses may use this to implement different behaviors depending on its
   * value.
   *
   * @var string
   */
  protected $operation;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\exo_list_builder\EntityListInterface
   */
  protected $entity;

  /**
   * The action definition.
   *
   * @var array
   */
  protected $action;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_list_action_cancel_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $exo_entity_list_action = NULL) {
    $actions = $this->entity->getActions();
    if (!isset($actions[$exo_entity_list_action])) {
      throw new AccessDeniedHttpException();
    }
    $this->action = $actions[$exo_entity_list_action];
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.<br>');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if (\Drupal::request()->query->get('op') === 'clear') {
      return $this->t('Are you sure you want to clear the finished %name record?', ['%name' => $this->action['label']]);
    }
    return $this->t('Are you sure you want to cancel pending %name actions?', ['%name' => $this->action['label']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Return');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    if (\Drupal::request()->query->get('op') === 'clear') {
      return $this->t('Clear Finished Record');
    }
    return $this->t('Cancel Pending Actions');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $handler = $this->entity->getHandler();
    if ($queue = $handler->getQueue($this->action['id'])) {
      /** @var \Drupal\exo_list_builder\Plugin\ExoListActionInterface $instance */
      $instance = \Drupal::service('plugin.manager.exo_list_action')->createInstance($this->action['id'], $this->action['settings']);

      /** @var \Drupal\exo_list_builder\QueueWorker\ExoListActionProcess $queue_worker */
      $queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('exo_list_action:' . $this->entity->id() . ':' . $instance->getPluginId());
      $queue_worker->deleteContext();
      $queue->deleteQueue();
    }

    if (\Drupal::request()->query->get('op') === 'clear') {
      $message = $this->t('The finished %action record has been cleared.', [
        '@label' => $this->entity->label(),
        '%action' => $this->action['label'],
      ]);
    }
    else {
      $message = $this->t('The pending %action actions have been canceled.', [
        '@label' => $this->entity->label(),
        '%action' => $this->action['label'],
      ]);
    }
    $this->messenger()->addMessage($message);

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(EntityInterface $entity) {
    $this->entity = $entity;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter($entity_type_id) !== NULL) {
      $entity = $route_match->getParameter($entity_type_id);
    }
    else {
      $values = [];
      // If the entity has bundles, fetch it from the route match.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if ($bundle_key = $entity_type->getKey('bundle')) {
        if (($bundle_entity_type_id = $entity_type->getBundleEntityType()) && $route_match->getRawParameter($bundle_entity_type_id)) {
          $values[$bundle_key] = $route_match->getParameter($bundle_entity_type_id)->id();
        }
        elseif ($route_match->getRawParameter($bundle_key)) {
          $values[$bundle_key] = $route_match->getParameter($bundle_key);
        }
      }

      $entity = $this->entityTypeManager->getStorage($entity_type_id)->create($values);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOperation($operation) {
    // If NULL is passed, do not overwrite the operation.
    if ($operation) {
      $this->operation = $operation;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

}
