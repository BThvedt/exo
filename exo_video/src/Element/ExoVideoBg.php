<?php

namespace Drupal\exo_video\Element;

use Drupal\Core\Render\Element\RenderElement;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;

/**
 * Provides a render element for an eXo video background.
 *
 * Properties:
 * - #tag: The tag name to build.
 * - #attributes: (array, optional) HTML attributes to apply to the tag. The
 *   attributes are escaped, see \Drupal\Core\Template\Attribute.
 * - #value: (string, optional) A string containing the textual contents of
 *   the tag.
 * - #noscript: (bool, optional) When set to TRUE, the markup
 *   (including any prefix or suffix) will be wrapped in a <noscript> element.
 *
 * Usage example:
 * @code
 * $build['hello'] = [
 *   '#type' => 'html_tag',
 *   '#tag' => 'p',
 *   '#value' => $this->t('Hello World'),
 * ];
 * @endcode
 *
 * @RenderElement("exo_video_bg")
 */
class ExoVideoBg extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#pre_render' => [
        [$class, 'preRenderExoVideoBg'],
      ],
      '#id' => 'exo-video-bg',
      '#attributes' => [],
      // Can be youtube or vimeo.
      '#video_provider' => NULL,
      '#video_url' => NULL,
      '#video_id' => NULL,
      '#video_resolution' => '16:9',
      '#video_position' => 'absolute',
      '#video_loop' => TRUE,
      '#video_autoplay' => TRUE,
      '#video_mute' => TRUE,
      // The path or URI to the original image.
      '#video_image' => NULL,
      // Can be always, hover, viewport.
      '#video_when' => 'always',
      '#video_sizing' => 'cover',
      '#video_controls' => FALSE,
      // Will provide for click to modal functionality.
      '#video_expand' => FALSE,
    ];
  }

  /**
   * Pre-render callback: Renders a generic HTML tag with attributes.
   *
   * @param array $element
   *   An associative array.
   *
   * @return array
   *   An associative array.
   */
  public static function preRenderExoVideoBg(array $element) {
    $build = [];
    $element['#video_provider'] = strtolower($element['#video_provider']);
    if (empty($element['#video_id']) && !empty($element['#video_url'])) {
      $element['#video_id'] = self::getIdFromUrl($element['#video_provider'], $element['#video_url']);
    }
    if (!empty($element['#video_provider']) && !empty($element['#video_id'])) {
      $id = Html::getUniqueId($element['#id']);
      $build = [
        '#type' => 'container',
        '#attached' => [
          'library' => ['exo_video/base'],
        ],
        '#attributes' => NestedArray::mergeDeep([
          'id' => $id,
          'class' => ['exo-video'],
        ], $element['#attributes']),
      ];
      $resolution = explode(':', $element['#video_resolution']);
      $build['#attached']['drupalSettings']['exoVideo']['videos'][$id] = [
        'provider' => $element['#video_provider'],
        'videoId' => $element['#video_id'],
        'position' => $element['#video_position'],
        'videoRatio' => $resolution[0] / $resolution[1],
        'loop' => $element['#video_loop'],
        'autoplay' => $element['#video_autoplay'],
        'mute' => $element['#video_mute'],
        'image' => $element['#video_image'],
        'when' => $element['#video_when'],
        'sizing' => $element['#video_sizing'],
        'controls' => $element['#video_controls'],
        'expand' => $element['#video_expand'],
      ];
    }
    return ['video' => $build];
  }

  /**
   * Get the video id from the url.
   *
   * @param string $provider
   *   The provider. Either youtube or vimeo.
   * @param string $url
   *   The url of the remote video.
   */
  public static function getIdFromUrl($provider, $url) {
    $matches = [];
    switch ($provider) {
      case 'youtube':
        preg_match('/^https?:\/\/(www\.)?((?!.*list=)youtube\.com\/watch\?.*v=|youtu\.be\/)(?<id>[0-9A-Za-z_-]*)/', $url, $matches);
        break;

      case 'vimeo':
        preg_match('/^https?:\/\/(www\.)?vimeo.com\/(channels\/[a-zA-Z0-9]*\/)?(?<id>[0-9]*)(\/[a-zA-Z0-9]+)?(\#t=(\d+)s)?$/', $url, $matches);
        break;
    }
    return $matches['id'] ?? FALSE;
  }

}
