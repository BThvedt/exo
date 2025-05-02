<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_alchemist\Controller\ExoFieldParentsFormTrait;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form form removing a component.
 *
 * @internal
 */
class ExoComponentConfigureForm extends FormBase {

  use AjaxFormHelperTrait;
  use LayoutRebuildTrait;
  use LayoutBuilderHighlightTrait;
  use ExoFieldParentsFormTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * The eXo component plugin manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * The UUID of the component.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The field delta.
   *
   * @var int
   */
  protected $delta;

  /**
   * The current region.
   *
   * @var string
   */
  protected $region;

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The entity being modified.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * Constructs a new ExoComponentContinueForm object.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The eXo component manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid generator.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ExoComponentManager $exo_component_manager, BlockManagerInterface $block_manager, EntityTypeManagerInterface $entity_type_manager, UuidInterface $uuid, AccountInterface $current_user) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->exoComponentManager = $exo_component_manager;
    $this->blockManager = $block_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->uuidGenerator = $uuid;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.exo_component'),
      $container->get('plugin.manager.block'),
      $container->get('entity_type.manager'),
      $container->get('uuid'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_configure_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $plugin_id = NULL) {
    $this->sectionStorage = $section_storage;
    $this->delta = $delta;
    $this->region = $region;
    $this->pluginId = $plugin_id;
    $definition = $this->exoComponentManager->getInstalledDefinition($plugin_id);
    $this->entity = $form_state->get('parent_entity') ?: $this->exoComponentManager->cloneEntity($definition);
    $required_paths = $this->exoComponentManager->getExoComponentFieldManager()->getRequiredPaths($definition);
    $finished = TRUE;
    if (isset($required_paths[$delta])) {
      $delta = $form_state->get('required_path_delta') ?: 0;
      $processed = $form_state->get('required_processed') ?: [];
      $finished = count($required_paths) == count($processed);
      $path = $required_paths[$delta];
      $parents = explode('.', $path);
      $field_name = $this->getFieldNameFromParents($parents);
      $child_entity = $this->getTargetEntity($this->entity, $parents);
      $child_definition = $this->exoComponentManager()->getEntityComponentDefinition($child_entity);
      $component_field = $child_definition->getFieldBySafeId($field_name);

      $form['#id'] = 'exo-component-configure';
      $form['wrapper'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Configure %label', [
          '%label' => $component_field->getLabel(),
        ]),
        '#suffix' => $this->t('Step %done of %total', [
          '%done' => $delta + 1,
          '%total' => count($required_paths),
        ]),
        '#description' => $component_field->getDescription(),
      ];
      $form['wrapper']['#allow_multiple'] = TRUE;
      $form['wrapper'] += $this->getTargetForm($form['wrapper'], $form_state, $this->entity, $parents);

      $form['actions'] = ['#type' => 'actions'];
      if (isset($required_paths[$delta - 1])) {
        $form['actions']['previous'] = [
          '#type' => 'submit',
          '#value' => $this->t('Previous'),
          '#submit' => ['::continueSubmit'],
          '#required_path_delta' => $delta - 1,
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => '::ajaxContinueSubmit',
            'wrapper' => 'exo-component-configure',
          ],
        ];
      }
      if (isset($required_paths[$delta + 1])) {
        $form['actions']['next'] = [
          '#type' => 'submit',
          '#value' => $this->t('Continue'),
          '#button_type' => $finished ? '' : 'primary',
          '#submit' => ['::continueSubmit'],
          '#required_path_process' => TRUE,
          '#required_path_delta' => $delta + 1,
          '#ajax' => [
            'callback' => '::ajaxContinueSubmit',
            'wrapper' => 'exo-component-configure',
          ],
        ];
      }
    }
    if ($finished || !isset($required_paths[$delta + 1])) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add Component'),
        '#button_type' => 'primary',
        '#do_submit' => TRUE,
        '#ajax' => [
          'callback' => '::ajaxSubmit',
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateTargetForm($form['wrapper'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (!empty($trigger['#do_submit'])) {
      $this->submitTargetForm($form['wrapper'], $form_state);

      $definition = $this->exoComponentManager->getInstalledDefinition($this->pluginId);
      $block_plugin_id = 'inline_block:' . $definition->safeId();
      $this->uuid = $this->uuidGenerator->generate();
      $component = new SectionComponent($this->uuid, $this->region, [
        'id' => $block_plugin_id,
        'label_display' => FALSE,
        'block_serialized' => serialize($this->entity),
      ]);
      $section = $this->sectionStorage->getSection($this->delta);
      $section->appendComponent($component);
      $this->layoutTempstoreRepository->set($this->sectionStorage);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function continueSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $this->submitTargetForm($form['wrapper'], $form_state);

    $delta = $trigger['#required_path_delta'];
    $form_state->set('required_path_delta', $delta);

    if (!empty($trigger['#required_path_process'])) {
      $processed = $form_state->get('required_processed') ?: [];
      $processed[$delta] = TRUE;
      $form_state->set('required_processed', $processed);
    }

    // Reset.
    $input = $form_state->getUserInput();
    foreach ($input as $key => $value) {
      if (substr($key, 0, 10) === 'exo_field_') {
        unset($input[$key]);
      }
    }
    $form_state->setUserInput($input);

    // Temporarily store changes.
    $form_state->set('parent_entity', $this->entity);
    $form_state->setRebuild();
  }

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or represents a
   *   successful submission.
   */
  public function ajaxContinueSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
      $form['#sorted'] = FALSE;
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('[data-drupal-selector="' . $form['#attributes']['data-drupal-selector'] . '"]', $form));
    }
    else {
      $response = $this->successfulAjaxContinueSubmit($form, $form_state);
    }
    return $response;
  }

  /**
   * Allows the form to respond to a successful AJAX submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  protected function successfulAjaxContinueSubmit(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->rebuildAndClose($this->sectionStorage);
  }

}
