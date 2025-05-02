<?php

namespace Drupal\exo_icon\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Class ExoIconEntityTypeForm.
 */
class ExoIconEntityTypeForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ExoIconEntityTypeForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'exo_icon.entity_types',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_icon_entity_type_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('exo_icon.entity_types');

    $entity_types = $this->entityTypeManager->getDefinitions();
    $form['types'] = [
      '#tree' => TRUE,
    ];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $form['types'][$entity_type_id] = [
          '#type' => 'exo_icon',
          '#title' => $entity_type->getLabel(),
          '#default_value' => exo_icon_entity_icon($entity_type),
          '#weight' => 0,
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('exo_icon.entity_types')
      ->setData($form_state->getValue('types'))
      ->save();
  }

}
