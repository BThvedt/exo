<?php

namespace Drupal\exo_list_builder\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a generic Menu block.
 *
 * @Block(
 *   id = "exo_list_filter",
 *   admin_label = @Translation("List Filter"),
 *   category = @Translation("eXo Entity List"),
 *   deriver = "Drupal\exo_list_builder\Plugin\Derivative\ExoListFilterBlock",
 * )
 */
class ExoListFilterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The menu storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityListStorage;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new SystemMenuBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_list_storage
   *   The eXo entity list storage.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The entity form builder.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityStorageInterface $entity_list_storage, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityListStorage = $entity_list_storage;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('exo_entity_list'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $entity_list_id = ltrim($this->getDerivativeId(), 'exo_entity_list_');
    /** @var \Drupal\exo_list_builder\EntityListInterface $entity */
    $entity = $this->entityListStorage->load($entity_list_id);
    if (!$entity) {
      return $build;
    }
    $build['form'] = $this->formBuilder->getForm('\Drupal\exo_list_builder\Form\EntityListFilterForm', $entity);
    $build['#cache']['contexts'][] = 'url.query_args:' . $entity->getKey();
    return $build;
  }

}
