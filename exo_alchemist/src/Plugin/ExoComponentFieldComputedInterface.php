<?php

namespace Drupal\exo_alchemist\Plugin;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_alchemist\ExoComponentValues;

/**
 * Defines an interface for Component Field plugins.
 */
interface ExoComponentFieldComputedInterface extends ExoComponentFieldInterface {

  /**
   * Acts on field as the default component is being removed.
   *
   * This method is called when a component default is being removed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The default component entity.
   * @param bool $update
   *   If this is a field update.
   */
  public function onFieldClean(ContentEntityInterface $entity, $update = TRUE);

  /**
   * Operations that can be run before update during layout building.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component.
   */
  public function onDraftUpdateLayoutBuilderEntity(ContentEntityInterface $entity);

  /**
   * Acts on field before it is updated.
   *
   * This method is called when a field is being updated from within a
   * layout-builder-enabled entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this field.
   */
  public function onPreSaveLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity);

  /**
   * Acts on field after it is updated.
   *
   * This method is called when a field is being updated from within a
   * layout-builder-enabled entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this field.
   */
  public function onPostSaveLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity);

  /**
   * Acts on field after it is deleted.
   *
   * This method is called when a field is being removed from within a
   * layout-builder-enabled entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this field.
   */
  public function onPostDeleteLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity);

  /**
   * Acts on discard changes.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The layout-builder-enabled entity that contains this field.
   */
  public function onDiscardLayoutBuilderEntity(ContentEntityInterface $entity, EntityInterface $parent_entity);

  /**
   * Acts on field before it is cloned.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component.
   * @param bool $all
   *   Flag that determines if this is a partial clone or full clone.
   */
  public function onClone(ContentEntityInterface $entity, $all = FALSE);

  /**
   * Called when default entity is being populated with values.
   *
   * @param \Drupal\exo_alchemist\ExoComponentValues $values
   *   The field values.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity.
   */
  public function populateValues(ExoComponentValues $values, ContentEntityInterface $entity);

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
  public function view(ContentEntityInterface $entity, array $contexts);

  /**
   * Return the default value of an item.
   *
   * @param \Drupal\Core\Plugin\Context\Context[] $contexts
   *   An array of current contexts.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   * @param bool $return_as_object
   *   If TRUE, will return access as object.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(array $contexts, AccountInterface $account = NULL, $return_as_object = FALSE);

}
