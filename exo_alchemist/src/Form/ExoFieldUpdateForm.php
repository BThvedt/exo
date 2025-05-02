<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_alchemist\Ajax\ExoComponentModifierAttributesCommand;
use Drupal\exo_alchemist\Controller\ExoFieldParentsFormTrait;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\exo_alchemist\ExoComponentPropertyManager;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form form removing a component.
 *
 * @internal
 */
class ExoFieldUpdateForm extends FormBase {

  use AjaxFormHelperTrait;
  use LayoutRebuildTrait;
  use LayoutBuilderHighlightTrait;
  use ExoFieldParentsFormTrait;
  use ExoIconTranslationTrait;

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
   * The active entity parents.
   *
   * @var array
   */
  protected $parents;

  /**
   * The plugin being configured.
   *
   * @var \Drupal\layout_builder\Plugin\Block\InlineBlock
   */
  protected $block;

  /**
   * The entity being modified.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * Preview contexts.
   *
   * @var \Drupal\Core\Plugin\Context\Context[]
   */
  protected $contexts;

  /**
   * Constructs a new ExoComponentAppearanceForm object.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The eXo component manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ExoComponentManager $exo_component_manager, BlockManagerInterface $block_manager, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->exoComponentManager = $exo_component_manager;
    $this->blockManager = $block_manager;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_field_update_form';
  }

  /**
   * Builds the block form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region of the block.
   * @param string $uuid
   *   The UUID of the block being updated.
   * @param string $path
   *   The path to the field requested for updating.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL, $path = NULL) {
    $form['#id'] = 'exo-field-update-form';
    $this->sectionStorage = $section_storage;
    $this->contexts = $section_storage->getContexts();
    $component = $section_storage->getSection($delta)->getComponent($uuid);
    $this->delta = $delta;
    $this->region = $region;
    $this->uuid = $component->getUuid();
    $this->block = $component->getPlugin();
    $this->entity = $form_state->get('parent_entity') ?: $this->extractBlockEntity($this->block);
    // Set Alchemist is preview so that the property manager knows when
    // refreshing modifiers.
    $this->entity->exoAlchemistPreview = TRUE;
    $this->parents = explode('.', $path);
    $definition = $this->exoComponentManager()->getEntityComponentDefinition($this->entity);

    $form += $this->getTargetForm($form, $form_state, $this->entity, $this->parents);

    if ($definition->isGlobal()) {
      $form['is_global'] = [
        '#markup' => '<div class="message">' . $this->icon('<strong>Global Component</strong>: Changes will be applied to all instances of this component.')->setIcon('regular-globe') . '</div>',
        '#weight' => -1000,
      ];
    }

    $child_entity = $this->getTargetEntity($this->entity, $this->parents);
    $child_definition = $this->exoComponentManager()->getEntityComponentDefinition($child_entity);
    $modifier_target = $child_definition->getModifierTarget();
    $field_name = $this->getFieldNameFromParents($this->parents);
    // We are targeting a field.
    if ($field = $definition->getFieldBySafeId($field_name)) {
      $modifier_target = $field->getModifierTarget();
    }
    $field_reset = [];
    if ($modifier_target && ($field_modifier = $definition->getModifier($modifier_target))) {
      $this->exoComponentManager->getExoComponentPropertyManager()->buildForm($form, $form_state, $definition, $this->entity);
      if (!empty($form['modifiers'])) {
        $form['modifiers']['#attributes']['data-exo-alchemist-revert'] = TRUE;
        foreach ($definition->getModifiers() as $modifier) {
          if ($field_modifier->getName() === $modifier->getName()) {
            $form['modifiers'][$modifier->getName()]['#title'] = $this->t('Appearance');
            $form['modifiers'][$modifier->getName()]['#type'] = 'fieldset';
            $form['modifiers'][$modifier->getName()]['#group'] = '';
          }
          if ($field_modifier->getName() !== $modifier->getName() && $field_modifier->getName() !== $modifier->getGroup()) {
            unset($form['modifiers'][$modifier->getName()]);
          }
          else {
            $field_reset[] = $modifier->getName();
          }
        }
      }

      $form['modifiers']['_info'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'exo-alchemist-revert-message',
            'messages',
            'messages--warning',
            'warning',
            'hidden',
          ],
        ],
        '#children' => $this->t('You have unsaved changes.'),
        '#weight' => -10,
      ];

      $form['refresh'] = [
        '#type' => 'submit',
        '#value' => $this->t('Refresh'),
        '#id' => 'exo-alchemist-appearance-refresh',
        '#op' => 'refresh',
        '#attributes' => [
          'class' => ['hidden'],
        ],
        '#ajax' => [
          'callback' => '::ajaxAppearanceSubmit',
          'progress' => [
            'type' => 'none',
          ],
        ],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#button_type' => 'primary',
      '#do_submit' => TRUE,
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];
    if (!empty($field_reset)) {
      $form['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset Appearance'),
        '#op' => 'reset',
        '#field_reset' => $field_reset,
        '#do_submit' => TRUE,
        '#ajax' => [
          'callback' => '::ajaxSubmit',
        ],
      ];
    }

    return $form;
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
  public function ajaxAppearanceSubmit(array &$form, FormStateInterface $form_state) {
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
      $response = $this->successfulAjaxAppearanceSubmit($form, $form_state);
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
  protected function successfulAjaxAppearanceSubmit(array $form, FormStateInterface $form_state) {
    $definition = $this->exoComponentManager->getEntityComponentDefinition($this->entity);
    $attributes = $this->exoComponentManager->getExoComponentPropertyManager()->getModifierAttributes($definition, $this->entity, $this->contexts);

    $response = new AjaxResponse();
    $response->addCommand(new ExoComponentModifierAttributesCommand($attributes));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateTargetForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $op = $trigger['#op'] ?? 'submit';
    $definition = $this->exoComponentManager->getEntityComponentDefinition($this->entity);

    if ($op === 'submit') {
      $this->submitTargetForm($form, $form_state);
    }

    if ($modifier_values = $form_state->getValue('modifiers')) {
      if ($op == 'reset') {
        $this->exoComponentManager->getExoComponentPropertyManager()->populateEntity($definition, $this->entity, $trigger['#field_reset']);
      }
      else {
        if (!$this->entity->get(ExoComponentPropertyManager::MODIFIERS_FIELD_NAME)->isEmpty()) {
          $original_values = $this->entity->get(ExoComponentPropertyManager::MODIFIERS_FIELD_NAME)->first()->value;
          $modifier_values = NestedArray::mergeDeep($original_values, $modifier_values);
        }
        $this->entity->get(ExoComponentPropertyManager::MODIFIERS_FIELD_NAME)->setValue(['value' => $modifier_values]);
      }
      // Temporarily store changes.
      $form_state->set('parent_entity', $this->entity);
    }

    if (empty($trigger['#do_submit'])) {
      $form_state->setRebuild();
    }
    else {
      $component = $this->sectionStorage->getSection($this->delta)->getComponent($this->uuid);
      $configuration = $this->block->getConfiguration();
      // Allow component to act on update.
      $this->exoComponentManager->onDraftUpdateLayoutBuilderEntity($definition, $this->entity);
      $configuration['block_serialized'] = serialize($this->entity);
      $component->setConfiguration($configuration);
      $this->layoutTempstoreRepository->set($this->sectionStorage);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->rebuildAndClose($this->sectionStorage);
  }

  /**
   * Get the section storage.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage.
   */
  public function getSectionStorage() {
    return $this->sectionStorage;
  }

}
