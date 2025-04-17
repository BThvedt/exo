<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\exo_alchemist\Command\ExoComponentCommand;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldBuildFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A 'field' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "entity_form",
 *   label = @Translation("Entity Form"),
 * )
 */
class EntityForm extends EntityReferenceBase implements ContainerFactoryPluginInterface {

  use ExoComponentFieldBuildFormTrait;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

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
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->entityFormBuilder = $entity_form_builder;
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
      $container->get('entity.form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    parent::processDefinition();
    $field = $this->getFieldDefinition();
    if (!$field->hasAdditionalValue('entity_operation')) {
      $field->setAdditionalValue('entity_operation', 'default');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldConfig() {
    $config = [
      'settings' => [
        'handler' => 'default',
      ],
    ];
    $entity_definition = $this->entityTypeManager()->getDefinition($this->getEntityType());
    if ($entity_definition->hasKey('bundle')) {
      $config['settings']['handler_settings']['target_bundles'] = array_combine($this->getEntityTypeBundles(), $this->getEntityTypeBundles());
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    return parent::propertyInfo() + [
      'form.field.*' => $this->t('Entity form field.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    $entity = $this->getReferencedEntity($item, $contexts);
    if ($bundle_of = $entity->getEntityType()->getBundleOf()) {
      $entity = $this->getNewEntity($bundle_of, $entity->id());
    }
    $value = NestedArray::mergeDeep($this->viewValueBuildForm($entity, $contexts), parent::viewValue($item, $delta, $contexts));
    return $value;
  }

  /**
   * Get a form class.
   */
  protected function viewValueForm(?EntityInterface $entity = NULL, ?array $contexts = NULL) {
    $field = $this->getFieldDefinition();
    $form = $this->entityFormBuilder->getForm($entity, $field->getAdditionalValue('entity_operation'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildCommand(ExoComponentCommand $command, array &$data) {
    $entity_types = \Drupal::entityTypeManager()->getDefinitions();
    ksort($entity_types);
    $data['entity_type'] = $command->getIo()->choice(
      t('Entity Type'),
      array_keys($entity_types)
    );

    $bundless = \Drupal::service('entity_type.bundle.info')->getBundleInfo($data['entity_type']);
    $data['bundles'] = $command->getIo()->choiceNoList(
      t('Bundles'),
      array_keys($bundless),
      NULL,
      TRUE
    );
  }

}
