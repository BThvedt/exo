<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldDisplayFormTrait;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A 'webform' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "webform",
 *   label = @Translation("Webform"),
 *   provider = "webform",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class Webform extends EntityReferenceBase implements ContainerFactoryPluginInterface {
  use ExoComponentFieldDisplayFormTrait;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  protected $entityType = 'webform';

  /**
   * Creates a Webform instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
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
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    $field = $this->getFieldDefinition();
    // Backwards compatibility with old webform field.
    if ($field->hasAdditionalValue('webform_id')) {
      $field->setDefault([
        'target_id' => $field->getAdditionalValue('webform_id'),
      ]);
      $field->unsetAdditionalValue('target_id');
    }
    parent::processDefinition($field);
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageConfig() {
    return [
      'type' => 'webform',
      'settings' => [
        'target_type' => $this->getEntityType(),
        'auto_create' => FALSE,
        'default_data' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldConfig() {
    return [
      'settings' => [
        'handler' => 'default',
        'handler_settings' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetConfig() {
    return [
      'type' => 'webform_entity_reference_select',
      'settings' => [
        'default_data' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    return [
      'render' => $this->t('The rendered form.'),
    ] + parent::propertyInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    $value = parent::viewValue($item, $delta, $contexts);
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $this->getReferencedEntity($item, $contexts);
    if ($webform) {
      $element = $this->getWebformRenderable($webform);
      // Calculate the max-age based on the open/close data/time for the item
      // and webform.
      $max_age = 0;
      $states = ['open', 'close'];
      foreach ($states as $state) {
        if ($item->status === WebformInterface::STATUS_SCHEDULED) {
          $item_state = $item->$state;
          if ($item_state && strtotime($item_state) > time()) {
            $item_seconds = strtotime($item_state) - time();
            if (!$max_age && $item_seconds > $max_age) {
              $max_age = $item_seconds;
            }
          }
        }
        if ($webform->status() === WebformInterface::STATUS_SCHEDULED) {
          $webform_state = $webform->get($state);
          if ($webform_state && strtotime($webform_state) > time()) {
            $webform_seconds = strtotime($webform_state) - time();
            if (!$max_age && $webform_seconds > $max_age) {
              $max_age = $webform_seconds;
            }
          }
        }
      }

      if ($max_age) {
        $element['#cache']['max-age'] = $max_age;
      }
      $value['render'] = $element;
    }
    return $value;
  }

  /**
   * Get a renderable array for a webform.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   *
   * @return mixed
   *   A renderable element.
   */
  protected function getWebformRenderable(WebformInterface $webform, ?FieldItemInterface $item = NULL) {
    $element = [
      '#type' => 'webform',
      '#webform' => $webform,
      '#default_data' => [],
      '#entity' => $this->getParentEntity(),
    ];
    $this->renderer->addCacheableDependency($element, $webform);
    return $element;
  }

}
