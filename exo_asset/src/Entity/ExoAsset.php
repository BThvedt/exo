<?php

namespace Drupal\exo_asset\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\Component\Utility\Html;
use Drupal\link\LinkItemInterface;

/**
 * Defines the Asset entity.
 *
 * @ingroup exo_asset
 *
 * @ContentEntityType(
 *   id = "exo_asset",
 *   label = @Translation("Asset"),
 *   label_plural = @Translation("Assets"),
 *   label_collection = @Translation("Assets"),
 *   handlers = {
 *     "storage" = "Drupal\exo_asset\ExoAssetStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\exo_asset\ExoAssetListBuilder",
 *     "views_data" = "Drupal\exo_asset\Entity\ExoAssetViewsData",
 *     "translation" = "Drupal\exo_asset\ExoAssetTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\exo_asset\Form\ExoAssetForm",
 *       "add" = "Drupal\exo_asset\Form\ExoAssetForm",
 *       "edit" = "Drupal\exo_asset\Form\ExoAssetForm",
 *       "delete" = "Drupal\exo_asset\Form\ExoAssetDeleteForm",
 *     },
 *     "access" = "Drupal\exo_asset\ExoAssetAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\exo_asset\ExoAssetHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "exo_asset",
 *   data_table = "exo_asset_field_data",
 *   revision_table = "exo_asset_revision",
 *   revision_data_table = "exo_asset_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer asset entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message"
 *   },
 *   common_reference_target = TRUE,
 *   links = {
 *     "add-form" = "/admin/config/exo/asset/add",
 *     "edit-form" = "/admin/config/exo/asset/{exo_asset}/edit",
 *     "delete-form" = "/admin/config/exo/asset/{exo_asset}/delete",
 *     "version-history" = "/admin/config/exo/asset/{exo_asset}/revisions",
 *     "revision_revert" = "/admin/config/exo/asset/{exo_asset}/revisions/{exo_asset_revision}/revert",
 *     "revision_delete" = "/admin/config/exo/asset/{exo_asset}/revisions/{exo_asset_revision}/delete",
 *     "translation_revert" = "/admin/config/exo/asset/{exo_asset}/revisions/{exo_asset_revision}/revert/{langcode}",
 *     "collection" = "/admin/config/exo/asset",
 *   },
 *   field_ui_base_route = "entity.exo_asset.collection"
 * )
 */
