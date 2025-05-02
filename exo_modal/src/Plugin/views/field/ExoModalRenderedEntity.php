<?php

namespace Drupal\exo_modal\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\exo\ExoSettingsInterface;
use Drupal\exo_modal\ExoModalGeneratorInterface;
use Drupal\views\Plugin\views\field\RenderedEntity;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;

/**
 * Provides a field handler which renders an entity in a certain view mode.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("exo_modal_rendered_entity")
 */
class ExoModalRenderedEntity extends RenderedEntity {

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
   * Constructs a new RenderedEntity object.
   *
   * @param array $options
   *   A options array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\exo\ExoSettingsInterface $exo_modal_settings
   *   The eXo options service.
   * @param \Drupal\exo_modal\ExoModalGeneratorInterface $exo_modal_generator
   *   The eXo modal generator.
   */
  public function __construct(array $options, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, EntityRepositoryInterface $entity_repository, EntityDisplayRepositoryInterface $entity_display_repository, ExoSettingsInterface $exo_modal_settings, ExoModalGeneratorInterface $exo_modal_generator) {
    parent::__construct($options, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $entity_repository, $entity_display_repository);
    $this->exoModalSettings = $exo_modal_settings;
    $this->exoModalGenerator = $exo_modal_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $options, $plugin_id, $plugin_definition) {
    return new static(
      $options,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('entity.repository'),
      $container->get('entity_display.repository'),
      $container->get('exo_modal.settings'),
      $container->get('exo_modal.generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->exoModalSettings = $this->exoModalSettings->createInstance($this->options['modal']);
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['ajax'] = ['default' => FALSE];
    $options['text'] = ['default' => 'Open'];
    $options['icon'] = ['default' => ''];
    $options['icon_only'] = ['default' => FALSE];
    $options['modal'] = [
      'default' => [
        'exo_default' => 1,
      ],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['ajax'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load in dynamically'),
      '#default_value' => $this->options['ajax'],
    ];
    $form['modal'] = [];
    $form['modal'] = $this->exoModalSettings->buildForm($form['modal'], $form_state) + [
      '#type' => 'fieldset',
      '#title' => $this->t('Modal'),
      '#element_validate' => [[$this, 'buildOptionsModalValidate']],
      '#weight' => 10,
    ];

    // We move the trigger settings outside of the modal settings as they
    // will most often be changed.
    foreach (Element::children($form['modal']['settings']['trigger']) as $key) {
      $form[$key] = $form['modal']['settings']['trigger'][$key];
      $form[$key]['#default_value'] = $this->options[$key];
      $form[$key]['#title'] = $this->t('Trigger @name', ['@name' => $form[$key]['#title']]);
    }
    $form['modal']['settings']['trigger']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsModalValidate($element, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    $this->exoModalSettings->validateForm($element, $subform_state);
  }

  /**
   * Generate a unique modal id based on the entity.
   */
  protected function getModalId($entity) {
    $keys = [$entity->getEntityTypeId(), $entity->id()];
    if ($entity->getEntityType()->isRevisionable()) {
      $keys[] = $entity->getRevisionId();
    }
    return 'exo_modal_view_' . implode('-', $keys);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntityTranslation($this->getEntity($values), $values);
    $build = [];
    if (isset($entity)) {
      $access = $entity->access('view', NULL, TRUE);
      $build['#access'] = $access;
      if ($access->isAllowed()) {
        $id = $this->getModalId($entity);
        $use_ajax = $this->options['ajax'];
        $modal = $this->buildModal($entity);
        if ($use_ajax) {
          $url = Url::fromRoute('exo_modal.api.views.field', [
            'view' => $this->view->id(),
            'view_display_id' => $this->view->current_display,
            'field' => $this->field_alias != 'unknown' ? $this->field_alias : $this->field,
            'entity_type' => $entity->getEntityTypeId(),
            'entity' => $entity->id(),
            'revision_id' => $entity->getRevisionId(),
          ])->getInternalPath();
          $modal->setSetting(['modal', 'ajax'], $url);
          $build['modal'] = $modal->toRenderableTrigger();
        }
        else {
          $build['modal'] = $modal->toRenderable();
        }
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildModal(EntityInterface $entity) {
    $id = $this->getModalId($entity);
    $modal = $this->exoModalGenerator->generate(
      $id,
      $this->options['modal'],
      $this->buildModalContent($entity)
    );
    $modal->addTriggerClass('button');
    $modal->setSetting(['modal', 'title'], $entity->label());
    $modal->setSetting(['modal', 'icon'], exo_icon_entity_icon($entity));
    $modal->setTrigger($this->options['text'], $this->options['icon'], $this->options['icon_only']);
    return $modal;
  }

  /**
   * Builds and returns the renderable array for display within the modal.
   *
   * @return array
   *   A renderable array representing the content of the modal.
   */
  protected function buildModalContent(EntityInterface $entity) {
    $build = [];
    if (isset($entity)) {
      $access = $entity->access('view', NULL, TRUE);
      $build['#access'] = $access;
      if ($access->isAllowed()) {
        $view_builder = $this->entityManager->getViewBuilder($this->getEntityTypeId());
        $build += $view_builder->view($entity, $this->options['view_mode']);
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $view_display_storage = $this->entityManager->getStorage('entity_view_display');
    $view_displays = $view_display_storage->loadMultiple($view_display_storage
      ->getQuery()
      ->condition('targetEntityType', $this->getEntityTypeId())
      ->execute());

    $tags = [];
    foreach ($view_displays as $view_display) {
      $tags = array_merge($tags, $view_display->getCacheTags());
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We purposefully do not call parent::query() because we do not want the
    // default query behavior for Views fields. Instead, let the entity
    // translation renderer provide the correct query behavior.
    if ($this->languageManager->isMultilingual()) {
      $this->getEntityTranslationRenderer()->query($this->query, $this->relationship);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->getEntityType();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLanguageManager() {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getView() {
    return $this->view;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $view_mode = $this->entityManager
      ->getStorage('entity_view_mode')
      ->load($this->getEntityTypeId() . '.' . $this->options['view_mode']);
    if ($view_mode) {
      $dependencies[$view_mode->getConfigDependencyKey()][] = $view_mode->getConfigDependencyName();
    }

    return $dependencies;
  }

}
