<?php

namespace Drupal\exo_modal\Plugin\Block;

use Drupal\exo_modal\Plugin\ExoModalBlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;
use Drupal\Component\Utility\NestedArray;

/**
 * Provides a block to display a view within a modal.
 *
 * @Block(
 *   id = "exo_modal_views",
 *   admin_label = @Translation("eXo Modal Views"),
 *   provider = "exo_modal"
 * )
 */
class ExoModalViewsBlock extends ExoModalBlockBase {

  /**
   * The view.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'view' => [
        'view_id' => '',
        'view_display_id' => '',
        'view_argument' => '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $element_id = 'exoModalViewBlock-view-display-select';
    $view = NestedArray::getValue($form_state->getUserInput(), [
      'settings',
      'view',
      'view_id',
    ]) ?: $this->configuration['view']['view_id'];

    $form['view'] = [
      '#type' => 'details',
      '#title' => $this->t('View'),
      '#open' => TRUE,
      '#attributes' => [
        'id' => $element_id,
      ],
    ];

    $form['view']['view_id'] = [
      '#type' => 'select',
      '#title' => $this->t('View'),
      '#options' => $this->getViews(),
      '#default_value' => $this->configuration['view']['view_id'],
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
      '#limit_validation_errors' => [],
      '#multiple' => FALSE,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'event' => 'change',
        'wrapper' => $element_id,
        'progress' => [
          'type' => 'throbber',
          'message' => t('Getting display Ids...'),
        ],
      ],
    ];

    $form['view']['view_display_id'] = [
      '#type' => 'select',
      '#title' => $this->t('View Display'),
      '#options' => [],
      '#default_value' => $this->configuration['view']['view_display_id'],
      '#empty_option' => $this->t('- Select -'),
      '#empty_value' => '',
      '#multiple' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="settings[view][view_id]"]' => ['filled' => TRUE],
        ],
      ],
    ];

    if (!empty($view)) {
      $form['view']['view_display_id']['#options'] += $this->getViewDisplayIds($view);
      $form['view']['view_display_id']['#required'] = TRUE;
    }

    $form['view']['view_argument'] = [
      '#title' => $this->t('Argument'),
      '#type' => 'textfield',
      '#default_value' => !empty($this->configuration['view_argument']) ? $this->configuration['view_argument'] : NULL,
      '#states' => [
        'visible' => [
          ':input[name="settings[view][view_id]"]' => ['filled' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * AJAX function to get display IDs for a particular View.
   */
  public static function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    array_pop($parents);
    $element = NestedArray::getValue($form, $parents);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['view'] = $form_state->getValue(['view']);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildModalContent() {
    return $this->getView()->buildRenderable();
  }

  /**
   * Helper function to get all display ids.
   */
  protected function getView() {
    if (!isset($this->view)) {
      $argument = $this->configuration['view']['view_argument'];
      $arguments = [$argument];
      if (preg_match('/\//', $argument)) {
        $arguments = explode('/', $argument);
      }
      $this->view = Views::getView($this->configuration['view']['view_id']);
      $this->view->setDisplay($this->configuration['view']['view_display_id']);
      $this->view->setArguments($arguments);
      // ViewExecutable does not extend CacheableDependencyInterface so this
      // needs to be done the dirty way.
      $this->getCacheableMetadata()->addCacheTags($this->view->getCacheTags());
    }
    return $this->view;
  }

  /**
   * Helper function to get all display ids.
   */
  protected function getViews() {
    $views = Views::getEnabledViews();
    $options = [];
    foreach ($views as $view) {
      if ($view->status()) {
        $options[$view->get('id')] = $view->get('label');
      }
    }
    return $options;
  }

  /**
   * Helper to get display ids for a particular View.
   */
  protected function getViewDisplayIds($entity_id) {
    $views = Views::getEnabledViews();
    $options = [];
    foreach ($views as $view) {
      if ($view->get('id') == $entity_id) {
        foreach ($view->get('display') as $display) {
          $options[$display['id']] = $display['display_title'];
        }
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $this->getView();
    return parent::getCacheTags();
  }

}
