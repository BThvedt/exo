<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ExoAlchemistSettingsForm.
 */
class ExoAlchemistSettingsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'exo_alchemist.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_alchemist_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('exo_alchemist.settings');

    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        $options[$definition->id()] = $definition->getLabel();
      }
    }
    asort($options);
    $form['exposed_entity_type_fields'] = [
      '#type' => 'select',
      '#title' => $this->t('Expose Entity Fields'),
      '#description' => $this->t('Expose fields from these entities for use in components.'),
      '#multiple' => TRUE,
      '#options' => $options,
      '#default_value' => $config->get('exposed_entity_type_fields'),
    ];

    $form = parent::buildForm($form, $form_state);

    $form['actions']['entity_reference_revisions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Convert Fields for Revisions'),
      '#submit' => ['::submitRevisionConversion'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    Cache::invalidateTags(['exo_component_field_info']);

    $this->config('exo_alchemist.settings')
      ->set('exposed_entity_type_fields', array_filter($form_state->getValue('exposed_entity_type_fields')))
      ->save();
  }

  /**
   * Convert reference fields to reference revision fields.
   */
  public function submitRevisionConversion($form, FormStateInterface $form_state) {
    \Drupal::moduleHandler()->loadInclude('install', 'exo_alchemist');
    exo_alchemist_update_8005();
  }

}
