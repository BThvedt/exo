<?php

namespace Drupal\exo_toolbar\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\exo_toolbar\ExoToolbarRepositoryInterface;
use Drupal\exo\ExoSettingsInterface;
use Drupal\Core\Form\SubformState;
use Drupal\exo\Shared\ExoVisibilityFormTrait;

/**
 * Class ExoToolbarForm.
 */
class ExoToolbarForm extends EntityForm {
  use ExoVisibilityFormTrait;

  /**
   * The eXo toolbar repository.
   *
   * @var \Drupal\exo_toolbar\ExoToolbarRepositoryInterface
   */
  protected $exoToolbarRepository;

  /**
   * Drupal\exo\ExoSettingsInterface definition.
   *
   * @var \Drupal\exo\ExoSettingsInterface
   */
  protected $exoSettings;

  /**
   * Constructs a new ExoSettingsForm object.
   */
  public function __construct(
    ExoToolbarRepositoryInterface $exo_toolbar_repository,
    ExoSettingsInterface $exo_settings,
  ) {
    $this->exoToolbarRepository = $exo_toolbar_repository;
    $this->exoSettings = $exo_settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('exo_toolbar.repository'),
      $container->get('exo_toolbar.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $exo_toolbar = $this->entity;

    $form['#tree'] = TRUE;
    // Set parents due to SubformState issue.
    // @see https://www.drupal.org/project/drupal/issues/2798261
    $form['#parents'] = [];

    if ($exo_toolbar->isNew()) {
      $form['#title'] = $this->t('Add Toolbar');
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $exo_toolbar->label(),
      '#description' => $this->t("Label for the eXo Toolbar."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $exo_toolbar->id(),
      '#machine_name' => [
        'exists' => '\Drupal\exo_toolbar\Entity\ExoToolbar::load',
      ],
      '#disabled' => !$exo_toolbar->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#default_value' => $exo_toolbar->status(),
    ];

    $form['settings'] = [
      '#process' => ['::formProcess'],
    ];

    $form['visibility'] = $this->buildVisibilityInterface([], $form_state);
    return $form;
  }

  /**
   * Process the settings element and add settings form.
   */
  public function formProcess($element, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    $element += $this->entity->getExoSettings()->buildForm($element, $subform_state);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->entity->getExoSettings()->validateForm($form['settings'], $subform_state);
    $this->validateVisibility($form, $form_state);
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->entity->getExoSettings()->submitForm($form['settings'], $subform_state);
    $this->submitVisibility($form, $form_state);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $exo_toolbar = $this->entity;
    $status = $exo_toolbar->save();

    switch ($status) {
      case SAVED_NEW:
        \Drupal::messenger()->addMessage($this->t('Created the %label eXo Toolbar.', [
          '%label' => $exo_toolbar->label(),
        ]));
        break;

      default:
        \Drupal::messenger()->addMessage($this->t('Saved the %label eXo Toolbar.', [
          '%label' => $exo_toolbar->label(),
        ]));
    }
    $form_state->setRedirectUrl($exo_toolbar->toUrl('edit-form'));
  }

}
