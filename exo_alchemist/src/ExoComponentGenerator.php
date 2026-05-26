<?php

namespace Drupal\exo_alchemist;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\DefaultsSectionStorageInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\Form\OverridesEntityForm;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Cache\Cache;

/**
 * Class ExoComponentGenerator.
 */
class ExoComponentGenerator {

  use LayoutEntityHelperTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The eXo component manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * The UUID generator service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * Cached entity builders.
   *
   * @var \Drupal\exo_alchemist\ExoComponentBuilder[]
   */
  protected $entityBuilders;

  /**
   * The section storages keyed.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface[]
   */
  protected $sectionStorages;

  /**
   * Constructs a new ExoComponentGenerator object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The component manager.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid generator.
   */
  public function __construct(Connection $database, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ExoComponentManager $exo_component_manager, SectionStorageManagerInterface $section_storage_manager, UuidInterface $uuid) {
    $this->database = $database;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->exoComponentManager = $exo_component_manager;
    $this->sectionStorageManager = $section_storage_manager;
    $this->uuidGenerator = $uuid;
  }

  /**
   * Return instance of a component builder.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to insert the component into.
   * @param bool $temporary
   *   If TRUE, components will only be saved as temporary.
   * @param string $view_mode
   *   The entity view mode.
   *
   * @return \Drupal\exo_alchemist\ExoComponentBuilder
   *   The component builder.
   */
  public function getEntityBuilder(EntityInterface $entity, $temporary = FALSE, $view_mode = 'full') {
    $view_mode = LayoutBuilderEntityViewDisplay::collectRenderDisplay($entity, $view_mode)->getMode();
    $key = $entity->uuid() . $view_mode . (string) $temporary;
    if (!isset($this->entityBuilders[$key])) {
      $this->entityBuilders[$key] = new ExoComponentBuilder($this->layoutTempstoreRepository, $this->exoComponentManager, $this->uuidGenerator, $this->getSectionStorageForEntity($entity), $entity, $temporary, $view_mode);
    }
    return $this->entityBuilders[$key];
  }

  /**
   * Determines if an entity is a component type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity is a component type otherwise FALSE.
   */
  protected function isComponentTypeEntity(EntityInterface $entity) {
    return $entity->getEntityTypeId() == ExoComponentManager::ENTITY_BUNDLE_TYPE;
  }

  /**
   * Determines if an entity is a component.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity is a component otherwise FALSE.
   */
  protected function isComponentEntity(EntityInterface $entity) {
    return $entity->getEntityTypeId() == ExoComponentManager::ENTITY_TYPE;
  }

  /**
   * Determines if an entity can have a layout.
   *
   * This is converted to a public method.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity can have a layout otherwise FALSE.
   */
  public function isLayoutCompatibleEntity(EntityInterface $entity) {
    return $this->getSectionStorageForEntity($entity) !== NULL;
  }

  /**
   * Called when a node form has been submitted.
   */
  public function nodeFormSubmit(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $entity = $form_object->getEntity();
    $user = \Drupal::currentUser();
    if ($this->isLayoutCompatibleEntity($entity) && ($user->hasPermission('configure any layout') || $user->hasPermission('configure editable ' . $entity->bundle() . ' ' . $entity->getEntityTypeId() . ' layout overrides'))) {
      $form_state->setRedirect('layout_builder.overrides.' . $entity->getEntityTypeId() . '.view', [
        'node' => $entity->id(),
      ]);
    }
  }

