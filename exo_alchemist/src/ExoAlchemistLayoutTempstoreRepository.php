<?php

namespace Drupal\exo_alchemist;

use Drupal\exo_alchemist\Plugin\ExoComponentFieldComputedInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\LayoutTempstoreRepository;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a mechanism for loading layouts from tempstore.
 */
class ExoAlchemistLayoutTempstoreRepository extends LayoutTempstoreRepository {

  use LayoutEntityHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function delete(SectionStorageInterface $section_storage) {
    if ($section_storage instanceof ExoComponentSectionStorageInterface) {
      $layout_entity = $section_storage->getEntity();
      /** @var \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager */
      $exo_component_manager = \Drupal::service('plugin.manager.exo_component');
      foreach ($section_storage->getSections() as $section) {
        foreach ($section->getComponents() as $component) {
          if (ExoComponentManager::isExoComponent($component)) {
            $component_entity = $exo_component_manager->entityLoadFromComponent($component);
            $definition = $exo_component_manager->getEntityComponentDefinition($component_entity);
            foreach ($definition->getFields() as $field) {
              $component_field = $exo_component_manager->getExoComponentFieldManager()->createFieldInstance($field);
              if ($component_field instanceof ExoComponentFieldComputedInterface) {
                $component_field->onDiscardLayoutBuilderEntity($component_entity, $layout_entity);
              }
            }
          }
        }
      }
    }
    $key = $this->getKey($section_storage);
    $this->getTempstore($section_storage)->delete($key);
  }

}
