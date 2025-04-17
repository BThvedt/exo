<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\exo_alchemist\ExoComponentValue;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldFileTrait;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldImageStylesTrait;

/**
 * A 'image' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "image",
 *   label = @Translation("Image"),
 *   storage = {
 *     "type" = "image",
 *   },
 *   widget = {
 *     "type" = "image_image",
 *   },
 * )
 */
class Image extends EntityReferenceBase {

  use ExoComponentFieldFileTrait;
  use ExoComponentFieldImageStylesTrait;

  /**
   * The entity type to reference.
   *
   * @var string
   */
  protected $entityType = 'file';

  /**
   * {@inheritdoc}
   */
  public function getStorageConfig() {
    return [
      'type' => 'image',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldConfig() {
    $field = $this->getFieldDefinition();
    return [
      'settings' => [
        'file_directory' => $field->getType() . '/' . $field->getName(),
        'file_extensions' => 'png gif jpg jpeg',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetConfig() {
    return [
      'type' => 'image_image',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    parent::processDefinition();
    $field = $this->getFieldDefinition();
    $this->processDefinitionImageStyles($field);
  }

  /**
   * {@inheritdoc}
   */
  public function validateValue(ExoComponentValue $value) {
    parent::validateValue($value);
    if ($value->get('value')) {
      $value->set('path', $value->get('value'));
      $value->unset('value');
    }
    $this->validateValueFile($value, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = parent::propertyInfo();
    $properties['url'] = $this->t('The image url.');
    $properties['width'] = $this->t('The image width.');
    $properties['height'] = $this->t('The image height.');
    $field = $this->getFieldDefinition();
    if ($field->getAdditionalValue('style_generate') !== FALSE) {
      foreach ($this->propertyInfoImageStyles($field) as $key => $property) {
        $properties['style.' . $key] = $property;
      }
    }
    if ($this->moduleHandler()->moduleExists('exo_imagine')) {
      $properties['imagine'] = $this->t('Renderable eXo Imagine image.');
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function onInstall(ConfigEntityInterface $entity) {
    parent::onInstall($entity);
    $field = $this->getFieldDefinition();
    $this->buildImageStyles($field);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(ConfigEntityInterface $entity) {
    parent::onUpdate($entity);
    $field = $this->getFieldDefinition();
    $this->buildImageStyles($field);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue($delta = 0) {
    return [
      'path' => \Drupal::service('extension.list.module')->getPath('exo_alchemist') . '/images/default.png',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $item->entity;
    if ($file) {
      $field = $this->getFieldDefinition();
      /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
      $file_url_generator = \Drupal::service('file_url_generator');
      $value = [
        'url' => $file_url_generator->generateString($file->getFileUri()),
        'width' => $item->width,
        'height' => $item->height,
        'title' => $file->label(),
      ];
      if ($field->getAdditionalValue('style_generate') !== FALSE) {
        $value['style'] = $this->getImageStylesAsUrl($this->getFieldDefinition(), $file);
      }
      if ($this->moduleHandler()->moduleExists('exo_imagine')) {
        $field = $this->getFieldDefinition();
        $settings = [];
        foreach ($field->getAdditionalValue('styles') as $breakpoint => $data) {
          $settings['breakpoints'][$breakpoint] = [
            'width' => $data['width'] ?? NULL,
            'height' => $data['height'] ?? NULL,
          ];
        }
        $value['imagine'] = $item->view([
          'type' => 'exo_imagine',
          'label' => 'hidden',
          'settings' => $settings,
        ]);
      }
      return $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function cleanValue(FieldItemInterface $item, $delta, $update = TRUE) {
    parent::cleanValue($item, $delta, $update);
    if ($item && $item->entity) {
      $item->entity->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getValueEntity(ExoComponentValue $value, ?FieldItemInterface $item = NULL) {
    $has_entity = $item && $item->entity;
    if ($has_entity) {
      $old_id = $item->entity->id();
      // We always remove the current value.
      $this->cleanValue($item, $value->getDelta(), TRUE);
    }
    if ($file = $this->componentFile($value)) {
      if ($has_entity) {
        // We want to keep the same id as the last file.
        \Drupal::database()->update('file_managed')->fields([
          'fid' => $old_id,
        ])->condition('fid', $file->id())->execute();
        $file->fid = $old_id;
      }
      return $file;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFileDirectory(ExoComponentValue $value) {
    return 'public://images';
  }

}
