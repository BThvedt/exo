<?php

namespace Drupal\exo_entity_browser\Plugin\EntityBrowser\Display;

use Drupal\entity_browser\Plugin\EntityBrowser\Display\Modal;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\exo_modal\Ajax\ExoModalOpenCommand;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Presents entity browser in an Modal.
 *
 * @EntityBrowserDisplay(
 *   id = "exo_modal",
 *   label = @Translation("eXo Modal"),
 *   description = @Translation("Displays the entity browser in an eXo Modal."),
 *   uses_route = TRUE
 * )
 */
class ExoModal extends Modal {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'modal_description' => $this->t('Select from library or upload new.'),
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();

    $form = parent::buildConfigurationForm($form, $form_state);

    $form['modal_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal description'),
      '#default_value' => $configuration['modal_description'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function displayEntityBrowser(array $element, FormStateInterface $form_state, array &$complete_form, array $persistent_data = []) {
    $display = parent::displayEntityBrowser($element, $form_state, $complete_form, $persistent_data);
    $display['open_modal']['#attached']['library'] = ['exo_entity_browser/modal'];
    $entities = $form_state->getValue(array_splice($element['#eb_parents'], 0, -1));
    foreach ($entities as $entity) {
      if ($entity instanceof EntityInterface) {
        $display['open_modal']['#attached']['drupalSettings']['entity_browser'][$this->getUuid()]['entities'][] = $entity->getEntityTypeId() . ':' . $entity->id();
      }
    }
    $display['open_modal']['#attached']['drupalSettings']['entity_browser']['exo_modal'] = $display['open_modal']['#attached']['drupalSettings']['entity_browser']['modal'];
    unset($display['open_modal']['#attached']['drupalSettings']['entity_browser']['modal']);
    return $display;
  }

  /**
   * Generates the content and opens the modal.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response.
   */
  public function openModal(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    array_pop($parents);
    $parents = array_merge($parents, ['path']);
    $input = $form_state->getUserInput();
    $src = NestedArray::getValue($input, $parents);

    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
    $cardinality = $element['#cardinality'];

    $content = [];
    $element_name = $this->configuration['entity_browser_id'];
    $name = 'entity_browser_iframe_' . $element_name;

    $options = [
      'title' => $this->configuration['link_text'],
      'subtitle' => $this->configuration['modal_description'],
      'icon' => 'thin-camera',
      'iframe' => TRUE,
      'iframeURL' => $src,
      'openFullscreen' => TRUE,
      'padding' => '1.24rem',
    ];

    $response = new AjaxResponse();
    $response->addCommand(new ExoModalOpenCommand('exo_entity_browser', $content, $options));
    return $response;
  }

  /**
   * KernelEvents::RESPONSE listener.
   *
   * Intercepts default response and injects response that will trigger JS to
   * propagate selected entities upstream.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *
   *   Response event.
   */
  public function propagateSelection(ResponseEvent $event) {
    $render = [
      'labels' => [
        '#markup' => 'Labels: ' . implode(', ', array_map(function (EntityInterface $item) {
          return $item->label();
        }, $this->entities)),
        '#attached' => [
          'library' => ['exo_entity_browser/modal.selection'],
          'drupalSettings' => [
            'entity_browser' => [
              $this->pluginDefinition['id'] => [
                'entities' => array_map(function (EntityInterface $item) {
                  return [$item->id(), $item->uuid(), $item->getEntityTypeId()];
                }, $this->entities),
                'uuid' => $this->request->query->get('uuid'),
              ],
            ],
          ],
        ],
      ],
    ];

    $event->setResponse(new Response(\Drupal::service('bare_html_page_renderer')->renderBarePage($render, 'Entity browser', 'page')));
  }

}