  /**
   * Called before entity has been saved.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity about to be saved.
   *
   * @return $this
   */
  public function handlePreSave(EntityInterface $entity) {
    $this->exoComponentManager->handleEntityEvent('preSave', $entity);
    if ($this->isLayoutCompatibleEntity($entity)) {
      $this->handleLayoutEntityPreSave($entity);
      if ($entity->getEntityTypeId() == 'entity_view_display') {
        // Because layout builder removed block_serialized before we can get to
        // it, we manually call layout_builder_entity_presave when appropriate.
        $this->handleEntityViewDisplayPreSave($entity);
      }
      if (class_exists('\Drupal\layout_builder\Hook\LayoutBuilderHooks')) {
        $hooks = new \Drupal\layout_builder\Hook\LayoutBuilderHooks();
        return $hooks->entityPresave($entity);
      }
      elseif (function_exists('layout_builder_entity_presave')) {
        // Call core's presave.
        layout_builder_entity_presave($entity);
      }

      // We need to remove the block_revision_id so that it is not exported.
      // We will use the block_uuid to load the block.
      if ($entity instanceof LayoutEntityDisplayInterface) {
        $layout = $entity->getThirdPartySetting('layout_builder', 'sections');
        foreach ($layout as $section) {
          /** @var \Drupal\layout_builder\Section $section */
          foreach ($section->getComponents() as $component) {
            if (ExoComponentManager::isExoComponent($component)) {
              $configuration = $component->get('configuration');
              $configuration['block_revision_id'] = '';
              $component->setConfiguration($configuration);
            }
          }
        }
        $layout = $entity->setThirdPartySetting('layout_builder', 'sections', $layout);
      }

      // Support global components.
      if ($sections = $this->getEntitySections($entity)) {
        foreach ($sections as $section) {
          foreach ($section->getComponents() as $component) {
            $plugin = $component->getPlugin();
            if ($plugin instanceof DerivativeInspectionInterface && $plugin->getBaseId() === 'global_block') {
              /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $plugin */
              $plugin->saveBlockContent();
              $post_save_configuration = $plugin->getConfiguration();
              $component->setConfiguration($post_save_configuration);
            }
          }
        }
      }
    }
    if ($this->isComponentEntity($entity)) {
      $this->handleComponentEntityPreSave($entity);
    }
    return $this;
  }

  /**
   * Called before component entity has been saved.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component entity.
   *
   * @return $this
   */
  protected function handleComponentEntityPreSave(ContentEntityInterface $entity) {
    if (substr($entity->bundle(), 0, 4) == 'exo_' && $entity->id() && $entity->isNew()) {
      // This is an ugly workaround of the lack of deep serialization. Entities
      // nested more than 1 level are never serialized and we therefore we set
      // these entities as "new" so that they are serialized and then we set
      // them back here.
      // @see \Drupal\exo_alchemist\Form/ExoFieldUpdateForm::submitForm().
      // @see https://www.drupal.org/project/drupal/issues/2824097
      // @todo Remove when patch added to core.
      $entity->enforceIsNew(FALSE);
      $entity->original = \Drupal::entityTypeManager()->getStorage('block_content')->load($entity->id());
    }
    return $this;
  }

  /**
   * Called before layout-enabled entity has been saved.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout-enabled entity.
   *
   * @return $this
   */
  protected function handleLayoutEntityPreSave(EntityInterface $entity) {
    if (!empty($entity->exoAlchemistClone)) {
      $to_storage = $this->getSectionStorageForEntity($entity);
      $this->cloneComponents($to_storage);
    }
    if (!$entity->isNew()) {
      // Handle component creation and removal as implemented by the builder.
      if (!empty($entity->_exoComponent)) {
        foreach ($entity->_exoComponent as $view_mode => $data) {
          $builder = $this->getEntityBuilder($entity, $data['temporary'], $view_mode);
          if ($sections = $builder->getTemporaryValue('sections')) {
            $builder->doSave($sections);
          }
          if ($remove = $builder->getTemporaryValue('remove')) {
            foreach ($remove as $component) {
              $builder->doRemove($component);
            }
          }
        }
        unset($entity->_exoComponent);
      }
      if (!$entity instanceof LayoutEntityDisplayInterface) {
        $from_storage = $this->getSectionStorageForEntity($entity->original);
        $to_storage = $this->getSectionStorageForEntity($entity);

        // Remove all locked sections so that layout builder will not try to
        // duplicate them. They will be added back in dynamically.
        foreach (static::getLockedSections($to_storage->getSections()) as $section) {
          foreach ($section->getComponents() as $uuid => $component) {
            $section->removeComponent($uuid);
          }
        }

        // When moving from default storage to override storage.
        if ($from_storage instanceof DefaultsSectionStorageInterface && $to_storage instanceof OverridesSectionStorageInterface) {
          $this->onDefaultToOverride($entity, $from_storage, $to_storage);
        }
        // When moving from override storage to default storage.
        if ($from_storage instanceof OverridesSectionStorageInterface && $to_storage instanceof DefaultsSectionStorageInterface) {
          $this->onOverrideToDefault($entity, $from_storage, $to_storage);
        }
        // When updated an override storage.
        if ($from_storage instanceof OverridesSectionStorageInterface && $to_storage instanceof OverridesSectionStorageInterface) {
          $this->onOverrideToOverride($entity, $from_storage, $to_storage);
        }
      }
    }
    else {
      if (!empty($entity->_exoComponent)) {
        // Core layout builder will try to process sections. It cannot handle
        // sections set on new nodes. We remove the sections here and re-add
        // them in ::handlePostSave().
        $section_storage = $this->getSectionStorageForEntity($entity);
        if ($section_storage) {
          $section_storage->removeAllSections(TRUE);
        }
      }
    }
    return $this;
  }

