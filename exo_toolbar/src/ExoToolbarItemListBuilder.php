<?php

namespace Drupal\exo_toolbar;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\exo_toolbar\Entity\ExoToolbarInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\exo_toolbar\Plugin\ExoToolbarRegionPluginInterface;

/**
 * Provides a listing of eXo Toolbar Item entities.
 */
class ExoToolbarItemListBuilder extends ConfigEntityListBuilder implements FormInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The eXo toolbar.
   *
   * @var \Drupal\exo_toolbar\Entity\ExoToolbarInterface
   */
  protected $exoToolbar;

  /**
   * Constructs a new BlockListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    FormBuilderInterface $form_builder,
  ) {
    parent::__construct($entity_type, $storage);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\exo_toolbar\Entity\ExoToolbarInterface $exo_toolbar
   *   The toolbar to list items for.
   *
   * @return array
   *   The item list as a renderable array.
   */
  public function render(?Request $request = NULL, ?ExoToolbarInterface $exo_toolbar = NULL) {
    if ($request && $exo_toolbar) {
      $this->request = $request;
      $this->exoToolbar = $exo_toolbar;
      return $this->formBuilder->getForm($this);
    }
    return parent::render();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_toolbar_items_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'core/drupal.tableheader';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attributes']['class'][] = 'clearfix';

    // Build the form tree.
    $form['items'] = $this->buildItemsForm();

    $form['actions'] = [
      '#tree' => FALSE,
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Items'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'));

    if (isset($this->exoToolbar)) {
      $query->condition('toolbar', $this->exoToolbar->id());
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->accessCheck(FALSE)->execute();
  }

  /**
   * Builds the main "items" portion of the form.
   *
   * @return array
   *   The form array.
   */
  protected function buildItemsForm() {
    $items = [];
    $entities = $this->load();
    /** @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface[] $entities */
    foreach ($entities as $entity_id => $entity) {
      $definition = $entity->getPlugin()->getPluginDefinition();
      $items[$entity->getRegionId()][$entity->getSectionId()][$entity_id] = [
        'label' => $entity->label(),
        'entity_id' => $entity_id,
        'weight' => $entity->getWeight(),
        'entity' => $entity,
        'toolbar' => $entity->getToolbarId(),
        'region' => $entity->getRegionId(),
        'section' => $entity->getSectionId(),
        'category' => $definition['category'],
        'status' => $entity->status(),
      ];
    }

    $form = [];
    $regions = $this->exoToolbar->getRegions();
    if ($regions) {
      foreach ($regions as $region) {
        $region_id = $region->getPluginId();
        $region_items = $items[$region->getPluginId()] ?? [];
        $form[$region_id] = $this->buildRegionsForm($region, $region_items) + [
          '#type' => 'details',
          '#title' => $this->t('Region: %name', ['%name' => $region->label()]),
          '#open' => !empty($region_items),
        ];
      }
    }
    else {
      return [
        '#markup' => '<em>' . $this->t('No regions are currently enabled in this toolbar') . '</em>',
      ];
    }

    return $form;
  }

  /**
   * Build the "regions" portion of the form.
   *
   * @param Drupal\exo_toolbar\Plugin\ExoToolbarRegionPluginInterface $region
   *   The region.
   * @param array $items
   *   An array of region items.
   *
   * @return array
   *   The form array.
   */
  protected function buildRegionsForm(ExoToolbarRegionPluginInterface $region, array $items) {
    $form = [];

    foreach ($region->getSections() as $section) {
      /** @var \Drupal\exo_toolbar\ExoToolbarSectionInterface $section */
      $section_id = $section->id();
      $section_items = $items[$section_id] ?? [];
      $form[$section_id] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Section: %name', ['%name' => $section->label()]),
        'items' => $this->buildSectionForm($region, $section, $section_items),
      ];
      $form[$section_id]['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Place item in the %name', ['%name' => $section->label()]),
        '#url' => Url::fromRoute('entity.exo_toolbar.library', [
          'exo_toolbar' => $this->exoToolbar->id(),
          'exo_toolbar_region' => $region->getPluginId(),
        ], [
          'query' => [
            'section' => $section_id,
          ],
        ]),
        '#attributes' => [
          'class' => ['use-ajax', 'button', 'button--small'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 700,
          ]),
        ],
        '#weight' => -100,
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
    }

    return $form;
  }

  /**
   * Build the "sections" portion of the form.
   *
   * @param \Drupal\exo_toolbar\Plugin\ExoToolbarRegionPluginInterface $region
   *   The region.
   * @param \Drupal\exo_toolbar\ExoToolbarSectionInterface $section
   *   The section id.
   * @param array $items
   *   An array of region items.
   *
   * @return array
   *   The form array.
   */
  protected function buildSectionForm(ExoToolbarRegionPluginInterface $region, ExoToolbarSectionInterface $section, array $items) {
    if (empty($items)) {
      return [
        '#markup' => '<em>' . $this->t('No items in this section') . '</em>',
      ];
    }

    $weight_delta = round(count($items) / 2);
    $regions = $this->exoToolbar->getRegionCollection();
    $region_id = $region->getPluginId();
    $region_options = $this->exoToolbar->getRegionLabels();
    $region_section_options = [];
    $section_id = $section->id();
    foreach ($regions as $region) {
      /** @var /Drupal/exo_toolbar/Plugin/ExoToolbarRegionPluginInterface $region */
      foreach ($region->getSections() as $region_section) {
        $region_section_options[$region->getPluginId()][$region_section->id()] = $region_section->label();
      }
    }

    $form = [
      '#type' => 'table',
      '#header' => [
        $this->t('Item'),
        $this->t('Category'),
        $this->t('Region'),
        $this->t('Section'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#attributes' => [
        'id' => 'items-' . $region_id . '-' . $section_id,
      ],
    ];

    $form['#tabledrag'][] = [
      'table_id' => 'items-' . $region_id . '-' . $section_id,
      'action' => 'order',
      'relationship' => 'sibling',
      'group' => 'item-weight-' . $region_id . '-' . $section_id,
    ];

    foreach ($items as $info) {
      $entity_id = $info['entity_id'];

      $form[$entity_id] = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
      ];
      $form[$entity_id]['#attributes']['class'][] = $info['status'] ? 'item-enabled' : 'item-disabled';
      $form[$entity_id]['info'] = [
        '#plain_text' => $info['status'] ? $info['label'] : $this->t('@label (disabled)', ['@label' => $info['label']]),
        '#wrapper_attributes' => [
          'class' => ['item'],
        ],
      ];
      $form[$entity_id]['type'] = [
        '#markup' => $info['category'],
      ];
      $form[$entity_id]['region-toolbar']['region'] = [
        '#type' => 'select',
        '#default_value' => $region_id,
        '#required' => TRUE,
        '#title' => $this->t('Region for @item item', ['@item' => $info['label']]),
        '#title_display' => 'invisible',
        '#options' => $region_options,
        '#parents' => ['items', $entity_id, 'region'],
      ];
      $form[$entity_id]['region-toolbar']['toolbar'] = [
        '#type' => 'hidden',
        '#value' => $info['toolbar'],
        '#parents' => ['items', $entity_id, 'toolbar'],
      ];
      foreach ($region_section_options as $region_section_id => $sections) {
        $form[$entity_id]['sections'][$region_section_id] = [
          '#type' => 'select',
          '#default_value' => isset($sections[$info['section']]) ? $info['section'] : '',
          '#required' => TRUE,
          '#title' => $this->t('Section for @item item', ['@item' => $info['label']]),
          '#title_display' => 'invisible',
          '#options' => $sections,
          '#parents' => ['items', $entity_id, 'section', $region_section_id],
          '#states' => [
            'visible' => [
              ':input[name="items[' . $entity_id . '][region]"]' => ['value' => $region_section_id],
            ],
          ],
        ];
      }
      $form[$entity_id]['weight'] = [
        '#type' => 'weight',
        '#default_value' => $info['weight'],
        '#delta' => $weight_delta,
        '#title' => $this->t('Weight for @item item', ['@item' => $info['label']]),
        '#title_display' => 'invisible',
        '#attributes' => [
          'class' => ['item-weight-' . $region_id . '-' . $section_id],
        ],
      ];
      $form[$entity_id]['operations'] = $this->buildOperations($info['entity']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entities = $this->storage->loadMultiple(array_keys($form_state->getValue('items')));
    /** @var \Drupal\exo_toolbar\Entity\ExoToolbarInterface[] $entities */
    foreach ($entities as $entity_id => $entity) {
      $entity_values = $form_state->getValue(['items', $entity_id]);
      $entity->setWeight($entity_values['weight']);
      $entity->setRegion($entity_values['region']);
      $entity->setSection($entity_values['section'][$entity_values['region']]);
      $entity->save();
    }
    \Drupal::messenger()->addMessage(t('The item settings have been updated.'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Item');
    $header['toolbar'] = $this->t('Toolbar');
    $header['region'] = $this->t('Region');
    $header['section'] = $this->t('Section');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label']['data']['#markup'] = $this->t('@title <small>%machine</small>', [
      '@title' => $entity->label(),
      '%machine' => $entity->id(),
    ]);
    $row['toolbar'] = $entity->getToolbar()->label();
    $row['region'] = $entity->getRegion()->label();
    $row['section'] = $entity->getSectionLabel();
    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $build = [
      '#type' => 'operations',
      '#links' => $entity->getOperations(),
    ];

    return $build;
  }

}
