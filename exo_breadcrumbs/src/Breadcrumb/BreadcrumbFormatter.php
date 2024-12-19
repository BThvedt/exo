<?php

namespace Drupal\exo_breadcrumbs\Breadcrumb;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteObjectInterface as RoutingRouteObjectInterface;
use Drupal\system\PathBasedBreadcrumbBuilder;

/**
 * Adds the current page title to the breadcrumb.
 *
 * Applies the set home/first link text from within the form.
 *
 * Extend PathBased Breadcrumbs to include the current page title.
 *
 * {@inheritdoc}
 */
class BreadcrumbFormatter extends PathBasedBreadcrumbBuilder {

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    // Determine if it is not an admin route.
    if (!exo_is_admin()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $parent_breadcrumb = parent::build($route_match);
    $links = $parent_breadcrumb->getLinks();

    if (empty($links)) {
      return $parent_breadcrumb;
    }

    $breadcrumb->addCacheContexts(['url.path.parent', 'url.path.is_front', 'route']);
    // $breadcrumb->addCacheContexts($parent_breadcrumb->getCacheContexts());

    // Set home to configured title.
    /** @var \Drupal\Core\Link $first_link */
    $first_link = reset($links);
    if ($first_link->getUrl()->getRouteName() == '<front>') {
      $first_link->setText(\Drupal::service('exo_breadcrumbs.settings')->getSetting('home_title'));
    }

    // Adds current page title as non-clickable final breadcrumb.
    $request = \Drupal::request();
    $route = $request->attributes->get(RoutingRouteObjectInterface::ROUTE_OBJECT);
    $title = $this->titleResolver->getTitle($request, $route);
    if (!isset($title)) {
      $path = trim($this->context->getPathInfo(), '/');
      $path_elements = explode('/', $path);
      // Fallback to using the raw path component as the title if the
      // route is missing a _title or _title_callback attribute.
      $title = str_replace(['-', '_'], ' ', Unicode::ucfirst(end($path_elements)));
    }
    $links[] = Link::createFromRoute($title, '<none>');

    return $breadcrumb->setLinks($links);
  }

}
