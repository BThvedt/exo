<?php

namespace Drupal\exo_alchemist\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a unique integer.
 *
 * @Constraint(
 *   id = "ExoComponentTag",
 *   label = @Translation("eXo Component Tag", context = "Validation"),
 * )
 */
class ExoComponentTag extends Constraint {

  /**
   * Message.
   *
   * @var string
   */
  public $notFound = '%value tag not found';

  /**
   * The tags option.
   *
   * @var string|array
   */
  public $tags;

  /**
   * Gets the tags option as array.
   *
   * @return array
   *   The tags.
   */
  public function getTags() {
    // Support passing the tags as string, but force it to be an array.
    if (!is_array($this->tags)) {
      $this->tags = [$this->tags];
    }
    return $this->tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): ?string {
    return 'tags';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['tags'];
  }

}
