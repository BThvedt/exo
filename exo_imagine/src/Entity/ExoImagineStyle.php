<?php

namespace Drupal\exo_imagine\Entity;

use Drupal\image\ImageStyleInterface;

/**
 * Decorator for a commerce order.
 */
class ExoImagineStyle implements ExoImagineStyleInterface {

  /**
   * The image style.
   *
   * @var \Drupal\image\ImageStyleInterface
   */
  protected $style;

  /**
   * The last used timestamp.
   *
   * @var int
   */
  protected $lastUsed;

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ImageStyleInterface $style) {
    $this->style = $style;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->getStyle()->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getStyle()->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getStyle() {
    $style = $this->style;
    while (is_a($style, get_class($this))) {
      /** @var \Drupal\exo_imagine\Entity\ExoImagineStyleInterface $style */
      $style = $style->getStyle();
    }
    return $style;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->getStyle()->getThirdPartySetting('exo_imagine', 'data', [
      'width' => 0,
      'height' => 0,
      'unique' => '',
      'quality' => NULL,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getWidth() {
    return $this->getSettings()['width'];
  }

  /**
   * {@inheritdoc}
   */
  public function getHeight() {
    return $this->getSettings()['height'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUnique() {
    return $this->getSettings()['unique'];
  }

  /**
   * {@inheritdoc}
   */
  public function getQuality() {
    return $this->getSettings()['quality'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLastUsedTimestamp() {
    if (!isset($this->lastUsed)) {
      /** @var \Drupal\Core\State\StateInterface $state */
      $this->lastUsed = $this->state()->get($this->getStyle()->getName(), 0);
    }
    return $this->lastUsed;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastUsedTimestamp() {
    $request_time = \Drupal::time()->getCurrentTime();
    $this->state()->set($this->getStyle()->getName(), $request_time);
    return $this;
  }

  /**
   * Returns the state service.
   *
   * @return \Drupal\Core\State\StateInterface
   *   The state service.
   */
  protected function state() {
    if (!$this->state) {
      $this->state = \Drupal::service('state');
    }
    return $this->state;
  }

}
