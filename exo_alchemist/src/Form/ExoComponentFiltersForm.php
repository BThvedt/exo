<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_alchemist\Controller\ExoFieldParentsTrait;
use Drupal\exo_alchemist\ExoComponentFieldManager;
use Drupal\exo_alchemist\ExoComponentManager;
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
class ExoComponentFiltersForm extends FormBase {

  use AjaxFormHelperTrait;
  use LayoutRebuildTrait;
  use LayoutBuilderHighlightTrait;
  use ExoFieldParentsTrait;
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
   * The UUID of the block being removed.
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
   * The component definition.
   *
   * @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   */
  protected $definition;

  /**
   * The entity form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected $formDisplay;

  /**
   * The eXo component plugin manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * Constructs a new ExoComponentAppearanceForm object.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The eXo component manager.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ExoComponentManager $exo_component_manager) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->exoComponentManager = $exo_component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.exo_component')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_fields_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $this->sectionStorage = $section_storage;
    $this->delta = $delta;
    $this->region = $region;
    $this->uuid = $uuid;

    $component = $this->sectionStorage->getSection($this->delta)->getComponent($this->uuid);
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $this->block */
    $this->block = $component->getPlugin();
    $this->entity = $this->extractBlockEntity($this->block);
    /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition */
    if (empty($this->entity)) {
      \Drupal::messenger()->addWarning($this->t('An error occurred. Please remove this component and add it again.'));
      return $form;
    }
    $this->definition = $this->exoComponentManager->getEntityBundleComponentDefinition($this->entity->type->entity);

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $this->formDisplay = $form_display = $this->entityTypeManager()->getStorage('entity_form_display')->load($this->entity->getEntityTypeId() . '.' . $this->entity->bundle() . '.default');
    if ($form_display) {
      foreach ($this->definition->getFields() as $field) {
        if (!$field->isFilter()) {
          $form_display->removeComponent($field->safeId());
        }
      }
    }

    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->icon('Filters')->setIcon('regular-filter'),
      '#description' => $this->t('The results of this component can be filtered by the following options.'),
    ];
    $form_display->buildForm($this->entity, $form['filters'], $form_state);
    $form['filters']['revision_log']['#access'] = FALSE;

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#button_type' => 'primary',
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->formDisplay->extractFormValues($this->entity, $form, $form_state);
    $this->formDisplay->validateFormValues($this->entity, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $component = $this->sectionStorage->getSection($this->delta)->getComponent($this->uuid);
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $block */
    $block = $component->getPlugin();

    // Handle hiding and showing.
    foreach ($this->definition->getFields() as $field) {
      if ($field->isFilter()) {
        if ($this->entity->hasField($field->getFieldName())) {
          if ($this->entity->get($field->getFieldName())->isEmpty()) {
            ExoComponentFieldManager::setHiddenFieldName($this->entity, $field->getName());
          }
          else {
            ExoComponentFieldManager::setVisibleFieldName($this->entity, $field->getName());
          }
        }
      }
    }

    $configuration = $block->getConfiguration();
    $configuration['block_serialized'] = serialize($this->entity);
    $section = $this->sectionStorage->getSection($this->delta);
    $section->getComponent($this->uuid)->setConfiguration($configuration);
    $this->layoutTempstoreRepository->set($this->sectionStorage);
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->rebuildAndClose($this->sectionStorage);
  }

}
