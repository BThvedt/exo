<?php

namespace Drupal\exo_toolbar\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsTrait;
use Drupal\exo_toolbar\Entity\ExoToolbarItemInterface;

/**
 * An AJAX command for inserting a region on an item.
 *
 * @ingroup ajax
 */
class ExoToolbarRegionItemCommand implements CommandInterface, CommandWithAttachedAssetsInterface {
  use CommandWithAttachedAssetsTrait;

  /**
   * A CSS selector string.
   *
   * @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface
   */
  protected $exoToolbarItem;

  /**
   * A settings array to be passed to any attached JavaScript behavior.
   *
   * @var array
   */
  protected $settings;

  /**
   * The renderable content array.
   *
   * @var array
   */
  protected $content;

  /**
   * Constructs an ExoToolbarRegionItemCommand object.
   *
   * @param \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface $exo_toolbar_item
   *   A eXo toolbar item.
   * @param array $settings
   *   An array of JavaScript settings to be passed to any attached behaviors.
   */
  public function __construct(ExoToolbarItemInterface $exo_toolbar_item, ?array $settings = NULL) {
    $this->exoToolbarItem = $exo_toolbar_item;
    $this->content = [
      '#type' => 'exo_toolbar_region',
      '#exo_toolbar' => $exo_toolbar_item->getToolbar(),
      '#exo_toolbar_region_id' => 'item:' . $exo_toolbar_item->id(),
      '#attached' => ['library' => ['exo_toolbar/region']],
    ];
    $this->settings = $settings;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'exoToolbarRegion',
      'method' => 'append',
      'selector' => '#' . $this->exoToolbarItem->getToolbar()->getAttributeId(),
      'item_id' => $this->exoToolbarItem->id(),
      'data' => $this->getRenderedContent(),
      'settings' => $this->settings,
    ];
  }

}
