<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_alchemist\Ajax\ExoComponentModifierAttributesCommand;
use Drupal\exo_alchemist\Controller\ExoFieldParentsTrait;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\exo_alchemist\ExoComponentPropertyManager;
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
class ExoComponentAppearanceForm extends FormBase {

  use AjaxFormHelperTrait;
  use LayoutRebuildTrait;
  use LayoutBuilderHighlightTrait;
  use ExoFieldParentsTrait;

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
   * The UUID of the block being acted on.
   *
   * @var string
   */
  protected $uuid;

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
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The eXo component manager.
   * @param \Drupal\exo_alchemist\ExoComponentPropertyManager $exo_component_property_manager
   *   The eXo component property manager.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ExoComponentManager $exo_component_manager, ExoComponentPropertyManager $exo_component_property_manager) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->exoComponentManager = $exo_component_manager;
    $this->exoComponentPropertyManager = $exo_component_property_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.exo_component'),
      $container->get('plugin.manager.exo_component_property')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_appearance_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $this->sectionStorage = $section_storage;
    $this->contexts = $section_storage->getContexts();
    $this->delta = $delta;
    $this->region = $region;
    $this->uuid = $uuid;

    $component = $this->sectionStorage->getSection($this->delta)->getComponent($this->uuid);
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $this->block */
    $this->block = $component->getPlugin();
    $this->entity = $this->extractBlockEntity($this->block);
    // Set Alchemist is preview so that the property manager knows when
    // refreshing modifiers.
    $this->entity->exoAlchemistPreview = TRUE;
    $definition = $this->exoComponentManager->getEntityBundleComponentDefinition($this->entity->type->entity);

    if ($this->isAjax()) {
      $target_highlight_id = !empty($this->uuid) ? $this->blockUpdateHighlightId($this->uuid) : $this->sectionUpdateHighlightId($delta);
      $form['#attributes']['data-layout-builder-target-highlight-id'] = $target_highlight_id;
    }

    $this->exoComponentPropertyManager->buildForm($form, $form_state, $definition, $this->entity);
    $form['modifiers']['#attributes']['data-exo-alchemist-revert'] = TRUE;
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
      '#attributes' => [
        'class' => ['hidden'],
      ],
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'progress' => [
          'type' => 'none',
        ],
      ],
    ];

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
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Appearance'),
      '#op' => 'reset',
      '#do_submit' => TRUE,
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $op = $trigger['#op'] ?? 'submit';
    $definition = $this->exoComponentManager->getEntityBundleComponentDefinition($this->entity->type->entity);
    switch ($op) {
      case 'submit':
        $this->entity->get(ExoComponentPropertyManager::MODIFIERS_FIELD_NAME)->setValue(['value' => $form_state->getValue('modifiers')]);
        break;

      case 'reset':
        $this->exoComponentPropertyManager->populateEntity($definition, $this->entity);
        break;
    }

    if (!empty($trigger['#do_submit'])) {
      // Save changes.
      $component = $this->sectionStorage->getSection($this->delta)->getComponent($this->uuid);
      $configuration = $this->block->getConfiguration();
      $configuration['block_serialized'] = serialize($this->entity);
      $component->setConfiguration($configuration);
      $this->layoutTempstoreRepository->set($this->sectionStorage);
    }
    else {
      $form_state->setTemporaryValue('appearanceEntity', $this->entity);
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();

    $entity = $form_state->getTemporaryValue('appearanceEntity') ?: $this->entity;

    $definition = $this->exoComponentManager->getEntityBundleComponentDefinition($entity->type->entity);
    $attributes = $this->exoComponentPropertyManager->getModifierAttributes($definition, $entity, $this->contexts);
    if (!empty($trigger['#do_submit'])) {
      return $this->rebuildAndClose($this->sectionStorage);
    }
    $response = new AjaxResponse();
    $response->addCommand(new ExoComponentModifierAttributesCommand($attributes));
    return $response;
  }

}
