<?php

namespace Drupal\exo_config_file\Plugin\Block;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Image\ImageFactory;
use Drupal\exo_link\Plugin\Block\LinkBlock;

/**
 * Provides a block to display a config file image.
 *
 * @Block(
 *   id = "image",
 *   admin_label = @Translation("Image"),
 * )
 */
class ImageBlock extends LinkBlock implements ContainerFactoryPluginInterface {

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
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs an AggregatorFeedBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user, EntityStorageInterface $image_style_storage, ImageFactory $image_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->imageStyleStorage = $image_style_storage;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('image.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'image' => '',
      'image_style' => 'thumbnail',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $image = $this->configuration['image'];

    $form['title']['#title'] = $this->t('Alt text');
    $form['uri']['#required'] = FALSE;
    $form['icon']['#access'] = FALSE;

    $form['image'] = [
      '#type' => 'exo_config_file',
      '#title' => !$image ? $this->t('Image') : $this->t('Replace Image'),
      '#default_value' => $image,
      '#extensions' => ['jpg', 'jpeg', 'png', 'gif'],
      '#required' => empty($image),
    ];

    $image_styles = image_style_options(FALSE);
    $description_link = Link::fromTextAndUrl(
      $this->t('Configure Image Styles'),
      Url::fromRoute('entity.image_style.collection')
    );
    $form['image_style'] = [
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->configuration['image_style'],
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $image_styles,
      '#description' => $description_link->toRenderable() + [
        '#access' => $this->currentUser->hasPermission('administer image styles'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['image'] = $form_state->getValue('image');
    $this->configuration['image_style'] = $form_state->getValue('image_style');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $image = \Drupal::service('image.factory')->get($this->configuration['image']);
    if ($image->isValid()) {
      $cache_tags = [];
      if (!empty($this->configuration['image_style'])) {
        $image_style = $this->imageStyleStorage->load($this->configuration['image_style']);
        $cache_tags = $image_style->getCacheTags();
        $build['image'] = [
          '#theme' => 'image_style',
          '#style_name' => $this->configuration['image_style'],
          '#width' => $image->getWidth(),
          '#height' => $image->getHeight(),
          '#uri' => $image->getSource(),
          '#attributes' => [
            'alt' => $this->configuration['title'],
          ],
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ];
      }
      else {
        $build['image'] = [
          '#theme' => 'image',
          '#width' => $image->getWidth(),
          '#height' => $image->getHeight(),
          '#uri' => $image->getSource(),
          '#attributes' => [
            'alt' => $this->configuration['title'],
          ],
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ];
      }
      if (!empty($this->configuration['uri'])) {
        $build['image'] = [
          '#title' => $build['image'],
          '#type' => 'link',
          '#url' => Url::fromUri($this->configuration['uri']),
        ];
      }
    }
    return $build;
  }

}
