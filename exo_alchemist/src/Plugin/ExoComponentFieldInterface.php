<?php

namespace Drupal\exo_alchemist\Plugin;

use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\exo_alchemist\Command\ExoComponentCommand;
use Drupal\exo_alchemist\ExoComponentContextInterface;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Defines an interface for Component Field plugins.
 */
interface ExoComponentFieldInterface extends PluginInspectionInterface, ContextAwarePluginInterface, CacheableDependencyInterface, ExoComponentContextInterface {

  /**
   * Get the field definition.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField
   *   The field definition.
   */
  public function getFieldDefinition();

  /**
   * Get component field definition by component field name.
   *
   * @param string $name
   *   The component field name.
   *
   * @return \Drupal\exo_alchemist\Definition\ExoComponentDefinitionField|null
   *   The component field definition.
   */
  public function getComponentFieldDefinition($name);

  /**
   * Process a component field definition.
   */
  public function processDefinition();

  /**
   * Runs when fields are being checked for changes.
   *
   * @param array $changes
   *   The changes array.
   * @param \Drupal\exo_alchemist\Plugin\ExoComponentFieldInterface $from_field
   *   The from field instance.
   * @param \Drupal\field\FieldStorageConfigInterface $from_storage
   *   The from field storage.
   * @param \Drupal\field\FieldConfigInterface $from_config
   *   The from field config.
   */
  public function onFieldChanges(array &$changes, ExoComponentFieldInterface $from_field, ?FieldStorageConfigInterface $from_storage = NULL, ?FieldConfigInterface $from_config = NULL);

  /**
   * Runs before install of the config entity used as the entity type.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity used as the entity type.
   */
  public function onInstall(ConfigEntityInterface $entity);

  /**
   * Runs before update of the config entity used as the entity type.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity used as the entity type.
   */
  public function onUpdate(ConfigEntityInterface $entity);

  /**
   * Runs before delete of the config entity used as the entity type.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity used as the entity type.
   */
  public function onUninstall(ConfigEntityInterface $entity);

  /**
   * Acts on field as it is installed.
   *
   * This method is called when a field is being added to a component.
   */
  public function onFieldInstall();

  /**
   * Acts on field before as is updated.
   *
   * This method is called when a field is being updated within a component.
   */
  public function onFieldUpdate();

  /**
   * Acts on field before as is uninstalled.
   *
   * This method is called when a field is being removed from a component.
   */
  public function onFieldUninstall();

  /**
   * Return component property info.
   *
   * @return array
   *   An array of property_id => description.
   */
  public function propertyInfo();

  /**
   * Build component parents.
   *
   * @return array
   *   The array of parents.
   */
  public function getParents();

  /**
   * Get component parents as string.
   *
   * @return string
   *   The path to a field.
   */
  public function getParentsAsPath();

  /**
   * Build component item parents.
   *
   * @param string $delta
   *   The delta of the item being viewed.
   *
   * @return array
   *   The array of parents.
   */
  public function getItemParents($delta);

  /**
   * Get component item parents as string.
   *
   * @param string $delta
   *   The delta of the item being viewed.
   *
   * @return string
   *   The path to a field item.
   */
  public function getItemParentsAsPath($delta);

  /**
   * Build command and add questions as needed.
   */
  public static function buildCommand(ExoComponentCommand $command, array &$data);

  /**
   * Allows altering context of a component before being displayed.
   *
   * @param \Drupal\Core\Config\Entity\ContentEntityInterface $entity
   *   The config entity used as the entity type.
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   */
  public function alterContexts(ContentEntityInterface $entity, array &$contexts);

  /**
   * Check if field is editable.
   *
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   *
   * @return bool
   *   Returns TRUE if field is editable.
   */
  public function isEditable(array $contexts);

  /**
   * Check if field is removeable.
   *
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   *
   * @return bool
   *   Returns TRUE if field is removeable.
   */
  public function isRemoveable(array $contexts);

  /**
   * Check if field is removeable.
   *
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   *
   * @return bool
   *   Returns TRUE if field is removeable.
   */
  public function isHideable(array $contexts);

  /**
   * Check if field is computed.
   *
   * @return bool
   *   Returns TRUE if field is computed.
   */
  public function isComputed();

  /**
   * Check if field is required.
   *
   * @return bool
   *   Returns TRUE if field is required.
   */
  public function isRequired();

  /**
   * Check if field is invisible.
   *
   * @return bool
   *   Returns TRUE if field is invisible.
   */
  public function isInvisible();

  /**
   * Check if field is locked within default storage interface.
   *
   * Locked means the field cannot be edited.
   *
   * @return bool
   *   Returns TRUE if storage is locked.
   */
  public function isDefaultStorageLocked();

}
