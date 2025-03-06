<?php

namespace Drupal\exo_alchemist\Plugin;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_alchemist\ExoComponentValues;

/**
 * Base class for Component Field plugins.
 */
abstract class ExoComponentFieldComputedBase extends ExoComponentFieldBase implements ExoComponentFieldComputedInterface {

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    $field = $this->getFieldDefinition();
    $field->setComputed(TRUE);
    parent::processDefinition($field);
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldClean(ContentEntityInterface $entity, $update = TRUE) {}

  /**
   * {@inheritdoc}
   */
  public function onDraftUpdateLayoutBuilderEntity(ContentEntityInterface $entity) {}

  /**
   * {@inheritdoc}
   */
  public function onPreSaveLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity) {}

  /**
   * {@inheritdoc}
   */
  public function onPostSaveLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity) {}

  /**
   * {@inheritdoc}
   */
  public function onPostDeleteLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity) {}

  /**
   * {@inheritdoc}
   */
  public function onDiscardLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity) {}

  /**
   * {@inheritdoc}
   */
  public function onClone(ContentEntityInterface $entity, $all = FALSE) {}

  /**
   * {@inheritdoc}
   */
  public function populateValues(ExoComponentValues $values, ContentEntityInterface $entity) {}

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity, array $contexts) {
    // Allow component to act before values are built.
    if ($handler = $this->getFieldDefinition()->getComponent()->getHandler()) {
      $handler->fieldPreViewAlter($this, $entity, $contexts);
    }
    return [
      $this->viewValue($entity, $contexts),
    ];
  }

  /**
   * Return the computed value of a field that is set as computed.
   *
   * @param \Drupal\core\Entity\ContentEntityInterface $entity
   *   The entity being rendered.
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   *
   * @return array
   *   A value that will be sent to twig.
   */
  public function viewValue(ContentEntityInterface $entity, array $contexts) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function access(array $contexts, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?: \Drupal::currentUser();
    $access = $this->componentAccess($contexts, $account);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Indicates whether the field should be shown.
   *
   * Fields with specific access checking should override this method rather
   * than access(), in order to avoid repeating the handling of the
   * $return_as_object argument.
   *
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   *
   * @see self::access()
   */
  protected function componentAccess(array $contexts, AccountInterface $account) {
    // By default, the field is visible.
    return AccessResult::allowed();
  }

}
