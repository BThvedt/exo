<?php

namespace Drupal\exo\Form;

use Drupal\exo\Plugin\ExoThrobberManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ExoLoaderSettingsForm.
 *
 * @package Drupal\exo\Form
 */
class ExoLoaderSettingsForm extends ConfigFormBase {

  /**
   * The throbber manager.
   *
   * @var \Drupal\exo\Plugin\ExoThrobberManagerInterface
   */
  protected $throbberManager;

  /**
   * Function to construct.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\exo\Plugin\ExoThrobberManagerInterface $throbber_manager
   *   The throbber manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, ExoThrobberManagerInterface $throbber_manager) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->throbberManager = $throbber_manager;
  }

  /**
   * Function to create.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container value.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('exo.throbber.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_loader_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['exo.loader'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('exo.loader');

    $form['wrapper'] = [
      '#prefix' => '<div id="throbber-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['wrapper']['throbber'] = [
      '#type' => 'select',
      '#title' => t('Throbber'),
      '#description' => t('Choose your throbber'),
      '#required' => TRUE,
      '#options' => $this->throbberManager->getThrobberOptionList(),
      '#default_value' => $settings->get('throbber'),
      '#ajax' => [
        'wrapper' => 'throbber-wrapper',
        'callback' => [$this, 'ajaxThrobberChange'],
      ],
    ];

    if (!empty($form_state->getValue('throbber')) || !empty($settings->get('throbber'))) {
      $plugin_id = !empty($form_state->getValue('throbber')) ? $form_state->getValue('throbber') : $settings->get('throbber');
      if ($this->throbberManager->getDefinition($plugin_id, FALSE)) {
        // Show preview of throbber.
        if (!empty($form_state->getValue('throbber'))) {
          $throbber = $this->throbberManager->loadThrobberInstance($form_state->getValue('throbber'));
        }
        else {
          $throbber = $this->throbberManager->loadThrobberInstance($settings->get('throbber'));
        }

        $form['wrapper']['throbber']['#attached']['library'] = [
          'exo/throbber_admin',
        ];

        $form['wrapper']['throbber']['#suffix'] = '<span class="throbber-example">' . $throbber->getMarkup() . '</span>';
      }
    }

    $form['hide_ajax_message'] = [
      '#type' => 'checkbox',
      '#title' => t('Never show ajax loading message'),
      '#description' => t('Choose whether you want to hide the loading ajax message even when it is set.'),
      '#default_value' => $settings->get('hide_ajax_message') ?: 0,
    ];

    $form['always_fullscreen'] = [
      '#type' => 'checkbox',
      '#title' => t('Always show loader as overlay (fullscreen)'),
      '#description' => t('Choose whether you want to show the loader as an overlay, no matter what the settings of the loader are.'),
      '#default_value' => $settings->get('always_fullscreen') ?: 0,
    ];

    $form['show_admin_paths'] = [
      '#type' => 'checkbox',
      '#title' => t('Use ajax loader on admin pages'),
      '#description' => t('Choose whether you also want to show the loader on admin pages or still like to use the default core loader.'),
      '#default_value' => $settings->get('show_admin_paths') ?: 0,
    ];

    $form['throbber_position'] = [
      '#type' => 'textfield',
      '#title' => t('Throbber position'),
      '#required' => TRUE,
      '#description' => t('Allows you to change the position where the throbber is inserted. A valid css selector must be used here. The default value is: body'),
      '#default_value' => $settings->get('throbber_position') ?: 'body',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback when throbber is changed.
   */
  public function ajaxThrobberChange(array $form, FormStateInterface $form_state) {
    return $form['wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('exo.loader')
      ->set('throbber', $form_state->getValue('throbber'))
      ->set('hide_ajax_message', $form_state->getValue('hide_ajax_message'))
      ->set('always_fullscreen', $form_state->getValue('always_fullscreen'))
      ->set('show_admin_paths', $form_state->getValue('show_admin_paths'))
      ->set('throbber_position', $form_state->getValue('throbber_position'))
      ->save();

    // Clear cache, so that library is picked up.
    drupal_flush_all_caches();

    parent::submitForm($form, $form_state);
  }

}
