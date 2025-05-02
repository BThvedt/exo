<?php

namespace Drupal\exo_site_settings;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of config pages.
 *
 * @ingroup exo_site_settings
 */
class SiteSettingsListBuilder extends EntityListBuilder {

  /**
   * The entity storage class for exo_site_settings_type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $exoSiteSettingsTypeStorage;

  /**
   * {@inheritdoc}
   */
  protected $limit = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getStorage('exo_site_settings_type')
    );
  }

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The exo_site_settings entity storage class.
   * @param \Drupal\Core\Entity\EntityStorageInterface $ecpt_storage
   *   The exo_site_settings_type entity storage class.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityStorageInterface $ecpt_storage) {
    $this->entityTypeId = $entity_type->id();
    $this->storage = $storage;
    $this->exoSiteSettingsTypeStorage = $ecpt_storage;
    $this->entityType = $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\exo_site_settings\Entity\SiteSettings $entity */
    $row['name'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = [];
    $operations['edit'] = [
      'title' => t('Edit'),
      'weight' => 10,
      'query' => [],
      'url' => $entity->toUrl('page-form'),
    ];
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_ids = $this->getEntityIds();
    $entities = $this->exoSiteSettingsTypeStorage->loadMultiple($entity_ids);
    foreach ($entities as $key => $entity) {
      if ($entity->isAggregate() || !$entity->access('page_update')) {
        unset($entities[$key]);
      }
    }
    return $entities;
  }

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $query = $this->exoSiteSettingsTypeStorage->getQuery();
    return $query
      ->sort('weight')
      ->pager($this->limit)
      ->accessCheck(FALSE)
      ->execute();
  }

}
