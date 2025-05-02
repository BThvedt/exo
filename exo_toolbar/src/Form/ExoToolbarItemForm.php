<?php

namespace Drupal\exo_toolbar\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo\Shared\ExoVisibilityFormTrait;
use Drupal\Core\Form\SubformState;
use Drupal\exo_toolbar\Plugin\ExoToolbarItemPluginInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\exo_toolbar\Entity\ExoToolbarItemInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;

/**
 * Class ExoToolbarItemForm.
 */
class ExoToolbarItemForm extends EntityForm {
  use ExoVisibilityFormTrait;

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The plugin form manager.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface
   */
  protected $entity;

  /**
   * Constructs a BlockForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_manager
   *   The plugin form manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PluginFormFactoryInterface $plugin_form_manager) {
    $this->storage = $entity_type_manager->getStorage('exo_toolbar_item');
    $this->pluginFormFactory = $plugin_form_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin_form.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $entity = $this->entity;

    if ($entity->isNew()) {
      $form['#title'] = $this->t('Add Toolbar Item');
    }

    $form['#tree'] = TRUE;
    $form['settings'] = [];
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $subform_state->set('entity', $entity);
    $form['settings'] = $this->getPluginForm($entity->getPlugin())->buildConfigurationForm($form['settings'], $subform_state);

    $form['id'] = [
      '#type' => 'machine_name',
      '#maxlength' => 64,
      '#description' => $this->t('A unique name for this block instance. Must be alpha-numeric and underscore separated.'),
      '#default_value' => !$entity->isNew() ? $entity->id() : $this->getUniqueMachineName($entity),
      '#machine_name' => [
        'exists' => '\Drupal\exo_toolbar\Entity\ExoToolbarItem::load',
        'replace_pattern' => '[^a-z0-9_.]+',
        'source' => ['settings', 'title'],
      ],
      '#required' => TRUE,
      '#disabled' => !$entity->isNew(),
    ];

    if ($entity->getToolbarId()) {
      $form['toolbar'] = [
        '#type' => 'value',
        '#value' => $entity->getToolbarId(),
      ];
    }

    $entity_region = $entity->getRegionId();
    $region = $entity->isNew() ? $this->getRequest()->query->get('region', $entity_region) : $entity_region;
    $form['region'] = [
      '#type' => 'hidden',
      '#default_value' => $region,
    ];

    $entity_section = $entity->getSectionId();
    $section = $entity->isNew() ? $this->getRequest()->query->get('section', $entity_section) : $entity_section;
    $form['section'] = [
      '#type' => 'hidden',
      '#default_value' => $section,
    ];

    // Hidden weight setting.
    $weight = $entity->isNew() ? $entity->getToolbar()->getNextWeight($region, $section) : $entity->getWeight();
    $form['weight'] = [
      '#type' => 'hidden',
      '#default_value' => $weight,
    ];

    $form['visibility'] = $this->buildVisibilityInterface([], $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $form_state->setValue('weight', (int) $form_state->getValue('weight'));
    // The Block Entity form puts all block plugin form elements in the
    // settings form element, so just pass that to the block for validation.
    $this->getPluginForm($this->entity->getPlugin())->validateConfigurationForm($form['settings'], SubformState::createForSubform($form['settings'], $form, $form_state));
    $this->validateVisibility($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $entity = $this->entity;
    // The Block Entity form puts all block plugin form elements in the
    // settings form element, so just pass that to the block for submission.
    $sub_form_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    // Call the plugin submit handler.
    $block = $entity->getPlugin();
    $this->getPluginForm($block)->submitConfigurationForm($form['settings'], $sub_form_state);
    // If this block is context-aware, set the context mapping.
    if ($block instanceof ContextAwarePluginInterface && $block->getContextDefinitions()) {
      $context_mapping = $sub_form_state->getValue('context_mapping', []);
      $block->setContextMapping($context_mapping);
    }

    $this->submitVisibility($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    /** @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface $entity */
    $status = $entity->save();

    switch ($status) {
      case SAVED_NEW:
        \Drupal::messenger()->addMessage($this->t('Created the %label eXo Toolbar Item.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        \Drupal::messenger()->addMessage($this->t('Saved the %label eXo Toolbar Item.', [
          '%label' => $entity->label(),
        ]));
    }

    $form_state->setRedirect(
      'entity.exo_toolbar.edit_form', [
        'exo_toolbar' => $entity->getToolbarId(),
      ]
    );
  }

  /**
   * Generates a unique machine name for a block.
   *
   * @param \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface $item
   *   The item entity.
   *
   * @return string
   *   Returns the unique name.
   */
  public function getUniqueMachineName(ExoToolbarItemInterface $item) {
    $suggestion = $item->getPlugin()->getMachineNameSuggestion();

    // Get all the blocks which starts with the suggested machine name.
    $query = $this->storage->getQuery();
    $query->condition('id', $suggestion, 'CONTAINS');
    $item_ids = $query->accessCheck(FALSE)->execute();

    $item_ids = array_map(function ($item_id) {
      $parts = explode('.', $item_id);
      return end($parts);
    }, $item_ids);

    // Iterate through potential IDs until we get a new one. E.g.
    // 'plugin', 'plugin_2', 'plugin_3', etc.
    $count = 1;
    $machine_default = $suggestion;
    while (in_array($machine_default, $item_ids)) {
      $machine_default = $suggestion . '_' . ++$count;
    }
    return $machine_default;
  }

  /**
   * Retrieves the plugin form for a given item and operation.
   *
   * @param \Drupal\exo_toolbar\Plugin\ExoToolbarItemPluginInterface $item
   *   The item plugin.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   *   The plugin form for the item.
   */
  protected function getPluginForm(ExoToolbarItemPluginInterface $item) {
    if ($item instanceof PluginWithFormsInterface) {
      return $this->pluginFormFactory->createInstance($item, 'configure');
    }
    return $item;
  }

}
