<?php

namespace Drupal\exo_form;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Class ExoFormElementHandler.
 */
class ExoFormElementHandler implements TrustedCallbackInterface {

  /**
   * Drupal\exo_form\Plugin\ExoFormManager definition.
   *
   * @var \Drupal\exo_form\Plugin\ExoFormManager
   */
  protected static $exoFormPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Pre-render an element.
   *
   * @param array $element
   *   The element being pre-rendered.
   */
  public static function preRender(array $element) {
    if (empty($element['#type']) || !exo_form_access()) {
      return $element;
    }
    $is_admin = exo_is_admin();
    $is_form = !empty($element['#parents']);
    if ($is_form || $is_admin) {
      $type = $element['#type'];
      if (empty($element['#attributes']['aria-label'])) {
        $element['#attributes']['aria-label'] = $element['#placeholder'] ?? $element['#title'] ?? NULL;
        if (is_array($element['#attributes']['aria-label'])) {
          $element['#attributes']['aria-label'] = NULL;
        }
      }
      foreach (static::exoFormPluginManager()->getPluginInstancesByType($type) as $id => $instance) {
        if ($instance->applies($element)) {
          $element = $instance->preRender($element);
        }
      }
      if (!$is_form && !$is_admin) {
        $element['#theme_wrappers'][] = 'exo_form_container';
      }
      $element['#attached']['library'][] = 'exo_form/base';
    }
    return $element;
  }

  /**
   * Gets the exo form plugin manager.
   *
   * @return \Drupal\exo_form\Plugin\ExoFormManager
   *   The exo form plugin manager.
   */
  private static function exoFormPluginManager() {
    return static::$exoFormPluginManager ?: \Drupal::service('plugin.manager.exo_form');
  }

}
