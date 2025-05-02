<?php

namespace Drupal\exo_menu;

use Drupal\exo\ExoSettingsPluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_menu\Plugin\ExoMenuPluginManagerInterface;

/**
 * Class UxMenuOptions.
 *
 * @package Drupal\exo_menu
 */
class ExoMenuSettings extends ExoSettingsPluginBase {

  /**
   * The eXo menu manager.
   *
   * @var \Drupal\exo_menu\Plugin\ExoMenuPluginManagerInterface
   */
  protected $exoMenuManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, ExoMenuPluginManagerInterface $exo_menu_manager) {
    parent::__construct($config_factory);
    $this->exoMenuManager = $exo_menu_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginManager() {
    return $this->exoMenuManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleId() {
    return 'exo_menu';
  }

  /**
   * Get the menu form.
   *
   * @param array $settings
   *   The current enabled menus as an array of menu ids.
   * @param array $limit_menus
   *   (optional) Array of menu names to limit the options, or NULL to load all.
   *
   * @return array
   *   The menu form.
   */
  public function buildMenuForm(array $settings, ?array $limit_menus = NULL) {
    $form = [
      '#type' => 'table',
      '#header' => [
        $this->t('Menus'),
        $this->t('Weight'),
      ],
      '#element_validate' => [[get_class($this), 'validateMenuForm']],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'menu-weight',
        ],
      ],
    ];
    $count = 0;
    foreach ($this->getMenuOptions($settings, $limit_menus) as $id => $label) {
      $form[$id]['#attributes']['class'][] = 'draggable';
      $form[$id]['#weight'] = $count;
      $form[$id]['status'] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#default_value' => in_array($id, $settings),
      ];
      $form[$id]['weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => $count,
        '#attributes' => ['class' => ['menu-weight']],
      ];
      $count++;
    }
    return $form;
  }

  /**
   * Given the menu form values, clean them into a simple array.
   */
  public static function validateMenuForm($element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    $values = array_filter($values, function ($menu) {
      return $menu['status'] == 1;
    });
    uasort($values, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    $values = array_keys($values);
    $form_state->setValueForElement($element, $values);
  }

  /**
   * Gets a list of menu names for use as options.
   *
   * @param array $settings
   *   (optional) The current enabled menus as an array of menu ids.
   * @param array $limit_menus
   *   (optional) Array of menu names to limit the options, or NULL to load all.
   *
   * @return array
   *   Keys are menu names (ids) values are the menu labels.
   */
  protected function getMenuOptions(array $settings = [], ?array $limit_menus = NULL) {
    $menus = \Drupal::entityTypeManager()->getStorage('menu')->loadMultiple($limit_menus);
    $options = array_flip($settings);
    /** @var \Drupal\system\MenuInterface[] $menus */
    foreach ($menus as $menu) {
      $options[$menu->id()] = $menu->label();
    }
    return $options;
  }

}