  /**
   * Called before layout-enabled entity view display entity has been saved.
   *
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $entity
   *   The layout-enabled entity.
   *
   * @return $this
   */
  protected function handleEntityViewDisplayPreSave(EntityViewDisplayInterface $entity) {
    if ($entity instanceof LayoutEntityDisplayInterface && empty($entity->handleEntityViewDisplayPreSaveSkip)) {
      $storage = [];
      $this->handleEntityViewDisplayPreSaveRecursive($entity->getSections(), $entity, $storage);
      if (!empty($storage)) {
        foreach ($entity->getThirdPartySettings('exo_alchemist') as $key => $serialized_block) {
          $entity->unsetThirdPartySetting('exo_alchemist', $key);
        }
        foreach ($storage as $key => $serialized_block) {
          $entity->setThirdPartySetting('exo_alchemist', $key, $serialized_block);
        }
      }
      // Make sure to clear out any temp storage we might have.
      $this->layoutTempstoreRepository->delete($this->getSectionStorageForEntity($entity));
    }
    return $this;
  }

  /**
   * Loop through all components and extract their entities as needed.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   The sections.
   * @param \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $entity
   *   The layout-enabled entity.
   * @param array $storage
   *   The extracted entities.
   *
   * @return $this
   */
  protected function handleEntityViewDisplayPreSaveRecursive(array $sections, LayoutEntityDisplayInterface $entity, array &$storage = []) {
    foreach ($sections as $section) {
      foreach ($section->getComponents() as $uuid => $component) {
        if (ExoComponentManager::isExoComponent($component)) {
          // We can skip this when manually adding a field.
          if (substr(\Drupal::routeMatch()->getRouteName(), 0, 34) === 'field_ui.field_storage_config_add_') {
            continue;
          }
          $key = 'component_' . $component->getUuid();
          $existing_block = $entity->getThirdPartySetting('exo_alchemist', $key, NULL);
          $stored_block = unserialize($existing_block ?? '');
          $configuration = $component->get('configuration');
          $is_updated = !empty($configuration['block_serialized']);
          $do_save = FALSE;
          /** @var \Drupal\block_content\BlockContentInterface $block */
          // If we have a block_uuid we want to make sure we ignore the revision
          // id as it may not be the same across environments.
          $block = $this->exoComponentManager->entityLoadFromComponent($component, empty($configuration['block_uuid']));
          if (!$existing_block && !$block) {
            // This is a serious issue as we have no way to build this entity.
            continue;
          }
          if (!$block && $stored_block) {
            $block = $stored_block->createDuplicate();
            $block->set('uuid', $stored_block->uuid());
            $do_save = TRUE;
          }
          if ($section_storage = $this->getSectionStorageForEntity($block)) {
            $nested_sections = $section_storage->getSections();
            if ($existing_block) {
              if ($stored_storage = $this->getSectionStorageForEntity($stored_block)) {
                $stored_sections = $stored_storage->getSections();
                if ($nested_sections != $stored_sections) {
                  $is_updated = TRUE;
                }
              }
            }
            $block->enforceIsNew(FALSE);
            $this->handleEntityViewDisplayPreSaveRecursive($nested_sections, $entity, $storage);
          }
          // Check rescursive before adding to storage so that children are
          // built first.
          $storage[$key] = $existing_block;
          if (!$existing_block || $is_updated) {
            $storage[$key] = serialize($block);
          }
          else {
            /** @var \Drupal\block_content\BlockContentInterface $stored_block */
            if ($stored_block->getChangedTime() > $block->getChangedTime()) {
              foreach ($stored_block->getFields(FALSE) as $key => $items) {
                if (in_array($key, ['id', 'revision_id', 'revision_user'])) {
                  continue;
                }
                $block->set($key, $items->getValue());
              }
              $do_save = TRUE;
            }
          }
          if ($do_save) {
            $block->save();
          }
          $configuration['block_uuid'] = $block->uuid();
          $configuration['block_revision_id'] = $block->get('revision_id')->value;
          $component->setConfiguration($configuration);
        }
        else {
          // Remove all non-exo components.
          $section->removeComponent($uuid);
        }
      }
    }
    return $this;
  }

