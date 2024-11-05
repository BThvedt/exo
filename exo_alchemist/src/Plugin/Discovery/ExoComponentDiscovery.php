<?php

namespace Drupal\exo_alchemist\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Site\Settings;

/**
 * Allows YAML files to define plugin definitions.
 *
 * If the value of a key (like title) in the definition is translatable then
 * the addTranslatableProperty() method can be used to mark it as such and also
 * to add translation context. Then
 * \Drupal\Core\StringTranslation\TranslatableMarkup will be used to translate
 * the string and also to mark it safe. Only strings written in the YAML files
 * should be marked as safe, strings coming from dynamic plugin definitions
 * potentially containing user input should not.
 */
class ExoComponentDiscovery implements DiscoveryInterface {

  use DiscoveryTrait;

  /**
   * An array of directories to scan, keyed by the provider.
   *
   * @var array
   */
  protected $directories = [];

  /**
   * YAML file discovery and parsing handler.
   *
   * @var \Drupal\Core\Discovery\YamlDiscovery
   */
  protected $discovery;

  /**
   * Contains an array of translatable properties passed along to t().
   *
   * @var array
   */
  protected $translatableProperties = [];

  /**
   * Construct a YamlDiscovery object.
   *
   * @param array $directories
   *   An array of directories to scan.
   */
  public function __construct(array $directories) {
    $this->directories = $directories;
  }

