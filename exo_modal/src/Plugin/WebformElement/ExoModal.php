<?php

namespace Drupal\exo_modal\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\ContainerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'flexbox' element.
 *
 * @WebformElement(
 *   id = "exo_modal",
 *   default_key = "modal",
 *   label = @Translation("eXo Modal"),
 *   description = @Translation("Provides a eXo modal container."),
 *   category = @Translation("Containers"),
 * )
 */
class ExoModal extends ContainerBase {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);

    $instance->logger = $container->get('logger.factory')->get('webform');
    $instance->configFactory = $container->get('config.factory');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->elementInfo = $container->get('plugin.manager.element_info');
    $instance->elementManager = $container->get('plugin.manager.webform.element');
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->librariesManager = $container->get('webform.libraries_manager');
    $instance->exoModalSettings = $container->get('exo_modal.settings')->createInstance([]);
    $instance->exoModalGenerator = $container->get('exo_modal.generator');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return [
      // Title.
      'title_display' => '',
      'help_display' => '',
      // Modal.
      'exo_default' => FALSE,
      'exo_preset' => '',
    ] + $this->modalSettingPropertiesToWebformProperties($this->exoModalSettings->getDefaultSettings()) + parent::getDefaultProperties();
  }

  /**
   * {@inheritdoc}
   */
  protected function modalSettingPropertiesToWebformProperties($properties, $prefix = '') {
    $return = [];
    foreach ($properties as $key => $property) {
      if ($prefix) {
        $key = $prefix . '__' . $key;
      }
      if (is_array($property)) {
        $return += $this->modalSettingPropertiesToWebformProperties($property, $key);
      }
      else {
        $return[$key] = $property;
      }
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  protected function build($format, array &$element, WebformSubmissionInterface $webform_submission, array $options = []) {
    /** @var \Drupal\webform\WebformSubmissionViewBuilderInterface $view_builder */
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('webform_submission');
    return $view_builder->buildElements($element, $webform_submission, $options, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function preview() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['modal'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Modal'),
    ];
    // This is not working yet.
    $form['modal'] = $this->exoModalSettings->buildForm($form['modal'], $form_state);
    return $form;
  }

}
