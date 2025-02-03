<?php

namespace Drupal\exo_alchemist\Definition;

use Drupal\Component\Plugin\Definition\ContextAwarePluginDefinitionInterface;
use Drupal\Component\Plugin\Definition\ContextAwarePluginDefinitionTrait;
use Drupal\Component\Plugin\Definition\PluginDefinition;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\exo\Shared\ExoArrayAccessDefinitionTrait;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;

/**
 * Class ExoComponentDefinition.
 *
 * @package Drupal\exo_alchemist\Definition
 */
class ExoComponentDefinition extends PluginDefinition implements ContextAwarePluginDefinitionInterface, \ArrayAccess {

  use ContextAwarePluginDefinitionTrait;
  use ExoArrayAccessDefinitionTrait;

  /**
   * Component prefix.
   */
  const PATTERN_PREFIX = 'exo_component_';

  /**
   * Provides default values for all exo_component plugins.
   *
   * @var array
   */
  protected $definition = [
    // Add required and optional plugin properties.
    'id' => '',
    'name' => '',
    'label' => '',
    'description' => '',
    'version' => '0.0.0',
    'category' => '',
    'permission' => '',
    'entity_type' => '',
    'bundle' => '',
    'ignore' => FALSE,
    'hidden' => FALSE,
    'computed' => FALSE,
    'modifier' => '',
    'modifiers' => [],
    'modifier_globals' => [],
    'fields' => [],
    'js' => [],
    'css' => [],
    'libraries' => [],
    'path' => '',
    'template' => '',
    'theme hook' => '',
    'thumbnail' => '',
    'provider' => '',
    'enhancements' => [],
    'animations' => [],
    'parents' => [],
    'tags' => [],
    // Allows to define global component. Will only have a single instance.
    'global' => FALSE,
    'additional' => [],
  ];

  /**
   * The object's dependents.
   *
   * @var array
   */
  protected $dependents = [];

  /**
   * Provides TRUE if definition is installed.
   *
   * @var bool
   */
  protected $installed = FALSE;

  /**
   * Provides TRUE if definition is installed but no longer available.
   *
   * @var bool
   */
  protected $missing = FALSE;

  /**
   * The component handler.
   *
   * @var \Drupal\exo_alchemist\ExoComponentHandlerInterface[]
   */
  protected static $handler = [];

  /**
   * The default component modifiers for each component.
   *
   * @var array
   */
  protected static $globalModifiers = [
    'color_bg' => [
      'type' => 'exo_theme_color',
      'label' => 'Background Color',
      'status' => TRUE,
    ],
    'color_bg_content' => [
      'type' => 'exo_theme_color',
      'label' => 'Background Color: Content',
      'status' => FALSE,
    ],
    'invert' => [
      'type' => 'invert',
      'label' => 'Invert Colors',
      'status' => TRUE,
    ],
    'text_shadow' => [
      'type' => 'text_shadow',
      'label' => 'Text Shadow',
      'status' => FALSE,
    ],
    'overlay' => [
      'type' => 'overlay',
      'label' => 'Overlay',
      'status' => TRUE,
    ],
    'height' => [
      'type' => 'height',
      'label' => 'Height',
      'status' => TRUE,
    ],
    'margin_v' => [
      'type' => 'margin_vertical',
      'label' => 'Margin',
      'description' => 'Margin is the space between components.',
      'status' => TRUE,
    ],
    'padding_v' => [
      'type' => 'padding_vertical',
      'label' => 'Padding',
      'description' => 'Padding is the space between a component and its contents.',
      'status' => TRUE,
    ],
    'padding_v_content' => [
      'type' => 'padding_vertical',
      'label' => 'Padding: Content',
      'description' => 'Padding is the space between a component\'s content and a component\'s edge.',
      'status' => FALSE,
    ],
    'containment' => [
      'type' => 'containment',
      'label' => 'Containment',
      'status' => TRUE,
    ],
    'containment_content' => [
      'type' => 'containment',
      'label' => 'Containment: Content',
      'status' => TRUE,
    ],
    'border_radius' => [
      'type' => 'border_radius',
      'label' => 'Border Radius',
      'status' => TRUE,
    ],
    'breakpoint_hide' => [
      'type' => 'breakpoint',
      'label' => 'Breakpoint: Hide',
      'description' => 'This component will not be visible when viewed from the selected screen sizes.',
      'default' => [],
      'status' => FALSE,
    ],
  ];