  /**
   * Gets the section storage for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The section storage if found otherwise NULL.
   */
  protected function getNestedSectionStorageForEntity(EntityInterface $parent_entity, EntityInterface $entity) {
    // @todo Take into account other view modes in
    //   https://www.drupal.org/node/3008924.
    $view_mode = 'full';
    $contexts['entity'] = EntityContext::fromEntity($parent_entity);
    $contexts['component_entity'] = EntityContext::fromEntity($entity);
    if ($entity instanceof FieldableEntityInterface) {
      $view_mode = LayoutBuilderEntityViewDisplay::collectRenderDisplay($entity, $view_mode)->getMode();
      $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
    }
    $storage = $this->sectionStorageManager()->findByContext($contexts, new CacheableMetadata());
    if ($storage && $this->layoutTempstoreRepository->has($storage)) {
      $storage = $this->layoutTempstoreRepository->get($storage);
    }
    return $storage;
  }

  /**
   * Clone components.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $to_storage
   *   The new section storage.
   */
  protected function cloneComponents(SectionStorageInterface $to_storage) {
    // For all unlocked sections, we want to clone the component.
    foreach (static::getUnlockedSections($to_storage->getSections()) as $section) {
      foreach ($section->getComponents() as $component) {
        if (ExoComponentManager::isExoComponent($component)) {
          $component_entity = NULL;
          $configuration = $component->get('configuration');
          if (!empty($configuration['block_revision_id'])) {
            $component_entity = $this->exoComponentManager->entityLoadByRevisionId($configuration['block_revision_id']);
          }
          if (!empty($configuration['block_uuid'])) {
            $component_entity = $this->exoComponentManager->entityLoadByUuid($configuration['block_uuid']);
          }
          if ($component_entity) {
            $definition = $this->exoComponentManager->getEntityComponentDefinition($component_entity);
            $component_entity->exoAlchemistClone = TRUE;
            $component_entity = $this->exoComponentManager->cloneEntity($definition, $component_entity);
            $configuration['block_revision_id'] = $configuration['block_uuid'] = NULL;
            $configuration['block_serialized'] = serialize($component_entity);
            $component->setConfiguration($configuration);
          }
        }
      }
    }
  }

  /**
   * Clone components usage.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\layout_builder\SectionStorageInterface $to_storage
   *   The new section storage.
   */
  protected function cloneComponentsUsage(EntityInterface $entity, SectionStorageInterface $to_storage) {
    // For all unlocked sections, we want to clone the component.
    foreach (static::getUnlockedSections($to_storage->getSections()) as $section) {
      foreach ($section->getComponents() as $component) {
        if (ExoComponentManager::isExoComponent($component)) {
          $component_entity = NULL;
          $configuration = $component->get('configuration');
          if (!empty($configuration['block_revision_id'])) {
            $component_entity = $this->exoComponentManager->entityLoadByRevisionId($configuration['block_revision_id']);
          }
          if (!empty($configuration['block_uuid'])) {
            $component_entity = $this->exoComponentManager->entityLoadByUuid($configuration['block_uuid']);
          }
          if ($component_entity) {
            // We need to save usage.
            /** @var \Drupal\layout_builder\InlineBlockUsage $inline_block_usage */
            $inline_block_usage = \Drupal::service('inline_block.usage');
            $inline_block_usage->deleteUsage([$component_entity->id()]);
            $inline_block_usage->addUsage($component_entity->id(), $entity);
          }
        }
      }
    }
  }

  /**
   * When moving from default storage to override storage.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout-enabled entity.
   * @param \Drupal\layout_builder\SectionStorageInterface $from_storage
   *   The previous section storage.
   * @param \Drupal\layout_builder\SectionStorageInterface $to_storage
   *   The new section storage.
   */
  protected function onDefaultToOverride(EntityInterface $entity, SectionStorageInterface $from_storage, SectionStorageInterface $to_storage) {
    $this->cloneComponents($to_storage);
  }

