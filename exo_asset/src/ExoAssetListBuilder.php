<?php

namespace Drupal\exo_asset;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\exo_asset\Entity\ExoAsset;

/**
 * Defines a class to build a listing of Asset entities.
 *
 * @ingroup exo_asset
 */
class ExoAssetListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['image'] = $this->t('Image');
    $header['image_mobile'] = $this->t('Mobile Image');
    $header['video'] = $this->t('Video');
    foreach (ExoAsset::getAttributeFields() as $field_definition) {
      $header[$field_definition->getName()] = $field_definition->getLabel();
    }
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\exo_asset\Entity\ExoAsset $entity */
    $row['id'] = $entity->id();
    $row['image'] = [];
    if (!$entity->image->isEmpty()) {
      $row['image']['data'] = $entity->image->view([
        'type' => 'media_thumbnail',
        'label' => 'hidden',
        'settings' => [
          'image_style' => 'exo_asset_preview',
        ],
      ]);
    }
    $row['image_mobile'] = [];
    if (!$entity->image_mobile->isEmpty()) {
      $row['image_mobile']['data'] = $entity->image_mobile->view([
        'type' => 'media_thumbnail',
        'label' => 'hidden',
        'settings' => [
          'image_style' => 'exo_asset_preview',
        ],
      ]);
    }
    $row['video'] = [];
    if (!$entity->video->isEmpty()) {
      $row['video']['data'] = $entity->video->view([
        'type' => 'media_thumbnail',
        'label' => 'hidden',
        'settings' => [
          'image_style' => 'exo_asset_preview',
        ],
      ]);
    }
    foreach (ExoAsset::getAttributeFields() as $field_definition) {
      $field_name = $field_definition->getName();
      $row[$field_name] = [];
      if (!$entity->{$field_name}->isEmpty()) {
        $row[$field_name]['data'] = $entity->{$field_name}->view([
          'type' => 'exo_attribute_string',
          'label' => 'hidden',
        ]);
      }
    }
    return $row + parent::buildRow($entity);
  }

}