  /**
   * ExoComponentDefinition constructor.
   */
  public function __construct(array $definition = []) {
    // Allow installed to be passed in to the definition but do not store it
    // as part of the definition.
    if (isset($definition['context_definitions'])) {
      // $this->contextDefinitions
      unset($definition['context_definitions']);
    }
    if (isset($definition['installed'])) {
      $this->setInstalled($definition['installed']);
      unset($definition['installed']);
    }
    foreach ($definition as $name => $value) {
      if (array_key_exists($name, $this->definition)) {
        $this->definition[$name] = $value;
      }
      else {
        $this->definition['additional'][$name] = $value;
      }
    }
    $this->id = $this->definition['id'];
    $this->provider = $this->definition['provider'];
    $this->setThemeHook(self::PATTERN_PREFIX . $this->id());
    $this->setFields($this->definition['fields']);
    $this->setModifiers($this->definition['modifiers']);
    $this->setEnhancements($this->definition['enhancements']);
    $this->setAnimations($this->definition['animations']);
    if ($entity_type_id = $this->definition['entity_type']) {
      $context_definition = EntityContextDefinition::fromEntityTypeId($entity_type_id)->setLabel('Entity');
      if ($bundle = $this->definition['bundle']) {
        if (!is_array($bundle)) {
          $bundle = [$bundle];
        }
        $context_definition->addConstraint('Bundle', $bundle);
      }
      $this->addContextDefinition('entity', $context_definition);
      $this->addContextDefinition('view_mode', new ContextDefinition('string'));
    }
  }

