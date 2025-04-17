<?php

namespace Drupal\exo_site_settings;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Defines the storage handler class for exo config page entities.
 *
 * This extends the base storage class, adding required special handling for
 * exo config page entities.
 *
 * @ingroup exo_site_settings
 */
class SiteSettingsTypeStorage extends ConfigEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(?array $ids = NULL) {
    $entities = parent::doLoadMultiple($ids);
    uasort($entities, 'Drupal\exo_site_settings\Entity\SiteSettingsType::sort');
    return $entities;
  }

  /**
   * Builds an entity query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $entity_query
   *   EntityQuery instance.
   * @param array $values
   *   An associative array of properties of the entity, where the keys are the
   *   property names and the values are the values those properties must have.
   */
  protected function buildPropertyQuery(QueryInterface $entity_query, array $values) {
    parent::buildPropertyQuery($entity_query, $values);
    $entity_query->sort('weight');
  }

}
