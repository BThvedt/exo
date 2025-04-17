<?php

namespace Drupal\exo_list_builder;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Define field type export manager interface.
 */
interface ExoListManagerInterface extends PluginManagerInterface {

  /**
   * Get definition options.
   *
   * @return array
   *   An array of definition options.
   */
  public function getOptions();

  /**
   * Get definition options for a given field type.
   *
   * @param string $field_type
   *   The field type.
   * @param string $entity_type
   *   The entity type id.
   * @param array $bundle
   *   The bundle id.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   An array of definition options.
   */
  public function getFieldOptions($field_type, $entity_type = NULL, ?array $bundles = NULL, $field_name = NULL);

}
