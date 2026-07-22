<?php

namespace Drupal\exo_modal\Plugin\Field\FieldFormatter;

use Drupal\exo_modal\Plugin\ExoModalFieldFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\exo_modal\ExoModalInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Provides a block to display an eXo menu.
 *
 * @FieldFormatter(
 *   id = "exo_modal_entity_reference_entity_view",
 *   label = @Translation("eXo Modal"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ExoModalEntityReferenceEntityFormatter extends ExoModalFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + [
      'modal_view_mode' => 'default',
      'trigger_type' => 'text',
      'view_mode' => 'default',
      'text' => 'Open',
      'text_auto' => FALSE,
      'text_type_auto' => FALSE,
      'icon' => '',
      'icon_only' => FALSE,
      'icon_auto' => TRUE,
      'modal_icon_auto' => TRUE,
      'modal_title_auto' => TRUE,
    ];
  }

  /**
   * Get trigger type options.
   */
  protected function triggerTypeOptions() {
    return [
      'text' => $this->t('Text'),
      'entity' => $this->t('Rendered Entity'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $form = parent::settingsForm($form, $form_state);
    $form['trigger_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Trigger Type'),
      '#options' => $this->triggerTypeOptions(),
      '#default_value' => $this->getSetting('trigger_type'),
      '#attributes' => [
        'class' => ['exo-modal-entity-reference-entity-formatter-' . $field_name],
      ],
      '#required' => TRUE,
    ];

    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Trigger view mode'),
      '#description' => $this->t('The view mode the entity will use when displayed within the modal.'),
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
      '#default_value' => $this->getSetting('view_mode'),
      '#states' => [
        'visible' => [
          '.exo-modal-entity-reference-entity-formatter-' . $field_name => ['value' => 'entity'],
        ],
      ],
    ];

    // We move the trigger settings outside of the modal settings as they
    // will most often be changed.
    $states = [
      'visible' => [
        '.exo-modal-entity-reference-entity-formatter-' . $field_name => ['value' => 'text'],
      ],
    ];
    foreach (Element::children($form['modal']['settings']['trigger']) as $key) {
      $form[$key] = $form['modal']['settings']['trigger'][$key];
      $form[$key]['#default_value'] = $this->getSetting($key);
      $form[$key]['#title'] = $this->t('Trigger @name', ['@name' => $form[$key]['#title']]);
      $form[$key]['#states'] = $states;
    }
    $form['modal']['settings']['trigger']['#access'] = FALSE;

    $form['text_auto'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Append entity title to trigger text'),
      '#description' => $this->t('Will append the entity title to the trigger text.'),
      '#default_value' => $this->getSetting('text_auto'),
      '#states' => $states,
    ];

    $form['text_type_auto'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Append entity type to trigger text'),
      '#description' => $this->t('Will append the entity type to the trigger text.'),
      '#default_value' => $this->getSetting('text_type_auto'),
      '#states' => $states,
    ];

    $form['icon_auto'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use eXo entity icon as trigger icon'),
      '#description' => $this->t('Will use the icon configured for this entity type. Will not override trigger icon if specified.'),
      '#default_value' => $this->getSetting('icon_auto'),
      '#states' => $states,
    ];

    $form['modal_title_auto'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use entity title as modal title'),
      '#description' => $this->t('Will use the entity title within the modal.'),
      '#default_value' => $this->getSetting('modal_title_auto'),
    ];

    $form['modal_icon_auto'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use eXo entity icon as modal icon'),
      '#description' => $this->t('Will use the entity icon within the modal.'),
      '#default_value' => $this->getSetting('modal_icon_auto'),
    ];

    $form['modal_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Modal view mode'),
      '#description' => $this->t('The view mode the entity will use when displayed within the modal.'),
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
      '#default_value' => $this->getSetting('modal_view_mode'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $types = $this->triggerTypeOptions();
    $type = $this->getSetting('trigger_type');
    $summary[] = $this->t('Trigger type: @type', ['@type' => $types[$type] ?? $type]);

    if ($this->getSetting('text_auto')) {
      $summary[] = $this->t('Append title to trigger: @value', ['@value' => 'Yes']);
    }

    if ($this->getSetting('text_type_auto')) {
      $summary[] = $this->t('Append type to trigger: @value', ['@value' => 'Yes']);
    }

    if ($this->getSetting('modal_title_auto')) {
      $summary[] = $this->t('Automatic title: @value', ['@value' => 'Yes']);
    }

    if ($this->getSetting('icon_auto')) {
      $summary[] = $this->t('Automatic icon: @value', ['@value' => 'Yes']);
    }

    $view_modes = $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type'));
    if ($type == 'entity') {
      $view_mode = $this->getSetting('view_mode');
      $summary[] = $this->t('Trigger rendered as @mode', ['@mode' => $view_modes[$view_mode] ?? $view_mode]);
    }

    $view_mode = $this->getSetting('modal_view_mode');
    $summary[] = $this->t('Modal rendered as @mode', ['@mode' => $view_modes[$view_mode] ?? $view_mode]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    if ($items instanceof EntityReferenceFieldItemListInterface) {
      $entities = $this->getEntitiesToView($items, $langcode);

      $field_level_access_cacheability = new CacheableMetadata();

      // Try to map the cacheability of the access result that was set at
      // _accessCacheability in getEntitiesToView() to the corresponding render
      // subtree. If no such subtree is found, then merge it with the field-level
      // access cacheability.
      foreach ($items as $delta => $item) {

        if (!isset($entities[$delta]) && isset($elements[$delta])) {
          $elements[$delta]['#access'] = FALSE;
        }

        // Ignore items for which access cacheability could not be determined in
        // prepareView().
        if (!empty($item->_accessCacheability)) {
          if (isset($elements[$delta])) {
            CacheableMetadata::createFromRenderArray($elements[$delta])
              ->merge($item->_accessCacheability)
              ->applyTo($elements[$delta]);
          }
          else {
            $field_level_access_cacheability = $field_level_access_cacheability->merge($item->_accessCacheability);
          }
        }
      }

      // Apply the cacheability metadata for the inaccessible entities and the
      // entities for which the corresponding render subtree could not be found.
      // This causes the field to be rendered (and cached) according to the cache
      // contexts by which the access results vary, to ensure only users with
      // access to this field can view it. It also tags this field with the cache
      // tags on which the access results depend, to ensure users that cannot view
      // this field at the moment will gain access once any of those cache tags
      // are invalidated.
      $field_level_access_cacheability->merge(CacheableMetadata::createFromRenderArray($elements))
        ->applyTo($elements);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateModal(FieldItemInterface $item, $delta, $settings = []) {
    $field_name = $this->fieldDefinition->getName();
    $entity = $item->getEntity()->get($field_name)->get($delta)->entity;
    $modal = parent::generateModal($item, $delta, $settings);
    $trigger = $this->getSetting('text');
    $icon = $this->getSetting('icon');
    // The referenced entity may no longer exist, leaving a broken reference.
    // In that case fall back to the configured trigger text and icon rather
    // than fataling on a NULL entity.
    if ($entity && $this->getSetting('trigger_type') == 'entity') {
      $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
      $trigger = $view_builder->view($entity, $this->getSetting('view_mode'), $entity->language()->getId());
      $modal->setTriggerUrl($entity->toUrl()->toString());
    }
    if ($entity && $this->getSetting('trigger_type') == 'text') {
      if ($this->getSetting('text_auto')) {
        $trigger .= ' ' . $entity->label();
      }
      if ($this->getSetting('text_type_auto')) {
        if ($entity instanceof ContentEntityInterface) {
          $key = $this->entityTypeManager->getDefinition($entity->getEntityTypeId())->getKey('bundle');
          $trigger .= ' ' . $entity->get($key)->entity->label();
        }
        // $trigger .= ' ' . $entity->label();
      }
      if ($this->getSetting('icon_auto')) {
        $entity_icon = exo_icon_entity_icon($entity);
        if (!$icon) {
          $icon = $entity_icon;
        }
      }
    }
    $modal->setTrigger($trigger, $icon, $this->getSetting('icon_only'));
    return $modal;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewModalElement(ExoModalInterface $modal, FieldItemInterface $item, $delta, $langcode) {
    $field_name = $this->fieldDefinition->getName();
    $entity = $item->getEntity()->get($field_name)->get($delta)->entity;
    // The referenced entity may no longer exist. There is nothing to render
    // inside the modal in that case.
    if (!$entity) {
      return [];
    }
    if ($this->getSetting('modal_title_auto')) {
      $modal->setSetting(['modal', 'title'], $entity->label());
    }
    $entity_icon = exo_icon_entity_icon($entity);
    if ($this->getSetting('modal_icon_auto') && !$modal->getSetting([
      'modal',
      'icon',
    ])) {
      if ($entity_icon) {
        $modal->setSetting(['modal', 'icon'], $entity_icon);
      }
    }
    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    return $view_builder->view($entity, $this->getSetting('modal_view_mode'), $langcode);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase::getEntitiesToView()
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $entities = [];

    foreach ($items as $delta => $item) {
      if (!empty($item->_loaded)) {
        $entity = $item->entity;

        if ($entity instanceof TranslatableInterface) {
          $entity = \Drupal::service('entity.repository')->getTranslationFromContext($entity, $langcode);
        }

        $access = $this->checkAccess($entity);
        $item->_accessCacheability = CacheableMetadata::createFromObject($access);
        if ($access->isAllowed()) {
          $entity->_referringItem = $items[$delta];
          $entities[$delta] = $entity;
        }
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase::prepareView()
   */
  public function prepareView(array $entities_items) {
    $ids = [];
    foreach ($entities_items as $items) {
      foreach ($items as $item) {
        $item->_loaded = FALSE;
        if ($this->needsEntityLoad($item)) {
          $ids[] = $item->target_id;
        }
      }
    }
    if ($ids) {
      $target_type = $this->getFieldSetting('target_type');
      $target_entities = \Drupal::entityTypeManager()->getStorage($target_type)->loadMultiple($ids);
    }

    foreach ($entities_items as $items) {
      foreach ($items as $item) {
        if (isset($target_entities[$item->target_id])) {
          $item->entity = $target_entities[$item->target_id];
          $item->_loaded = TRUE;
        }
        elseif ($item->hasNewEntity()) {
          $item->_loaded = TRUE;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase::needsEntityLoad()
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase::checkAccess()
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view', NULL, TRUE);
  }

}
