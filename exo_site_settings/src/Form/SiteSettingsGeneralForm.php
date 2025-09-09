<?php

namespace Drupal\exo_site_settings\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\exo\ExoNestedEntityFormTrait;

/**
 * Aggregated settings form.
 */
class SiteSettingsGeneralForm extends FormBase {

  use ExoNestedEntityFormTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * Subclasses should use the self::config() method, which may be overridden to
   * address specific needs when loading config, rather than this property
   * directly. See \Drupal\Core\Form\ConfigFormBase::config() for an example of
   * this.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The setting entities.
   *
   * @var \Drupal\exo_site_settings\SiteSettingsInterface[]
   */
  protected $entities = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $exo_site_settings_storage = $this->entityTypeManager->getStorage('exo_site_settings');
    /** @var \Drupal\exo_site_settings\SiteSettingsStorage $exo_site_settings_storage */
    foreach ($this->entityTypeManager->getStorage('exo_site_settings_type')->loadMultiple() as $exo_site_settings_type) {
      /** @var \Drupal\exo_site_settings\Entity\SiteSettingsTypeInterface $exo_site_settings_type */
      if (($exo_site_settings_type->isAggregate() || $exo_site_settings_type->id() == 'general') && $exo_site_settings_type->access('page_update')) {
        $exo_site_settings = $exo_site_settings_storage->loadOrCreateByType($exo_site_settings_type->id());
        if ($exo_site_settings) {
          $this->entities[$exo_site_settings_type->id()] = $exo_site_settings;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_settings_aggregate_form';
  }

  /**
   * {@inheritdoc}
   *
   * The build form needs to take care of the following:
   *   - Creating a custom form state object for each inner form (and keep it
   *     inside the main form state.
   *   - Generating a render array for each inner form.
   *   - Handle compatibility issues such as #process array and action elements.
   *
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $innerForms = [];
    foreach ($this->entities as $entity) {
      $innerForm = $this->createInnerForm([$entity->id()], $entity->getEntityTypeId(), $entity->bundle(), 'default', $entity);
      if ($innerForm) {
        $innerForms[$entity->id()] = [
          '#type' => 'container',
          '#title' => $entity->label(),
          'form' => $this->buildInnerForm($innerForm, $form_state, $form),
        ];
      }
    }

    if (!empty($innerForms)) {
      if (count($this->entities) > 1) {
        $form['tabs'] = [
          '#type' => 'vertical_tabs',
        ];
        foreach ($innerForms as &$innerForm) {
          $innerForm['#type'] = 'details';
          $innerForm['#group'] = 'tabs';
        }
      }
      $form += $innerForms;
    }

    // Default action elements.
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
        '#submit' => ['::submitForm'],
      ],
    ];

    // Handle copyright.
    if (!empty($form['general']['form']['field_copyright'])) {
      $form['general']['form']['field_copyright']['widget'][0]['value']['#field_prefix'] = '&copy; ' . date('Y');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->entities as $entity) {
      if (isset($form[$entity->id()])) {
        $this->validateInnerForm($form[$entity->id()]['form'], $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      return;
    }
    foreach ($this->entities as $entity) {
      if (isset($form[$entity->id()])) {
        $entity = $this->submitInnerForm($form[$entity->id()]['form'], $form_state);
        $entity->save();
      }
    }
    $this->messenger()->addStatus($this->t('The site settings have been updated.'));
    $form_state->setRedirect('entity.exo_site_settings.collection');
  }

}
