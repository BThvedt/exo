<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * A 'media' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "media_audio",
 *   label = @Translation("Media: Audio"),
 *   properties = {
 *     "url" = @Translation("The absolute url of the audio."),
 *     "title" = @Translation("The title of the audio."),
 *   },
 *   provider = "media",
 * )
 */
class MediaAudio extends MediaFileBase {
  use ExoIconTranslationTrait;

  /**
   * Get the entity type.
   */
  protected function getEntityTypeBundles() {
    return ['audio' => 'audio'];
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = parent::propertyInfo();
    $properties['icon'] = $this->t('The file type icon.');
    $properties['icon_render'] = $this->t('The rendered file type icon.');
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewFileValue(MediaInterface $media, FileInterface $file) {
    $icon = \Drupal::service('exo_icon.mime_manager')->getMimeIcon($file->getMimeType());
    $field_name = $media->getSource()->getSourceFieldDefinition($media->bundle->entity)->getName();
    return [
      'render' => $media->get($field_name)->view([
        'type' => 'file_audio',
        'label' => 'hidden',
        'settings' => [
          'controls' => TRUE,
          'autoplay' => FALSE,
          'loop' => FALSE,
          'multiple_file_display_type' => 'tags',
        ],
      ]),
      'icon' => $icon,
      'icon_render' => $this->icon()->setIcon($icon)->toRenderable(),
    ] + parent::viewFileValue($media, $file);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue($delta = 0) {
    return [
      'name' => 'Example File',
      'path' => \Drupal::service('extension.list.module')->getPath('exo_alchemist') . '/audio/default.mp3',
    ];
  }

}