  /**
   * Set one of the YAML values as being translatable.
   *
   * @param string $value_key
   *   The key corresponding to the value in the YAML that contains a
   *   translatable string.
   * @param string $context_key
   *   (Optional) the translation context for the value specified by the
   *   $value_key.
   *
   * @return $this
   */
  public function addTranslatableProperty($value_key, $context_key = '') {
    $this->translatableProperties[$value_key] = $context_key;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $plugins = $this->findAll();

    // Flatten definitions into what's expected from plugins.
    $definitions = [];
    foreach ($plugins as $provider => $list) {
      foreach ($list as $id => $definition) {
        // Add TranslatableMarkup.
        foreach ($this->translatableProperties as $property => $context_key) {
          if (isset($definition[$property])) {
            $options = [];
            // Move the t() context from the definition to the translation
            // wrapper.
            if ($context_key && isset($definition[$context_key])) {
              $options['context'] = $definition[$context_key];
              unset($definition[$context_key]);
            }
            $definition[$property] = new TranslatableMarkup($definition[$property], [], $options);
          }
        }
        // Add ID and provider.
        $definitions[$id] = $definition + [
          'provider' => $provider,
          'id' => $id,
        ];
      }
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function findAll() {
    $all = [];

    $root = \Drupal::root();

    // Traverse directories looking for pattern definitions.
    foreach ($this->directories as $provider => $directory) {
      $extend = [];
      foreach ($this->scanDirectory($directory) as $file_path => $file) {
        $absolute_path = dirname($file_path);
        $relative_path = str_replace($root, '', $absolute_path);
        $id = $file->name;
        $content = file_get_contents($file_path);
        $content = Yaml::decode($content);
        $content['id'] = ltrim(str_replace("-", "_", $provider . '_' . $id), "0..9_");
        $content['path'] = $relative_path;
        $content['absolute_path'] = $absolute_path;
        if (!empty($content['extend'])) {
          $content['extend_id'] = ltrim(str_replace("-", "_", $provider . '_' . $content['extend']), "0..9_");
          $extend[$id] = $content;
        }
        elseif ($definition = $this->buildDefinition($provider, $id, $content)) {
          $all[$provider][$definition['id']] = $definition;
        }
      }
      // Extend definitions.
      // - Extending will use the extended component and changes can be made.
      foreach ($extend as $id => $content) {
        if (isset($all[$provider][$content['extend_id']])) {
          $extend_definition = $all[$provider][$content['extend_id']];
          $content['template'] = $id;

          $definition = $this->buildDefinition($provider, $id, $content);
          $extend_definition['css'] = [];
          $extend_definition['js'] = [];
          $extend_definition['libraries'] = [];
          $definition = NestedArray::mergeDeep($extend_definition, $definition);

          if (!$this->templateExists($content, $content['path'], $content['absolute_path'], $id)) {
            $definition['template'] = $extend_definition['template'];
          }

          // Reset fields after merge.
          $definition['fields'] = $extend_definition['fields'] ?? [];
          if (!empty($content['fields'])) {
            foreach ($content['fields'] as $field_id => $field) {
              $definition['fields'][$field_id] = $definition['fields'][$field_id] ?? [];
              foreach ($field as $key => $val) {
                $definition['fields'][$field_id][$key] = $val;
              }
            }
          }
          foreach ($extend_definition['fields'] as $field_id => $field) {
            // Make sure type never changes.
            $definition['fields'][$field_id]['type'] = $field['type'];
            if (!in_array($field['type'], ['sequence'])) {
              // Reuse all extended fields except sequences.
              $definition['fields'][$field_id]['extend_id'] = $content['extend_id'];
            }
          }
          // Order fields by extended field order.
          $definition['fields'] = array_merge($content['fields'], $definition['fields']);
          $all[$provider][$definition['id']] = $definition;
        }
      }
    }

    return $all;
  }

  /**
   * Build a single definition.
   */
  private function buildDefinition($provider, $id, $content) {
    // Component definition needs to have some valid content.
    if (empty($content)) {
      return;
    }

    $absolute_path = $content['absolute_path'];
    // We do not the absolute path in the definition.
    unset($content['absolute_path']);
    $relative_path = $content['path'];

    // Skip component if overriden and set to ignore.
    if (isset($content['ignore']) && $content['ignore'] == TRUE) {
      return;
    }

    // We need a Twig file to have a valid component.
    if (empty($content['template']) && !$this->templateExists($content, $relative_path, $absolute_path, $id)) {
      return;
    }

    // Set component meta.
    $definition = $content;
    $definition['provider'] = $provider;
    $definition['name'] = $id;
    $definition['path'] = $relative_path;
    $definition['template'] = $content['template'] ?? $this->getTemplatePath($content, $relative_path, $absolute_path, $id);
    $definition['thumbnail'] = $this->getThumbnailPath($content, $relative_path, $absolute_path, $id);
    $definition['css'] = $this->getCss($content, $relative_path, $absolute_path, $id);
    $definition['js'] = $this->getJs($content, $relative_path, $absolute_path, $id);
    $definition['libraries'] = $content['libraries'] ?? [];
    return $definition;
  }

  /**
   * Check if template exists.
   */
  private function templateExists($content, $relative_path, $absolute_path, $id) {
    return !empty($this->getTemplatePath($content, $relative_path, $absolute_path, $id));
  }

  /**
   * Get template path.
   */
  private function getTemplatePath($content, $relative_path, $absolute_path, $id) {
    $glob_paths = glob($absolute_path . "/*" . ltrim($id, "0..9_-") . ".html.twig");
    $closest_template = array_shift($glob_paths);
    return $closest_template ? str_replace([
      $absolute_path . '/',
      '.html',
      '.twig',
    ], '', $closest_template) : NULL;
  }

  /**
   * Get template path.
   */
  private function getThumbnailPath($content, $relative_path, $absolute_path, $id) {
    // If a Twig template is explicitly defined, use that...
    if (isset($content['thumbnail'])) {
      // Strip out only the file name in case a path was provided in the use
      // value.
      $template_array = explode("/", $content['thumbnail']);
      $template_file = end($template_array);
      return $relative_path . "/" . $template_file;
    }
    // Next try an exact match for a template with the same name as the
    // pattern deifnition file.
    elseif (file_exists($absolute_path . "/" . $id . ".jpg")) {
      return $relative_path . "/" . $id . ".jpg";
    }
    elseif (file_exists($absolute_path . "/" . $id . ".png")) {
      return $relative_path . "/" . $id . ".png";
    }
    // Finally, look for a match that contains the id. This allows for a
    // template name that only differs by leading numbers for example.
    else {
      // Assuming here that the first match is our best option.
      $glob_paths = glob($absolute_path . "/*" . ltrim($id, "0..9_-") . ".*jpg");
      if ($glob_paths) {
        $closest_template = array_shift($glob_paths);
        return str_replace($absolute_path, $relative_path, $closest_template);
      }
      $glob_paths = glob($absolute_path . "/*" . ltrim($id, "0..9_-") . ".*png");
      if ($glob_paths) {
        $closest_template = array_shift($glob_paths);
        return str_replace($absolute_path, $relative_path, $closest_template);
      }
      else {
        return '/' . \Drupal::service('module_handler')->getModule('exo_alchemist')->getPath() . '/images/component-default.png';
      }
    }
  }

  /**
   * Get CSS paths.
   *
   * @return array
   *   An array of file paths.
   */
  private function getCss($content, $relative_path, $absolute_path, $id) {
    // If a Twig template is explicitly defined, use that...
    if (isset($content['css'])) {
      $files = [];
      foreach ((array) $content['css'] as $css) {
        $array = explode("/", $css);
        $file = end($array);
        $files[] = $relative_path . "/" . $file;
      }
      return $files;
    }
    // Next try an exact match for a template with the same name as the
    // pattern deifnition file.
    elseif (file_exists($absolute_path . "/" . $id . ".css")) {
      return [$relative_path . "/" . $id . ".css"];
    }
    return NULL;
  }

  /**
   * Get JS paths.
   *
   * @return array
   *   An array of file paths.
   */
  private function getJs($content, $relative_path, $absolute_path, $id) {
    // If a Twig template is explicitly defined, use that...
    if (isset($content['js'])) {
      $files = [];
      foreach ((array) $content['js'] as $js) {
        $array = explode("/", $js);
        $file = end($array);
        $files[] = $relative_path . "/" . $file;
      }
      return $files;
    }
    // Next try an exact match for a template with the same name as the
    // pattern deifnition file.
    elseif (file_exists($absolute_path . "/" . $id . ".js")) {
      return [$relative_path . "/" . $id . ".js"];
    }
    return NULL;
  }

  /**
   * Gets all ExoAlchemist registry files in a given directory.
   *
   * @param string $directory
   *   An extension's directory (i.e. /var/www/html/modules/foo).
   *
   * @return array
   *   An associative array (keyed by registry file) of objects with 'uri',
   *   'filename', and 'name' properties corresponding to the matched files.
   */
  protected function scanDirectory($directory) {
    $files = [];
    $directory .= '/components';
    if (file_exists($directory)) {
      $files = $this->fileScanDirectory($directory);
    }
    return $files;
  }

  /**
   * {@inheritdoc}
   */
  public function fileScanDirectory($directory) {
    $options = ['nomask' => $this->getNoMask()];
    $extensions = $this->getFileExtensions();
    $extensions = array_map('preg_quote', $extensions);
    $extensions = implode('|', $extensions);
    return \Drupal::service('file_system')->scanDirectory($directory, "/{$extensions}$/", $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getFileExtensions() {
    return [
      ".yml",
    ];
  }

  /**
   * Returns a regular expression for directories to be excluded in a file scan.
   *
   * @return string
   *   Regular expression.
   */
  protected function getNoMask() {
    $ignore = Settings::get('file_scan_ignore_directories', []);
    // We add 'tests' directory to the ones found in settings.
    $ignore[] = 'tests';
    array_walk($ignore, function (&$value) {
      $value = preg_quote($value, '/');
    });
    return '/^' . implode('|', $ignore) . '$/';
  }

}
