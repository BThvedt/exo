<?php

namespace Drupal\exo_alchemist\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\exo_alchemist\ExoComponentGenerator;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;

/**
 * Provides an entity view display entity that has a layout.
 */
class ExoLayoutBuilderEntityViewDisplay extends LayoutBuilderEntityViewDisplay {

  /**
   * A record of the last processed entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected static $rootEntity;

  /**
   * Builds the render array for the sections of a given entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The render array representing the sections of the entity.
   */
  protected function buildSections(FieldableEntityInterface $entity) {
    // The only exo block content entities that support layouts are those
    // passed through as a 'section' field.
    // @see \Drupal\exo_alchemist\Plugin\ExoComponentField\SectionBase::viewValue()
    if ($entity->getEntityTypeId() === 'block_content' && substr($entity->bundle(), 0, 4) === 'exo_' && empty($entity->exoComponentSection)) {
      return [];
    }
    $contexts = $this->getContextsForEntity($entity);
    // @todo Remove in https://www.drupal.org/project/drupal/issues/3018782.
    $label = new TranslatableMarkup('@entity being viewed', [
      '@entity' => $entity->getEntityType()->getSingularLabel(),
    ]);
    $contexts['layout_builder.entity'] = EntityContext::fromEntity($entity, $label);

    $cacheability = new CacheableMetadata();
    /** @var \Drupal\layout_builder\OverridesSectionStorageInterface $storage */
    $storage = $this->sectionStorageManager()->findByContext($contexts, $cacheability);

    $build = [];
    if ($storage) {
      ExoComponentGenerator::mergeSections($storage);
      // Alchemist allows for complex nesting of components and layouts. As a
      // result, we want to always maintain the root entity responsible for
      // rendering the sections. Currently, this will never be an exo block.
      if ($entity->getEntityTypeId() !== 'block_content' && substr($entity->bundle(), 0, 4) !== 'exo_') {
        static::$rootEntity = $entity;
      }
      $contexts['layout_builder.entity'] = EntityContext::fromEntity(static::$rootEntity ?: $entity, $label);
      foreach ($storage->getSections() as $delta => $section) {
        $build[$delta] = $section->toRenderArray($contexts);
      }
    }
    // The render array is built based on decisions made by @SectionStorage
    // plugins and therefore it needs to depend on the accumulated
    // cacheability of those decisions.
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Get a label given a component name.
   *
   * @param string $component_name
   *   The component name.
   */
  public function getComponentLabel($component_name) {
    $field_definition = $this->getFieldDefinition($component_name);
    if ($field_definition) {
      return $field_definition->getLabel();
    }
    $extra_fields = $this->entityFieldManager->getExtraFields($this->getTargetEntityTypeId(), $this->getTargetBundle());
    if (isset($extra_fields['display'][$component_name])) {
      return $extra_fields['display'][$component_name]['label'];
    }
    return 'None';
  }

  /**
   * Gets the section storage manager.
   *
   * @return \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   *   The section storage manager.
   */
  private function sectionStorageManager() {
    return \Drupal::service('plugin.manager.layout_builder.section_storage');
  }

}
