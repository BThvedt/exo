<?php

namespace Drupal\exo_toolbar\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves block plugin definitions for all toolbar region items.
 */
class ExoToolbarRegion extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The toolbar region item storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $exoToolbarItemStorage;

  /**
   * Constructs a ExoToolbarRegion object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $exo_toolbar_item_storage
   *   The toolbar item storage.
   */
  public function __construct(EntityStorageInterface $exo_toolbar_item_storage) {
    $this->exoToolbarItemStorage = $exo_toolbar_item_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('exo_toolbar_item')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /** @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface[] $exo_toolbar_items */
    $exo_toolbar_items = $this->exoToolbarItemStorage->loadByProperties(['plugin' => 'region']);
    // Reset the discovered definitions.
    $this->derivatives = [];
    foreach ($exo_toolbar_items as $exo_toolbar_item) {
      $this->derivatives[$exo_toolbar_item->id()] = $base_plugin_definition;
      $this->derivatives[$exo_toolbar_item->id()]['admin_label'] = $exo_toolbar_item->label();
      $this->derivatives[$exo_toolbar_item->id()]['id'] = 'item:' . $exo_toolbar_item->id();
      $this->derivatives[$exo_toolbar_item->id()]['no_ui'] = TRUE;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
