<?php

namespace Drupal\exo_icon\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Plugin implementation of the 'entity reference label' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_icon",
 *   label = @Translation("eXo Label with Icon"),
 *   description = @Translation("Display the icon of an entity reference."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceIconFormatter extends EntityReferenceLabelFormatter {
  use ExoIconTranslationTrait;

  /**
   * Available icon field options.
   *
   * @var array
   *   An array of icon field ids => labels.
   */
  protected $iconFields;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'icon_only' => FALSE,
      'page_convert' => FALSE,
      'field_name' => NULL,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['icon_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show icon only'),
      '#default_value' => $this->getSetting('icon_only'),
    ];
    $elements['page_convert'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Page Convert'),
      '#description' => $this->t('Convert any entity with the word "page" in the title to use "page" and the icon for page. This is useful for content types where various page types are created but should all use the same name and icon for display purposes.'),
      '#default_value' => $this->getSetting('page_convert'),
    ];

    $icon_fields = $this->getIconFields();
    if (!empty($icon_fields)) {
      $elements['field_name'] = [
        '#type' => 'select',
        '#title' => $this->t('Field to use as icon source'),
        '#description' => $this->t('If nothing is selected, the icon will be automatically detected if possible.'),
        '#options' => ['' => $this->t('- Automatic -')] + $icon_fields,
        '#default_value' => $this->getSetting('field_name'),
      ];
    }

    return $elements;
  }

  /**
   * Get available icon fields.
   */
  protected function getIconFields() {
    if (!isset($this->iconFields)) {
      $entity_field_manager = \Drupal::service('entity_field.manager');
      $this->iconFields = [];
      $target_type = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type');
      $bundles = $this->fieldDefinition->getSetting('handler_settings')['target_bundles'] ?? [];
      foreach ($bundles as $bundle) {
        foreach ($entity_field_manager->getFieldDefinitions($target_type, $bundle) as $field_definition) {
          if ($field_definition->getType() == 'icon') {
            $this->iconFields[$field_definition->getName()] = $field_definition->getLabel();
          }
        }
      }
    }
    return $this->iconFields;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('icon_only')) {
      $summary[] = $this->t('Icon only');
    }
    if ($this->getSetting('page_convert')) {
      $summary[] = $this->t('Page conversion');
    }
    if ($field_name = $this->getSetting('field_name')) {
      $summary[] = $this->t('Icon source field: %field_name', [
        '%field_name' => isset($this->getIconFields()[$field_name]) ? $this->getIconFields()[$field_name] . ' (' . $field_name . ')' : $this->t('Field no longer exists.'),
      ]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $output_as_link = $this->getSetting('link');

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $label = $entity->label();
      if (empty($label)) {
        // Fall back to entity id if label is not set.
        $label = $entity->id();
      }
      $icon = $this->icon($label);

      if (($field_name = $this->getSetting('field_name')) && $entity->hasField($field_name) && !$entity->{$field_name}->isEmpty()) {
        $icon->setIcon($entity->{$field_name}->value);
      }
      if (!$icon->getIcon()) {
        $type_entity = $entity;
        $bundle_key = $entity->getEntityType()->getKey('bundle');
        if ($entity instanceof ContentEntityInterface && $bundle_key && isset($entity->{$bundle_key})) {
          $type_entity = $entity->{$bundle_key}->entity;
        }
        if ($type_entity instanceof ConfigEntityInterface) {
          if ($this->getSetting('page_convert')) {
            $parts = explode(' ', strtolower($label));
            if (in_array('page', $parts)) {
              $icon->setText('Page');
              $page_entity = \Drupal::entityTypeManager()->getStorage($type_entity->getEntityTypeId())->load('page');
              if ($page_entity) {
                $type_entity = $page_entity;
              }
            }
            if ($type_entity) {
              $icon->setIcon(exo_icon_entity_icon($type_entity));
            }
          }
          else {
            $icon->setIcon(exo_icon_entity_icon($type_entity));
          }
        }
      }
      $icon->setIconOnly($this->getSetting('icon_only'));
      // If the link is to be displayed and the entity has a uri, display a
      // link.
      if ($output_as_link && !$entity->isNew()) {
        try {
          $uri = $entity->toUrl();
        }
        catch (UndefinedLinkTemplateException $e) {
          $output_as_link = FALSE;
        }
      }

      if ($output_as_link && isset($uri) && !$entity->isNew()) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $icon,
          '#url' => $uri,
          '#options' => $uri->getOptions(),
        ];

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += ['attributes' => []];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }
      }
      else {
        $elements[$delta]['#markup'] = $icon->render();
      }
      $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
    }

    return $elements;
  }

}
