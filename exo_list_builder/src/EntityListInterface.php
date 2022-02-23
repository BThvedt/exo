<?php

namespace Drupal\exo_list_builder;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining eXo Entity List entities.
 */
interface EntityListInterface extends ConfigEntityInterface {

  /**
   * Get the target entity type id.
   *
   * @return string
   *   The entity type id.
   */
  public function getTargetEntityTypeId();

  /**
   * Get the target entity type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   *   The target entity type definition.
   */
  public function getTargetEntityType();

  /**
   * Get the target bundle ids.
   *
   * @return array
   *   The target bundle ids.
   */
  public function getTargetBundleIds();

  /**
   * Get the target bundle ids to include.
   *
   * @return array
   *   The target bundle ids to include.
   */
  public function getTargetBundleIncludeIds();

  /**
   * Get the target bundle ids to exclude.
   *
   * @return array
   *   The target bundle ids to exclude.
   */
  public function getTargetBundleExcludeIds();

  /**
   * Check if we are using all bundles.
   *
   * @return bool
   *   All bundles if TRUE.
   */
  public function isAllBundles();

  /**
   * Check if entity list support overriding the list builder.
   *
   * @return bool
   *   Return TRUE if the entity list support overriding the list builder.
   */
  public function allowOverride();

  /**
   * Check if the entity should be used as the list builder for the target type.
   *
   * @return bool
   *   Returns TRUE if the entity should be used as the list builder.
   */
  public function isOverride();

  /**
   * Return the entity list url.
   *
   * @returns tring
   *   The entity list url.
   */
  public function getUrl();

  /**
   * Get items per page.
   *
   * @return int
   *   The number of items per page.
   */
  public function getLimit();

  /**
   * Get enabled action definitions.
   *
   * @return array
   *   The action ddefinitions.
   */
  public function getActions();

  /**
   * Get available action definitions.
   *
   * @return array
   *   The action ddefinitions.
   */
  public function getAvailableActions();

  /**
   * Get the sort default field.
   *
   * @return string
   *   The sort default field id.
   */
  public function getSort();

  /**
   * Get enabled field definitions.
   *
   * @return array
   *   The field definitions.
   */
  public function getFields();

  /**
   * Set enabled field definitions.
   *
   * @param array $fields
   *   The field definitions.
   *
   * @return $this
   */
  public function setFields(array $fields);

  /**
   * Get available field definitions.
   *
   * @return array
   *   The field definitions.
   */
  public function getAvailableFields();

  /**
   * Check if field is enabled.
   *
   * @return bool
   *   TRUE if the field is enabled.
   */
  public function hasField($field_name);

  /**
   * Get field definition.
   *
   * @return array
   *   The field definition.
   */
  public function getField($field_name);

  /**
   * Get field value.
   *
   * @param string $field_name
   *   The field name.
   * @param string $key
   *   The value key.
   *
   * @return mixed
   *   The field value.
   */
  public function getFieldValue($field_name, $key);

  /**
   * Gets the weight of this list.
   *
   * @return int
   *   The weight.
   */
  public function getWeight();

  /**
   * Sets the weight of this list.
   *
   * @param int $weight
   *   The weight of the variant.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Get the exo_list_buidler handler class.
   *
   * @return string
   *   The class.
   */
  public function getHandlerClass();

  /**
   * Get the exo_list_builder entity handler.
   *
   * @return \Drupal\exo_list_builder\ExoListBuilderInterface
   *   The entity handler.
   */
  public function getHandler();

}
