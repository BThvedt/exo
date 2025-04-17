<?php

namespace Drupal\exo_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Template\Attribute;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\exo_image\ExoImageStyleManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\exo\ExoSettingsInterface;
use Drupal\Core\Form\SubformState;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Render\Markup;
use Drupal\exo\Plugin\Field\FieldFormatter\ExoEntityReferenceSelectionTrait;
use Drupal\exo\Plugin\Field\FieldFormatter\ExoEntityReferenceLinkTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Utility\Html;

/**
 * Plugin implementation of the 'eXo Image' formatter.
 *
 * @FieldFormatter(
 *   id = "exo_image",
 *   label = @Translation("eXo Image (deprecated)"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ExoImageFormatter extends ImageFormatter {
  use ExoEntityReferenceSelectionTrait;
  use ExoEntityReferenceLinkTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * The exo image style manager.
   *
   * @var Drupal\exo_image\ExoImageStyleManagerInterface
   */
  protected $exoImageStyleManager;

  /**
   * The MIME type guesser.
   *
   * @var \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * The exo settings.
   *
   * @var \Drupal\exo\ExoSettingsInterface
   */
  protected $exoImageSettings;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs an ImageFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\exo\ExoSettingsInterface $exo_image_settings
   *   The exo image settings.
   * @param \Drupal\exo_image\ExoImageStyleManagerInterface $exo_image_style_manager
   *   The exo image stype manager.
   * @param \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface $mime_type_guesser
   *   The MIME type guesser.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, EntityStorageInterface $image_style_storage, ExoSettingsInterface $exo_image_settings, ExoImageStyleManagerInterface $exo_image_style_manager, MimeTypeGuesserInterface $mime_type_guesser, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage);
    $this->exoImageSettings = $exo_image_settings;
    $this->exoImageStyleManager = $exo_image_style_manager;
    $this->mimeTypeGuesser = $mime_type_guesser;
    $this->logger = $logger_factory->get('exo_image');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('exo_image.settings'),
      $container->get('exo_image.style.manager'),
      $container->get('file.mime_type.guesser'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'enabled' => [],
      'breakpoints' => [],
    ] + self::selectionDefaultSettings() + parent::defaultSettings();
  }

  /**
   * Get breakpoint settings.
   */
  public function getBreakpointSettings() {
    $settings = [];
    $breakpoints = $this->exoImageStyleManager->getBreakpoints();
    foreach ($breakpoints as $key => $breakpoint) {
      $default = $key === key($breakpoints);
      $config = $this->getSetting('breakpoints')[$key] ?? [];
      $settings[$key] = [
        'label' => $default ? $this->t('Default') : $breakpoint->getLabel(),
        'description' => $default ? $this->t('The settings used when no settings are specified for a given breakpoint.') : $breakpoint->getMediaQuery(),
        'enabled' => $default || isset($this->getSetting('breakpoints')[$key]),
        'required' => $default,
        'settings' => $this->exoImageSettings->createInstance($config),
      ];
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    // Do not use an image style here. exo_image calculates one for us.
    unset($element['image_style']);

    $element['enabled'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled Breakpoints'),
    ];
    $element['breakpoints'] = [];

    $element['breakpoints']['#element_validate'][] = [get_class($this), 'validateElementBreakpoints'];

    foreach ($this->getBreakpointSettings() as $key => $data) {
      $id = Html::getUniqueId('exo-image-formatter-breakpoint-' . $key);
      $element['enabled'][$key] = [
        '#type' => 'checkbox',
        '#title' => $data['label'],
        '#default_value' => $data['enabled'],
        '#description' => $data['description'],
        '#disabled' => $data['required'],
        '#id' => $id,
      ];
      $element['breakpoints'][$key] = [
        '#type' => 'fieldset',
        '#title' => $data['label'],
        '#states' => [
          'visible' => [
            '#' . $id => [
              'checked' => TRUE,
            ],
          ],
        ],
      ] + $data['settings']->buildForm([], $form_state);
    }

    $element += $this->linkSettingsForm($element, $form_state);
    $element += $this->selectionSettingsForm($element, $form_state);

    return $element;
  }

  /**
   * Validate breakpoint settings.
   */
  public static function validateElementBreakpoints(array $element, FormStateInterface $form_state) {
    $exo_image_settings = \Drupal::service('exo_image.settings');
    $parents = $element['#parents'];
    array_pop($parents);
    $values = $form_state->getValue($parents);
    $enabled = array_filter($values['enabled']);
    unset($values['enabled']);
    $values['breakpoints'] = array_intersect_key($values['breakpoints'], $enabled);
    $form_state->setValue($parents, $values);
    foreach ($values['breakpoints'] as $key => $settings) {
      $subform_state = SubformState::createForSubform($element[$key], $form_state->getCompleteForm(), $form_state);
      $instance = $exo_image_settings->createInstance($settings);
      $instance->validateForm($element[$key], $subform_state);
      $instance->submitForm($element[$key], $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    foreach ($this->getBreakpointSettings() as $key => $data) {
      if ($data['enabled']) {
        $summary[] = $this->settingsSummaryItem($data['label'], $data['settings']);
      }
    }
    return array_merge($summary, $this->selectionSettingsSummary());
  }

  /**
   * Build a summary for a given settings.
   */
  protected function settingsSummaryItem($label, $settings) {
    $options = $this->exoImageSettings->imageHandlingOptions();
    $handler = $settings->getSetting('handler');
    $args = [
      '%label' => $label,
      '@handler' => $options[$handler],
    ];
    // Add extra options for some handlers.
    if ($handler == 'ratio') {
      $args['@handler'] .= ' (' . $settings->getSetting(['ratio', 'width']) . ':' . $settings->getSetting(['ratio', 'height']) . ')';
    }
    return $this->t('Image handling (%label): @handler', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $has_focal_point = \Drupal::moduleHandler()->moduleExists('focal_point');

    if ($items instanceof EntityReferenceFieldItemListInterface) {
      $files = $this->getEntitiesToView($items, $langcode);
      $defaults = [
        'webp' => $this->exoImageSettings->getSetting('webp') && !empty($this->exoImageStyleManager->supportsWebP()),
      ] + $this->exoImageSettings->getSiteSettingsDiff();
      foreach ($elements as $delta => &$element) {
        $file = $files[$delta];
        $item = $element['#item'];
        $cache = $element['#cache'] + ['tags' => [], 'contexts' => []];
        $element = [];
        $breakpoint_settings = $this->getBreakpointSettings();

        // SVG Support.
        if ($file->getMimeType() === 'image/svg+xml') {
          // Render as SVG tag.
          $svgRaw = $this->fileGetContents($file);
          if ($svgRaw) {
            $svgRaw = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $svgRaw);
            $svgRaw = trim($svgRaw);
            $elements[$delta] = [
              '#markup' => Markup::create($svgRaw),
              '#cache' => [
                'tags' => $file->getCacheTags(),
                'contexts' => $file->getCacheContexts(),
              ],
            ];
            $cache['tags'] = Cache::mergeTags($cache['tags'], $file->getCacheTags());
            $cache['contexts'] = Cache::mergeTags($cache['contexts'], $file->getCacheContexts());
          }
        }
        else {
          $uri = $file->getFileUri();
          foreach ($breakpoint_settings as $key => $data) {
            if ($data['enabled'] && !$item->isEmpty()) {
              $settings = $data['settings'];
              $handler = $settings->getSetting('handler');
              $width = $item->getValue()['width'];
              $height = $item->getValue()['height'];
              if ($width == NULL || $height == NULL) {
                continue;
              }
              $preview_width = 80;
              $preview_height = ($height / $width) * $preview_width;

              $element[$key]['#theme'] = 'exo_image';
              $element[$key]['#attributes']['class'] = ['exo-image', $key];
              $element[$key]['#data'] = [
                'fid' => $item->entity->id(),
                'filename' => pathinfo($item->entity->getFileUri())['basename'],
                'alt' => $item->getValue()['alt'],
                'handler' => $handler,
              ];

              switch ($handler) {
                case 'ratio':
                  $ratio_width = $settings->getSetting(['ratio', 'width']);
                  $ratio_height = $settings->getSetting(['ratio', 'height']);
                  $element[$key]['#data']['ratio'] = [
                    'width' => $ratio_width,
                    'height' => $ratio_height,
                  ];
                  $height = round(($width / $ratio_width) * $ratio_height);
                  $preview_height = round(($preview_width / $ratio_width) * $ratio_height);
                  break;

                default:
                  // Nothing extra needed here.
                  break;
              }

              $preview_src = '';
              if ($settings->getSetting('blur')) {
                $image_style = $this->exoImageStyleManager->findImageStyle($preview_width, $preview_height, 3);
                $cache['tags'] = Cache::mergeTags($cache['tags'], $image_style->getCacheTags());

                $image_style_uri = $image_style->buildUri($uri);
                if (!file_exists($image_style_uri)) {
                  $image_style->createDerivative($uri, $image_style_uri);
                }
                if (in_array('image/webp', \Drupal::request()->getAcceptableContentTypes())) {
                  if ($webp_uri = $this->exoImageStyleManager->createWebpCopy($image_style_uri, 3)) {
                    $image_style_uri = $webp_uri;
                  }
                }
                $mime_type = $this->mimeTypeGuesser->guess($image_style_uri);
                if (file_exists($image_style_uri)) {
                  $preview_src = 'data:' . $mime_type . ';base64,' . base64_encode(file_get_contents($image_style_uri));
                  [$preview_width, $preview_height] = getimagesize($image_style_uri);
                }
              }
              else {
                $preview_src = 'data:image/svg+xml;charset=utf-8,%3Csvg xmlns%3D\'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg\' viewBox%3D\'0 0 ' . $preview_width . ' ' . $preview_height . '\'%2F%3E';
              }
              $element[$key]['#image_attributes'] = new Attribute([
                'src' => $preview_src,
                'class' => ['exo-image-preview'],
                'alt' => $item->getValue()['alt'],
                'width' => $preview_width,
                'height' => $preview_height,
              ]);

              $element[$key]['#data']['thumb_ratio'] = $preview_height / $preview_width;
              if (!empty($settings->getLocalSettingsDiff()['bg'])) {
                $element[$key]['#attributes']['class'][] = 'bg-x-center';
                $element[$key]['#attributes']['class'][] = 'bg-y-center';
                // If ($has_focal_point) {
                //   $crops = \Drupal::entityTypeManager()
                //     ->getStorage('crop')
                //     ->loadByProperties(['uri' => $uri, 'type' => 'focal_point']);
                //   foreach ($crops as $crop) {
                //     $anchor = $crop->anchor();
                //     if ($anchor['x'] > round($width / 2)) {
                //       $element[$key]['#attributes']['class'][] = 'bg-right';
                //     }
                //     elseif ($anchor['x'] < round($width / 2)) {
                //       $element[$key]['#attributes']['class'][] = 'bg-left';
                //     }
                //     else {
                //       $element[$key]['#attributes']['class'][] = 'bg-x-center';
                //     }
                //     if ($anchor['y'] > round($height / 2)) {
                //       $element[$key]['#attributes']['class'][] = 'bg-top';
                //     }
                //     elseif ($anchor['y'] < round($height / 2)) {
                //       $element[$key]['#attributes']['class'][] = 'bg-bottom';
                //     }
                //     else {
                //       $element[$key]['#attributes']['class'][] = 'bg-y-center';
                //     }
                //   }
                // }.
                $element[$key]['#data']['bg'] = !empty($settings->getLocalSettingsDiff()['bg']) ? 1 : 0;
              }
              if (isset($settings->getLocalSettingsDiff()['visible'])) {
                $element[$key]['#data']['visible'] = !empty($settings->getLocalSettingsDiff()['visible']) ? 1 : 0;
              }
              if (isset($settings->getLocalSettingsDiff()['animate'])) {
                $element[$key]['#data']['animate'] = !empty($settings->getLocalSettingsDiff()['animate']) ? 1 : 0;
              }
              if (isset($settings->getLocalSettingsDiff()['blur'])) {
                $element[$key]['#data']['blur'] = !empty($settings->getLocalSettingsDiff()['blur']) ? 1 : 0;
              }
            }
            else {
              $element[key($breakpoint_settings)]['#attributes']['class'][] = $key;
            }
          }
          $element['#attached']['drupalSettings']['exoImage']['defaults'] = $defaults;
        }
        $element['#cache'] = $cache;
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $entities = parent::getEntitiesToView($items, $langcode);
    $entities = $this->filterSelectionEntities($entities);
    return $entities;
  }

  /**
   * Provides content of the file.
   *
   * @param \Drupal\file\Entity\File $file
   *   File to handle.
   *
   * @return string
   *   File content.
   */
  protected function fileGetContents(File $file) {
    $fileUri = $file->getFileUri();

    if (file_exists($fileUri)) {
      return file_get_contents($fileUri);
    }

    $this->logger->error(
      'File @file_uri (ID: @file_id) does not exists in filesystem.',
      ['@file_id' => $file->id(), '@file_uri' => $fileUri]
    );

    return FALSE;
  }

}
