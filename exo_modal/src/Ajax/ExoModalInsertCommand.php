<?php

namespace Drupal\exo_modal\Ajax;

use Drupal\Core\Ajax\InsertCommand;

/**
 * Defines an AJAX command to insert a modal.
 *
 * @ingroup ajax
 */
class ExoModalInsertCommand extends InsertCommand {

  /**
   * Constructs an InsertCommand object.
   *
   * @param string $selector
   *   A CSS selector.
   * @param string|array $content
   *   The content that will be inserted in the matched element(s), either a
   *   render array or an HTML string.
   * @param array $settings
   *   An array of JavaScript settings to be passed to any attached behaviors.
   */
  public function __construct($selector, $content, ?array $settings = NULL) {
    $this->selector = $selector;
    if (!is_array($content)) {
      $content = ['#markup' => $content];
    }
    $content['#attached']['library'][] = 'exo_modal/ajax';
    $this->content = $content;
    $this->settings = $settings;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {

    return [
      'command' => 'exoModalInsert',
      'selector' => $this->selector,
      'data' => $this->getRenderedContent(),
      'settings' => $this->settings,
    ];
  }

}
