<?php

namespace Drupal\exo_list_builder;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * Provides the Field type export plugin manager.
 */
class ExoListManager extends DefaultPluginManager implements ExoListManagerInterface {

  /**
   * The plugin type.
   *
   * @var string
   */
  protected $type;

  /**
   * {@inheritDoc}
   */
  protected $defaults = [
    'weight' => 0,
  ];

  /**
   * Constructs a new ExoListManager object.
   *
   * @param string $type
   *   The plugin type, for example element or filter.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct($type, \Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->type = $type;
    $camel = Container::camelize($type);
    $interface = 'Drupal\exo_list_builder\Plugin\ExoList' . $camel . 'Interface';
    $annotation = 'Drupal\exo_list_builder\Annotation\ExoList' . $camel;
    parent::__construct('Plugin/ExoList/' . $camel, $namespaces, $module_handler, $interface, $annotation);

    $this->alterInfo('exo_list_builder_' . $type . '_info');
    $this->setCacheBackend($cache_backend, 'exo_list_builder_' . $type . '_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    $options = [];
    $definitions = $this->getDefinitions();
    $this->sortDefinitions($definitions);
    foreach ($definitions as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldOptions($field_type, $entity_type = NULL, ?array $bundles = NULL, $field_name = NULL) {
    $options = [];
    $definitions = $this->getDefinitions();
    $this->sortDefinitions($definitions);
    foreach ($definitions as $plugin_id => $definition) {
      if (!isset($definition['field_type'])) {
        continue;
      }
      if ($field_type === 'config' && !in_array($field_type, $definition['field_type'])) {
        continue;
      }
      if (in_array($field_type, $definition['field_type']) || empty($definition['field_type'])) {
        if ($entity_type) {
          if (!empty($definition['entity_type']) && !in_array($entity_type, $definition['entity_type'])) {
            continue;
          }
        }
        if ($bundles) {
          if (!empty($definition['bundle'])) {
            $found = FALSE;
            foreach ($definition['bundle'] as $bundle_id) {
              if (in_array($bundle_id, $bundles)) {
                $found = TRUE;
              }
            }
            if (!$found) {
              continue;
            }
          }
        }
        if ($field_name) {
          if (!empty($definition['field_name']) && !in_array($field_name, $definition['field_name'])) {
            continue;
          }
        }
        if (isset($definition['exclusive']) && $definition['exclusive'] === TRUE) {
          $exclusive = [$plugin_id => $definition['label']];
          if (isset($definitions['_hidden'])) {
            $exclusive = ['_hidden' => $definitions['_hidden']['label']] + $exclusive;
          }
          return $exclusive;
        }
        if ($field_type === 'custom') {
          if ($field_name && !in_array($field_name, $definition['field_name'])) {
            continue;
          }
        }
        $options[$plugin_id] = $definition['label'];
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function removeExcludeDefinitions(array $definitions) {
    $definitions = $definitions ?? $this->getDefinitions();
    // Exclude 'broken' fallback plugin.
    unset($definitions['broken']);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    return 'broken';
  }

  /**
   * Sort definitions by weigth descending.
   *
   * @param array $definitions
   *   The definitions.
   */
  protected function sortDefinitions(array &$definitions) {
    uasort($definitions, function ($a, $b) {
      return $a['weight'] - $b['weight'];
    });
  }

}
