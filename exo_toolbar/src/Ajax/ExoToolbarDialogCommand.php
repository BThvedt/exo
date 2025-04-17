<?php

namespace Drupal\exo_toolbar\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsTrait;
use Drupal\exo_toolbar\Entity\ExoToolbarItemInterface;

/**
 * An AJAX command for inserting a dialog on an item.
 *
 * @ingroup ajax
 */
class ExoToolbarDialogCommand implements CommandInterface, CommandWithAttachedAssetsInterface {

  use CommandWithAttachedAssetsTrait;

  /**
   * A CSS selector string.
   *
   * @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface
   */
  protected $exoToolbarItem;

  /**
   * The content for the matched element(s).
   *
   * Either a render array or an HTML string.
   *
   * @var string|array
   */
  protected $content;

  /**
   * A settings array to be passed to any attached JavaScript behavior.
   *
   * @var array
   */
  protected $settings;

  /**
   * Constructs an ExoToolbarDialogCommand object.
   *
   * @param \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface $exo_toolbar_item
   *   A eXo toolbar item.
   * @param string|array $content
   *   The content that will be inserted in the matched element(s), either a
   *   render array or an HTML string.
   * @param array $settings
   *   An array of JavaScript settings to be passed to any attached behaviors.
   */
  public function __construct(ExoToolbarItemInterface $exo_toolbar_item, $content, ?array $settings = NULL) {
    if (!is_array($content)) {
      $content = ['#markup' => $content];
    }
    $this->exoToolbarItem = $exo_toolbar_item;
    $this->content = $content;
    $this->settings = $settings;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {

    return [
      'command' => 'exoToolbarDialog',
      'item_id' => $this->exoToolbarItem->id(),
      'data' => $this->getRenderedContent(),
      'settings' => $this->settings,
    ];
  }

}
