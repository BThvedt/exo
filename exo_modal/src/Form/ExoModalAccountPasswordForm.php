<?php

namespace Drupal\exo_modal\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Form\UserPasswordForm;

/**
 * Provides a user login form.
 *
 * @internal
 */
class ExoModalAccountPasswordForm extends UserPasswordForm {
  use ExoModalFormAjaxTrait;

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $exo_settings = NULL) {
    $form = parent::buildForm($form, $form_state);
    $form['#id'] = 'exo-modal-account-password';
    $form['#attributes']['class'][] = 'exo-modal-account--password';

    $form['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#attributes' => [
        'class' => ['form--title'],
      ],
      '#value' => $this->t('Need help getting into your account?'),
      '#weight' => -100,
    ];

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['form--description'],
      ],
      '#value' => $this->t("Enter your email address - if it's attached to your account, check your inbox within a few minutes for a link to help you access your account."),
      '#weight' => -100,
    ];

    $form['mail']['#access'] = FALSE;

    $form['actions']['#weight'] = 1000;
    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxSubmit',
        'wrapper' => $form['#id'],
      ];
      $form['actions']['close'] = [
        '#markup' => '<a href="#" data-exo-modal-close>' . $this->t('Never Mind') . '</a>',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $this->ajaxMessages($response, '#' . $form['#id']);
    // $response->addCommand(new RedirectCommand('/'));
    return $response;
  }

}
