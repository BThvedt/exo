<?php

namespace Drupal\exo_alchemist\Plugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_alchemist\ExoComponentValue;
use Drupal\exo_alchemist\ExoComponentValues;

/**
 * Defines an interface for Component Field plugins.
 */
interface ExoComponentFieldFieldableInterface extends ExoComponentFieldInterface {

  /**
   * Extending classes must use to return the storage values for the field.
   *
   * @return array
   *   An array of values to set to the FieldStorageConfig.
   */
  public function getStorageConfig();

  /**
   * Extending classes must use to return the values for the field.
   *
   * @return array
   *   An array of values to set to the FieldConfig.
   */
  public function getFieldConfig();

  /**
   * Extending classes should use to define the field widget config.
   *
   * @return array
   *   A field widget definition ['type' => string, 'settings' => []].
   */
  public function getWidgetConfig();

  /**
   * Extending classes should use to define the field formatter config.
   *
   * @return array
   *   A field widget definition ['type' => string, 'settings' => []].
   */
  public function getFormatterConfig();

  /**
   * Acts on field as the default component is being removed.
   *
   * This method is called when a component default is being removed.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items for the default component entity.
   * @param bool $update
   *   If this is a field update.
   */
  public function onFieldClean(FieldItemListInterface $items, $update = TRUE);

  /**
   * Operations that can be run before update during layout building.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   */
  public function onDraftUpdateLayoutBuilderEntity(FieldItemListInterface $items);

  /**
   * Return the default value of a field.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValues $values
   *   The field values.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   *
   * @return array
   *   A value that will be set to the Drupal default entity field.
   */
  public function populateValues(ExoComponentValues $values, FieldItemListInterface $items);

  /**
   * Return the default value of a field.
   *
   * @param string $delta
   *   The field item delta.
   *
   * @return array
   *   A value that will be set to the Drupal default entity field.
   */
  public function getDefaultValue($delta = 0);

  /**
   * Set component values on an entity.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValues $values
   *   The field values.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   */
  public function getValues(ExoComponentValues $values, FieldItemListInterface $items);

  /**
   * Extending classes can use this method to validate the component value.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValue $value
   *   The field value.
   */
  public function validateValue(ExoComponentValue $value);

  /**
   * Acts on field before it is updated.
   *
   * This method is called when a field is being updated from within a
   * layout-builder-enabled entity.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this entity.
   */
  public function onPreSaveLayoutBuilderEntity(FieldItemListInterface $items, EntityInterface $parent_entity);

  /**
   * Acts on field after it is updated.
   *
   * This method is called when a field is being updated from within a
   * layout-builder-enabled entity.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this entity.
   */
  public function onPostSaveLayoutBuilderEntity(FieldItemListInterface $items, EntityInterface $parent_entity);

  /**
   * Acts on field after it is deleted.
   *
   * This method is called when a field is being removed from within a
   * layout-builder-enabled entity.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this entity.
   */
  public function onPostDeleteLayoutBuilderEntity(FieldItemListInterface $items, EntityInterface $parent_entity);

  /**
   * Acts on field before it is cloned.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param bool $all
   *   Flag that determines if this is a partial clone or full clone.
   */
  public function onClone(FieldItemListInterface $items, $all = FALSE);

  /**
   * Acts on empty field to restore its values.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValues $values
   *   The field values.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param bool $force
   *   If TRUE, will force restore.
   */
  public function onFieldRestore(ExoComponentValues $values, FieldItemListInterface $items, $force = FALSE);

  /**
   * Return the values that will be passed to the component for display.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   *
   * @return array
   *   A value that will be sent to twig.
   */
  public function view(FieldItemListInterface $items, array $contexts);

  /**
   * Returns the value that will be passed to the component for display.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param string $delta
   *   The field item delta.
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   *
   * @return array
   *   A value that will be sent to twig.
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts);

  /**
   * Extending classes can use this method to return values for an empty item.
   *
   * Should reflect properties reflected in propertyInfo().
   *
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   *
   * @return array
   *   An array of key => value that will be passed as Twig variables.
   */
  public function viewEmptyValue(array $contexts);

  /**
   * Return a list of paths that require configuration before being added.
   *
   * @return string[]
   *   An array of field paths.
   */
  public function getRequiredPaths();

  /**
   * Return the default value of an item.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
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
  public function access(FieldItemListInterface $items, array $contexts, ?AccountInterface $account = NULL, $return_as_object = FALSE);

}
