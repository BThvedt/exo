<?php

namespace Drupal\exo_imagine\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'exo image media' formatter.
 *
 * @FieldFormatter(
 *   id = "exo_imagine_media_gallery",
 *   label = @Translation("eXo Gallery"),
 *   provider = "exo_media",
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ExoImagineMediaGalleryFormatter extends ExoImagineMediaFormatter {
  use ExoIconTranslationTrait;

  /**
   * The eXo modal generator.
   *
   * @var \Drupal\exo_modal\ExoModalGeneratorInterface
   */
  protected $exoModalGenerator;

  /**
   * The oEmbed resource fetcher.
   *
   * @var \Drupal\media\OEmbed\ResourceFetcherInterface
   */
  protected $resourceFetcher;

  /**
   * The oEmbed URL resolver service.
   *
   * @var \Drupal\media\OEmbed\UrlResolverInterface
   */
  protected $urlResolver;

  /**
   * The media settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $mediaSettings;

  /**
   * The iFrame URL helper service.
   *
   * @var \Drupal\media\IFrameUrlHelper
   */
  protected $iFrameUrlHelper;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Let the parent chain construct the instance so this stays compatible
    // across core versions that change the ImageFormatter constructor
    // signature (e.g. Drupal 11.4 added the ImageDerivativeUtilities
    // argument). The gallery-specific services are attached via property
    // injection.
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->exoModalGenerator = $container->get('exo_modal.generator');
    $instance->resourceFetcher = $container->get('media.oembed.resource_fetcher');
    $instance->urlResolver = $container->get('media.oembed.url_resolver');
    $instance->mediaSettings = $container->get('config.factory')->get('media.settings');
    $instance->iFrameUrlHelper = $container->get('media.oembed.iframe_url_helper');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'max_width' => 0,
      'max_height' => 0,
      'resolution' => '16:9',
      'image_style' => '',
      'type' => 'image',
      'text' => 'View Video',
      'icon' => 'regular-play-circle',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $image_styles = image_style_options(FALSE);
    $form['image_style'] = [
      '#title' => t('Full Image style'),
      '#description' => t('Select the image style to use for the full image. If you select "None", the original image will be used.'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $image_styles = image_style_options(FALSE);
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);
    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('image_style');
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = $this->t('Full Image style: %style', ['%style' => $image_styles[$image_style_setting]]);
    }
    else {
      $summary[] = $this->t('Full Image <em>Original</em>');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $group_id = $this->getGroupId($items->getEntity());
    foreach ($elements as $delta => $element) {
      // Add media cache tags.
      if (isset($this->mediaEntities[$delta])) {
        /** @var \Drupal\media\MediaInterface $media */
        $media = $this->mediaEntities[$delta];
        switch ($media->bundle()) {
          case 'image':
            $source_field = $media->getSource()->getConfiguration()['source_field'];
            $media_item = $media->get($source_field)->first();
            $this->alterImageElement($elements[$delta], $media_item, $delta, $group_id);
            break;

          case 'remote_video':
            $source_field = $media->getSource()->getConfiguration()['source_field'];
            $media_item = $media->get($source_field)->first();
            $this->alterRemoteVideoElement($elements[$delta], $media_item, $delta, $group_id);
            break;
        }
      }
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterImageElement(&$element, FieldItemInterface $item, $delta, $group_id) {
    $modal = $this->exoModalGenerator->generate($this->getUniqueId($item, $delta));

    $file = $item->entity;
    $image_uri = $file->getFileUri();
    $image_style_setting = $this->getSetting('image_style');
    if (!empty($image_style_setting)) {
      /** @var \Drupal\image\ImageStyleInterface $image_style */
      $image_style = $this->imageStyleStorage->load($image_style_setting);
      $image_uri = $image_style->buildUrl($image_uri);
      $modal->addCacheTags($image_style->getCacheTags());
    }
    $url = $this->fileUrlGenerator->generateString($image_uri);

    $modal->setSetting(['modal', 'group'], $group_id);
    $modal->setSetting(['modal', 'borderBottom'], FALSE);
    $modal->setSetting(['modal', 'closeInBody'], TRUE);
    $modal->setSetting(['modal', 'imageUrl'], $url);
    $modal->setModalSetting('class', 'exo-media-gallery-modal');
    $modal->setTrigger($element);
    $element = $modal->toRenderable();
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRemoteVideoElement(&$element, FieldItemInterface $item, $delta, $group_id) {
    try {
      $oembed = $this->generateOembed($item);
    }
    catch (ResourceException $e) {
      return;
    }
    if (!empty($oembed['#attributes']['src'])) {
      $modal = $this->exoModalGenerator->generate($this->getUniqueId($item, $delta));
      $resolution = explode(':', $this->getSetting('resolution'));
      $ratio = $resolution[0] / $resolution[1];
      $modal->setSetting(['modal', 'group'], $group_id);
      $modal->setSetting(['modal', 'width'], '90%');
      $modal->setSetting(['modal', 'iframe'], TRUE);
      $modal->setSetting(['modal', 'iframeURL'], $oembed['#attributes']['src']);
      $modal->setSetting(['modal', 'iframeWidth'], '800px');
      $modal->setSetting(['modal', 'iframeHeight'], 800 * $ratio . 'px');
      $modal->setSetting(['modal', 'iframeResponsive'], TRUE);
      $modal->setSetting(['modal', 'closeInBody'], TRUE);
      $modal->setModalSetting('class', 'exo-media-gallery-modal');

      $title = [
        'text' => [
          '#markup' => $this->icon('View Video')->setIcon('regular-play-circle'),
          '#weight' => 10,
          '#prefix' => '<div class="exo-oembed-text"><div class="exo-oembed-text-inner">',
          '#suffix' => '</div></div>',
        ],
        'image' => $element,
      ];
      $modal->setTrigger($title);

      // $modal->setTrigger($element);
      $element = $modal->toRenderable();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function generateOembed(FieldItemInterface $item) {
    $element = [];
    $max_width = $this->getSetting('max_width');
    $max_height = $this->getSetting('max_height');

    $main_property = $item->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName();
    $value = $item->{$main_property};

    if (empty($value)) {
      return;
    }

    try {
      $resource_url = $this->urlResolver->getResourceUrl($value, $max_width, $max_height);
      $resource = $this->resourceFetcher->fetchResource($resource_url);
    }
    catch (ResourceException $exception) {
      \Drupal::logger('exo_oembed')->error("Could not retrieve the remote URL (@url).", ['@url' => $value]);
      return;
    }

    if ($resource->getType() === Resource::TYPE_LINK) {
      $element = [
        '#title' => $resource->getTitle(),
        '#type' => 'link',
        '#url' => Url::fromUri($value),
      ];
    }
    elseif ($resource->getType() === Resource::TYPE_PHOTO) {
      $element = [
        '#theme' => 'image',
        '#uri' => $resource->getUrl()->toString(),
        '#width' => $max_width ?: $resource->getWidth(),
        '#height' => $max_height ?: $resource->getHeight(),
      ];
    }
    else {
      $url = Url::fromRoute('media.oembed_iframe', [], [
        'query' => [
          'url' => $value,
          'max_width' => $max_width,
          'max_height' => $max_height,
          'hash' => $this->iFrameUrlHelper->getHash($value, $max_width, $max_height),
        ],
      ]);

      $domain = $this->mediaSettings->get('iframe_domain');
      if ($domain) {
        $url->setOption('base_url', $domain);
      }

      // Render videos and rich content in an iframe for security reasons.
      // @see: https://oembed.com/#section3
      $element = [
        '#type' => 'html_tag',
        '#tag' => 'iframe',
        '#attributes' => [
          'src' => $url->toString(),
          'frameborder' => 0,
          'scrolling' => FALSE,
          'allowtransparency' => TRUE,
          'width' => $max_width ?: $resource->getWidth(),
          'height' => $max_height ?: $resource->getHeight(),
          'class' => ['media-oembed-content'],
        ],
        '#attached' => [
          'library' => [
            'media/oembed.formatter',
          ],
        ],
      ];

      CacheableMetadata::createFromObject($resource)
        ->addCacheTags($this->mediaSettings->getCacheTags())
        ->applyTo($element);
    }
    return $element;
  }

  /**
   * Get unique modal id.
   */
  protected function getGroupId(EntityInterface $entity) {
    return md5(implode('_', [
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $entity->id(),
      $this->viewMode,
    ]));
  }

  /**
   * Get unique modal id.
   */
  protected function getUniqueId(FieldItemInterface $item, $delta) {
    $entity = $item->getEntity();
    $field_definition = $item->getFieldDefinition();
    return Html::getUniqueId(md5(implode('_', [
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $entity->id(),
      $this->viewMode,
      $field_definition->getName(),
      $delta,
    ])));
  }

}
