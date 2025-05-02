<?php

namespace Drupal\exo\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\exo\ExoThemePluginManagerInterface;
use Drupal\exo\ExoThemeProviderPluginManagerInterface;
use Drupal\Core\Cache\Cache;

/**
 * Class ExoThemeSettingsForm.
 */
class ExoThemeSettingsForm extends ConfigFormBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The eXo theme manager.
   *
   * @var \Drupal\exo\ExoThemePluginManagerInterface
   */
  protected $exoThemeManager;

  /**
   * Drupal\exo\ExoThemeProviderPluginManagerInterface definition.
   *
   * @var \Drupal\exo\ExoThemeProviderPluginManagerInterface
   */
  protected $exoThemeProviderManager;

  /**
   * Constructs a new ExoThemeSettingsForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ThemeHandlerInterface $theme_handler,
    ExoThemePluginManagerInterface $exo_theme_manager,
    ExoThemeProviderPluginManagerInterface $exo_theme_provider_manager,
  ) {
    parent::__construct($config_factory);
    $this->themeHandler = $theme_handler;
    $this->exoThemeManager = $exo_theme_manager;
    $this->exoThemeProviderManager = $exo_theme_provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('theme_handler'),
      $container->get('plugin.manager.exo_theme'),
      $container->get('plugin.manager.exo_theme_provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'exo.theme',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_theme_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = $this->exoThemeManager->getDefinitions();
    array_walk($options, function (&$value, $key) {
      return $value = [
        '#theme' => 'exo_theme_option',
        '#label' => $value['label'],
        '#colors' => $value['colors'],
      ];
    });

    $config = $this->config('exo.theme');
    $form['theme'] = [
      '#type' => 'exo_radios',
      '#exo_style' => 'inline',
      '#title' => $this->t('Theme'),
      '#description' => $this->t('The eXo theme that will be used by all supported modules.'),
      '#default_value' => $config->get('theme'),
      '#options' => $options,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('exo.theme')
      ->set('theme', $form_state->getValue('theme'))
      ->save();

    // Clear libraries cache.
    foreach (Cache::getBins() as $service_id => $cache_backend) {
      $cache_backend->deleteAll();
    }
  }

}
