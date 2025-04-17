<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for moving a section.
 *
 * @internal
 *   Form classes are internal.
 */
class ExoSectionMoveForm extends FormBase {

  use AjaxFormHelperTrait;
  use LayoutBuilderHighlightTrait;
  use LayoutRebuildTrait;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * The Layout Tempstore.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstore;

  /**
   * Constructs a new MoveSectionsForm.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->layoutTempstore = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_section_move';
  }

  /**
   * Builds the move section form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL) {
    $this->sectionStorage = $section_storage;

    $storage_label = $section_storage->label();
    $aria_label = $this->t('Sections in @storage_label layout', ['@storage_label' => $storage_label]);

    $form['sections_wrapper']['sections'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Sections'),
        $this->t('Delta'),
      ],
      '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'table-sort-delta',
          ],
      ],
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'id' => 'layout-builder-sections-table',
            'class' => ['layout-builder-sections-table'],
            'aria-label' => $aria_label,
          ],
        ],
      ],
    ];

    $sections = $section_storage->getSections();

    foreach ($sections as $section_delta => $section) {
      $row_classes = [
        'draggable',
        'layout-builder-sections-table__row',
      ];

      $layout_settings = $section->getLayoutSettings();
      $section_label = !empty($layout_settings['label']) ? $layout_settings['label'] : $this->t('Section @section', ['@section' => $section_delta + 1]);

      $label = [
        '#markup' => $section_label,
        '#wrapper_attributes' => ['class' => ['layout-builder-sections-table__section-label']],
      ];

      $form['sections_wrapper']['sections'][$section_delta] = [
        '#attributes' => ['class' => $row_classes],
        'label' => $label,
        'delta' => [
          '#type' => 'select',
          '#options' => range(0, count($sections) - 1),
          '#default_value' => $section_delta,
          '#title' => $this->t('Delta for @section section', ['@section' => $section_label]),
          '#title_display' => 'invisible',
          '#attributes' => [
            'class' => ['table-sort-delta'],
          ],
        ],
      ];

    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reorder'),
      '#button_type' => 'primary',
    ];

    $form['#attributes']['data-add-layout-builder-wrapper'] = 'layout-builder--move-sections-active';

    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $new_deltas = $this->getNewDeltas($form_state);
    if (count($new_deltas)) {
      $sections = $this->sectionStorage->getSections();
      // Create a numeric array with the section deltas reordered.
      $deltas = array_combine(array_keys($new_deltas), array_column($new_deltas, 'delta'));
      asort($deltas);
      $order = array_keys($deltas);
      // Reorder sections.
      $sections = array_map(function ($delta) use ($sections) {
          return $sections[$delta];
      }, $order);
      $this->sectionStorage->removeAllSections();
      foreach ($sections as $section) {
        $this->sectionStorage->appendSection($section);
      }
      $this->layoutTempstore->set($this->sectionStorage);
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->rebuildAndClose($this->sectionStorage);
  }

  /**
   * Gets the submitted section deltas.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The section deltas.
   */
  protected function getNewDeltas(FormStateInterface $form_state) {
    if ($form_state->hasValue('sections')) {
      return $form_state->getValue('sections');
    }
    return [];
  }

}
