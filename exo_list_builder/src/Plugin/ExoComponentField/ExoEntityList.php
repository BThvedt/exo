<?php

namespace Drupal\exo_list_builder\Plugin\ExoComponentField;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\exo_alchemist\Command\ExoComponentCommand;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldComputedBase;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldDisplayFormTrait;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldPreviewEntityTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A 'view' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "exo_entity_list",
 *   label = @Translation("Entity List"),
 * )
 */
class ExoEntityList extends ExoComponentFieldComputedBase implements ContainerFactoryPluginInterface {

  use ExoComponentFieldDisplayFormTrait;
  use ExoComponentFieldPreviewEntityTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    parent::processDefinition();
    $field = $this->getFieldDefinition();
    if (!$field->hasAdditionalValue('exo_entity_list_id')) {
      throw new PluginException(sprintf('eXo Component Field plugin (%s) requires [view_id] be set.', $field->getType()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = [
      'render' => $this->t('The entity list renderable.'),
      'page' => $this->t('The entity list page.'),
      'count' => $this->t('The entity list result count. (Requires: exo_entity_list_count: true)'),
    ];
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(ContentEntityInterface $entity, array $contexts) {
    $value = [];
    $field = $this->getFieldDefinition();
    $render = [];
    /** @var \Drupal\exo_list_builder\EntityListInterface $entity */
    $entity = $this->entityTypeManager->getStorage('exo_entity_list')->load($field->getAdditionalValue('exo_entity_list_id'));
    if ($entity) {
      $handler = $entity->getHandler();
      if ($this->isLayoutBuilder($contexts)) {
        $render = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity);
        $render = $this->getFormAsPlaceholder($render);
      }
      else {
        $render = [
          '#type' => 'exo_entity_list',
          '#entity_list' => $entity,
        ];
      }
      if ($field->hasAdditionalValue('exo_entity_list_hide_if_empty') && !$handler->isFiltered() && empty($handler->getTotal())) {
        $value['#component_access'] = FALSE;
      }
      $value['render'] = $render;
      $value['page'] = $handler->getOption('page');
      if ($field->hasAdditionalValue('exo_entity_list_count')) {
        $value['count'] = $handler->getTotal();
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildCommand(ExoComponentCommand $command, array &$data) {
    $data['exo_entity_list_id'] = $command->getIo()->ask(
      t('eXo Entity List ID'),
      NULL
    );
  }

}
