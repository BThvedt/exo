<?php

namespace Drupal\exo_alchemist\Plugin\SectionStorage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\exo_alchemist\ExoComponentSectionNestedStorageInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines the 'components' section storage type.
 *
 * @SectionStorage(
 *   id = "components",
 *   weight = -10,
 *   handles_permission_check = TRUE,
 *   context_definitions = {
 *     "component_entity" = @ContextDefinition("entity", constraints = {
 *       "EntityHasField" = \Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage::FIELD_NAME,
 *     }),
 *     "entity" = @ContextDefinition("entity", constraints = {
 *       "EntityHasField" = \Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage::FIELD_NAME,
 *     }),
 *     "view_mode" = @ContextDefinition("string", default_value = "default"),
 *   }
 * )
 *
 * @internal
 *   Plugin classes are internal.
 */
class ExoComponentSectionStorage extends ExoOverridesSectionStorage implements ExoComponentSectionNestedStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getStorageId() {
    $entity = $this->getEntity();
    $layout_entity = $this->getParentEntity();
    $id = $layout_entity->getEntityTypeId() . '.' . ($layout_entity->isNew() ? $layout_entity->uuid() : $layout_entity->id()) . '.' . $entity->getEntityTypeId() . '.' . $entity->uuid();
    return $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->getContextValue('component_entity');
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntity() {
    if (!empty($this->getContextDefinitions()['layout_builder.entity'])) {
      return $this->getContextValue('layout_builder.entity');
    }
    if (!empty($this->getContextDefinitions()['entity'])) {
      return $this->getContextValue('entity');
    }
    if (!empty($this->getContextDefinitions()['layout_entity'])) {
      return $this->getContextValue('layout_entity');
    }
  }

  /**
   * Get the parent overrides storage.
   *
   * @return \Drupal\layout_builder\OverridesSectionStorageInterface
   *   The overrides storage of the layout.
   */
  public function getParentEntityStorage() {
    $layout_entity = $this->getParentEntity();
    $contexts['entity'] = EntityContext::fromEntity($layout_entity);
    // @todo Expand to work for all view modes in
    //   https://www.drupal.org/node/2907413.
    $view_mode = 'full';
    // Retrieve the actual view mode from the returned view display as the
    // requested view mode may not exist and a fallback will be used.
    $view_mode = LayoutBuilderEntityViewDisplay::collectRenderDisplay($layout_entity, $view_mode)->getMode();
    $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
    return $this->sectionStorageManager->load('overrides', $contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function buildRoutes(RouteCollection $collection) {}

  /**
   * {@inheritdoc}
   */
  public function buildLocalTasks($base_plugin_definition) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($account === NULL) {
      $account = $this->currentUser;
    }

    $entity = $this->getParentEntity();

    // Create an access result that will allow access to the layout if one of
    // these conditions applies:
    // 1. The user can configure any layouts.
    $any_access = AccessResult::allowedIfHasPermission($account, 'configure any layout');
    // 2. The user can configure layouts on all items of the bundle type.
    $bundle_access = AccessResult::allowedIfHasPermission($account, "configure all {$entity->bundle()} {$entity->getEntityTypeId()} layout overrides");
    // 3. The user can configure layouts items of this bundle type they can edit
    //    AND the user has access to edit this entity.
    $edit_only_bundle_access = AccessResult::allowedIfHasPermission($account, "configure editable {$entity->bundle()} {$entity->getEntityTypeId()} layout overrides");
    $edit_only_bundle_access = $edit_only_bundle_access->andIf($entity->access('update', $account, TRUE));

    $result = $any_access
      ->orIf($bundle_access)
      ->orIf($edit_only_bundle_access);

    // Access also depends on the default being enabled.
    $result = $result->andIf($this->getDefaultSectionStorage()->access($operation, $account, TRUE));
    $result = $this->handleTranslationAccess($result, $operation, $account);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutBuilderUrl($rel = 'view') {
    $entity = $this->getParentEntity();
    $route_parameters[$entity->getEntityTypeId()] = $entity->id();
    return Url::fromRoute("layout_builder.overrides.{$entity->getEntityTypeId()}.$rel", $route_parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function deriveContextsFromRoute($value, $definition, $name, array $defaults) {
    $contexts = [];

    $layout_entity = $this->extractEntityFromRoute($value, $defaults);
    $component_entity = NULL;
    if ($layout_entity) {
      $contexts['entity'] = EntityContext::fromEntity($layout_entity);
      $component_entity = $this->extractComponentEntityFromLayoutEntity($value, $layout_entity);
    }

    if ($component_entity) {
      $contexts['component_entity'] = EntityContext::fromEntity($component_entity);
      // @todo Expand to work for all view modes in
      //   https://www.drupal.org/node/2907413.
      $view_mode = 'full';
      // Retrieve the actual view mode from the returned view display as the
      // requested view mode may not exist and a fallback will be used.
      $view_mode = LayoutBuilderEntityViewDisplay::collectRenderDisplay($component_entity, $view_mode)->getMode();
      $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  private function extractEntityFromRoute($value, array $defaults) {
    [$layout_entity_type_id, $layout_entity_id] = explode('.', $value, 4);
    $entity = $this->entityRepository->getActive($layout_entity_type_id, $layout_entity_id);
    if ($entity instanceof FieldableEntityInterface && $entity->hasField(static::FIELD_NAME)) {
      return $entity;
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  private function extractComponentEntityFromLayoutEntity($value, EntityInterface $layout_entity) {
    $entity = NULL;
    [,, $entity_type_id, $entity_id] = explode('.', $value, 4);
    $entity = $this->entityRepository->getActive($entity_type_id, $entity_id);
    if (!$entity) {
      /** @var \Drupal\exo_alchemist\ExoComponentRepository $repository */
      $repository = \Drupal::service('exo_alchemist.repository');
      $components_with_section = $repository->getComponents($layout_entity, TRUE);
      foreach ($components_with_section as $component_with_section) {
        if ($component_with_section->uuid() === $entity_id) {
          return $component_with_section;
        }
        foreach ($component_with_section->getFields() as $field) {
          if ($field instanceof EntityReferenceFieldItemListInterface && !$field->isEmpty()) {
            foreach ($field->referencedEntities() as $ref) {
              if ($ref->uuid() === $entity_id) {
                return $ref;
              }
            }
          }
        }
      }
    }
    return $entity;
  }

}