  /**
   * Return array definition.
   *
   * @return array
   *   Array definition.
   */
  public function toArray() {
    $definition = $this->definition;
    $definition['label'] = (string) $definition['label'];
    foreach ($this->getFields() as $field) {
      $definition['fields'][$field->getName()] = $field->toArray();
    }
    foreach ($this->getModifiers() as $modifier) {
      $definition['modifiers'][$modifier->getName()] = $modifier->toArray();
    }
    foreach ($this->getEnhancements() as $enhancement) {
      $definition['enhancements'][$enhancement->getName()] = $enhancement->toArray();
    }
    foreach ($this->getAnimations() as $animation) {
      $definition['animations'][$animation->getName()] = $animation->toArray();
    }
    unset($definition['context_definitions']);
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function extendId() {
    return $this->getAdditionalValue('extend_id');
  }

  /**
   * A string that is 32 characters long and can be used for entity ids.
   *
   * @return string
   *   A 32 character string.
   */
  public function safeId() {
    return 'exo_' . substr(hash('sha256', $this->id()), 0, 28);
  }

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getName() {
    return $this->definition['name'];
  }

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getLabel() {
    return $this->definition['label'];
  }

  /**
   * Setter.
   *
   * @param mixed $label
   *   Property value.
   *
   * @return $this
   */
  public function setLabel($label) {
    $this->definition['label'] = $label;
    return $this;
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function getVersion() {
    return $this->definition['version'];
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function getCategory() {
    return $this->definition['category'];
  }

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getHandlerPath() {
    return $this->getAdditionalValue('handler_path') ?? ltrim($this->getPath(), '/');
  }

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getHandlerName() {
    return $this->getAdditionalValue('handler_name') ?? str_replace('_', '', ucwords($this->getName(), '_'));
  }

  /**
   * Getter.
   *
   * @return \Drupal\exo_alchemist\ExoComponentHandlerInterface
   *   Property value.
   */
  public function getHandler() {
    $id = $this->id();
    if (!array_key_exists($id, static::$handler)) {
      static::$handler[$id] = NULL;
      $name = $this->getHandlerName();
      $filepath = $this->getHandlerPath() . '/' . $name . '.php';
      if (!class_exists($name)) {
        if (file_exists($filepath)) {
          include $filepath;
        }
      }
      if (class_exists($name)) {
        static::$handler[$id] = new $name();
      }
      elseif ($this->isExtended()) {
        /** @var \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager */
        $exo_component_manager = \Drupal::service('plugin.manager.exo_component');
        if ($exo_component_manager->hasDefinition($this->extendId())) {
          static::$handler[$id] = $exo_component_manager->getDefinition($this->extendId())->getHandler();
        }
      }
    }
    return static::$handler[$id];
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    $vars = get_object_vars($this);
    unset($vars['handler']);
    return array_keys($vars);
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function getPath() {
    return $this->definition['path'];
  }

  /**
   * Setter.
   *
   * @param mixed $path
   *   Property value.
   *
   * @return $this
   */
  public function setPath($path) {
    $this->definition['path'] = $path;
    return $this;
  }

  /**
   * Setter.
   *
   * @param array $fields
   *   Property value.
   *
   * @return $this
   */
  public function setFields(array $fields) {
    foreach ($fields as $name => $field) {
      if (!$field instanceof ExoComponentDefinitionField) {
        $field = $this->getFieldDefinition($name, $field);
      }
      $this->definition['fields'][$field->getName()] = $field;
    }
    return $this;
  }

  /**
   * Getter.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField[]
   *   Property value.
   */
  public function getFields() {
    return $this->definition['fields'];
  }

  /**
   * Getter.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField[]
   *   Property value.
   */
  public function getManagedFields() {
    $fields = [];
    foreach ($this->getFields() as $key => $field) {
      if (!$field->isExtended()) {
        $fields[$key] = $field;
      }
    }
    return $fields;
  }

  /**
   * Getter.
   *
   * @return bool
   *   Property value.
   */
  public function hasFields() {
    return !empty($this->definition['fields']);
  }

  /**
   * Get field by type.
   *
   * @param string $type
   *   The field type.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField[]
   *   Property value.
   */
  public function getFieldsByType($type) {
    return array_filter($this->getFields(), function ($field) use ($type) {
      /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field */
      $parts = explode(PluginBase::DERIVATIVE_SEPARATOR, $field->getType());
      return $parts[0] == $type;
    });
  }

  /**
   * Get field as options.
   *
   * @return array
   *   Fields as select options.
   */
  public function getFieldsAsOptions() {
    $options = [];
    foreach ($this->getFields() as $field) {
      $options[$field->getName()] = $field->getLabel();
    }
    return $options;
  }

  /**
   * Get field.
   *
   * @param string $name
   *   Field name.
   *
   * @return ExoComponentDefinitionField|null
   *   Definition field.
   */
  public function getField($name) {
    return $this->hasField($name) ? $this->definition['fields'][$name] : NULL;
  }

  /**
   * Get field by its safe id.
   *
   * @param string $safe_id
   *   Safe id. This is used as the field name.
   *
   * @return ExoComponentDefinitionField|null
   *   Definition field.
   */
  public function getFieldBySafeId($safe_id) {
    foreach ($this->getFields() as $field) {
      if ($field->safeId() == $safe_id) {
        return $field;
      }
    }
    return NULL;
  }

  /**
   * Check whereas field exists.
   *
   * @param string $name
   *   Field name.
   *
   * @return bool
   *   Whereas field exists
   */
  public function hasField($name) {
    return isset($this->definition['fields'][$name]);
  }

  /**
   * Set field.
   *
   * @param string $name
   *   Field name.
   * @param string $label
   *   Field label.
   *
   * @return $this
   */
  public function setField($name, $label) {
    $this->definition['fields'][$name] = $this->getFieldDefinition($name, $label);
    return $this;
  }

  /**
   * Get the modifier target id.
   *
   * @return string
   *   Property value.
   */
  public function getModifierTarget() {
    return !empty($this->definition['modifier']) ? $this->definition['modifier'] : $this->getName();
  }

  /**
   * Setter.
   *
   * @param mixed $modifiers
   *   Property value.
   *
   * @return $this
   */
  public function setModifiers($modifiers) {
    $this->definition['modifiers'] = [];
    if ($this->definition['modifier_globals'] !== FALSE) {
      $globals = [
        'label' => 'Global',
        'properties' => self::getGlobalModifiers(),
      ];
      if (!empty($this->definition['modifier_globals']['properties'])) {
        $globals['properties'] = $this->definition['modifier_globals']['properties'];
      }
      if (!empty($this->definition['modifier_globals']['defaults'])) {
        foreach ($this->definition['modifier_globals']['defaults'] as $property => $default) {
          if (isset($globals['properties'][$property])) {
            $globals['properties'][$property]['default'] = $default;
          }
        }
      }
      if (!empty($this->definition['modifier_globals']['status'])) {
        foreach ($this->definition['modifier_globals']['status'] as $property => $value) {
          if (isset($globals['properties'][$property])) {
            $globals['properties'][$property]['status'] = !empty($value);
          }
        }
      }
      if (!empty($this->definition['modifier_globals']['extend'])) {
        $globals['properties'] = NestedArray::mergeDeep($globals['properties'], $this->definition['modifier_globals']['extend']);
      }
      // Globals use a status key to allow components to easily enable/disable
      // them without having to redefine them.
      foreach ($globals['properties'] as $property => &$info) {
        if (isset($info['status']) && empty($info['status'])) {
          unset($globals['properties'][$property]);
        }
        // Status is not a real property attribute and is only used for globals.
        unset($info['status']);
      }
      $modifiers += [
        '_global' => $globals,
      ];
    }
    foreach ($modifiers as $name => $value) {
      if ($value === FALSE) {
        continue;
      }
      $modifier = $this->getModifierDefinition($name, $value);
      $this->definition['modifiers'][$modifier->getName()] = $modifier;
    }
    ksort($this->definition['modifiers']);
    return $this;
  }

  /**
   * Getter.
   *
   * @return ExoComponentDefinitionModifier[]
   *   Property value.
   */
  public function getModifiers() {
    return $this->definition['modifiers'];
  }

  /**
   * Get modifier.
   *
   * @param string $name
   *   Modifier name.
   *
   * @return ExoComponentDefinitionModifier|null
   *   Definition modifier.
   */
  public function getModifier($name) {
    return $this->hasModifier($name) ? $this->definition['modifiers'][$name] : NULL;
  }

  /**
   * Get global modifier.
   *
   * @return ExoComponentDefinitionModifier|null
   *   Definition modifier.
   */
  public function getGlobalModifier() {
    return $this->getModifier('_global');
  }

  /**
   * Check whereas modifier exists.
   *
   * @param string $name
   *   Field name.
   *
   * @return bool
   *   Whereas field exists
   */
  public function hasModifier($name) {
    return isset($this->definition['modifiers'][$name]);
  }

  /**
   * Set enhancements.
   *
   * @param mixed $enhancements
   *   Property value.
   *
   * @return $this
   */
  public function setEnhancements($enhancements) {
    foreach ($enhancements as $name => $value) {
      if ($value === FALSE) {
        continue;
      }
      $enhancement = $this->getEnhancementDefinition($name, $value);
      $this->definition['enhancements'][$enhancement->getName()] = $enhancement;
    }
    return $this;
  }

  /**
   * Get enhancements.
   *
   * @return ExoComponentDefinitionEnhancement[]
   *   Property value.
   */
  public function getEnhancements() {
    return $this->definition['enhancements'];
  }

  /**
   * Set animations.
   *
   * @param mixed $animations
   *   Property value.
   *
   * @return $this
   */
  public function setAnimations($animations) {
    foreach ($animations as $name => $value) {
      if ($value === FALSE) {
        continue;
      }
      $animation = $this->getAnimationDefinition($name, $value);
      $this->definition['animations'][$animation->getName()] = $animation;
    }
    return $this;
  }

  /**
   * Getter.
   *
   * @return ExoComponentDefinitionAnimation[]
   *   Property value.
   */
  public function getAnimations() {
    return $this->definition['animations'];
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function getCss() {
    return $this->definition['css'];
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function getJs() {
    return $this->definition['js'];
  }

  /**
   * Getter.
   *
   * @return bool
   *   Whereas has library.
   */
  public function hasLibrary() {
    return !empty($this->getCss()) || !empty($this->getJs());
  }

  /**
   * Getter.
   *
   * @return bool
   *   Whereas has library.
   */
  public function getLibraryDependencies() {
    return $this->definition['libraries'];
  }

  /**
   * Getter.
   *
   * @return string
   *   The library id.
   */
  public function getLibraryId() {
    return 'exo_component.' . $this->id();
  }

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getDescription() {
    return $this->definition['description'];
  }

  /**
   * Setter.
   *
   * @param string $description
   *   Property value.
   *
   * @return $this
   */
  public function setDescription($description) {
    $this->definition['description'] = $description;
    return $this;
  }

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getPermission() {
    return $this->definition['permission'];
  }

  /**
   * Check if component is locked.
   *
   * @return bool
   *   Returns TRUE if component is locked.
   */
  public function isLocked() {
    $locked = FALSE;
    $permission = $this->getPermission();
    if ($permission && !\Drupal::currentUser()->hasPermission($permission)) {
      // No changes can be made within restricted sections.
      $locked = TRUE;
    }
    return $locked;
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function isExtended() {
    return !empty($this->extendId());
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function isGlobal() {
    return !empty($this->definition['global']);
  }

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getTemplate() {
    return $this->definition['template'];
  }

  /**
   * Setter.
   *
   * @param string $template
   *   Property value.
   *
   * @return $this
   */
  public function setTemplate($template) {
    $this->definition['template'] = $template;
    return $this;
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function getThumbnailSource() {
    return $this->definition['thumbnail'];
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function getThumbnailDirectory() {
    return 'public://exo-alchemist/' . $this->id();
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function getThumbnailFilename() {
    $info = pathinfo($this->getThumbnailSource());
    return $info['basename'];
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function getThumbnailUri() {
    return $this->getThumbnailSource() ? $this->getThumbnailDirectory() . '/' . $this->getThumbnailFilename() : NULL;
  }

  /**
   * Setter.
   *
   * @param mixed $thumbnail
   *   Property value.
   *
   * @return $this
   */
  public function setThumbnail($thumbnail) {
    $this->definition['thumbnail'] = $thumbnail;
    return $this;
  }

  /**
   * Getter.
   *
   * @return bool
   *   Whereas has thumbnail.
   */
  public function hasThumbnail() {
    return !empty($this->definition['thumbnail']);
  }

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getThemeHook() {
    return $this->definition['theme hook'];
  }

  /**
   * Setter.
   *
   * @param string $theme_hook
   *   Property value.
   *
   * @return $this
   */
  public function setThemeHook($theme_hook) {
    $this->definition['theme hook'] = $theme_hook;
    return $this;
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function isHidden() {
    return !empty($this->definition['hidden']);
  }

  /**
   * Getter.
   *
   * @return mixed
   *   Property value.
   */
  public function isComputed() {
    return !empty($this->definition['computed']);
  }

  /**
   * Get Provider property.
   *
   * @return string
   *   Property value.
   */
  public function getProvider() {
    return $this->definition['provider'];
  }

  /**
   * Setter.
   *
   * @param mixed $provider
   *   Property value.
   *
   * @return $this
   */
  public function setProvider($provider) {
    $this->definition['provider'] = $provider;
    return $this;
  }

  /**
   * Get Deriver property.
   *
   * @return mixed
   *   Property value.
   */
  public function getDeriver() {
    return $this->definition['deriver'];
  }

  /**
   * Set Deriver property.
   *
   * @param mixed $deriver
   *   Property value.
   *
   * @return $this
   */
  public function setDeriver($deriver) {
    $this->definition['deriver'] = $deriver;
    return $this;
  }

  /**
   * Add an item as a parent.
   *
   * @param string $key
   *   A string that will be used as the unique key for the parent.
   * @param string $value
   *   A string that will be appending to the parents.
   *
   * @return $this
   */
  protected function addParent($key, $value) {
    $this->definition['parents'][$key] = $value;
    return $this;
  }

  /**
   * Add field as a parent.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The field.
   *
   * @return $this
   */
  public function addParentField(ExoComponentDefinitionField $field) {
    $this->addParent($field->safeId(), $field->safeId());
    return $this;
  }

  /**
   * Add a field delta as a parent.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField $field
   *   The field.
   * @param int $delta
   *   The delta.
   *
   * @return $this
   */
  public function addParentFieldDelta(ExoComponentDefinitionField $field, $delta) {
    $this->addParent($field->safeId() . '_delta', $delta);
    return $this;
  }

  /**
   * Get the parents of this component.
   *
   * @return array
   *   An array of parent keys.
   */
  public function getParents() {
    return $this->definition['parents'];
  }

  /**
   * Clear the parents of this component.
   *
   * @return $this
   */
  public function clearParents() {
    $this->definition['parents'] = [];
    return $this;
  }

  /**
   * Get the parents of this component as a string.
   *
   * @return string
   *   Parent keys separated by a period.
   */
  public function getParentsAsPath() {
    return implode('.', $this->getParents());
  }

  /**
   * Get the tags of this component.
   *
   * @return array
   *   An array of tags.
   */
  public function getTags() {
    return $this->definition['tags'];
  }

  /**
   * Get additional property.
   *
   * @return array
   *   Property value.
   */
  public function getAdditional() {
    return $this->definition['additional'];
  }

  /**
   * Get additional property value.
   *
   * @param mixed $parents
   *   An array of parent keys of the value, starting with the outermost key.
   * @param bool $key_exists
   *   (optional) If given, an already defined variable that is altered by
   *   reference.
   *
   * @return mixed
   *   The requested nested value. Possibly NULL if the value is NULL or not all
   *   nested parent keys exist. $key_exists is altered by reference and is a
   *   Boolean that indicates whether all nested parent keys exist (TRUE) or not
   *   (FALSE). This allows to distinguish between the two possibilities when
   *   NULL is returned.
   */
  public function getAdditionalValue($parents, &$key_exists = NULL) {
    return NestedArray::getValue($this->definition['additional'], (array) $parents, $key_exists);
  }

  /**
   * Set additional property value.
   *
   * @param mixed $parents
   *   An array of parent keys, starting with the outermost key.
   * @param mixed $value
   *   The value to set.
   * @param bool $force
   *   (optional) If TRUE, the value is forced into the structure even if it
   *   requires the deletion of an already existing non-array parent value. If
   *   FALSE, PHP throws an error if trying to add into a value that is not an
   *   array. Defaults to FALSE.
   *
   * @see NestedArray::unsetValue()
   * @see NestedArray::getValue()
   */
  public function setAdditionalValue($parents, $value, $force = FALSE) {
    return NestedArray::setValue($this->definition['additional'], (array) $parents, $value, $force);
  }

  /**
   * Set additional property.
   *
   * @param array $additional
   *   Property value.
   *
   * @return $this
   */
  public function setAdditional(array $additional) {
    $this->definition['additional'] = $additional;
    return $this;
  }

  /**
   * Add additional property.
   *
   * @param string $key
   *   Property key.
   * @param mixed $value
   *   Property value.
   *
   * @return $this
   */
  public function addAdditional($key, $value) {
    $this->definition['additional'][$key] = $value;
    return $this;
  }

  /**
   * Set Class property.
   *
   * @param string $class
   *   Property value.
   *
   * @return $this
   */
  public function setClass($class) {
    parent::setClass($class);
    $this->definition['class'] = $class;
    return $this;
  }

  /**
   * Get Class property.
   *
   * @return string
   *   Property value.
   */
  public function getClass() {
    return $this->definition['class'];
  }

  /**
   * Factory method: create a new field definition.
   *
   * @param string $name
   *   Field name.
   * @param string $value
   *   Field value.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField
   *   Definition instance.
   */
  public function getFieldDefinition($name, $value) {
    $field = new ExoComponentDefinitionField($name, $value, $this);
    return $field;
  }

  /**
   * Factory method: create a new modifier definition.
   *
   * @param string $name
   *   Field name.
   * @param string $value
   *   Field value.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinitionModifier
   *   Definition instance.
   */
  public function getModifierDefinition($name, $value) {
    $field = new ExoComponentDefinitionModifier($name, $value, $this);
    return $field;
  }

  /**
   * Factory method: create a new enhancement definition.
   *
   * @param string $name
   *   Field name.
   * @param array $value
   *   Field value.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinitionEnhancement
   *   Definition instance.
   */
  public function getEnhancementDefinition($name, array $value) {
    $animation = new ExoComponentDefinitionEnhancement($name, $value, $this);
    return $animation;
  }

  /**
   * Factory method: create a new animation definition.
   *
   * @param string $name
   *   Field name.
   * @param array $value
   *   Field value.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinitionAnimation
   *   Definition instance.
   */
  public function getAnimationDefinition($name, array $value) {
    $animation = new ExoComponentDefinitionAnimation($name, $value, $this);
    return $animation;
  }

  /**
   * Is installed.
   *
   * @return bool
   *   Property value.
   */
  public function isInstalled() {
    return $this->installed === TRUE;
  }

  /**
   * Setter.
   *
   * @param mixed $value
   *   Property value.
   *
   * @return $this
   */
  public function setInstalled($value = TRUE) {
    $this->installed = $value == TRUE;
    return $this;
  }

  /**
   * Is missing.
   *
   * @return bool
   *   Property value.
   */
  public function isMissing() {
    return $this->missing === TRUE;
  }

  /**
   * Setter.
   *
   * @param mixed $value
   *   Property value.
   *
   * @return $this
   */
  public function setMissing($value = TRUE) {
    $this->missing = $value == TRUE;
    return $this;
  }

  /**
   * Adds a dependent.
   *
   * @param string $type
   *   Type of dependent being added: 'module', 'theme', 'config', 'content'.
   * @param string $name
   *   If $type is 'module' or 'theme', the name of the module or theme. If
   *   $type is 'config' or 'content', the result of
   *   EntityInterface::getConfigDependencyName().
   *
   * @see \Drupal\Core\Entity\EntityInterface::getConfigDependencyName()
   *
   * @return $this
   */
  public function addDependent($type, $name) {
    if (empty($this->dependents[$type])) {
      $this->dependents[$type] = [$name];
      if (count($this->dependents) > 1) {
        // Ensure a consistent order of type keys.
        ksort($this->dependents);
      }
    }
    elseif (!in_array($name, $this->dependents[$type])) {
      $this->dependents[$type][] = $name;
      // Ensure a consistent order of dependent names.
      sort($this->dependents[$type], SORT_FLAG_CASE);
    }
    return $this;
  }

  /**
   * Adds multiple dependents.
   *
   * @param array $dependents
   *   An array of dependents keyed by the type of dependent. One example:
   *   @code
   *   [
   *     'module' => [
   *       'node',
   *       'field',
   *       'image',
   *     ],
   *   ];
   *   @endcode
   *
   * @see \Drupal\Core\Entity\DependencyTrait::addDependent
   */
  public function addDependents(array $dependents) {
    foreach ($dependents as $dependent_type => $list) {
      foreach ($list as $name) {
        $this->addDependent($dependent_type, $name);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependents() {
    foreach ($this->getFields() as $field) {
      $this->addDependents($field->calculateDependents());
    }
    return $this->dependents;
  }

  /**
   * Get the global modifiers.
   */
  public static function getGlobalModifiers() {
    return self::$globalModifiers;
  }

}
