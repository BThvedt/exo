<?php

namespace Drupal\exo_toolbar\Plugin;

use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;

/**
 * A trait that provides link utilities.
 */
trait ExoToolbarItemPluginLinkTrait {

  /**
   * Convert a uri or Drupal\Core\Url into Drupal\Core\Url.
   *
   * @var mixed $uri
   *  A uri or Drupal\Core\Url.
   */
  protected function getUrl($uri) {
    if ($uri instanceof Url) {
      $url = $uri;
    }
    else {
      $url = Url::fromUri($uri);
    }
    return $url;
  }

  /**
   * Convert a uri or Drupal\Core\Url into attributes.
   *
   * @var mixed $uri
   *  A uri or Drupal\Core\Url.
   */
  protected function getUriAsAttributes($uri, $attributes = []) {
    if ($url = $this->getUrl($uri)) {
      // External URLs can not have cacheable metadata.
      if ($url->isExternal()) {
        $href = $url->toString(FALSE);
      }
      elseif ($url->isRouted() && $url->getRouteName() === '<nolink>') {
        $href = '';
      }
      else {
        $generated_url = $url->toString(TRUE);
        // The result of the URL generator is a plain-text URL to use as the
        // href attribute, and it is escaped by \Drupal\Core\Template\Attribute.
        $href = $generated_url->getGeneratedUrl();

        if ($url->isRouted()) {
          // Set data element for active link setting.
          // @todo Drupal's active-link.js seems to not work for this. Why?
          $system_path = $url->getInternalPath();
          // Special case for the front page.
          $attributes['data-drupal-link-system-path'] = $system_path == '' ? '<front>' : $system_path;
        }
      }
      $attributes['href'] = $href;
    }
    return $attributes;
  }

  /**
   * Check if user has access to page.
   *
   * @param mixed $uri
   *   The uri or \Drupal\Core\Url object.
   *
   * @return Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function uriAccess($uri) {
    if (!empty($uri)) {
      if ($uri instanceof Url) {
        $url = $uri;
      }
      else {
        $url = Url::fromUri($uri);
      }
      return $url->access() ? AccessResult::allowed() : AccessResult::forbidden();
    }
    return AccessResult::forbidden();
  }

}
