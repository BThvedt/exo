<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\exo_alchemist\Command\ExoComponentCommand;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldComputedBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Template\Attribute;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A 'view' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "entity_query",
 *   label = @Translation("Entity Query"),
 * )
 */
class EntityQuery extends ExoComponentFieldComputedBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new FieldBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
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
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    parent::processDefinition();
    $field = $this->getFieldDefinition();
    if (!$field->hasAdditionalValue('entity_type')) {
      throw new PluginException(sprintf('eXo Component Field plugin (%s) requires [entity_type] be set.', $field->getType()));
    }
    if (!$field->hasAdditionalValue('query_limit')) {
      $field->setAdditionalValue('query_limit', 10);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $field = $this->getFieldDefinition();
    $prefix = 'entities.%.';
    if ($field->getAdditionalValue('query_limit') == 1) {
      $prefix = '';
    }
    $properties = [
      $prefix . 'attributes' => $this->t('The entity attributes.'),
      $prefix . 'entity' => $this->t('The entity object.'),
      $prefix . 'entity_id' => $this->t('The entity id.'),
      $prefix . 'entity_uuid' => $this->t('The entity uuid.'),
      $prefix . 'entity_label' => $this->t('The entity label.'),
      $prefix . 'entity_view_url' => $this->t('The entity canonical url.'),
      $prefix . 'entity_edit_url' => $this->t('The entity edit url.'),
    ];
    if ($this->getFieldDefinition()->getAdditionalValue('view_mode')) {
      $properties[$prefix . 'render'] = $this->t('The rendered entity.');
    }
    if ($this->getFieldDefinition()->getAdditionalValue('field_view_mode')) {
      foreach ($this->getEntityFields() as $field_name => $field) {
        $properties[$prefix . 'field.' . $field_name . '.render'] = $this->t('The rendered entity for %label.', [
          '%label' => $field->getName(),
        ]);
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(ContentEntityInterface $entity, array $contexts) {
    $value = [
      'entities' => [],
    ];
    $field = $this->getFieldDefinition();

    $entity_type = $this->getEntityType();
    $bundles = $this->getEntityTypeBundles();
    $view_mode = $field->getAdditionalValue('view_mode');
    $field_view_mode = $this->getFieldDefinition()->getAdditionalValue('field_view_mode');
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $query = $storage->getQuery();
    $query->accessCheck(TRUE);

    if (!empty($bundles)) {
      $type = $this->entityTypeManager->getDefinition($entity_type);
      $bundles_key = $type->getKey('bundle');
      $query->condition($bundles_key, array_values($bundles), 'IN');
    }

    if ($limit = $field->getAdditionalValue('query_limit')) {
      $query->range(0, $limit);
    }

    if ($conditions = $field->getAdditionalValue('query_condition')) {
      foreach ($conditions as $key => $condition) {
        $operator = $condition['operator'] ?? NULL;
        $query->condition($condition['field'], $condition['value'], $operator);
      }
    }

    if ($sort = $field->getAdditionalValue('query_sort')) {
      foreach ($sort as $key => $direction) {
        $query->sort($key, $direction);
      }
    }

    if ($tags = $field->getAdditionalValue('query_tag')) {
      foreach ($tags as $key => $tag) {
        $query->addTag($tag);
      }
    }

    $results = $query->execute();
    if (!empty($results)) {
      foreach ($storage->loadMultiple($results) as $entity) {
        $contexts['cacheable_metadata']->addCacheableDependency($entity);
        $value['entities'][$entity->id()]['attributes'] = new Attribute([
          'class' => [
            'type--' . Html::getClass($entity->getEntityTypeId()),
            'id--' . $entity->id(),
          ],
        ]);
        $value['entities'][$entity->id()]['entity'] = $entity;
        $value['entities'][$entity->id()]['entity_label'] = $entity->label();
        $value['entities'][$entity->id()]['entity_id'] = $entity->id() ? $entity->id() : $entity->uuid();
        $value['entities'][$entity->id()]['entity_uuid'] = $entity->uuid();
        if (!$entity->isNew()) {
          if ($entity->hasLinkTemplate('canonical')) {
            $value['entities'][$entity->id()]['entity_view_url'] = $entity->toUrl()->toString();
          }
          if ($entity->hasLinkTemplate('edit-form')) {
            $value['entities'][$entity->id()]['entity_edit_url'] = $entity->toUrl('edit-form')->toString();
          }
        }
        if ($view_mode) {
          $value['entities'][$entity->id()]['render'] = $this->entityTypeManager->getViewBuilder($entity_type)->view($entity, $view_mode);
          $value['entities'][$entity->id()]['render']['#exo_hide_title'] = TRUE;
        }
        if ($field_view_mode && $entity instanceof FieldableEntityInterface) {
          foreach ($this->getEntityFields() as $field_name => $field) {
            if ($entity->hasField($field_name)) {
              $value['entities'][$entity->id()]['field'][$field_name]['render'] = $entity->get($field_name)->view($field_view_mode);
            }
          }
        }
      }
    }

    if ($field->getAdditionalValue('query_limit') == 1) {
      $value = reset($value['entities']);
    }

    return $value;
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

    $data['query_limit'] = $command->getIo()->ask(
      t('Result Count (0 for unlimited)'),
      '10'
    );
  }

  /**
   * Get the entity type.
   *
   * @return string
   *   The entity type.
   */
  protected function getEntityType() {
    return $this->getFieldDefinition()->getAdditionalValue('entity_type');
  }

  /**
   * Get the entity type.
   *
   * @return array
   *   An array of support bundles.
   */
  protected function getEntityTypeBundles() {
    $bundles = $this->getFieldDefinition()->getAdditionalValue('bundles');
    if (empty($bundles)) {
      $bundles = $this->getFieldDefinition()->getAdditionalValue('bundle');
    }
    if (empty($bundles)) {
      return [];
    }
    return is_array($bundles) ? $bundles : [$bundles => $bundles];
  }

  /**
   * Get the entity fields.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The field definitions.
   */
  protected function getEntityFields() {
    $entity_type = $this->getEntityType();
    $bundles = $this->getEntityTypeBundles();
    $fields = [];
    foreach ($bundles as $bundle) {
      foreach ($this->entityFieldManager->getFieldDefinitions($entity_type, $bundle) as $field_name => $field) {
        if ($field->isDisplayConfigurable('view')) {
          $fields[$field_name] = $field;
        }
      }
    }
    return $fields;
  }

}
