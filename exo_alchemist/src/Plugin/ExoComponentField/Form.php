<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldComputedBase;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldBuildFormTrait;

/**
 * A 'form' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "form",
 *   label = @Translation("Form"),
 * )
 */
class Form extends ExoComponentFieldComputedBase implements ContainerFactoryPluginInterface {

  use ExoComponentFieldBuildFormTrait;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The form.
   *
   * @var [type]
   */
  protected $form;

  /**
   * Creates a PageTitle instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    parent::processDefinition();
    $field = $this->getFieldDefinition();
    if (!$field->hasAdditionalValue('form_class')) {
      throw new PluginException(sprintf('eXo Component Field plugin (%s) requires [form_class] be set.', $field->getType()));
    }
    if (!$field->hasAdditionalValue('form_args')) {
      $field->setAdditionalValue('form_args', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = $this->propertyInfoBuildForm() + parent::propertyInfo();
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(ContentEntityInterface $entity, array $contexts) {
    $value = $this->viewValueBuildForm($entity, $contexts) + parent::viewValue($entity, $contexts);
    return $value;
  }

  /**
   * Get a form class.
   */
  protected function viewValueForm(?EntityInterface $entity = NULL, ?array $contexts = NULL) {
    $field = $this->getFieldDefinition();
    $args = array_merge([
      $field->getAdditionalValue('form_class'),
    ], $field->getAdditionalValue('form_args'));
    return call_user_func_array([$this->formBuilder, 'getForm'], $args);
  }

}
