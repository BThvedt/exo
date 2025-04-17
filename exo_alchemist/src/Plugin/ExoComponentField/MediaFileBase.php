<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\exo_alchemist\ExoComponentValue;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldFileTrait;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Base component for media that uses files.
 */
abstract class MediaFileBase extends MediaBase {

  use ExoComponentFieldFileTrait;

  /**
   * {@inheritdoc}
   */
  public function validateValue(ExoComponentValue $value) {
    parent::validateValue($value);
    if ($base_value = $value->get('value')) {
      $value->set('path', $base_value);
      $value->unset('value');
    }
    $this->validateValueFile($value, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = parent::propertyInfo();
    $properties['url'] = $this->t('The file url.');
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    /** @var \Drupal\media\MediaInterface $media */
    $media = $item->entity;
    if ($media instanceof MediaInterface) {
      $source = $media->getSource();
      if ($source) {
        $source_field_definition = $media->getSource()->getSourceFieldDefinition($media->bundle->entity);
        $file = $media->{$source_field_definition->getName()}->entity;
        if ($file) {
          if (!file_exists($file->getFileUri())) {
            // We have a media entity and file entity, but the file does not
            // exist. Create it with default.
            $field = $this->getFieldDefinition();
            $val = $field->getDefaultsAsArray()[$delta] ?? NULL;
            if ($val) {
              $value = new ExoComponentValue($field, $val);
              $this->validateValue($value);
              // Make sure we have default component files.
              if ($this->getFileUri($value) === $file->getFileUri()) {
                $this->componentFileData($file->getFileUri(), $value);
              }
            }
          }
          return $this->viewFileValue($media, $file);
        }
        else {
          // We have a media entity but no file. Create it with default.
          $field = $this->getFieldDefinition();
          $data = $field->getDefaultsAsArray()[$delta] ?? $this->getDefaultValue($field);
          $value = new ExoComponentValue($field, $data);
          $this->validateValue($value);
          $file = $this->componentFile($value);
          $media->{$source_field_definition->getName()}->setValue($file);
          $media->save();
        }
      }
    }
  }

  /**
   * Extending classes can use to act on the file.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   *
   * @return array
   *   A value that will be sent to twig.
   */
  protected function viewFileValue(MediaInterface $media, FileInterface $file) {
    return [
      'url' => $file->createFileUrl(),
      'label' => $media->label(),
    ];
  }

  /**
   * Extending classes can use this method to set individual values.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValue $value
   *   The field value.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The current item.
   *
   * @return mixed
   *   A value suitable for setting to \Drupal\Core\Field\FieldItemInterface.
   */
  protected function setMediaValue(ExoComponentValue $value, ?FieldItemInterface $item = NULL) {
    $values = [];
    if ($file = $this->componentFile($value)) {
      $values[] = [
        'target_id' => $file->id(),
        'title' => $this->getMediaName($value),
        'alt' => $this->getMediaName($value),
      ];
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFileDirectory(ExoComponentValue $value) {
    return 'public://media/' . str_replace('_', '-', $value->get('bundle'));
  }

}
