<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_alchemist\Plugin\SectionStorage\ExoOverridesSectionStorage;
use Drupal\layout_builder\Form\OverridesEntityForm;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form containing the Layout Builder UI for overrides.
 *
 * @internal
 *   Form classes are internal.
 */
class ExoOverridesEntityForm extends OverridesEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL) {
    $build = parent::buildForm($form, $form_state, $section_storage);
    $build['layout_builder__layout']['widget']['#process'][] = [
      'Drupal\exo_alchemist\Element\ExoLayoutBuilder', 'process',
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $section_storage = $this->sectionStorage;
    if ($section_storage instanceof ExoOverridesSectionStorage) {
      $entity = $section_storage->getEntity();
      if (isset($entity->_exoComponentEntityFieldSave)) {
        foreach ($entity->_exoComponentEntityFieldSave as $field_name) {
          if ($this->entity->hasField($field_name) && $entity->hasField($field_name)) {
            $this->entity->get($field_name)->setValue($entity->get($field_name)->getValue());
          }
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Entity references may have been stored for saving.
    if (!empty($this->entity->_exoComponentReferenceSave)) {
      foreach ($this->entity->_exoComponentReferenceSave as $referenced_entity) {
        if ($referenced_entity instanceof ContentEntityInterface) {
          // Save them.
          $referenced_entity->save();
        }
      }
    }
    return parent::save($form, $form_state);
  }

}
