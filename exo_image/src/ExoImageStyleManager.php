<?php

namespace Drupal\exo_image;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\image\ImageEffectManager;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\exo\ExoSettingsInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class ExoImageStyleManager.
 */
class ExoImageStyleManager implements ExoImageStyleManagerInterface {
  use ExoIconTranslationTrait;

  /**
   * Drupal\Core\Extension\ModuleHandlerInterface definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Drupal\image\ImageEffectManager definition.
   *
   * @var \Drupal\image\ImageEffectManager
   */
  protected $pluginManagerImageEffect;

  /**
   * Exo settings.
   *
   * @var \Drupal\exo\ExoSettingsInterface
   */
  protected $exoSettings;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface defintion
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new ExoImageStyleManager object.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, ImageFactory $imageFactory, ImageEffectManager $plugin_manager_image_effect, ExoSettingsInterface $exo_settings, LoggerChannelFactoryInterface $logger_factory) {
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->imageFactory = $imageFactory;
    $this->pluginManagerImageEffect = $plugin_manager_image_effect;
    $this->exoSettings = $exo_settings;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Given a raw width and height: check if it adheres to the settings.
   *
   * @param int $width
   *   The raw requested width.
   * @param int $height
   *   The raw requested height.
   *
   * @return bool
   *   Indicates valid width/height against the settings.
   */
  public function checkRequestedDimensions($width, $height) {
    if ($width != intval($width) || $height != intval($height)) {
      return FALSE;
    }

    // Check if the width is between the defined min/max settings.
    if ($width > $this->exoSettings->getSetting('downscale') || $width < $this->exoSettings->getSetting('upscale')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Try and find an image style that matches the requested dimensions.
   *
   * @param int $width
   *   The width.
   * @param int $height
   *   The height.
   * @param int $quality
   *   The image quality.
   *
   * @return mixed
   *   A matching image style or NULL if none was found.
   */
  public function findImageStyle($width = NULL, $height = NULL, $quality = NULL) {
    if ($width) {
      $width = round($width);
    }
    if ($height) {
      $height = round($height);
    }
    $image_style_storage = $this->entityTypeManager->getStorage('image_style');
    $focal_point = $this->moduleHandler->moduleExists('focal_point');

    // Try and get an exact match:
    $name = $this->getImageStyleName($width, $height, $quality);
    $image_style = $image_style_storage->load($name);

    if (empty($image_style)) {
      // If the image has a height we might be able to use an image style with a
      // very small distortion.
      if (isset($height) && $height > 0) {
        $styles = $image_style_storage->loadMultiple();
        $current_ratio_distortion_diff = 360;
        foreach ($styles as $name => $style) {
          // Calculate the dimensions from the style name.
          $translated_name = str_replace('exo_image_', '', $name);
          // Skip image styles without exo_ prefix.
          if ($name == $translated_name) {
            continue;
          }
          if ($focal_point) {
            $translated_name = str_replace('focal_', '', $translated_name);
          }
          $dimensions = explode('__', $translated_name);
          // Skip image styles that will only scale.
          if ($dimensions[1] <= 0) {
            continue;
          }

          if ($dimensions[0] == $width) {
            // Find an image style with the least amount of distortion.
            $ratio_distortion = deg2rad($this->exoSettings->getSetting('ratio_distortion') / 60);
            $ratio = $dimensions[0] / $dimensions[1];
            $requested_ratio = $width / $height;
            $calculated_ratio_distortion_diff = abs(atan($ratio) - atan($requested_ratio));
            if ($calculated_ratio_distortion_diff <= $ratio_distortion
              && $calculated_ratio_distortion_diff < $current_ratio_distortion_diff) {
              $current_ratio_distortion_diff = $calculated_ratio_distortion_diff;
              $image_style = $styles[$name];
            }
          }
        }
      }
    }

    // No usable image style could be found, so we will have to create one.
    if (empty($image_style)) {
      // When the site starts from a cold cache situation and a lot of requests
      // come in, the webserver might fail at this point, so try a few times.
      $counter = 0;
      while (empty($image_style) && $counter < 10) {
        usleep(rand(10000, 50000));
        $image_style = $this->createImageStyle($width, $height, $quality);
        $counter++;
      }
    }

    return $image_style;
  }

  /**
   * Get unique style name.
   *
   * @param int $width
   *   The width.
   * @param int $height
   *   The height.
   * @param int $quality
   *   The image quality.
   *
   * @return string
   *   The name.
   */
  protected function getImageStyleName($width = NULL, $height = NULL, $quality = NULL) {
    $focal_point = $this->moduleHandler->moduleExists('focal_point');
    $name = 'exo_image';
    if ($focal_point) {
      $name = 'exo_image_focal';
    }
    $name .= '_' . $width . '__' . $height;
    if ($quality) {
      $name .= '__' . $quality;
    }
    return str_replace('.', '_', $name);
  }

  /**
   * Get unique style label.
   *
   * @param int $width
   *   The width.
   * @param int $height
   *   The height.
   * @param int $quality
   *   The image quality.
   *
   * @return string
   *   The name.
   */
  protected function getImageStyleLabel($width = NULL, $height = NULL, $quality = NULL) {
    $focal_point = $this->moduleHandler->moduleExists('focal_point');
    $label = 'eXo Image';
    if ($focal_point) {
      $label = 'eXo Image Focal';
    }
    $label .= ' (' . $width . 'x' . $height . ')';
    if ($quality) {
      $label .= ' (Quality: ' . $quality . '%)';
    }
    return $label;
  }

  /**
   * Load image style.
   *
   * @param int $width
   *   The width.
   * @param int $height
   *   The height.
   * @param int $quality
   *   The image quality.
   *
   * @return mixed
   *   The image style or FALSE in case something went wrong.
   */
  public function createImageStyle($width = NULL, $height = NULL, $quality = NULL) {
    $image_style_storage = $this->entityTypeManager->getStorage('image_style');
    $focal_point = $this->moduleHandler->moduleExists('focal_point');
    $name = $this->getImageStyleName($width, $height, $quality);
    $label = $this->getImageStyleLabel($width, $height, $quality);

    // When multiple images width the same dimension are requested in 1 page
    // we can sometimes trigger errors here. Image style can already be
    // created by another request that came in a few milliseconds before this
    // request. Catch that error and try and use the image style that was
    // already created.
    try {
      $style = $image_style_storage->create(['name' => $name, 'label' => $label]);
      $configuration = [
        'uuid' => NULL,
        'weight' => 0,
        'data' => [
          'upscale' => TRUE,
          'width' => NULL,
          'height' => NULL,
        ],
      ];
      $configuration['data']['width'] = $width;
      if ($height > 0) {
        $configuration['data']['height'] = $height;
      }

      // Height is NULL by default, images are scaled.
      if ($configuration['data']['width'] == NULL || $configuration['data']['height'] == NULL) {
        $configuration['id'] = 'image_scale';
      }
      else {
        $configuration['id'] = 'image_scale_and_crop';
        // If focal point module is activated, use that image style instead.
        if ($focal_point) {
          $configuration['id'] = 'focal_point_scale_and_crop';
        }
      }

      $effect = $this->pluginManagerImageEffect->createInstance($configuration['id'], $configuration);
      $style->addImageEffect($effect->getConfiguration());

      if ($quality) {
        // Quality.
        $configuration = [
          'id' => 'image_style_quality',
          'uuid' => NULL,
          'weight' => 1,
          'data' => [
            'quality' => $quality,
          ],
        ];
        $effect = $this->pluginManagerImageEffect->createInstance($configuration['id'], $configuration);
        $style->addImageEffect($effect->getConfiguration());
      }

      $style->save();
      $styles[$name] = $style;
      $image_style = $styles[$name];
    }
    catch (EntityStorageException $e) {
      // Wait a tiny little bit to make sure another request isn't still adding
      // effects to the image style.
      usleep(rand(10000, 50000));
      $image_style = $image_style_storage->load($name);
    }
    catch (\Exception $e) {
      return NULL;
    }
    return $image_style;
  }

  /**
   * Delete all exo image style derivatives.
   */
  public function deleteImageStyles() {
    $storage = $this->entityTypeManager->getStorage('image_style');
    $query = $storage->getQuery();
    $query->condition('name', 'exo_image_', 'STARTS_WITH');
    $entities = $storage->loadMultiple($query->execute());
    $storage->delete($entities);

    $storage = $this->entityTypeManager->getStorage('image_style');
    $query = $storage->getQuery();
    $query->condition('name', 'drimage_', 'STARTS_WITH');
    $entities = $storage->loadMultiple($query->execute());
    $storage->delete($entities);
  }

  /**
   * Creates a WebP copy of a source image URI.
   *
   * @param string $uri
   *   Image URI.
   * @param int $quality
   *   Image quality factor.
   *
   * @return bool|string
   *   The location of the WebP image if successful, FALSE if not successful.
   */
  public function createWebpCopy($uri, $quality = NULL) {
    $webp = FALSE;
    $pathInfo = pathinfo($uri);
    $destination = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    if (!$quality) {
      $quality = $this->exoSettings->getSetting('webp_quality');
    }
    if (file_exists($destination) && filemtime($uri) <= filemtime($destination)) {
      $webp = $destination;
    }
    else {
      // Generate a GD resource from the source image. You can't pass GD resources
      // created by the $imageFactory as a parameter to another function, so we
      // have to do everything in one function.
      $sourceImage = $this->imageFactory->get($uri, 'gd');
      /** @var \Drupal\system\Plugin\ImageToolkit\GDToolkit $toolkit */
      $toolkit = $sourceImage->getToolkit();
      $sourceImage = $toolkit->getImage();

      // If we can generate a GD resource from the source image, generate the URI
      // of the WebP copy and try to create it.
      if ($sourceImage !== NULL) {
        if (function_exists('imagewebp') && @imagewebp($sourceImage, $destination, $quality)) {
          @imagedestroy($sourceImage);
          $webp = $destination;
        }
        elseif (extension_loaded('imagick')) {
          $image = new \Imagick($uri);
          $image->setImageFormat('webp');
          $image->setImageCompressionQuality($quality);
          $image->setImageAlphaChannel(\imagick::ALPHACHANNEL_ACTIVATE);
          $image->setBackgroundColor(new \ImagickPixel('transparent'));
          $image->writeImage(\Drupal::service('file_system')->realpath($destination));
          $webp = $destination;
        }
        else {
          $error = $this->t('Could not generate WebP image.');
          $this->loggerFactory->get('exo_image')->error($error);
        }
      }
    }
    return $webp;
  }

  /**
   * Check if server supports webp conversion.
   */
  public function supportsWebP() {
    if (function_exists('imagewebp')) {
      return 'gd';
    }
    if (extension_loaded('imagick')) {
      return 'imagick';
    }
    return FALSE;
  }

  /**
   * Get theme breakpoints.
   */
  public function getBreakpoints($theme = NULL) {
    $breakpoints = [];
    if (!$theme) {
      $theme = \Drupal::config('system.theme')->get('default');
    }
    foreach (array_reverse(\Drupal::service('breakpoint.manager')->getBreakpointsByGroup($theme)) as $key => $breakpoint) {
      $parts = explode('.', $key);
      $breakpoints[$parts[1]] = $breakpoint;
    }
    return $breakpoints;
  }

}
