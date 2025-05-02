<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Url;
use Drupal\exo_alchemist\ExoComponentValue;
use Drupal\media\OEmbed\ResourceException;

/**
 * A 'media' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "media_remote_video",
 *   label = @Translation("Media: Remote Video"),
 *   properties = {
 *     "url" = @Translation("The absolute url of the video."),
 *     "embed" = @Translation("The embed code."),
 *     "thumbnailUrl" = @Translation("The thumbnail URL"),
 *     "thumbnailHeight" = @Translation("The thumbnail height"),
 *     "thumbnailWidth" = @Translation("The thumbnail width"),
 *     "title" = @Translation("The title of the video."),
 *   },
 *   provider = "media",
 * )
 */
class MediaRemoteVideo extends MediaBase {

  /**
   * The eXo modal generator.
   *
   * @var \Drupal\exo_modal\ExoModalGeneratorInterface
   */
  protected $exoModalGenerator;

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    parent::processDefinition();
    $field = $this->getFieldDefinition();
    if ($this->moduleHandler()->moduleExists('exo_modal')) {
      $field->setAdditionalValueIfEmpty('modal_trigger_text', 'View Video');
      $field->setAdditionalValueIfEmpty('modal_trigger_icon', 'regular-play-circle');
    }
  }

  /**
   * Get the entity type.
   */
  protected function getEntityTypeBundles() {
    return ['remote_video' => 'remote_video'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateValue(ExoComponentValue $value) {
    parent::validateValue($value);
    if ($value->get('target_id')) {
      return;
    }
    if ($value->get('value')) {
      $value->set('path', $value->get('value'));
      $value->unset('value');
    }
    if (!$value->has('path')) {
      throw new PluginException(sprintf('eXo Component Field plugin (%s) requires [default.path] be set.', $value->getDefinition()->getType()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = parent::propertyInfo();
    $properties['url'] = $this->t('The video url.');
    $properties['embed'] = $this->t('The video embed code.');
    $properties['thumbnailUrl'] = $this->t('The video thumbnail URL.');
    $properties['thumbnailHeight'] = $this->t('The video thumbnail height.');
    $properties['thumbnailWidth'] = $this->t('The video thumbnail width.');
    $properties['title'] = $this->t('The video title.');
    if ($this->moduleHandler()->moduleExists('exo_video')) {
      $properties['background'] = $this->t('The video as a background.');
    }
    if ($this->moduleHandler()->moduleExists('exo_modal')) {
      $properties['modal'] = $this->t('The video as a modal.');
    }
    if ($this->moduleHandler()->moduleExists('exo_imagine')) {
      $properties['imagine'] = $this->t('Renderable eXo Imagine thumbnail.');
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    $media = $item->entity;
    if ($media) {
      $field = $this->getFieldDefinition();
      $source_field_definition = $media->getSource()->getSourceFieldDefinition($media->bundle->entity);
      $url = $media->{$source_field_definition->getName()}->value;
      if (!$url) {
        return NULL;
      }
      /** @var \Drupal\media\OEmbed\UrlResolverInterface $url_resolver */
      $url_resolver = \Drupal::service('media.oembed.url_resolver');
      $resource_url = $url_resolver->getResourceUrl($url);
      $provider = $url_resolver->getProviderByUrl($url);
      $resource = NULL;
      try {
        $resource = \Drupal::service('media.oembed.resource_fetcher')->fetchResource($resource_url);
      }
      catch (ResourceException $e) {
        return NULL;
      }
      if ($resource) {
        /** @var \Drupal\media\OEmbed\Resource $resource */
        $value = [
          'url' => $url,
          'embed' => $resource->getHtml(),
          'thumbnailUrl' => $resource->getThumbnailUrl(),
          'thumbnailHeight' => $resource->getThumbnailHeight(),
          'thumbnailWidth' => $resource->getThumbnailWidth(),
          'title' => $resource->getTitle(),
        ];
        if ($this->moduleHandler()->moduleExists('exo_imagine')) {
          $imagine_settings = [];
          // Can set visible, blur and animate.
          $imagine_settings['display'] = $field->getAdditionalValue('imagine_display') ?? [];
          $styles = $field->getAdditionalValue('styles') ?? [];
          foreach ($styles as $breakpoint => $data) {
            $imagine_settings['breakpoints'][$breakpoint] = [
              'width' => $data['width'] ?? NULL,
              'height' => $data['height'] ?? NULL,
            ];
          }
          if ($media->hasField('thumbnail') && !$media->get('thumbnail')->isEmpty()) {
            $value['imagine'] = $media->get('thumbnail')->view([
              'type' => 'exo_imagine',
              'label' => 'hidden',
              'settings' => $imagine_settings,
            ]);
          }
        }
        if ($media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
          $image = $media->get('field_media_image')->entity->getFileUri();
          $value['thumbnailUrl'] = '';
          if ($image) {
            $url = \Drupal::service('file_url_generator')->generateAbsoluteString($image);
            if (file_exists($image)) {
              $size = getimagesize($image);
              $value['thumbnailUrl'] = Url::fromUri($url);
            }
          }
          $value['thumbnailWidth'] = $size[0] ?? '';
          $value['thumbnailHeight'] = $size[1] ?? '';

          if (!empty($imagine_settings)) {
            $value['imagine'] = $media->get('field_media_image')->view([
              'type' => 'exo_imagine',
              'label' => 'hidden',
              'settings' => $imagine_settings,
            ]);
          }
        }
        if ($this->moduleHandler()->moduleExists('exo_video')) {
          $settings = $field->getAdditionalValue('video_bg_settings') ?: [];
          $value['background'] = [
            '#type' => 'exo_video_bg',
            '#video_provider' => $provider->getName(),
            '#video_url' => $url,
          ];
          if (!empty($value['thumbnailUrl'])) {
            $value['background']['#video_image'] = $value['thumbnailUrl']->toString();
          }
          foreach ($settings as $key => $val) {
            $value['background']['#' . $key] = $val;
          }
          if ($this->isLayoutBuilder($contexts)) {
            $value['background']['#attributes']['class'][] = 'component-passthrough';
          }
        }
        if ($this->moduleHandler()->moduleExists('exo_modal')) {
          $id = str_replace(['_', ' '], '-', implode('-', [
            $field->id(),
            $item->getEntity()->id(),
            $delta,
          ]));
          preg_match('/src="([^"]+)"/', $value['embed'], $match);
          $embed_url = $match[1];
          $max_width = 1400;
          $width = $resource->getWidth();
          $height = $resource->getHeight();
          if ($width < $max_width) {
            $ratio = $max_width / $width;
            $width = $width * $ratio;
            $height = $height * $ratio;
          }
          $settings = [
            'trigger' => [
              'text' => $this->icon($field->getAdditionalValue('modal_trigger_text'))->setIcon($field->getAdditionalValue('modal_trigger_icon')),
            ],
            'modal' => [
              'iframe' => TRUE,
              'iframeURL' => $embed_url,
              'width' => $width,
              'iframeWidth' => $width . 'px',
              'iframeHeight' => $height . 'px',
              'iframeResponsive' => TRUE,
              'closeButton' => FALSE,
              'closeInBody' => 'isOuterRight',
            ],
          ];
          $modal = $this->exoModalGenerator()->generate($id, $settings);
          $modal->addModalClass(Html::getClass('exo-component-' . $field->getComponent()->getName() . '-modal'));
          $modal->addModalClass(Html::getClass('modal--' . $field->getName()));
          $value['modal'] = $modal->toRenderable();
        }
      }
      return $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setMediaValue(ExoComponentValue $value, ?FieldItemInterface $item = NULL) {
    return [
      'value' => $value->get('path'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue($delta = 0) {
    return [
      'name' => 'Example Video',
      'path' => 'https://vimeo.com/171918951',
    ];
  }

  /**
   * Get the modal generator.
   *
   * @return \Drupal\exo_modal\ExoModalGeneratorInterface
   *   The modal generator.
   */
  protected function exoModalGenerator() {
    if (!isset($this->exoModalGenerator)) {
      $this->exoModalGenerator = NULL;
      if ($this->moduleHandler()->moduleExists('exo_modal')) {
        $this->exoModalGenerator = \Drupal::service('exo_modal.generator');
      }
    }
    return $this->exoModalGenerator;
  }

}
