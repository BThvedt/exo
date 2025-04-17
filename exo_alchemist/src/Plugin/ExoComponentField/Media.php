<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\exo_alchemist\ExoComponentFieldManager;
use Drupal\exo_alchemist\ExoComponentValue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A 'media' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "media",
 *   label = @Translation("Media"),
 *   provider = "media",
 * )
 */
class Media extends MediaBase implements ContainerFactoryPluginInterface {

  /**
   * The eXo component field manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentFieldManager
   */
  protected $exoComponentFieldManager;

  /**
   * Constructs a LocalActionDefault object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\exo_alchemist\ExoComponentFieldManager $exo_component_field_manager
   *   The exo component field manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ExoComponentFieldManager $exo_component_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->exoComponentFieldManager = $exo_component_field_manager;
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
      $container->get('plugin.manager.exo_component_field')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    parent::processDefinition();
    $field = $this->getFieldDefinition();
    $defaults = [];
    foreach ($field->getDefaults() as $delta => $default) {
      $component_field_id = 'media_' . $default->getValue('bundle');
      if ($component_field = $this->exoComponentFieldManager->createInstance($component_field_id, $this->configuration)) {
        $temp_field = clone $field;
        $temp_field->setType($component_field_id);
        $temp_field->setDefaults([$default->toArray()]);
        $temp_field->setAdditionalValue('bundles', [$default->getValue('bundle')]);
        $temp_field->setAdditionalValue('entity_type', 'media');
        $component_field->processDefinition($temp_field);
        $defaults[] = $temp_field->getDefaults()[0]->toArray();
      }
    }
    $field->setDefaults($defaults);
  }

  /**
   * {@inheritdoc}
   */
  public function validateValue(ExoComponentValue $value) {
    parent::validateValue($value);
    $component_field_id = 'media_' . $value->get('bundle');
    if ($component_field = $this->exoComponentFieldManager->createInstance($component_field_id, $this->configuration)) {
      /** @var \Drupal\exo_alchemist\Plugin\ExoComponentField\MediaBase $component_field */
      $component_field->validateValue($value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    $output = [];
    if ($item->entity) {
      $component_field_id = 'media_' . $item->entity->bundle();
      if ($component_field = $this->exoComponentFieldManager->createInstance($component_field_id, $this->configuration)) {
        /** @var \Drupal\exo_alchemist\Plugin\ExoComponentField\MediaBase $component_field */
        $media = $item->entity;
        $output = $component_field->viewValue($item, 0, $contexts) + ['bundle' => $media->bundle()];
      }
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = [
      'bundle' => $this->t('The bundle type.'),
    ];
    $component_field_manager = \Drupal::service('plugin.manager.exo_component_field');
    foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple($this->getEntityTypeBundles()) as $bundle => $media_type) {
      $component_field_id = 'media_' . $bundle;
      if ($component_field = $component_field_manager->createInstance($component_field_id, $this->configuration)) {
        $properties += array_map(function ($description) use ($media_type) {
          return $media_type->label() . ': ' . $description;
        }, $component_field->propertyInfo());
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  protected function getValueEntity(ExoComponentValue $value, ?FieldItemInterface $item = NULL) {
    $entity = NULL;
    $component_field_id = 'media_' . $value->get('bundle');
    if ($component_field = $this->exoComponentFieldManager->createInstance($component_field_id, $this->configuration)) {
      /** @var \Drupal\exo_alchemist\Plugin\ExoComponentField\MediaBase $component_field */
      $entity = $component_field->getValueEntity($value, $item);
    }
    return $entity;
  }

}