  /**
   * When moving from override storage to default storage.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout-enabled entity.
   * @param \Drupal\layout_builder\SectionStorageInterface $from_storage
   *   The previous section storage.
   * @param \Drupal\layout_builder\SectionStorageInterface $to_storage
   *   The new section storage.
   */
  protected function onOverrideToDefault(EntityInterface $entity, SectionStorageInterface $from_storage, SectionStorageInterface $to_storage) {
    if ($this->exoComponentManager->entityAllowCleanup($entity->original)) {
      // Loop through unlocked sections and remove all components.
      foreach (static::getUnlockedSections($from_storage->getSections()) as $original_section) {
        foreach ($original_section->getComponents() as $component) {
          $this->removeComponent($entity, $component);
        }
      }
    }
  }

  /**
   * When moving from override storage to override storage.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout-enabled entity.
   * @param \Drupal\layout_builder\SectionStorageInterface $from_storage
   *   The previous section storage.
   * @param \Drupal\layout_builder\SectionStorageInterface $to_storage
   *   The new section storage.
   */
  protected function onOverrideToOverride(EntityInterface $entity, SectionStorageInterface $from_storage, SectionStorageInterface $to_storage) {
    if ($this->exoComponentManager->entityAllowCleanup($entity->original)) {
      // Loop through unlocked sections and delete all components.
      foreach (static::getUnlockedSections($from_storage->getSections()) as $delta => $original_section) {
        $from_components = $original_section->getComponents();
        $section = $to_storage->getSection($delta);
        if ($section) {
          /** @var \Drupal\layout_builder\SectionComponent[] $removed_components */
          $removed_components = array_diff_key($from_components, $section->getComponents());
          foreach ($removed_components as $component) {
            $this->removeComponent($entity, $component);
          }
        }
      }
    }
  }

  /**
   * Called after entity has been saved.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout-enabled entity.
   *
   * @return $this
   */
  public function handlePostSave(EntityInterface $entity) {
    if (!empty($entity->_exoComponent)) {
      // New entities cannot be processed in handlePreSave. In these instances,
      // we resave the entity dynamically and allow component creation.
      $entity->enforceIsNew(FALSE);
      $entity->original = clone $entity;
      $entity->save();
    }
    if ($this->isComponentTypeEntity($entity)) {
      $this->handleComponentTypeEntityPostSave($entity);
    }
    if ($this->isLayoutCompatibleEntity($entity)) {
      $this->handleLayoutEntityPostSave($entity);
    }
    $this->exoComponentManager->handleEntityEvent('postSave', $entity);
    return $this;
  }

  /**
   * Called after layout-enabled entity has been saved.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout-enabled entity.
   *
   * @return $this
   */
  protected function handleLayoutEntityPostSave(EntityInterface $entity) {
  
    // Invalidate block_content cache tags for all inline components used in
    // this layout entity, so changes (e.g. video swaps) are visible immediately.
    $block_ids = \Drupal::database()
      ->select('inline_block_usage', 'ibu')
      ->fields('ibu', ['block_content_id'])
      ->condition('layout_entity_type', $entity->getEntityTypeId())
      ->condition('layout_entity_id', $entity->id())
      ->execute()
      ->fetchCol();

    if ($block_ids) {
      $tags = array_map(fn($id) => 'block_content:' . $id, $block_ids);
      Cache::invalidateTags($tags);
    }

    if (!empty($entity->exoAlchemistClone)) {
      $to_storage = $this->getSectionStorageForEntity($entity);
      $this->cloneComponentsUsage($entity, $to_storage);
    }
    return $this;
  }

  /**
   * Called before component entity has been saved.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The component entity.
   *
   * @return $this
   */
  protected function handleComponentTypeEntityPostSave(ConfigEntityInterface $entity) {
    if ($definition = $this->exoComponentManager->getEntityBundleComponentDefinition($entity, TRUE)) {
      $this->exoComponentManager->installThumbnail($definition);
      $this->exoComponentManager->clearCachedDefinitions();
      // If the entity contains the exoComponentRebuild flag, the default
      // component will be rebuild. The exoComponentInstalling flag is used when
      // installing a component so that the default entity is not built until
      // all the fields have been added.
      if (empty($entity->exoComponentInstalling) && !$definition->isComputed() && (!empty($entity->exoComponentRebuilding) || !$this->exoComponentManager->loadEntity($definition))) {
        $this->exoComponentManager->buildEntity($definition);
      }
    }
    return $this;
  }

