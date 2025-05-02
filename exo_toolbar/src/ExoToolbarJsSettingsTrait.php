<?php

namespace Drupal\exo_toolbar;

/**
 * Provides javascript settings helpers.
 */
trait ExoToolbarJsSettingsTrait {

  /**
   * The element javascript settings.
   *
   * @var array
   */
  protected $jsSettings = [];

  /**
   * {@inheritdoc}
   */
  public function addJsSettings(array $value) {
    $this->jsSettings = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addJsSetting($name, $value) {
    $this->jsSettings[$name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsSettings() {
    return $this->jsSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsSetting($name) {
    return $this->jsSettings[$name] ?? NULL;
  }

}
