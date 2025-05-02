<?php

namespace Drupal\exo_modal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_modal\Ajax\ExoModalContentCommand;
use Drupal\exo_modal\ExoModalGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ExoModalEntityController.
 */
class ExoModalEntityController extends ControllerBase {
  use ExoModalResponseTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\exo_modal\ExoModalGeneratorInterface
   */
  protected $exoModalGenerator;

  /**
   * Constructs a new ExoModalBlockController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ExoModalGeneratorInterface $exo_modal_generator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->exoModalGenerator = $exo_modal_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('exo_modal.generator')
    );
  }

  /**
   * View modal content.
   */
  public function view(EntityInterface $entity, $display_id, $revision_id = NULL) {
    if ($revision_id) {
      $revision_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($revision_id);
      if ($revision_entity && $entity->id() === $revision_entity->id()) {
        $entity = $revision_entity;
      }
    }
    $build = [
      'messages' => [
        '#type' => 'status_messages',
      ],
      'entity' => $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, $display_id),
    ];
    $response = new AjaxResponse();
    $response->addCommand(new ExoModalContentCommand($build));
    return $response;
  }

  /**
   * Update entity form.
   */
  public function editForm(Request $request, EntityInterface $entity, $display_id, $revision_id = NULL) {
    if (!$entity->getEntityType()->hasFormClasses($display_id)) {
      throw new NotFoundHttpException();
    }
    $build = [
      '#title' => $this->t('Edit %label', ['%label' => $entity->label()]),
      'form' => \Drupal::service('entity.form_builder')->getForm($entity, $display_id),
    ];
    if (isset($build['form']['#title'])) {
      $build['#title'] = $build['form']['#title'];
    }
    if ($this->isAjax()) {
      $manager = \Drupal::service('plugin.manager.exo_icon');
      $icon = $manager->getDefinitionMatch('edit', [
        'local_action',
        'local_task',
        'admin',
      ]);
      return $this->buildModalResponse($request, $build, [
        'modal' => [
          'title' => $build['#title'],
          'icon' => $icon,
          'top' => 0,
          'radius' => 0,
          'transitionIn' => 'fadeInDown',
          'transitionOut' => 'fadeOutUp',
        ],
      ]);
    }
    return $build;
  }

  /**
   * Checks access for a specific request.
   */
  public function editFormAccess(AccountInterface $account, EntityInterface $entity, $display_id, $access_id = 'update') {
    if (!$entity->getEntityType()->hasFormClasses($display_id)) {
      return AccessResult::forbidden('Entity does not have form class of type "' . $display_id . '".');
    }
    return $entity->access($access_id, $account, TRUE);
  }

  /**
   * Update entity form.
   */
  public function deleteForm(Request $request, EntityInterface $entity) {
    $build = [
      '#title' => $this->t('Edit %label', ['%label' => $entity->label()]),
      'form' => \Drupal::service('entity.form_builder')->getForm($entity, 'delete'),
    ];
    if (isset($build['form']['#title'])) {
      $build['#title'] = $build['form']['#title'];
    }
    if ($this->isAjax()) {
      $manager = \Drupal::service('plugin.manager.exo_icon');
      $icon = $manager->getDefinitionMatch('edit', [
        'local_action',
        'local_task',
        'admin',
      ]);
      return $this->buildModalResponse($request, $build, [
        'exo_preset' => 'alert',
        'modal' => [
          'title' => $build['#title'],
          'icon' => $icon,
        ],
      ]);
    }
    return $build;
  }

  /**
   * Checks access for a specific request.
   */
  public function deleteFormAccess(AccountInterface $account, EntityInterface $entity, $access_id = 'update') {
    return $entity->access($access_id, $account, TRUE);
  }

  /**
   * Create entity form.
   */
  public function createForm(Request $request, $entity_type, $bundle, $display_id, $data = []) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type, FALSE);
    if (!$entity_type->getFormClass($display_id)) {
      throw new NotFoundHttpException();
    }
    $data = $request->query->all()['data'] ?: [];
    if ($bundle = $entity_type->getKey('bundle')) {
      $data[$bundle] = $bundle;
    }
    $entity = $this->entityTypeManager->getStorage($entity_type->id())->create($data);
    $build = [
      '#title' => $this->t('Create @entity_type', ['@entity_type' => $entity_type->getLabel()]),
      'form' => \Drupal::service('entity.form_builder')->getForm($entity, $display_id),
    ];
    if (isset($build['form']['#title'])) {
      $build['#title'] = $build['form']['#title'];
    }
    if ($this->isAjax()) {
      $manager = \Drupal::service('plugin.manager.exo_icon');
      $icon = $manager->getDefinitionMatch('create', [
        'local_action',
        'local_task',
        'admin',
      ]);
      return $this->buildModalResponse($request, $build, [
        'modal' => [
          'title' => $build['#title'],
          'icon' => $icon,
        ],
      ]);
    }
    return $build;
  }

  /**
   * Checks access for a specific request.
   */
  public function createFormAccess(AccountInterface $account, $entity_type, $bundle) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type, FALSE);
    if (empty($entity_type)) {
      return AccessResult::forbidden('Entity type does not exist.');
    }
    return $this->entityTypeManager->getAccessControlHandler($entity_type->id())->createAccess($bundle, $account, [], TRUE);
  }

}
