<?php

namespace Drupal\exo_alchemist;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\exo_alchemist\Plugin\Discovery\ExoComponentDiscovery;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Plugin\Context\ContextAwarePluginManagerInterface;
use Drupal\Core\Plugin\FilteredPluginManagerTrait;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\exo_alchemist\Definition\ExoComponentDefinition;
use Drupal\exo_alchemist\Plugin\Discovery\ExoComponentInstalledDiscovery;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldPreviewEntityTrait;
use Drupal\exo_icon\ExoIconTranslatableMarkup;

/**
 * Provides the default exo_component manager.
 */
class ExoComponentManager extends DefaultPluginManager implements ContextAwarePluginManagerInterface, ExoComponentContextInterface {

  use CategorizingPluginManagerTrait;
  use FilteredPluginManagerTrait;
  use LayoutEntityHelperTrait;
  use ExoComponentContextTrait;
  use ExoComponentFieldPreviewEntityTrait;

  /**
   * The entity bundle type to use as component entities.
   */
  const ENTITY_BUNDLE_TYPE = 'block_content_type';

  /**
   * The entity type to use as component entities.
   */
  const ENTITY_TYPE = 'block_content';

  /**
   * The object that discovers plugins managed by this manager.
   *
   * @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface
   */
  protected $installedDiscovery;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The eXo component field manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentFieldManager
   */
  protected $exoComponentFieldManager;

  /**
   * The eXo component property manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentPropertyManager
   */
  protected $exoComponentPropertyManager;

  /**
   * The eXo component enhancement manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentEnhancementManager
   */
  protected $exoComponentEnhancementManager;

  /**
   * The eXo component animation manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentAnimationManager
   */
  protected $exoComponentAnimationManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Cached definitions array.
   *
   * @var array
   */
  protected $definitionsInstalled;

  /**
   * An array of ops urls.
   *
   * @var string[]
   */
  protected $ops;

