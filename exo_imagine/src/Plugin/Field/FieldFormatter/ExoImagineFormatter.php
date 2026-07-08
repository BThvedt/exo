<?php

namespace Drupal\exo_imagine\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Template\Attribute;
use Drupal\exo\ExoSettingsInterface;
use Drupal\image\ImageDerivativeUtilities;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\exo\Plugin\Field\FieldFormatter\ExoEntityReferenceSelectionTrait;
use Drupal\exo\Plugin\Field\FieldFormatter\ExoEntityReferenceLinkTrait;
use Drupal\exo_imagine\ExoImagineManager;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'eXo Image' formatter.
 *
 * @FieldFormatter(
 *   id = "exo_imagine",
 *   label = @Translation("eXo Image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ExoImagineFormatter extends ImageFormatter {
  use ExoEntityReferenceSelectionTrait;
  use ExoEntityReferenceLinkTrait;

  /**
   * The exo imagine manager.
   *
   * @var \Drupal\exo_imagine\ExoImagineManager
   */
  protected $exoImagineManager;

  /**
   * The exo imagine settings.
   *
   * @var \Drupal\exo\ExoSettingsInstanceInterface
   */
  protected $exoImagineSettings;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
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
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \Drupal\exo\ExoSettingsInterface $exo_imagine_settings
   *   The exo image settings.
   * @param \Drupal\exo_imagine\ExoImagineManager $exo_imagine_manager
   *   The exo image stype manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, EntityStorageInterface $image_style_storage, FileUrlGeneratorInterface $file_url_generator, ImageDerivativeUtilities $image_derivative_utilities, ExoSettingsInterface $exo_imagine_settings, ExoImagineManager $exo_imagine_manager, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage, $file_url_generator, $image_derivative_utilities);
    $this->exoImagineSettings = $exo_imagine_settings->createInstance($this->getSetting('display'));
    $this->exoImagineManager = $exo_imagine_manager;
    $this->logger = $logger_factory->get('exo_imagine');
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
      $container->get('file_url_generator'),
      $container->get(ImageDerivativeUtilities::class),
      $container->get('exo_imagine.settings'),
      $container->get('exo_imagine.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $breakpoints = [];
    foreach (\Drupal::service('exo_imagine.manager')->getBreakpoints() as $key => $breakpoint) {
      $width = '';
      switch ($key) {
        case 'large':
          $width = 1200;
          break;

        case 'medium':
          $width = 1024;
          break;

        case 'small':
          $width = 640;
          break;
      }
      $breakpoints[$key] = [
        'width' => $width,
        'height' => '',
        'unique' => '',
      ];
    }
    return [
      'breakpoints' => $breakpoints,
      'display' => [],
    ] + self::selectionDefaultSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = $this->linkSettingsSummary();
    foreach ($this->getBreakpointSettings() as $key => $data) {
      if (!empty($data['width']) || !empty($data['height'])) {
        $summary[] = t('@label: %style', [
          '@label' => ucfirst($data['label']),
          '%style' => $this->exoImagineManager->getImageStyleLabel($data['width'], $data['height'], $data['quality'], $data['unique']),
        ]);
      }
    }
    return array_merge($summary, $this->selectionSettingsSummary());
  }

  /**
   * Get selectable styles.
   *
   * @return \Drupal\exo_imagine\Entity\ExoImagineStyleInterface[]
   *   Selectable styles.
   */
  protected function getSelectableImagineStyles() {
    return array_filter($this->exoImagineManager->getImagineStyles(), function ($imagine_style) {
      /** @var \Drupal\exo_imagine\Entity\ExoImagineStyleInterface $imagine_style */
      return strpos($imagine_style->id(), ExoImagineManager::PREVIEW_BLUR_QUALITY . 'q') === FALSE;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    // Do not use an image style here. exo_image calculates one for us.
    unset($element['image_style']);

    $style_options = ['' => $this->t('- New -')];
    foreach ($this->getSelectableImagineStyles() as $imagine_style) {
      $style_options[$imagine_style->id()] = str_replace(['eXo (', ')'], '', $imagine_style->label());
    }

    $element['breakpoints']['#element_validate'][] = [
      get_class($this),
      'validateBreakpoints',
    ];

    $unique_id = Html::getUniqueId('exo-imagine-formatter');

    foreach ($this->getBreakpointSettings() as $key => $data) {
      $id = $unique_id . '-' . $key;
      $imagine_style = $this->exoImagineManager->getImagineStyle($data['width'], $data['height'], $data['unique'], NULL, FALSE);
      $states = [
        'visible' => [
          '#' . $id . '-style' => ['value' => ''],
        ],
      ];
      $element['breakpoints'][$key] = [
        '#type' => 'details',
        '#title' => $data['label'],
        '#open' => !empty($data['width']) || !empty($data['height']),
      ];
      $element['breakpoints'][$key]['style'] = [
        '#type' => 'select',
        '#title' => $this->t('Style'),
        '#options' => $style_options,
        '#default_value' => $imagine_style ? $imagine_style->id() : '',
        '#id' => $id . '-style',
      ];
      $element['breakpoints'][$key]['width'] = [
        '#type' => 'number',
        '#title' => $this->t('Width'),
        '#field_suffix' => ' ' . t('pixels'),
        '#default_value' => $data['width'],
        '#min' => 1,
        '#step' => 1,
        '#states' => $states,
      ];
      $element['breakpoints'][$key]['height'] = [
        '#type' => 'number',
        '#title' => $this->t('Height'),
        '#field_suffix' => ' ' . t('pixels'),
        '#default_value' => $data['height'],
        '#min' => 1,
        '#step' => 1,
        '#states' => $states,
      ];
    }

    $element['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display Overrides'),
    ];
    $element['display'] = $this->exoImagineSettings->buildForm([], $form_state);
    $element['display']['#element_validate'][] = [
      get_class($this),
      'validateElementDisplay',
    ];

    $element += $this->linkSettingsForm($element, $form_state);
    $element += $this->selectionSettingsForm($element, $form_state);
    return $element;
  }

  /**
   * Validate breakpoint settings.
   */
  public static function validateBreakpoints(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    foreach ($values as &$data) {
      if (!empty($data['style'])) {
        $exo_imagine_style = \Drupal::service('exo_imagine.manager')->getImagineStyleByStyleId($data['style']);
        if ($exo_imagine_style) {
          $data['width'] = $exo_imagine_style->getWidth();
          $data['height'] = $exo_imagine_style->getHeight();
          $data['unique'] = $exo_imagine_style->getUnique();
          $data['height'] = $exo_imagine_style->getHeight();
        }
      }
    }
    $form_state->setValue($element['#parents'], $values);
  }

  /**
   * Validate element display settings.
   */
  public static function validateElementDisplay(array $element, FormStateInterface $form_state) {
    $exo_imagine_settings = \Drupal::service('exo_imagine.settings');
    $values = $form_state->getValue($element['#parents']);
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    $instance = $exo_imagine_settings->createInstance($values);
    $instance->validateForm($element, $subform_state);
    $instance->submitForm($element, $subform_state);
  }

  /**
   * Get breakpoint settings.
   */
  public function getBreakpointSettings() {
    $settings = [];
    $breakpoints = $this->exoImagineManager->getBreakpoints();
    $formatter_settings = $this->getSetting('breakpoints');
    foreach ($breakpoints as $key => $breakpoint) {
      $settings[$key] = [
        'label' => $breakpoint->getLabel(),
        'media' => $breakpoint->getMediaQuery(),
        'width' => NULL,
        'height' => NULL,
        'quality' => NULL,
        'unique' => '',
      ];
      if (isset($formatter_settings[$key])) {
        $breakpoint_settings = $formatter_settings[$key];
        $settings[$key]['width'] = $breakpoint_settings['width'] ?? NULL;
        $settings[$key]['height'] = $breakpoint_settings['height'] ?? NULL;
      }
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    if ($items instanceof EntityReferenceFieldItemListInterface) {
      $site_settings = $this->exoImagineSettings->getSiteSettingsDiff();
      $settings = $this->exoImagineSettings->getLocalSettingsDiff();
      $files = $this->getEntitiesToView($items, $langcode);
      $breakpoint_settings = $this->getBreakpointSettings();
      $blur = $this->exoImagineSettings->getSetting('blur');
      $animate = $this->exoImagineSettings->getSetting('animate');
      $is_preview = \Drupal::routeMatch()->getRouteName() === 'media.filter.preview';
      foreach (Element::children($elements) as $delta) {
        $element = &$elements[$delta];
        /** @var \Drupal\file\FileInterface $file */
        $file = $files[$delta];
        $item = $element['#item'];
        $cache = $element['#cache'] + ['tags' => [], 'contexts' => []];
        $entity = $items->getEntity();

        $element = [
          '#theme' => 'exo_imagine',
          '#url' => $this->getLinkUrl($file, $entity),
          '#attributes' => [
            'class' => ['exo-imagine'],
            'data-exo-imagine' => Json::encode($settings),
          ],
          '#image_picture_attributes' => new Attribute([
            'class' => ['exo-imagine-image-picture'],
          ]),
          '#preview_picture_attributes' => new Attribute([
            'class' => ['exo-imagine-preview-picture'],
          ]),
        ];
        if ($is_preview) {
          $element['#attributes']['class'][] = 'exo-imagine-loaded';
        }
        else {
          if ($blur) {
            $element['#attributes']['class'][] = 'exo-imagine-blur';
          }
          elseif ($animate) {
            // Late stage change. Was a simple else statement. If non-blur,
            // non-animated then image was opacity 0.
            $element['#attributes']['class'][] = 'exo-imagine-fade';
          }
        }

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
            $elements['#attributes']['class'][] = 'exo-imagine--svg';
          }
        }
        else {
          $valid_breakpoint_settings = array_filter($breakpoint_settings, function ($data) {
            return !empty($data['width']) || !empty($data['height']);
          });
          $first = array_key_first($valid_breakpoint_settings);
          foreach ($valid_breakpoint_settings as $key => $data) {
            $source_width = $item->width ?? NULL;
            $source_height = $item->height ?? NULL;
            $image_definition = $this->exoImagineManager->getImageDefinition($file, $data['width'], $data['height'], $data['unique'], TRUE, $source_width, $source_height);
            $cache['tags'] = Cache::mergeTags($cache['tags'], $image_definition['cache_tags']);
            $preview_definition = $this->exoImagineManager->getImagePreviewDefinition($file, $data['width'], $data['height'], $data['unique'], $blur, TRUE, $source_width, $source_height);
            $cache['tags'] = Cache::mergeTags($cache['tags'], $preview_definition['cache_tags']);

            if (empty($image_definition['src'])) {
              continue;
            }

            if (isset($image_definition['webp'])) {
              $element['#image_sources'][$key . 'webp'] = new Attribute([
                'media' => $data['media'],
                'data-srcset' => $image_definition['webp'],
                'width' => $preview_definition['width'],
                'height' => $preview_definition['height'],
                'type' => 'image/webp',
              ]);
            }

            $element['#image_sources'][$key] = new Attribute([
              'media' => $data['media'],
              'data-srcset' => $image_definition['src'],
              'type' => $image_definition['mime'],
              'width' => $image_definition['width'],
              'height' => $image_definition['height'],
            ]);

            if (isset($preview_definition['webp'])) {
              $element['#preview_sources'][$key . 'webp'] = new Attribute([
                'media' => $data['media'],
                'srcset' => $preview_definition['webp'],
                'width' => $preview_definition['width'],
                'height' => $preview_definition['height'],
                'type' => 'image/webp',
              ]);
            }

            $element['#preview_sources'][$key] = new Attribute([
              'media' => $data['media'],
              'srcset' => $preview_definition['src'],
              'width' => $preview_definition['width'],
              'height' => $preview_definition['height'],
              'type' => $preview_definition['mime'],
            ]);

            if ($key === $first) {
              $element['#image_attributes'] = new Attribute([
                'src' => 'about:blank',
                'class' => ['exo-imagine-image'],
                'alt' => $item->getValue()['alt'],
                'width' => $image_definition['width'],
                'height' => $image_definition['height'],
              ]);
              $element['#preview_attributes'] = new Attribute([
                'src' => 'about:blank',
                'class' => ['exo-imagine-preview'],
                'alt' => $item->getValue()['alt'],
                'width' => $preview_definition['width'],
                'height' => $preview_definition['height'],
              ]);
            }

            if ($is_preview) {
              $element['#image_attributes']->setAttribute('src', $image_definition['src']);
              $element['#preview_attributes']->setAttribute('style', 'display: none;');
            }
          }
        }
        $element['#cache'] = $cache;
        $element['#attached']['drupalSettings']['exoImagine']['defaults'] = $site_settings;
      }
    }

    return $elements;
  }

  /**
   * Provides content of the file.
   *
   * @param \Drupal\file\Entity\FileInterface $file
   *   File to handle.
   *
   * @return string
   *   File content.
   */
  protected function fileGetContents(FileInterface $file) {
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

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $entities = parent::getEntitiesToView($items, $langcode);
    $entities = $this->filterSelectionEntities($entities);
    return $entities;
  }

}
