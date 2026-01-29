<?php

namespace Drupal\exo_alchemist\Controller;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\exo\Access\ExoRefinableDependentAccessTrait;
use Drupal\exo_alchemist\ExoComponentFieldManager;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldFormInterface;

/**
 * Provides means of fetching target entity forms.
 */
trait ExoFieldParentsFormTrait {

  use ExoFieldParentsTrait;
  use ExoRefinableDependentAccessTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The eXo component plugin manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * Get a target form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent_entity
   *   The parent entity.
   * @param array $parents
   *   The parents of the child entity.
   *
   * @return mixed
   *   The target form array.
   */
  protected function getTargetForm(array $form, FormStateInterface $form_state, ContentEntityInterface $parent_entity, array $parents) {
    $form += [
      '#parents' => [],
    ];
    $form_state->set('parent_entity', $parent_entity);
    $form_state->set('component_parents', $parents);
    $entity = $this->getTargetEntity($parent_entity, $parents);
    // Set flag so we know we are performing a manual update.
    $entity->exoComponentUpdating = TRUE;
    $items = $this->getTargetItems($parent_entity, $parents);
    $definition = $this->exoComponentManager()->getEntityComponentDefinition($entity);
    $form_state->set('component_entity', $entity);
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->entityTypeManager()->getStorage('entity_form_display')->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.default');
    $form_state->set('form_display', $form_display);
    // When editing, we want to set any hidden field values to null.
    $hidden = ExoComponentFieldManager::getHiddenFieldNames($entity);
    foreach ($hidden as $name) {
      if ($field = $definition->getField($name)) {
        $field_name = $field->safeId();
        if ($entity->hasField($field_name)) {
          $entity->set($field_name, NULL);
        }
      }
    }
    /** @var \Drupal\exo_alchemist\Plugin\ExoComponentFieldInterface[] $component_fields */
    $component_fields = [];
    $field_name = $this->getFieldNameFromParents($parents);
    if ($form_display) {
      // Determine the fields that should be visible.
      $allowed_fields = [];
      if ($field_definition = $definition->getFieldBySafeId($field_name)) {
        $delta = 0;
        foreach ($definition->getFields() as $field) {
          if ($field->safeId() == $field_name || $field->getGroup() == $field_definition->getName()) {
            $allowed_fields[$delta] = $field->getFieldName();
          }
          else {
            // In order to properly validate, we need to remove the component
            // from the form display.
            $form_display->removeComponent($field->safeId());
          }
          $delta++;
        }
        ksort($allowed_fields);
      }
      else {
        $allowed_fields[] = $field_name;
      }
      foreach ($allowed_fields as $field_name) {
        $field_parents = $parents;
        $position = array_search($field_name, $field_parents);
        $field_parents[$position] = $field_name;
        $items = $this->getTargetItems($parent_entity, $field_parents);
        $widget = $form_display->getRenderer($field_name);
        $as_entity = $items instanceof EntityReferenceFieldItemListInterface && !$widget;
        if ($as_entity) {
          foreach ($definition->getFields() as $field) {
            if ($field->isEditable()) {
              $field_name = $field->safeId();
              if ($widget = $form_display->getRenderer($field_name)) {
                /** @var \Drupal\Core\Field\WidgetInterface $widget */
                $form[$field_name] = $widget->form($entity->get($field_name), $form, $form_state);
                $form[$field_name]['#access'] = $items->access('edit');
              }
            }
          }
        }
        else {
          if ($widget) {
            $key = $this->getKeyFromParents($parents);
            /** @var \Drupal\Core\Field\WidgetInterface $widget */
            $field = $definition->getFieldBySafeId($field_name);
            $handles_multiple = $widget->getPluginDefinition()['multiple_values'];
            $component_field = $this->exoComponentManager()->getExoComponentFieldManager()->createFieldInstance($field);
            if ($component_field instanceof ExoComponentFieldFormInterface) {
              $component_field->widgetAlter($widget, $form_state);
            }
            $value = $items->getValue();
            // When a widget handles multiple values, we need to set the values
            // to a single value.
            if ($key !== NULL && $handles_multiple) {
              $single_value = isset($value[$key]) ? [$value[$key]] : [];
              $items->setValue($single_value);
            }
            $form[$field_name] = $widget->form($items, $form, $form_state);
            $form[$field_name]['#access'] = $items->access('edit');
            unset($form[$field_name]['widget']['#theme']);
            unset($form[$field_name]['widget']['add_more']);
            if ($key !== NULL) {
              if ($handles_multiple) {
                if (empty($form['#allow_multiple'])) {
                  // When a widget handles multiple values, we need to treat it
                  // as if it handles a single widget (ex: exo_autocomplete).
                  // To do this we change the items value to a single value and
                  // store both the delta and original value in the element.
                  // In the submit callback, we merge them together to restore
                  // the old values with the updated new value.
                  foreach (Element::children($form[$field_name]['widget']) as $field_delta) {
                    $form[$field_name]['widget'][$field_delta]['#multiple'] = FALSE;
                    $form[$field_name]['#alchemist_original_value'] = $value;
                    $form[$field_name]['#alchemist_delta'] = $key;
                  }
                }
              }
              else {
                foreach (Element::children($form[$field_name]['widget']) as $field_delta) {
                  if ($field_delta != $key) {
                    $form[$field_name]['widget'][$field_delta]['#access'] = FALSE;
                  }
                  else {
                    if (isset($form[$field_name]['widget'][$field_delta]['#multiple'])) {
                      $form[$field_name]['widget'][$field_delta]['#multiple'] = FALSE;
                    }
                    $form[$field_name]['widget'][$field_delta]['_weight']['#access'] = FALSE;
                  }
                }
              }
            }
            $component_fields[$field_name] = $component_field;
          }
          else {
            // If no widget could be found, we pass the form directly to the
            // field plugin.
            $key = $this->getKeyFromParents($parents);
            $field = $definition->getFieldBySafeId($field_name);
            if ($field) {
              $form_state->set('exo_component_key', $key);
              $form[$field_name] = [];
              $component_fields[$field_name] = $this->exoComponentManager()->getExoComponentFieldManager()->createFieldInstance($field);
            }
          }
        }
      }
    }
    $target_field_names = Element::children($form);
    foreach ($target_field_names as $field_name) {
      $field = $definition->getFieldBySafeId($field_name);
      $component_field = $component_fields[$field_name] ?? $this->exoComponentManager()->getExoComponentFieldManager()->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFormInterface) {
        $component_field->formAlter($form[$field_name], $form_state);
      }
    }
    $form_state->set('target_field_names', $target_field_names);
    return $form;
  }

  /**
   * Validates a target form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateTargetForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $form_state->get('form_display');
    $entity = $form_state->get('component_entity');
    $form_display->extractFormValues($entity, $form, $form_state);
    $form_display->validateFormValues($entity, $form, $form_state);

    $definition = $this->exoComponentManager()->getEntityComponentDefinition($entity);
    $target_field_names = $form_state->get('target_field_names');
    foreach ($target_field_names as $field_name) {
      $field = $definition->getFieldBySafeId($field_name);
      $component_field = $this->exoComponentManager()->getExoComponentFieldManager()->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFormInterface) {
        $component_field->formValidate($form[$field_name], $form_state);
      }
    }
  }

  /**
   * Submits a target form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function submitTargetForm(array $form, FormStateInterface $form_state) {
    $entity = $form_state->get('component_entity');

    $definition = $this->exoComponentManager()->getEntityComponentDefinition($entity);
    // Set visibility for each field as fields will always have a value. If a
    // user removes a value for a non-required field, the default value is
    // populated.
    $hidden = [];
    $visible = [];
    foreach ($definition->getFields() as $field) {
      $field_name = $field->safeId();
      // Page title should not be handled automatically.
      if (in_array($field->getType(), ['page_title'])) {
        continue;
      }
      if ($entity->hasField($field_name)) {
        if ($field->isFilter()) {
          $visible[] = $field->getName();
        }
        elseif ($field->isInvisible() || ($entity->get($field_name)->isEmpty() && !$field->isRequired() && $field->isHideable())) {
          $hidden[] = $field->getName();
        }
        else {
          $visible[] = $field->getName();
        }
      }
    }
    $target_field_names = $form_state->get('target_field_names');
    foreach ($target_field_names as $field_name) {
      $field = $definition->getFieldBySafeId($field_name);
      $component_field = $this->exoComponentManager()->getExoComponentFieldManager()->createFieldInstance($field);
      if ($component_field instanceof ExoComponentFieldFormInterface) {
        $component_field->formSubmit($form[$field_name], $form_state);
      }

      if (isset($form[$field_name]['#alchemist_original_value'])) {
        $value = $entity->get($field_name)->getValue();
        $original_value = $form[$field_name]['#alchemist_original_value'];
        $delta = $form[$field_name]['#alchemist_delta'];
        $original_value[$delta] = $value[0];
        $entity->get($field_name)->setValue($original_value);
      }
    }

    // Handle visibility after component has been submitted as submit will
    // restore hidden fields when sequenced.
    foreach ($hidden as $name) {
      ExoComponentFieldManager::setHiddenFieldName($entity, $name);
    }
    foreach ($visible as $name) {
      ExoComponentFieldManager::setVisibleFieldName($entity, $name);
    }

    // Because we handle nested entities, we make sure our changes are set on
    // the parent entity.
    $this->setTargetEntity($form_state->get('parent_entity'), $entity, $form_state->get('component_parents'));
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = $this->container()->get('entity_type.manager');
    }
    return $this->entityTypeManager;
  }

  /**
   * Retrieves the exo component manager.
   *
   * @return \Drupal\exo_alchemist\ExoComponentManager
   *   The exo component manager.
   */
  protected function exoComponentManager() {
    if (!isset($this->exoComponentManager)) {
      $this->exoComponentManager = $this->container()->get('plugin.manager.exo_component');
    }
    return $this->exoComponentManager;
  }

}
