<?php

namespace Drupal\exo_icon;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Provides the default exo_icon manager.
 */
class ExoIconManager extends DefaultPluginManager implements ExoIconManagerInterface {

  /**
   * Icon matches.
   *
   * @var array
   */
  protected $matches = [];

  /**
   * Provides default values for all exo_icon plugins.
   *
   * @var array
   */
  protected $defaults = [
    'text' => '',
    'regex' => '',
    'smart' => '',
    'icon' => '',
    'prefix' => [],
    'weight' => 0,
  ];

  /**
   * Constructs a new ExoIconManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    // Add more services as required.
    $this->moduleHandler = $module_handler;
    $this->alterInfo('exo_icon_info');
    $this->setCacheBackend($cache_backend, 'exo_icon', ['exo_icon']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('icons', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    uasort($definitions, ['Drupal\Component\Utility\SortArray', 'sortByWeightProperty']);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    if (empty($definition['id'])) {
      throw new PluginException(sprintf('eXo Icon property (%s) definition "id" is required.', $plugin_id));
    }

    // Core uses *.icons.yml convention, which has a different structure.
    if (empty($definition['icon'])) {
      return;
    }

    if (!empty($definition['smart'])) {
      $text = $definition['smart'];
      $texts = $text . 's';
      $definition['regex'] = "^$text$|^$texts$|^$text | $text$";
      $definition['weight'] = $definition['weight'] ?? 10;
    }

    if (empty($definition['text']) && empty($definition['regex'])) {
      throw new PluginException(sprintf('eXo Icon property (%s) definition "text" or "regex" is required.', $plugin_id));
    }

    if (is_string($definition['prefix'])) {
      $definition['prefix'] = [$definition['prefix']];
    }

    if (!is_array($definition['prefix'])) {
      throw new PluginException(sprintf('eXo Icon property (%s) definition "prefix" should be an array of strings.', $plugin_id));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionsWithPrefix($prefixes = []) {
    $definitions = $this->getDefinitions();
    if (!is_array($prefixes)) {
      $prefixes = [$prefixes];
    }
    if (empty($prefixes)) {
      return $definitions;
    }
    $prefixed_definitions = [];
    if (!empty($prefixes)) {
      foreach ($prefixes as $prefix) {
        foreach ($definitions as $key => $definition) {
          if (in_array($prefix, $definition['prefix'])) {
            $prefixed_definitions[$key] = $definition;
          }
        }
      }
      foreach ($definitions as $key => $definition) {
        if (!empty($definition['prefix']) && empty(array_intersect($definition['prefix'], $prefixes))) {
          unset($definitions[$key]);
        }
      }
    }
    return $prefixed_definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionMatch($string, $prefixes = []) {
    $key = strtolower(implode('_', array_merge([
      (string) $string . '_',
    ], $prefixes)));
    if (!isset($this->matches[$key])) {
      $definitions = $this->getDefinitionsWithPrefix($prefixes);
      $string = strtolower($string);
      $icon_id = NULL;
      // Check for exact string matches first.
      foreach ($definitions as $definition) {
        if ($definition['text'] && $definition['text'] == $string) {
          $icon_id = $definition['icon'];
          break;
        }
      }
      if (!$icon_id) {
        // Check for regex exact string matches second.
        foreach ($definitions as $definition) {
          if ($definition['regex'] && preg_match('!' . $definition['regex'] . '!', $string)) {
            $icon_id = $definition['icon'];
            break;
          }
        }
      }
      $this->matches[$key] = $icon_id;
    }
    return $this->matches[$key];
  }

}