  /**
   * Constructs a new ExoComponentManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   * @param \Drupal\exo_alchemist\ExoComponentFieldManager $exo_component_field_manager
   *   The eXo component field manager.
   * @param \Drupal\exo_alchemist\ExoComponentPropertyManager $exo_component_property_manager
   *   The eXo component property manager.
   * @param \Drupal\exo_alchemist\ExoComponentEnhancementManager $exo_component_enhancement_manager
   *   The eXo component enhancement manager.
   * @param \Drupal\exo_alchemist\ExoComponentAnimationManager $exo_component_animation_manager
   *   The eXo component animation manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, EntityRepositoryInterface $entity_repository, CacheBackendInterface $cache, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, ExoComponentFieldManager $exo_component_field_manager, ExoComponentPropertyManager $exo_component_property_manager, ExoComponentEnhancementManager $exo_component_enhancement_manager, ExoComponentAnimationManager $exo_component_animation_manager, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_manager;
    $this->entityRepository = $entity_repository;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->exoComponentFieldManager = $exo_component_field_manager;
    $this->exoComponentPropertyManager = $exo_component_property_manager;
    $this->exoComponentEnhancementManager = $exo_component_enhancement_manager;
    $this->exoComponentAnimationManager = $exo_component_animation_manager;
    $this->currentUser = $current_user;
    $this->setCacheBackend($cache, 'exo_component_info', ['exo_component_info']);
    $this->alterInfo('exo_component_info');
  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'exo_component';
  }

  /**
   * {@inheritdoc}
   */
  public function getFilteredDefinitions($consumer, $contexts = NULL, array $extra = []) {
    if (!is_null($contexts)) {
      $definitions = $this->getDefinitionsForContexts($contexts);
    }
    else {
      $definitions = $this->getInstalledDefinitions();
    }

    // Check permissions.
    foreach ($definitions as $key => $definition) {
      $permission = $definition->getPermission();
      if ($permission && !$this->currentUser->hasPermission($permission)) {
        unset($definitions[$key]);
      }
    }

    $type = $this->getType();
    $hooks = [];
    $hooks[] = "plugin_filter_{$type}";
    $hooks[] = "plugin_filter_{$type}__{$consumer}";
    $this->moduleHandler()->alter($hooks, $definitions, $extra, $consumer);
    $this->themeManager()->alter($hooks, $definitions, $extra, $consumer);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getAlphabeticalDefinitions(array $definitions = NULL, $label_key = 'label') {
    // Sort the plugins by label.
    /** @var \Drupal\Core\Plugin\CategorizingPluginManagerTrait|\Drupal\Component\Plugin\PluginManagerInterface $this */
    $definitions = $definitions ?? $this->getDefinitions();
    uasort($definitions, function ($a, $b) use ($label_key) {
      return strnatcasecmp($a[$label_key], $b[$label_key]);
    });
    return $definitions;
  }

  /**
   * See \Drupal\Core\Plugin\Context\ContextAwarePluginManagerInterface::getDefinitionsForContexts().
   */
  public function getDefinitionsForContexts(array $contexts = []) {
    return $this->contextHandler()->filterPluginDefinitionsByContexts($contexts, $this->getInstalledDefinitions());
  }

  /**
   * Determines if the provider of a definition exists.
   *
   * @return bool
   *   TRUE if provider exists, FALSE otherwise.
   */
  protected function providerExists($provider) {
    return $this->moduleHandler->moduleExists($provider) || $this->themeHandler->themeExists($provider);
  }

  /**
   * Get the eXo component field manager.
   *
   * @return \Drupal\exo_alchemist\ExoComponentFieldManager
   *   The eXo component field manager.
   */
  public function getExoComponentFieldManager() {
    return $this->exoComponentFieldManager;
  }

  /**
   * Get the eXo component property manager.
   *
   * @return \Drupal\exo_alchemist\ExoComponentPropertyManager
   *   The eXo component property manager.
   */
  public function getExoComponentPropertyManager() {
    return $this->exoComponentPropertyManager;
  }

  /**
   * Get the eXo component enhancement manager.
   *
   * @return \Drupal\exo_alchemist\ExoComponentEnhancementManager
   *   The eXo component enhancement manager.
   */
  public function getExoComponentEnhancementManager() {
    return $this->exoComponentEnhancementManager;
  }

  /**
   * Get the eXo component animation manager.
   *
   * @return \Drupal\exo_alchemist\ExoComponentAnimationManager
   *   The eXo component animation manager.
   */
  public function getExoComponentAnimationManager() {
    return $this->exoComponentAnimationManager;
  }

  /**
   * {@inheritdoc}
   */
  public function hasInstalledDefinition($plugin_id) {
    return (bool) $this->getInstalledDefinition($plugin_id, FALSE);
  }

  /**
   * Gets installed definitions.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinition[]
   *   The eXo component definition.
   */
  public function getInstalledDefinitions() {
    $definitions = $this->getCachedInstalledDefinitions();
    if (!isset($definitions)) {
      $definitions = $this->findInstalledDefinitions();
      $this->setCachedInstalledDefinitions($definitions);
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   *   The eXo component definition.
   */
  public function getInstalledDefinition($plugin_id, $exception_on_invalid = TRUE) {
    $definitions = $this->getInstalledDefinitions();
    return $this->doGetDefinition($definitions, $plugin_id, $exception_on_invalid);
  }

  /**
   * Finds plugin definitions.
   *
   * @return array
   *   List of definitions to store in cache.
   */
  protected function findInstalledDefinitions() {
    $definitions = $this->getInstalledDiscovery()->getDefinitions();
    $state = \Drupal::state();
    $rebuild = $state->get('exo_alchemist.component_rebuild', []);
    foreach ($definitions as $plugin_id => &$definition) {
      $this->processInstalledDefinition($definition, $plugin_id);
      // When a config import contains an update to a component, we need to
      // check if the default entity needs to be built.
      // @see \Drupal\exo_alchemist\EventSubscriber::onSave()
      if (isset($rebuild[$definition->safeId()])) {
        unset($rebuild[$definition->safeId()]);
        $state->set('exo_alchemist.component_rebuild', $rebuild);
        $this->buildEntity($definition);
      }
    }
    if (!empty($rebuild)) {
      // $state->delete('exo_alchemist.component_rebuild');
    }
    return $definitions;
  }

  /**
   * Check if installed definition is different than code definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $to_definition
   *   The component definition.
   *
   * @return bool
   *   TRUE if installed definition is different than code definition.
   */
  public function installedDefinitionHasChanges(ExoComponentDefinition $to_definition) {
    $from_definition = $this->getDefinition($to_definition->id());
    $has_field_changes = $this->exoComponentFieldManager->installedDefinitionHasChanges($to_definition, $from_definition);
    return $to_definition->toArray() !== $from_definition->toArray() || $has_field_changes;
  }

  /**
   * Update installed definition to the code definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface|null
   *   The config entity.
   */
  public function updateInstalledDefinition(ExoComponentDefinition $definition) {
    return $this->updateEntityType($this->getDefinition($definition->id()));
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $discovery = new ExoComponentDiscovery($this->getDirectories());
      $discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInstalledDiscovery() {
    if (!isset($this->installedDiscovery)) {
      $discovery = new ExoComponentInstalledDiscovery($this->entityTypeManager);
      $discovery->addTranslatableProperty('label', 'label_context');
      $this->installedDiscovery = new ContainerDerivativeDiscoveryDecorator($discovery);
    }
    return $this->installedDiscovery;
  }

  /**
   * Create a list of all directories to scan.
   *
   * This includes all module directories and directories of the default theme
   * and all of its possible base themes.
   *
   * @return array
   *   An array containing directory paths keyed by their extension name.
   */
  protected function getDirectories() {
    $default_theme = $this->themeHandler->getDefault();
    $base_themes = $this->themeHandler->getBaseThemes($this->themeHandler->listInfo(), $default_theme);
    $theme_directories = $this->themeHandler->getThemeDirectories();

    $directories = [];
    if (isset($theme_directories[$default_theme])) {
      $directories[$default_theme] = $theme_directories[$default_theme];
      foreach ($base_themes as $name => $theme) {
        $directories[$name] = $theme_directories[$name];
      }
    }

    return $directories + $this->moduleHandler->getModuleDirectories();
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id, $validate = TRUE) {
    $this->processDefinitionCategory($definition);
    // You can add validation of the plugin definition here.
    if (empty($definition['id'])) {
      throw new PluginException(sprintf('eXo Component plugin (%s) definition "id" is required.', $plugin_id));
    }
    $definition = new ExoComponentDefinition($definition);
    $this->exoComponentFieldManager->processComponentDefinition($definition, $validate);
    $this->exoComponentPropertyManager->processComponentDefinition($definition);
    $this->exoComponentEnhancementManager->processComponentDefinition($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function processInstalledDefinition(&$definition, $plugin_id) {
    /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition */
    $definition['installed'] = TRUE;
    $this->processDefinition($definition, $plugin_id);
    $definition->setMissing(!$this->hasDefinition($definition->id()));
    $definition->addContextDefinition('tags', $this->getTagConstraint($definition));
    foreach ($definition->getFields() as $field) {
      $component_field = $this->exoComponentFieldManager->createFieldInstance($field);
      foreach ($component_field->getContextDefinitions() as $name => $context) {
        $definition->addContextDefinition($name, $context);
      }
    }
  }

  /**
   * Get tag constraint.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinition
   *   The definition.
   */
  protected function getTagConstraint(ExoComponentDefinition $definition) {
    $region_size = new ContextDefinition('map');
    $tags = $definition->getTags();
    $size_tags = [
      'all',
      'full',
      'large',
      'medium',
      'small',
    ];
    if (!array_intersect($tags, $size_tags)) {
      // The default size tag is full.
      $tags[] = 'full';
    }
    $region_size->addConstraint('ExoComponentTag', $tags);
    return $region_size;
  }

  /**
   * Check access on a definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param string $operation
   *   The operation. Can be 'create', 'update', 'delete', 'view', field:name.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessDefinition(ExoComponentDefinition $definition, $operation, AccountInterface $account = NULL) {
    if (empty($account)) {
      $account = \Drupal::currentUser();
    }
    if ($definition->isMissing()) {
      return AccessResult::allowedIf($operation == 'delete')
        ->andIf(AccessResult::allowedIfHasPermission($account, 'administer exo alchemist'));
    }
    if (substr($operation, 0, 6) === 'field.') {
      [$operation, $field_name] = explode('.', $operation);
      $field = $definition->getFieldBySafeId($field_name);
      if (!$field) {
        return AccessResult::forbidden('The definition does not contain a field with the safe id of ' . $field_name . '.');
      }
    }
    switch ($operation) {
      case 'create':
        return AccessResult::allowedIf(!$definition->isInstalled())
          ->andIf(AccessResult::allowedIfHasPermission($account, 'administer exo alchemist'));

      case 'update':
        if (!$this->loadEntity($definition)) {
          return AccessResult::allowed();
        }
        return AccessResult::allowedIf($definition->isInstalled() && $this->installedDefinitionHasChanges($definition))
          ->andIf(AccessResult::allowedIfHasPermission($account, 'administer exo alchemist'));
    }
    return AccessResult::allowedIf($definition->isInstalled())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'administer exo alchemist'));
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    parent::clearCachedDefinitions();
    Cache::invalidateTags(['library_info']);
    PhpStorageFactory::get('twig')->deleteAll();
    \Drupal::service('theme.registry')->reset();
    $this->definitionsInstalled = NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCachedInstalledDefinitions() {
    if (!isset($this->definitionsInstalled) && $cache = $this->cacheGet($this->cacheKey . '_installed')) {
      $this->definitionsInstalled = $cache->data;
    }
    return $this->definitionsInstalled;
  }

  /**
   * {@inheritdoc}
   */
  protected function setCachedInstalledDefinitions($definitions) {
    $this->cacheSet($this->cacheKey . '_installed', $definitions, Cache::PERMANENT, $this->cacheTags);
    $this->definitionsInstalled = $definitions;
  }

  /**
   * Extract component definition from a config entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity bundle to load the definition from.
   * @param bool $no_cache
   *   If TRUE, will build component definition directly from the provided
   *   entity.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   *   The component definition.
   */
  public function getEntityBundleComponentDefinition(ConfigEntityInterface $entity, $no_cache = FALSE) {
    $definition = NULL;
    if ($definition = ExoComponentInstalledDiscovery::getEntityDefinition($entity)) {
      if ($no_cache) {
        $this->processInstalledDefinition($definition, $definition['id']);
      }
      else {
        $definition = $this->getInstalledDefinition($definition['id']);
      }
    }
    return $definition;
  }

  /**
   * Extract component definition from a content entity.
   *
   * @param \Drupal\Core\Config\Entity\ContentEntityInterface $entity
   *   The entity to load the definition from.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinition
   *   The component definition.
   */
  public function getEntityComponentDefinition(ContentEntityInterface $entity) {
    $plugin_id = $this->getPluginIdFromSafeId($entity->bundle());
    return $this->getInstalledDefinition($plugin_id, FALSE);
  }

  /**
   * Get property info.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   */
  public function getPropertyInfo(ExoComponentDefinition $definition) {
    $info = [
      '_global' => [
        'label' => $this->t('Component'),
        'properties' => [
          'instance_id' => $this->t('Component instance id. Will be different per implementation.'),
          'user' => $this->t('The current user account.'),
          'component_path' => $this->t('The component path.'),
          'logged_in' => $this->t('Flag for authenticated user status. Will be true when the current user is a logged-in member.'),
          'is_admin' => $this->t('Flag for admin user status. Will be true when the current user is an administrator.'),
          'attributes' => $this->t('Component attributes.'),
          'content_attributes' => $this->t('Component content attributes.'),
          'preview' => $this->t('Will be TRUE when in layout mode.'),
          'preview_field_attributes.%' => $this->t('When in preview and a field is empty, the attributes of that field can be found here to allow the field to be editable.'),
        ],
      ],
    ];
    $info += $this->exoComponentFieldManager->getPropertyInfo($definition);
    $info += $this->exoComponentPropertyManager->getPropertyInfo($definition);
    $info += $this->exoComponentEnhancementManager->getPropertyInfo($definition);
    $info += $this->exoComponentAnimationManager->getPropertyInfo($definition);
    if ($handler = $definition->getHandler()) {
      $handler->propertyInfoAlter($info);
    }
    return $info;
  }

  /**
   * Load content type bundle for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The config entity.
   */
  public function loadEntityType(ExoComponentDefinition $definition) {
    $storage = $this->entityTypeManager->getStorage(self::ENTITY_BUNDLE_TYPE);
    return $storage->load($definition->safeId());
  }

  /**
   * Install content type bundle for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The config entity.
   */
  public function installEntityType(ExoComponentDefinition $definition) {
    $entity = $this->loadEntityType($definition);
    // This can be called even when an entity type is already installed. It can
    // be called over and over and will only run if entity has not yet been
    // created.
    if (!$entity) {
      $storage = $this->entityTypeManager->getStorage(self::ENTITY_BUNDLE_TYPE);
      $entity = $storage->create([
        'id' => $definition->safeId(),
        'label' => $definition->getLabel(),
        'description' => $definition->getDescription(),
      ]);
      // We do not want ExoComponentGenerator building our entity too soon.
      $entity->exoComponentInstalling = TRUE;
      // We allow fields to act on install AFTER the entity type has been saved.
      // This is intentional and should not be changed.
      $this->exoComponentFieldManager->installEntityType($definition, $entity);
      $this->saveEntityType($definition, $entity, NULL);
      // Save again now that everything is built and ready to roll.
      $entity->exoComponentInstalling = FALSE;
      $entity->exoComponentRebuilding = TRUE;
      // We call again so that fields are added.
      $this->saveEntityType($definition, $entity, NULL);
    }
    return $entity;
  }

  /**
   * Update content type bundle for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface|null
   *   The config entity.
   */
  public function updateEntityType(ExoComponentDefinition $definition) {
    if ($entity = $this->loadEntityType($definition)) {
      // Clean up all dependents as they are rebuilt each time.
      $this->cleanEntityTypeDependents($entity);
      $this->exoComponentFieldManager->updateEntityType($definition, $entity);
      // Flag this entity for rebuild.
      // @see \Drupal\exo_alchemist\ExoComponentGenerator::handleComponentTypeEntityPostSave()
      $entity->exoComponentRebuilding = TRUE;
      $this->saveEntityType($definition, $entity, $this->getEntityBundleComponentDefinition($entity));
      return $entity;
    }
    return NULL;
  }

  /**
   * Save content type bundle for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity bundle to load the definition from.
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $original_definition
   *   The current component definition.
   */
  protected function saveEntityType(ExoComponentDefinition $definition, ConfigEntityInterface $entity, ExoComponentDefinition $original_definition = NULL) {
    $entity->setThirdPartySetting('exo_alchemist', 'exo_component_definition', $definition->toArray());
    if ($dependents = $definition->calculateDependents()) {
      $entity->setThirdPartySetting('exo_alchemist', 'exo_component_dependents', $dependents);
    }
    else {
      $entity->unsetThirdPartySetting('exo_alchemist', 'exo_component_dependents');
    }
    if (empty($entity->exoComponentInstalling)) {
      $this->buildEntityType($definition, $original_definition);
    }
    $entity->save();
  }

  /**
   * Uninstall content type bundle for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   */
  public function uninstallEntityType(ExoComponentDefinition $definition) {
    if ($entity = $this->loadEntityType($definition)) {
      $entity_storage = $this->entityTypeManager->getStorage(self::ENTITY_TYPE);
      // Delete all entities belonging to this entity type.
      $entities = $entity_storage->loadByProperties(['type' => $entity->id()]);
      if (!empty($entities)) {
        $entity_storage->delete($entities);
      }
      // Clean up all dependents as they are rebuilt each time.
      $this->cleanEntityTypeDependents($entity);
      $this->exoComponentFieldManager->uninstallEntityType($definition, $entity);
      // Delete entity type.
      $entity->delete();
      $this->clearCachedDefinitions();
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Move thumbnail into files directory.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   */
  public function installThumbnail(ExoComponentDefinition $definition) {
    // Clean up existing thumbnail first.
    $this->uninstallThumbnail($definition);
    if (!$definition->isComputed()) {
      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      $directory = $definition->getThumbnailDirectory();
      if ($thumbnail = $definition->getThumbnailSource()) {
        $path = Url::fromUri('base://' . ltrim($thumbnail, '/'));
        $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $is_writable = is_writable($directory);
        $is_directory = is_dir($directory);
        if (!$is_writable || !$is_directory) {
          if (!$is_directory) {
            $error = t('The directory %directory does not exist.', ['%directory' => $directory]);
          }
          else {
            $error = t('The directory %directory is not writable.', ['%directory' => $directory]);
          }
          $description = t('An automated attempt to create this directory failed, possibly due to a permissions problem. To proceed with the installation, either create the directory and modify its permissions manually or ensure that the installer has the permissions to create it automatically. For more information, see INSTALL.txt or the <a href=":handbook_url">online handbook</a>.', [':handbook_url' => 'https://www.drupal.org/server-permissions']);
          $description = $error . ' ' . $description;
          \Drupal::messenger()->addError($description);
        }
        else {
          $file_system->copy(\Drupal::root() . $path->toString(), $definition->getThumbnailUri());
        }
      }
    }
  }

  /**
   * Remove thumbnail from files directory.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   */
  public function uninstallThumbnail(ExoComponentDefinition $definition) {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $directory = $definition->getThumbnailDirectory();
    try {
      if (file_exists($directory)) {
        $file_system->deleteRecursive($directory);
      }
    }
    catch (FileException $e) {
      // Fail silently.
    }
    if ($image_style = $this->entityTypeManager->getStorage('image_style')->load('exo_alchemist_preview')) {
      /** @var \Drupal\Image\Entity\ImageStyle $image_style */
      $image_style->flush($definition->getThumbnailUri());
    }
  }

  /**
   * Clean content type bundle of dependents.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity bundle to load the definition from.
   */
  protected function cleanEntityTypeDependents(ConfigEntityInterface $entity) {
    $dependents = $entity->getThirdPartySetting('exo_alchemist', 'exo_component_dependents');
    if ($dependents) {
      $config_factory = \Drupal::configFactory();
      $config_manager = \Drupal::service('config.manager');
      foreach ($dependents as $type => $names) {
        foreach ($names as $name) {
          switch ($type) {
            case 'config':
              $entity = $config_manager->loadConfigEntityByName($name);
              if ($entity) {
                $entity->delete();
              }
              else {
                $config = $config_factory->getEditable($name);
                if ($config) {
                  $config->delete();
                }
              }
              break;

            case 'content':
              [$entity_id,, $uuid] = explode(':', $name);
              $entity = $this->entityRepository->loadEntityByConfigTarget($entity_id, $uuid);
              if ($entity) {
                $entity->delete();
              }
              break;

            default:
              throw new \Exception('Only content and config dependents are supported.');
          }
        }
      }
    }
  }

  /**
   * Build content type bundle as defined in definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $original_definition
   *   The current component definition.
   */
  public function buildEntityType(ExoComponentDefinition $definition, ExoComponentDefinition $original_definition = NULL) {
    $form_display = $this->getEntityTypeFormDisplay($definition);
    $view_display = $this->getEntityTypeViewDisplay($definition);
    $this->exoComponentFieldManager->buildEntityType($definition, $form_display, $view_display, $original_definition);
    $this->exoComponentPropertyManager->buildEntityType($definition, $form_display, $view_display, $original_definition);
    if (count($form_display->getComponents())) {
      $form_display->save();
    }
    if (count($view_display->getComponents())) {
      $view_display->save();
    }
  }

  /**
   * Get the entity form display.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The form display.
   */
  public function getEntityTypeFormDisplay(ExoComponentDefinition $definition) {
    $entity_type = ExoComponentManager::ENTITY_TYPE;
    $bundle = $definition->safeId();
    $storage = $this->entityTypeManager->getStorage('entity_form_display');
    $form_display = $storage->load($entity_type . '.' . $bundle . '.default');
    if (!$form_display) {
      $form_display = $storage->create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    return $form_display;
  }

  /**
   * Get the entity view display.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The view display.
   */
  public function getEntityTypeViewDisplay(ExoComponentDefinition $definition) {
    $entity_type = ExoComponentManager::ENTITY_TYPE;
    $bundle = $definition->safeId();
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    $view_display = $storage->load($entity_type . '.' . $bundle . '.default');
    if (!$view_display) {
      $view_display = $storage->create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    return $view_display;
  }

  /**
   * Get the field changes given a definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $to_definition
   *   The component definition.
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition|null $from_definition
   *   The component definition.
   *
   * @return array
   *   An array containing ['add' => [], 'update' => [], 'remove' => []].
   */
  public function getEntityBundleFieldChanges(ExoComponentDefinition $to_definition, ExoComponentDefinition $from_definition = NULL) {
    return $this->exoComponentFieldManager->getEntityBundleFieldChanges($to_definition, $from_definition);
  }

  /**
   * Load default content for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param bool $no_cache
   *   Flag indicating if entity should be cached.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   */
  public function loadEntity(ExoComponentDefinition $definition, $no_cache = FALSE) {
    $entity = NULL;
    $storage = $this->entityTypeManager->getStorage(self::ENTITY_TYPE);
    $properties = [
      'type' => $definition->safeId(),
      'alchemist_default' => TRUE,
    ];
    if ($path = $definition->getParentsAsPath()) {
      $properties['alchemist_path'] = $path;
    }
    $entities = $storage->loadByProperties($properties);
    if (!empty($entities)) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = reset($entities);
      if ($no_cache) {
        $entity = $storage->loadUnchanged($entity->id());
      }
    }
    if ($entity) {
      $entity->alchemistDefinition = $definition;
    }
    return $entity;
  }

  /**
   * Load the global entity.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Optional entity. If not supplied, default will be used.
   * @param bool $all
   *   Flag that determines if this is a partial clone or full clone. For
   *   example, existing media will be reused if set to FALSE. New media will
   *   be created if set to TRUE.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   */
  public function loadGlobalEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity = NULL, $all = FALSE) {
    $entity = NULL;
    $storage = $this->entityTypeManager->getStorage(self::ENTITY_TYPE);
    $query = $storage->getQuery();
    $query->condition('type', $definition->safeId());
    $query->condition('alchemist_default', FALSE);
    if ($path = $definition->getParentsAsPath()) {
      $query->condition('alchemist_path', $path);
    }
    $query->accessCheck(FALSE);
    $query->range(0, 1);
    $ids = $query->execute();
    if (!empty($ids)) {
      $id = reset($ids);
      $entity = $storage->load($id);
    }
    else {
      $entity = $this->cloneEntity($definition, $entity, $all);
      /** @var \Drupal\block_content\Entity\BlockContent $entity */
      $entity->setReusable();
    }
    if ($entity) {
      $entity->alchemistDefinition = $definition;
    }
    return $entity;
  }

  /**
   * Load default content for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param bool $no_cache
   *   Flag indicating if entity should be cached.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   */
  public function loadEntityMultiple(ExoComponentDefinition $definition, $no_cache = FALSE) {
    $storage = $this->entityTypeManager->getStorage(self::ENTITY_TYPE);
    $properties = [
      'type' => $definition->safeId(),
      'alchemist_default' => TRUE,
    ];
    $entities = $storage->loadByProperties($properties);
    if ($no_cache) {
      $storage->resetCache(array_keys($entities));
    }
    return $entities;
  }

  /**
   * Build content entity for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   */
  public function buildEntity(ExoComponentDefinition $definition) {
    $entity = $this->loadEntity($definition);
    if (!$entity) {
      $storage = $this->entityTypeManager->getStorage(self::ENTITY_TYPE);
      $entity = $storage->create([
        'type' => $definition->safeId(),
        'info' => 'Preview for ' . $definition->getLabel(),
        'reusable' => FALSE,
        'alchemist_default' => TRUE,
        'alchemist_path' => $definition->getParentsAsPath(),
      ]);
    }
    /** @var \Drupal\core\Entity\ContentEntityInterface $entity */
    $this->populateEntity($definition, $entity);
    return $entity;
  }

  /**
   * Build content entity for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to populate.
   * @param bool $save
   *   Flag to save entity after population.
   */
  public function populateEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity, $save = TRUE) {
    // Populate properties before fields as we use the form_display save hook
    // to create the default entity.
    $this->exoComponentPropertyManager->populateEntity($definition, $entity);
    $this->exoComponentFieldManager->populateEntity($definition, $entity);
    if ($save) {
      $entity->save();
    }
  }

  /**
   * Called on update while layout building.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to populate.
   */
  public function onDraftUpdateLayoutBuilderEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity) {
    $this->exoComponentFieldManager->onDraftUpdateLayoutBuilderEntity($definition, $entity);
  }

  /**
   * Clone the default content for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Optional entity. If not supplied, default will be used.
   * @param bool $all
   *   Flag that determines if this is a partial clone or full clone. For
   *   example, existing media will be reused if set to FALSE. New media will
   *   be created if set to TRUE.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   */
  public function cloneEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity = NULL, $all = FALSE) {
    $entity = $entity ? $entity : $this->loadEntity($definition);
    if ($entity) {
      $entity = $entity->createDuplicate();
      $entity->set('info', 'Instance of ' . $definition->getLabel());
      $entity->set('alchemist_default', FALSE);
      $this->exoComponentFieldManager->cloneEntityFields($definition, $entity, $all);
    }
    return $entity;
  }

  /**
   * Restore the default content for fields that are empty.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to restore.
   * @param bool $force
   *   If TRUE, will force restore.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   */
  public function restoreEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity, $force = FALSE) {
    $force = $force ?: $definition->isGlobal();
    $this->exoComponentFieldManager->restoreEntityFields($definition, $entity, $force);
    return $entity;
  }

  /**
   * Event handler for entities containing components.
   *
   * @param string $op
   *   The operation to perform.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function handleEntityEvent($op, EntityInterface $entity) {
    $this->handleEntityCallback($entity, 'on' . ucfirst($op) . 'LayoutBuilderEntity');
  }

  /**
   * Callback handler for entities containing components.
   *
   * The entity is the entity where layout builder is enabled.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $callback
   *   The callback to fire on the component field manager.
   */
  protected function handleEntityCallback(EntityInterface $entity, $callback) {
    if (!$this->isLayoutCompatibleEntity($entity)) {
      return;
    }
    if ($sections = $this->getEntitySections($entity)) {
      foreach ($this->getValidBlockComponents($sections) as $component) {
        $component_entity = $this->entityLoadFromComponent($component);
        /** @var \Drupal\Core\Entity\EntityInterface $component_entity */
        if ($component_entity) {
          $plugin_id = $this->getPluginIdFromSafeId($component_entity->bundle());
          if ($definition = $this->getInstalledDefinition($plugin_id, FALSE)) {
            if (method_exists($this->exoComponentFieldManager, $callback)) {
              $this->exoComponentFieldManager->{$callback}($definition, $component_entity, $entity);
            }
          }
          // If the component entity has been changed we want to store those
          // changes so that they are saved.
          $this->entitySetToComponent($component, $component_entity);
        }
      }
    }
  }

  /**
   * Gets components that have Inline Block plugins.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   The layout sections.
   *
   * @return \Drupal\layout_builder\SectionComponent[]
   *   The components that contain Inline Block plugins.
   */
  protected function getValidBlockComponents(array $sections) {
    $inline_block_components = [];
    foreach ($sections as $section) {
      foreach ($section->getComponents() as $component) {
        $plugin = $component->getPlugin();
        if ($plugin instanceof DerivativeInspectionInterface && in_array($plugin->getBaseId(), [
          'inline_block',
          'global_block',
        ])) {
          $inline_block_components[] = $component;
        }
      }
    }
    return $inline_block_components;
  }

  /**
   * Uninstall content entity for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param bool $update
   *   If this is a field update.
   */
  public function cleanEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity, $update = TRUE) {
    $this->exoComponentFieldManager->cleanEntityFields($definition, $entity, $update);
  }

  /**
   * Check if entity should be displayed.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   * @param bool $return_as_object
   *   If TRUE, will return access as object.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessEntity(ExoComponentDefinition $definition, ContentEntityInterface $entity, array $contexts, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?: \Drupal::currentUser();
    $access = AccessResult::allowed();
    if ($definition->hasFields()) {
      $access = $this->exoComponentFieldManager->accessEntity($definition, $entity, $contexts, $account, TRUE);
    }
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * View content entity for definition.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param array $build
   *   The build array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   */
  public function viewEntity(ExoComponentDefinition $definition, array &$build, ContentEntityInterface $entity, array $contexts) {
    $is_layout_builder = $this->isLayoutBuilder($contexts);
    $is_preview = $this->isPreview($contexts);
    $build += ['#attached' => []];
    $build['#theme'] = $definition->getThemeHook();
    $build['#theme_wrappers'][] = 'exo_component_wrapper';

    $build['#exo_component'] = $definition;
    $build['#instance_id'] = $entity->uuid();
    $build['#component_path'] = $definition['path'];
    $build['#wrapper_attributes']['class'][] = 'exo-component-wrapper';
    if ($is_layout_builder || $is_preview) {
      $build['#wrapper_attributes']['class'][] = 'exo-component-wrapper-preview';
    }
    $build['#attributes']['class'][] = 'exo-component';
    if ($definition->isExtended()) {
      $extend_definition = $this->getDefinition($definition->extendId());
      $build['#wrapper_attributes']['class'][] = Html::getClass('exo-component-wrapper-' . $extend_definition->getName());
      $build['#attributes']['class'][] = Html::getClass('exo-component-' . $extend_definition->getName());
      if ($extend_definition->hasLibrary()) {
        $build['#attached']['library'][] = 'exo_alchemist/' . $extend_definition->getLibraryId();
      }
    }
    $build['#wrapper_attributes']['class'][] = Html::getClass('exo-component-wrapper-' . $definition->getName());
    $build['#attributes']['class'][] = Html::getClass('exo-component-' . $definition->getName());
    $build['#content_attributes']['class'][] = 'exo-component-content';
    $build['#preview'] = $is_layout_builder || $is_preview;
    $build['#preview_field_attributes'] = [];
    if ($definition->hasLibrary()) {
      $build['#attached']['library'][] = 'exo_alchemist/' . $definition->getLibraryId();
    }
    if ($this->isDefaultStorage($contexts)) {
      if (isset($contexts['layout_builder.entity'])) {
        $layout_entity = $contexts['layout_builder.entity']->getContextValue();
        if ($preview_entity = $this->getPreviewEntity($layout_entity->getEntityTypeId(), $layout_entity->bundle())) {
          $contexts['layout_builder.entity'] = EntityContext::fromEntity($preview_entity);
        }
      }
    }
    elseif ($definition->getContextDefinitions()) {
      // Preview with proper entity when component has entity context.
      if ($is_preview && isset($contexts['layout_builder.entity']) && $definition->hasContextDefinition('entity')) {
        $layout_entity = $contexts['layout_builder.entity']->getContextValue();
        $preview_entity_type = str_replace('entity:', '', $definition->getContextDefinition('entity')->getDataType());
        if ($layout_entity && $preview_entity_type !== $layout_entity->getEntityTypeId() && $preview_entity_type !== 'entity') {
          $preview_bundles = $definition->getContextDefinition('entity')->getConstraint('Bundle') ?: [$preview_entity_type];
          if ($preview_entity = $this->getPreviewEntity($preview_entity_type, $preview_bundles)) {
            $contexts['layout_builder.entity'] = EntityContext::fromEntity($preview_entity);
            \Drupal::messenger()->addMessage($this->t('This component is being previewed using <a href="@url">@label</a>.', [
              '@url' => $preview_entity->getEntityType()->hasLinkTemplate('canonical') ? $preview_entity->toUrl()->toString() : '',
              '@label' => $preview_entity->getEntityType()->getLabel() . ': ' . $preview_entity->label(),
            ]), 'alchemist');
          }
        }
      }
    }
    $values = $this->viewEntityValues($definition, $entity, $contexts);
    foreach ($values as $key => $value) {
      if (Element::property($key)) {
        if (!isset($build[$key])) {
          $build[$key] = $value;
        }
        elseif (is_array($build[$key])) {
          $build[$key] = NestedArray::mergeDeep($build[$key], $value);
        }
        continue;
      }
      $build['#' . $key] = $value;
    }
    if ($is_layout_builder) {
      $ops = $this->getOperations();
      $build['#exo_component_ops'] = array_keys($ops);
      $build['#attached']['drupalSettings']['exoAlchemist']['componentOps'] = $ops;
      $build['#attached']['drupalSettings']['exoAlchemist']['isLayoutBuilder'] = TRUE;
    }
    if ($definition->getAdditionalValue('cache') === FALSE) {
      // Kill page cache.
      // @todo Remove once bubbling of element's max-age to page cache is fixed.
      // @see https://www.drupal.org/project/webform/issues/3015760
      // @see https://www.drupal.org/project/drupal/issues/2352009
      if (\Drupal::currentUser()->isAnonymous() && $this->moduleHandler()->moduleExists('page_cache')) {
        \Drupal::service('page_cache_kill_switch')->trigger();
      }
    }
  }

  /**
   * View content entity for definition as values.
   *
   * Values are broken out this way so sequence and other nested fields can
   * access the raw values before they are turned into attributes.
   *
   * @param \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition
   *   The component definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   */
  public function viewEntityValues(ExoComponentDefinition $definition, ContentEntityInterface $entity, array $contexts) {
    $values = [
      '#attached' => [],
      '#wrapper_attributes' => [],
      '#attributes' => [],
      '#content_attributes' => [],
    ];
    $this->exoComponentPropertyManager->alterEntityValues($definition, $entity, $contexts);
    $this->exoComponentFieldManager->viewEntityValues($definition, $values, $entity, $contexts);
    $this->exoComponentPropertyManager->viewEntityValues($definition, $values, $entity, $contexts);
    $this->exoComponentEnhancementManager->viewEntityValues($definition, $values, $entity, $contexts);
    $this->exoComponentAnimationManager->viewEntityValues($definition, $values, $entity, $contexts);
    if ($handler = $definition->getHandler()) {
      $handler->viewAlter($values, $definition, $entity, $contexts);
    }
    if ($this->isLayoutBuilder($contexts)) {
      $values['#access'] = TRUE;
    }
    return $values;
  }

  /**
   * Check if entity allows components to be removed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return bool
   *   TRUE if entity allows cleanup.
   */
  public function entityAllowCleanup(ContentEntityInterface $entity) {
    if (!$entity->getEntityType()->isRevisionable()) {
      return TRUE;
    }
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $vid_count = $storage->getQuery()->accessCheck(FALSE)->allRevisions()->condition($entity->getEntityType()->getKey('id'), $entity->id())->count()->execute();
    if ($vid_count == 1) {
      return TRUE;
    }
    if ($entity->getEntityTypeId() === 'block_content') {
      // For block content, we want to use the parent's cleanup as block content
      // always have revisions and they may not be necessary if the parent is
      // not revisionable or has no revisions.
      $usage = \Drupal::service('inline_block.usage')->getUsage($entity->id());
      if ($usage) {
        $parent_entity = $this->entityTypeManager->getStorage($usage->layout_entity_type)->load($usage->layout_entity_id);
        return $this->entityAllowCleanup($parent_entity);
      }
    }
    return FALSE;
  }

  /**
   * Get ops url placeholders.
   *
   * @return array
   *   An array of tokenized urls.
   */
  public function getOperations() {
    if (!isset($this->ops)) {
      $this->ops = [];
      $ops = [
        'appearance' => [
          'label' => $this->t('Appearance'),
          'route' => 'layout_builder.component.appearance',
          'description' => $this->t('Configure component appearance.'),
        ],
        'elements' => [
          'label' => $this->t('Elements'),
          'route' => 'layout_builder.component.fields',
          'description' => $this->t('Configure component elements.'),
        ],
        'filters' => [
          'label' => $this->t('Filters'),
          'route' => 'layout_builder.component.filters',
          'description' => $this->t('Configure component filters.'),
        ],
        'move' => [
          'label' => $this->t('Move'),
          'route' => 'layout_builder.component.move',
          'description' => $this->t('Reorder components.'),
        ],
        'clone' => [
          'label' => $this->t('Clone'),
          'route' => 'layout_builder.component.clone',
          'description' => $this->t('Clone component.'),
        ],
        'restore' => [
          'label' => $this->t('Restore'),
          'route' => 'layout_builder.component.restore',
          'description' => $this->t('Are you sure you want to proceed?'),
        ],
        'remove' => [
          'label' => $this->t('Remove'),
          'route' => 'layout_builder.component.remove',
          'description' => $this->t('Are you sure you want to proceed?'),
        ],
      ];
      $this->moduleHandler->alter('exo_component_component_ops', $ops);
      foreach ($ops as $key => $data) {
        $icon = new ExoIconTranslatableMarkup($data['label']);
        $icon = $icon->match([
          'exo_alchemist',
          'local_task',
          'admin',
        ], $key);
        $this->ops[$key] = [
          'label' => $data['label'],
          'description' => !empty($data['description']) ? $data['description'] : '',
          'url' => explode('?', Url::fromRoute($data['route'], [
            'section_storage_type' => '-section_storage_type-',
            'section_storage' => '-section_storage-',
            'delta' => '-delta-',
            'region' => '-region-',
            'uuid' => '-uuid-',
            'block_uuid' => '-uuid-',
            'preceding_block_uuid' => '-next_uuid-',
            'delta_from' => '-delta-',
            'delta_to' => '-delta-',
            'region_to' => '-region-',
          ])->toString())[0],
          'title' => $icon->toString(),
          'icon' => $icon->getIcon() ? $icon->getIcon()->getId() : '',
        ];
      }
    }
    return $this->ops;
  }

  /**
   * Load a component entity by revision id.
   *
   * @param string $revision_id
   *   The revision id.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The component entity.
   */
  public function entityLoadByRevisionId($revision_id) {
    return $this->entityTypeManager->getStorage(self::ENTITY_TYPE)->loadRevision($revision_id);
  }

  /**
   * Load a component entity by uuid.
   *
   * @param string $uuid
   *   The uuid.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The component entity.
   */
  public function entityLoadByUuid($uuid) {
    return $this->entityRepository->loadEntityByUuid('block_content', $uuid);
  }

  /**
   * Load a component entity from a component.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component.
   * @param bool $allow_revision
   *   If true, revision id will be used.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The component entity.
   */
  public function entityLoadFromComponent(SectionComponent $component, $allow_revision = TRUE) {
    $entity = NULL;
    $configuration = $component->get('configuration');
    if (!empty($configuration['block_serialized'])) {
      $entity = unserialize($configuration['block_serialized']);
    }
    elseif (!empty($configuration['block_uuid'])) {
      $entity = $this->entityLoadByUuid($configuration['block_uuid']);
    }
    elseif (!empty($configuration['block_revision_id']) && $allow_revision) {
      $entity = $this->entityLoadByRevisionId($configuration['block_revision_id']);
    }
    return $entity;
  }

  /**
   * Set a component entity to a component.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component entity.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   The section component.
   */
  public function entitySetToComponent(SectionComponent $component, ContentEntityInterface $entity) {
    $configuration = $component->get('configuration');
    if (!empty($configuration['block_serialized'])) {
      // We only act on components that have a serialized version of the entity.
      // Other entities are static and should be able to be changed without
      // storing in the component.
      $configuration['block_serialized'] = serialize($entity);
      unset($configuration['block_uuid']);
      unset($configuration['block_revision_id']);
      $component->setConfiguration($configuration);
    }
    return $component;
  }

  /**
   * Check if component is a exo component section.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component.
   *
   * @return bool
   *   TRUE if this is an exo component section.
   */
  public static function isExoComponent(SectionComponent $component) {
    $configuration = $component->get('configuration');
    $id_parts = explode(':', $configuration['id']);
    return isset($id_parts[1]) && substr($id_parts[1], 0, 4) == 'exo_';
  }

  /**
   * Convert a safe id to a plugin id.
   *
   * @param string $safe_id
   *   The safe id.
   *
   * @return string
   *   The plugin id.
   */
  public function getPluginIdFromSafeId($safe_id) {
    foreach ($this->getInstalledDefinitions() as $plugin_id => $definition) {
      if ($safe_id === $definition->safeId()) {
        return $plugin_id;
      }
    }
    return NULL;
  }

  /**
   * Get component data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component entity.
   *
   * @return array
   *   An array of field names.
   */
  public static function getFieldData(ContentEntityInterface $entity) {
    return $entity->hasField('alchemist_data') && !$entity->get('alchemist_data')->isEmpty() ? $entity->get('alchemist_data')->first()->getValue() : [];
  }

  /**
   * Set component data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component entity.
   * @param array $data
   *   An array of values.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|false
   *   Will return the entity if value successfully set.
   */
  public static function setFieldData(ContentEntityInterface $entity, array $data) {
    if ($entity->hasField('alchemist_data')) {
      $entity->get('alchemist_data')->setValue([$data]);
      return $entity;
    }
    return FALSE;
  }

}
