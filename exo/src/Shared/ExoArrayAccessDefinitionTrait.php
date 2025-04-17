<?php

namespace Drupal\exo\Shared;

/**
 * Class ArrayAccessDefinitionTrait.
 *
 * @property $definition
 *
 * @package Drupal\exo\Definition
 */
trait ExoArrayAccessDefinitionTrait {

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetExists($offset) {
    return array_key_exists($offset, $this->definition);
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetGet($offset) {
    return $this->definition[$offset] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetSet($offset, $value) {
    $this->definition[$offset] = $value;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetUnset($offset) {
    unset($this->definition[$offset]);
  }

}