class ExoAsset extends RevisionableContentEntityBase implements ExoAssetInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly, make the exo_asset owner the
    // revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * Return attribute field definitions.
   *
   * These are the fields which are used to provide meta information and are
   * used as wrapper and entity attribute classes.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions.
   */
  public static function getAttributeFields() {
    $fields = [];
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('exo_asset', 'exo_asset');
    foreach ($field_definitions as $field_definition) {
      /* \Drupal\Core\Field\FieldDefinitionInterface $field */
      if ($field_definition->getType() == 'exo_attribute') {
        $fields[] = $field_definition;
      }
    }
    return $fields;
  }

  /**
   * Return non-attribute field definitions.
   *
   * These are the fields which are used to provide meta information and are
   * used as wrapper and entity attribute classes.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions.
   */
  public static function getOtherFields() {
    $fields = [];
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('exo_asset', 'exo_asset');
    foreach ($field_definitions as $field_definition) {
      /* \Drupal\Core\Field\FieldDefinitionInterface $field */
      if ($field_definition->getType() != 'exo_attribute' && $field_definition->isDisplayConfigurable('form') && !in_array($field_definition->getName(), [
        'image',
        'image_mobile',
        'video',
        'caption',
        'link',
        'moderation_state',
        'path',
      ])) {
        $fields[] = $field_definition;
      }
    }
    return $fields;
  }

  /**
   * Set attribute classes based on field values.
   *
   * @param string $key
   *   A key that will be added to the class names.
   *
   * @return array
   *   An array of class names.
   */
  public function getAttributeClasses($key = '') {
    $classes = [];
    foreach (self::getAttributeFields() as $field_definition) {
      $field_name = $field_definition->getName();
      if ($this->hasField($field_name) && !$this->{$field_name}->isEmpty()) {
        $classes[] = Html::getClass(implode('-', array_filter([
          'exo-asset',
          $key,
          $field_name,
          $this->{$field_name}->value,
        ])));
      }
    }
    return $classes;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  // this appears to be a method that is for an Entity Type, while ExoAsset is an Entity instance?
  // regardless, it was throwing warnings of calling undefined methods (getLabel, getStringTranslation)
  // as well as TranslatableMarkup is not defined either .. but I don't think it gets called so 
  // maybe can be safely removed?
  // public function getCollectionLabel() {
  //   if (empty($this->label_collection)) {
  //     $label = $this->getLabel();
  //     $this->label_collection = new TranslatableMarkup('@label entities!', ['@label' => $label], [], $this->getStringTranslation());
  //   }
  //   return $this->label_collection;
  // }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $has_media_library = \Drupal::service('module_handler')->moduleExists('media_library');

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Asset entity.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['image'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Image'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', ['target_bundles' => ['image' => 'image']])
      ->setDisplayOptions('form', [
        'type' => 'entity_browser_entity_reference',
        'weight' => -3,
        'settings' => [
          'entity_browser' => 'exo_media_image',
          'field_widget_display' => 'rendered_entity',
          'open' => TRUE,
          'field_widget_edit' => FALSE,
          'field_widget_remove' => TRUE,
          'field_widget_replace' => FALSE,
          'field_widget_display_settings' => [
            'view_mode' => 'preview',
          ],
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_entity_view',
        'label' => 'hidden',
        'settings' => [
          'view_mode' => 'full',
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    if ($has_media_library) {
      $fields['image']->setDisplayOptions('form', [
        'type' => 'media_library_widget',
        'weight' => -3,
      ]);
    }

    $fields['image_mobile'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Mobile Image'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', ['target_bundles' => ['image' => 'image']])
      ->setDisplayOptions('form', [
        'type' => 'entity_browser_entity_reference',
        'weight' => -3,
        'settings' => [
          'entity_browser' => 'exo_media_image',
          'field_widget_display' => 'rendered_entity',
          'open' => TRUE,
          'field_widget_edit' => FALSE,
          'field_widget_remove' => TRUE,
          'field_widget_replace' => FALSE,
          'field_widget_display_settings' => [
            'view_mode' => 'preview',
          ],
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_entity_view',
        'label' => 'hidden',
        'settings' => [
          'view_mode' => 'full',
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    if ($has_media_library) {
      $fields['image_mobile']->setDisplayOptions('form', [
        'type' => 'media_library_widget',
        'weight' => -2,
      ]);
    }

    $media_type = exo_asset_has_remote_video() ? 'remote_video' : 'video';
    $fields['video'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Video'))
      ->setDescription(t('Adding a video is optional.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', ['target_bundles' => [$media_type => $media_type]])
      ->setDisplayOptions('form', [
        'type' => 'entity_browser_entity_reference',
        'weight' => -2,
        'settings' => [
          'entity_browser' => 'exo_media_video',
          'field_widget_display' => 'rendered_entity',
          'open' => TRUE,
          'field_widget_edit' => FALSE,
          'field_widget_remove' => TRUE,
          'field_widget_replace' => FALSE,
          'field_widget_display_settings' => [
            'view_mode' => 'preview',
          ],
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_entity_view',
        'label' => 'hidden',
        'settings' => [
          'view_mode' => 'full',
        ],
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    if ($has_media_library) {
      $fields['video']->setDisplayOptions('form', [
        'type' => 'media_library_widget',
        'weight' => -1,
      ]);
    }

    $fields['link'] = BaseFieldDefinition::create('link')
      ->setLabel(t('Link'))
      ->setDescription(t('A link to go to when clicked.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSettings([
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDisplayOptions('form', [
        'type' => 'link_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['caption'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Caption'))
      ->setDescription(t('Attribution or description shown with the asset.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -1,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'hidden',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['alignment_x'] = BaseFieldDefinition::create('exo_attribute')
      ->setLabel(t('Alignment: Horizontal'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'exo_alignment_x',
        'weight' => 10,
      ]);

    $fields['alignment_y'] = BaseFieldDefinition::create('exo_attribute')
      ->setLabel(t('Alignment: Vertical'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'exo_alignment_y',
        'weight' => 10,
      ]);

    $fields['alignment_size'] = BaseFieldDefinition::create('exo_attribute')
      ->setLabel(t('Alignment: Sizing'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'exo_alignment_sizing',
        'weight' => 10,
      ]);

    $fields['color_text'] = BaseFieldDefinition::create('exo_attribute')
      ->setLabel(t('Color: Text'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'exo_text_contrast',
        'weight' => 10,
      ]);

    $fields['color_fg'] = BaseFieldDefinition::create('exo_attribute')
      ->setLabel(t('Color: Foreground'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'exo_theme_color',
        'weight' => 10,
      ]);

    $fields['color_bg'] = BaseFieldDefinition::create('exo_attribute')
      ->setLabel(t('Color: Background'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'exo_theme_color',
        'weight' => 10,
      ]);

    $fields['color_overlay'] = BaseFieldDefinition::create('exo_attribute')
      ->setLabel(t('Color: Overlay'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'exo_theme_color',
        'weight' => 10,
      ]);

    $fields['containment'] = BaseFieldDefinition::create('exo_attribute')
      ->setLabel(t('Containment'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'exo_containment',
        'weight' => 10,
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Published'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 100,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

}
