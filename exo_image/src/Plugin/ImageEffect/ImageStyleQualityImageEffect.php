<?php

namespace Drupal\exo_image\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows you to change the quality of an image, per image style.
 *
 * @ImageEffect(
 *   id = "image_style_quality",
 *   label = @Translation("Image Style Quality"),
 *   description = @Translation("Allows you to change the quality of an image, per image style.")
 * )
 */
class ImageStyleQualityImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, $gdConfig) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->gdConfig = $gdConfig;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('image'),
      $container->get('config.factory')->get('system.image.gd')
    );
  }

  /**
   * The GD image config object.
   *
   * @var null
   */
  public $gdConfig = NULL;

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $quality = $this->configuration['quality'];
    $this->gdConfig->setModuleOverride([
      'jpeg_quality' => $quality,
    ]);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $existing_quality = $this->configuration['quality'] ?? '75';
    $form['quality'] = [
      '#type' => 'number',
      '#title' => t('Quality'),
      '#description' => t('Define the image quality for JPEG manipulations. Ranges from 0 to 100. Higher values mean better image quality but bigger files.'),
      '#min' => 0,
      '#max' => 100,
      '#default_value' => $existing_quality,
      '#field_suffix' => t('%'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['quality'] = $form_state->getValue('quality');
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $quality = $this->configuration['quality'];
    return [
      '#markup' => '(' . $quality . '% ' . $this->t('Quality') . ')',
    ];
  }

}
