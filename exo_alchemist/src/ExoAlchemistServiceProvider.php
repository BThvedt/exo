<?php

namespace Drupal\exo_alchemist;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

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
    // Replace core's inline block dependency subscriber with the eXo version so
    // there is a single subscriber that (a) resolves dependencies for nested
    // eXo component blocks via the parent reference chain and (b) guards against
    // corrupt inline_block_usage rows (NULL layout entity) that would otherwise
    // make core's unguarded subscriber call getStorage(NULL) and throw.
    if ($container->hasDefinition('layout_builder.get_block_dependency_subscriber')) {
      $definition = $container->getDefinition('layout_builder.get_block_dependency_subscriber');
      $definition->setClass('Drupal\exo_alchemist\EventSubscriber\ExoComponentSetInlineBlockDependency');
      $definition->setArguments([
        new Reference('entity_type.manager'),
        new Reference('database'),
        new Reference('inline_block.usage'),
        new Reference('plugin.manager.layout_builder.section_storage'),
        new Reference('plugin.manager.exo_component'),
        new Reference('entity_field.manager'),
      ]);
    }
  }

}
