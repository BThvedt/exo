<?php

namespace Drupal\exo_alchemist;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the layout_builder.tempstore_repository service.
 */
class ExoAlchemistServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('layout_builder.tempstore_repository')) {
      $definition = $container->getDefinition('layout_builder.tempstore_repository');
      $definition->setClass('Drupal\exo_alchemist\ExoAlchemistLayoutTempstoreRepository');
    }
  }

}
