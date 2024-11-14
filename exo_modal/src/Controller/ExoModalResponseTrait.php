<?php

namespace Drupal\exo_modal\Controller;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\exo_modal\Ajax\ExoModalContentCommand;
use Drupal\exo_modal\Ajax\ExoModalInsertCommand;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a helper to determine if the current request is via AJAX.
 *
 * @internal
 */
trait ExoModalResponseTrait {

  use AjaxHelperTrait;

  /**
   * The eXo modal generator.
   *
   * @var \Drupal\exo_modal\ExoModalGeneratorInterface
   */
  protected $exoModalGenerator;

  /**
   * Build a modal.
   */
  protected function buildModalResponse(Request $request, $build, $settings = []) {
    $response = new AjaxResponse();
    $parameters = $request->query->all();
    // This request has been requested from an existing modal.
    if (!empty($parameters['from_modal'])) {
      $response->addCommand(new ExoModalContentCommand($build));
      return $response;
    }
    $settings = NestedArray::mergeDeep($settings, $parameters['modal'] ?: []);
    $modal = $this->exoModalGenerator()->generate('exo_modal_' . time(), NestedArray::mergeDeep([
      'modal' => [
        'autoOpen' => TRUE,
        'destroyOnClose' => TRUE,
      ],
    ], $settings));
    $modal->setContent($build);
    $response->addCommand(new ExoModalInsertCommand('body', $modal->toRenderableModal()));
    return $response;
  }

  /**
   * Retrieves eXo modal generator.
   *
   * @return \Drupal\exo_modal\ExoModalGeneratorInterface
   *   The eXo modal generator.
   */
  protected function exoModalGenerator() {
    if (!isset($this->exoModalGenerator)) {
      $this->exoModalGenerator = \Drupal::service('exo_modal.generator');
    }
    return $this->exoModalGenerator;
  }

}