  /**
   * Called before entity has been deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout-enabled entity.
   *
   * @return $this
   */
  public function handlePreDelete(EntityInterface $entity) {
    if ($this->isComponentEntity($entity)) {
      $this->handleComponentPreDelete($entity);
    }
    return $this;
  }

  /**
   * Called before component entity has been deleted.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The component entity.
   *
   * @return $this
   */
  protected function handleComponentPreDelete(ContentEntityInterface $entity) {
    // Only act on the default entity.
    if (!empty($entity->get('alchemist_default')->value)) {
      if ($definition = $this->exoComponentManager->getEntityComponentDefinition($entity)) {
        $this->exoComponentManager->cleanEntity($definition, $entity, FALSE);
      }
    }
    return $this;
  }

  /**
   * Called after entity has been deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout-enabled entity.
   *
   * @return $this
   */
  public function handlePostDelete(EntityInterface $entity) {
    $this->exoComponentManager->handleEntityEvent('postDelete', $entity);

    if ($this->isComponentTypeEntity($entity)) {
      $this->handleComponentEntityTypeDelete($entity);
    }
    return $this;
  }

  /**
   * Called after component entity type has been deleted.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The component entity.
   *
   * @return $this
   */
  protected function handleComponentEntityTypeDelete(ConfigEntityInterface $entity) {
    /** @var \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager */
    $exo_component_manager = \Drupal::service('plugin.manager.exo_component');
    if ($definition = $exo_component_manager->getEntityBundleComponentDefinition($entity, TRUE)) {
      $exo_component_manager->uninstallThumbnail($definition);
    }
    return $this;
  }

  /**
   * Process a layout builder element.
   */
  public function handleLayoutBuilderProcess(array &$element, FormStateInterface $form_state) {
    $save = FALSE;
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
    $section_storage = $element['#section_storage'];
    if ($form_object instanceof OverridesEntityForm) {
      if (static::mergeSections($section_storage)) {
        $save = TRUE;
      }
    }

    // Remove any components that no longer exist.
    foreach ($section_storage->getSections() as $section) {
      foreach ($section->getComponents() as $uuid => $component) {
        if (ExoComponentManager::isExoComponent($component)) {
          if (!$this->exoComponentManager->entityLoadFromComponent($component)) {
            $section->removeComponent($uuid);
            $save = TRUE;
          }
        }
      }
    }

    if ($save) {
      $this->layoutTempstoreRepository->set($section_storage);
    }
  }

