<?php

namespace Drupal\exo_modal\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Form\UserLoginForm;
use Drupal\exo_modal\Element\ExoModalUrlTrait;

/**
 * Provides a user login form.
 *
 * @internal
 */
class ExoModalAccountLoginForm extends UserLoginForm {
  use ExoModalFormAjaxTrait;
  use ExoModalUrlTrait;

  /**
   * The settings.
   *
   * @var array
   */
  protected $modalSettings;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $modal_settings = NULL) {
    $this->modalSettings = $modal_settings;
    $form = parent::buildForm($form, $form_state);
    $form['#id'] = 'exo-modal-account-login';
    $form['#action'] = Url::fromRoute('user.login')->toString();

    $form['exo_modal_messages'] = [
      '#markup' => '<div class="exo-modal-messages"></div>',
      '#weight' => -1000,
    ];

    $form['forgot'] = [
      '#type' => 'link',
      '#title' => $this->t('Forgot your password?'),
      '#url' => Url::fromRoute('user.pass'),
    ];

    $form['actions']['#weight'] = 1000;
    if ($this->isAjax()) {
      $form['forgot'] = [
        '#type' => 'exo_modal',
        '#id' => 'exo_modal_account_password',
        '#title' => $this->t('Forgot your password?'),
        '#ajax_url' => Url::fromRoute('exo_modal.api.account.password'),
        '#trigger_attributes' => [
          'href' => Url::fromRoute('user.pass')->toString(),
        ],
        '#modal_settings' => [
          'modal' => [
            'title' => '',
            'inherit' => TRUE,
          ],
        ],
        '#use_close' => FALSE,
        '#form_element' => FALSE,
      ];

      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxSubmit',
        'wrapper' => $form['#id'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForgot(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    if ($destination = \Drupal::destination()->get()) {
      $response->addCommand(new RedirectCommand($destination));
    }
    return $response;
  }

}
