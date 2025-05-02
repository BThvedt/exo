<?php

namespace Drupal\exo_icon\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\exo_config_file\Entity\ExoConfigFileInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Defines the eXo Icon Package entity.
 *
 * @ConfigEntityType(
 *   id = "exo_icon_package",
 *   label = @Translation("eXo Icon Package"),
 *   handlers = {
 *     "storage" = "Drupal\exo_icon\ExoIconPackageStorage",
 *     "view_builder" = "Drupal\exo_icon\ExoIconPackageViewBuilder",
 *     "list_builder" = "Drupal\exo_icon\ExoIconPackageListBuilder",
 *     "form" = {
 *       "add" = "Drupal\exo_icon\Form\ExoIconPackageForm",
 *       "edit" = "Drupal\exo_icon\Form\ExoIconPackageForm",
 *       "delete" = "Drupal\exo_icon\Form\ExoIconPackageDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\exo_icon\ExoIconPackageHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "exo_icon_package",
 *   admin_permission = "administer exo icon",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "global",
 *     "path",
 *     "type",
 *     "weight",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/exo/icons/package/{exo_icon_package}",
 *     "add-form" = "/admin/config/exo/icons/package/add",
 *     "edit-form" = "/admin/config/exo/icons/package/{exo_icon_package}/edit",
 *     "delete-form" = "/admin/config/exo/icons/package/{exo_icon_package}/delete",
 *     "collection" = "/admin/config/exo/icons"
 *   }
 * )
 */
class ExoIconPackage extends ConfigEntityBase implements ExoIconPackageInterface {

  /**
   * The eXo Icon Package ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The eXo Icon Package label.
   *
   * @var string
   */
  protected $label;

  /**
   * The path to the icon package.
   *
   * @var string
   */
  protected $path;

  /**
   * The icon type. Either 'font' or 'image'.
   *
   * @var string
   */
  protected $type;

  /**
   * The weight of this toolbar in relation to other toolbars.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The icon definitions.
   *
   * @var array
   */
  protected $iconDefinitions = [];

  /**
   * The icons.
   *
   * @var array
   */
  protected $icons = [];

  /**
   * The eXo icon repository.
   *
   * @var \Drupal\exo_icon\ExoIconRepositoryInterface
   */
  protected $exoIconRepository;

  /**
   * {@inheritdoc}
   */
  public function getIconId() {
    return 'icon-' . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  protected function setType($type) {
    $this->type = $type == 'image' ? 'image' : 'font';
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isGlobal() {
    return (bool) $this->get('global');
  }

  /**
   * {@inheritdoc}
   */
  public function isSvg() {
    return $this->getType() == 'image';
  }

  /**
   * {@inheritdoc}
   */
  public function isFont() {
    return $this->getType() == 'font';
  }

  /**
   * {@inheritdoc}
   */
  public function getStylesheet() {
    $path = $this->getPath() . '/style.css';
    return file_exists($path) ? \Drupal::service('file_url_generator')->generateString($path) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    if (isset($this->iconDefinitions)) {
      $this->iconDefinitions = [];
      $info = $this->getInfo();
      if ($info) {
        $id = $this->id();
        $default_definition = [
          'type' => $this->getType(),
          'prefix' => $this->getInfoPrefix(),
          'path' => \Drupal::service('file_url_generator')->generateString($this->getPath()),
          'package_id' => $id,
          'status' => $this->status(),
        ];
        foreach ($info['icons'] as $icon) {
          if (isset($icon['properties']['name'])) {
            $tag = $icon['properties']['name'];
            $this->iconDefinitions[$id . '-' . $tag] = [
              'id' => $id . '-' . $tag,
              'tag' => $tag,
            ] + $icon + $default_definition;
          }
          else {
            foreach ($icon['icon']['tags'] as $tag) {
              $this->iconDefinitions[$id . '-' . $tag] = [
                'id' => $id . '-' . $tag,
                'tag' => $tag,
              ] + $icon + $default_definition;
            }
          }
          // The icon definition is not used and keeping it can cause database
          // issues due to the size of the cached definitions. Not sure the
          // impact this will have on SVG.
          unset($this->iconDefinitions[$id . '-' . $tag]['icon']);
          unset($this->iconDefinitions[$id . '-' . $tag]['attrs']);
          unset($this->iconDefinitions[$id . '-' . $tag]['setIdx']);
          unset($this->iconDefinitions[$id . '-' . $tag]['setId']);
          unset($this->iconDefinitions[$id . '-' . $tag]['iconIdx']);
          unset($this->iconDefinitions[$id . '-' . $tag]['properties']['order']);
          unset($this->iconDefinitions[$id . '-' . $tag]['properties']['id']);
          unset($this->iconDefinitions[$id . '-' . $tag]['properties']['prevSize']);
          unset($this->iconDefinitions[$id . '-' . $tag]['properties']['ligatures']);
        }
      }
    }
    return $this->iconDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(?array $definitions = NULL) {
    $definitions = $definitions ? $definitions : $this->getDefinitions();
    return $this->exoIconRepository()->getInstances($definitions);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $definition) {
    return $this->exoIconRepository()->getInstance($definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabels($active_only = TRUE) {
    $labels = [];
    foreach (ExoIconPackage::loadMultiple() as $package) {
      if (!$active_only || $package->status()) {
        $labels[$package->id()] = $package->label();
      }
    }
    return $labels;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    if (empty($this->info)) {
      $this->info = [];
      $path = $this->getPath() . '/selection.json';
      if (file_exists($path)) {
        $data = file_get_contents($path);
        $this->info = Json::decode($data);
      }
    }
    return $this->info;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfoName() {
    $info = $this->getInfo();
    return $info['metadata']['name'] ?? substr($this->getInfoPrefix(), 0, -1);
  }

  /**
   * {@inheritdoc}
   */
  public function getInfoPrefix() {
    $info = $this->getInfo();
    return $info['preferences'][$this->getType() . 'Pref']['prefix'];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (file_exists($this->getPath() . '/symbol-defs.svg')) {
      $this->setType('image');
    }
    else {
      $this->setType('font');
    }
  }

  /**
   * Sorts by weight.
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    /** @var \Drupal\exo_icon\Entity\ExoIconPackage $a */
    /** @var \Drupal\exo_icon\Entity\ExoIconPackage $b */
    // Separate enabled from disabled.
    $status = (int) $b->status() - (int) $a->status();
    if ($status !== 0) {
      return $status;
    }
    return $a->getWeight() - $b->getWeight();
  }

  /**
   * Called when exoConfigFile has been created/updated with new attachment.
   *
   * @param \Drupal\exo_config_file\Entity\ExoConfigFileInterface $exo_config_file
   *   THe eXo config file.
   */
  public function exoConfigFileUpdate(ExoConfigFileInterface $exo_config_file) {
    // During config import this entity does not know about the exo config file
    // and must act on event triggered when the ExoConfigFile itself is saved.
    $this->setPath($exo_config_file->getFilePath());
    $this->preparePackage();
  }

  /**
   * Process an extracted package and prepare for use.
   */
  protected function preparePackage() {
    $path = $this->getPath();
    $base_id = $this->id();
    $icon_id = $this->getIconId();

    // Remove unnecessary files.
    $file_system = \Drupal::service('file_system');
    $file_system->deleteRecursive($path . '/demo-files');
    foreach ([
      'demo-external-svg.html',
      'demo.html',
      'Read Me.txt',
    ] as $filepath) {
      if (file_exists($path . '/' . $filepath)) {
        $file_system->delete($path . '/' . $filepath);
      }
    }

    if (file_exists($path . '/symbol-defs.svg')) {
      $this->setType('image');
      // Update symbol to match new id.
      $file_path = $path . '/symbol-defs.svg';
      $file_contents = file_get_contents($file_path);
      $file_contents = str_replace($this->getInfoPrefix(), $icon_id . '-', $file_contents);
      file_put_contents($file_path, $file_contents);
    }
    else {
      $files_to_rename = $path . '/fonts/*.*';
      foreach (glob($file_system->realpath($files_to_rename)) as $file_to_rename_path) {
        $file_new_path = str_replace('fonts/' . $this->getInfoName(), 'fonts/' . $icon_id, $file_to_rename_path);
        if ($file_to_rename_path !== $file_new_path) {
          $file_system->move($file_to_rename_path, $file_new_path, FileSystemInterface::EXISTS_REPLACE);
        }
      }
    }

    // Used after type has been set.
    $name = $this->getInfoName();
    $prefix = $this->getInfoPrefix();

    // Update selection.json.
    $file_path = $path . '/selection.json';
    $file_contents = file_get_contents($file_path);
    $replacements = [
      '"icons":' => '_ExoIconPackagesIcons',
      '"icon":' => '_ExoIconPackageIcon',
      'iconIdx' => '_ExoIconPackageIdx',
    ];
    // Prevent overwrite of system properties.
    foreach ($replacements as $find => $replace) {
      $file_contents = str_replace($find, $replace, $file_contents);
    }
    // The name and selector should be updated to match entity info.
    $file_contents = str_replace($prefix, $icon_id . '-', $file_contents);
    $file_contents = str_replace('"' . $name . '"', '"' . $icon_id . '"', $file_contents);
    $file_contents = str_replace('".' . $name . '"', '".' . $icon_id . '"', $file_contents);
    $file_contents = str_replace('"icon-"', '"' . $icon_id . '-"', $file_contents);
    $file_contents = str_replace('icomoon', $icon_id, $file_contents);
    // Revert system properties.
    foreach ($replacements as $replace => $find) {
      $file_contents = str_replace($find, $replace, $file_contents);
    }
    file_put_contents($file_path, $file_contents);

    // Update IcoMoon stylesheet.
    $file_path = $path . '/style.css';
    $file_contents = file_get_contents($file_path);
    // The style.css file provided by IcoMoon contains query parameters where it
    // loads in the font files. Drupal CSS aggregation doesn't handle this well
    // so we need to remove it.
    $file_contents = preg_replace('(\?[a-zA-Z0-9#\-\_]*)', '', $file_contents);
    // The name and selector should be updated to match entity info.
    $file_contents = str_replace($prefix, $icon_id . '-', $file_contents);
    $file_contents = str_replace($name, $icon_id, $file_contents);
    // Under some conditions, icon-icon exists.
    $file_contents = str_replace('icon-icon', 'icon', $file_contents);
    $file_contents = str_replace($icon_id . '-' . $base_id, $icon_id, $file_contents);
    file_put_contents($file_path, $file_contents);
  }

  /**
   * Gets the eXo icon repository.
   *
   * @return \Drupal\exo_icon\ExoIconRepositoryInterface
   *   The eXo icon repository.
   */
  protected function exoIconRepository() {
    if (!isset($this->exoIconRepository)) {
      $this->exoIconRepository = \Drupal::service('exo_icon.repository');
    }
    return $this->exoIconRepository;
  }

}