  /**
   * Remove a component if possible.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The component to remove.
   */
  public function removeComponent(EntityInterface $entity, SectionComponent $component) {
    if (ExoComponentManager::isExoComponent($component)) {
      $configuration = $component->get('configuration');
      $component_entity = $this->exoComponentManager->entityLoadByRevisionId($configuration['block_revision_id']);
      if ($component_entity) {
        $definition = $this->exoComponentManager->getEntityComponentDefinition($component_entity);
        // Let fields act on this event.
        $this->exoComponentManager->getExoComponentFieldManager()->onPostDeleteLayoutBuilderEntity($definition, $component_entity, $entity);
        // Delete the component.
        $this->removeUsageForComponent($component_entity);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeUsageForComponent(EntityInterface $entity) {
    $query = $this->database->update('inline_block_usage')
      ->fields([
        'layout_entity_type' => NULL,
        'layout_entity_id' => NULL,
      ]);
    $query->condition('block_content_id', $entity->id());
    $query->execute();
  }

  /**
   * Get merged/locked sections.
   *
   * Default displays can create locked sections. These sections should always
   * carry be merged into the overwritten layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $storage
   *   The section storage.
   *
   * @return bool
   *   TRUE if defaults changed.
   */
  public static function mergeSections(SectionStorageInterface $storage) {
    if ($storage instanceof DefaultsSectionStorageInterface) {
      return FALSE;
    }
    /** @var \Drupal\layout_builder\OverridesSectionStorageInterface $storage */
    $default_sections = $storage->getDefaultSectionStorage()->getSections();
    $default_uuid_sections = static::keySectionsByUuid($default_sections);
    if (empty($default_uuid_sections)) {
      return $storage->getSections();
    }
    $force = FALSE;
    $sections = $default_uuid_sections;
    foreach (static::getUnlockedSections(static::keySectionsByUuid($storage->getSections())) as $uuid => $section) {
      $sections[$uuid] = $section;
    }
    // We compare the locked sections to check for key differences.
    $current_sections = $storage->getSections();
    $current_uuid_sections = static::keySectionsByUuid($current_sections);
    $default_keys = static::getSectionKey(static::getLockedSections($default_uuid_sections));
    $current_keys = static::getSectionKey(static::getLockedSections($current_uuid_sections));
    // Early versions of alchemist did not use UUIDs for sections. For backwards
    // compatibility, we make them match.
    if (!empty($default_uuid_sections) && empty($current_uuid_sections)) {
      // There are times when alchemist did not have a UUID for sections and
      // components have already been added to them. We take current components
      // and add them to the first unlocked section.
      $force = TRUE;
      if ($current_sections) {
        /** @var \Drupal\layout_builder\Section $first_default_section */
        $unlocked_sections = static::getUnlockedSections($default_uuid_sections);
        $first_default_section = array_shift($unlocked_sections);
        foreach ($first_default_section->getComponents() as $component) {
          $first_default_section->removeComponent($component->getUuid());
        }
        foreach ($unlocked_sections as $delta => $unlocked_section) {
          foreach ($unlocked_section->getComponents() as $uuid => $component) {
            $unlocked_section->removeComponent($uuid);
          }
        }
        foreach ($current_sections as $section) {
          foreach ($section->getComponents() as $delta => $component) {
            $weight = $component->getWeight();
            $first_default_section->appendComponent($component);
            $first_default_section->getComponent($component->getUuid())->setWeight($weight);
          }
        }
      }
      $current_uuid_sections = $default_uuid_sections;
    }
    if ($force || ($default_keys !== $current_keys || array_keys($default_uuid_sections) !== array_keys($current_uuid_sections))) {
      $storage->removeAllSections(TRUE);
      foreach ($sections as $delta => $section) {
        $storage->appendSection($section);
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Sets the sections for an entity if any.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $sections
   *   An array of sections.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The entity layout sections if available.
   */
  protected function setEntitySections(EntityInterface $entity, array $sections = []) {
    $section_storage = $this->getSectionStorageForEntity($entity);
    if ($section_storage) {
      $section_storage->removeAllSections();
      foreach ($sections as $section) {
        $section_storage->appendSection($section);
      }
      return $section_storage;
    }
    return NULL;
  }

  /**
   * Key locked sections.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of sections.
   *
   * @return \Drupal\layout_builder\Section[]
   *   The sections keyed by uuid.
   */
  public static function getLockedSections(array $sections) {
    $return = [];
    foreach ($sections as $key => $section) {
      if (!empty($section->getLayoutSettings()['exo_section_lock'])) {
        $return[$key] = $section;
      }
    }
    return $return;
  }

  /**
   * Key locked sections.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of sections.
   *
   * @return \Drupal\layout_builder\Section[]
   *   The sections.
   */
  public static function getUnlockedSections(array $sections) {
    $return = [];
    foreach ($sections as $key => $section) {
      if (empty($section->getLayoutSettings()['exo_section_lock'])) {
        $return[$key] = $section;
      }
    }
    return $return;
  }

  /**
   * Get section key.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of sections.
   *
   * @return string
   *   The sections key.
   */
  protected static function getSectionKey(array $sections) {
    $key = [];
    foreach ($sections as $uuid => $section) {
      $key[] = $uuid;
      foreach ($section->getComponents() as $cuuid => $component) {
        $key[] = $cuuid;
      }
    }
    return $key;
  }

  /**
   * Key sections by UUID.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of sections.
   *
   * @return \Drupal\layout_builder\Section[]
   *   The sections keyed by uuid.
   */
  public static function keySectionsByUuid(array $sections) {
    $return = [];
    foreach ($sections as $delta => $section) {
      if (!empty($section->getLayoutSettings()['exo_section_uuid'])) {
        $return[$section->getLayoutSettings()['exo_section_uuid']] = $section;
      }
    }
    return $return;
  }

}
