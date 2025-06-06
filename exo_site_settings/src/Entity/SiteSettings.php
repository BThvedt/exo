<?php

namespace Drupal\exo_site_settings\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the config page entity.
 *
 * @ingroup exo_site_settings
 *
 * @ContentEntityType(
 *   id = "exo_site_settings",
 *   label = @Translation("Site Settings"),
 *   label_plural = @Translation("Site Settings"),
 *   label_collection = @Translation("Settings"),
 *   bundle_label = @Translation("Site Setting Type"),
 *   handlers = {
 *     "storage" = "Drupal\exo_site_settings\SiteSettingsStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\exo_site_settings\SiteSettingsListBuilder",
 *     "views_data" = "Drupal\exo_site_settings\Entity\SiteSettingsViewsData",
 *     "translation" = "Drupal\exo_site_settings\SiteSettingsTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\exo_site_settings\Form\SiteSettingsForm",
 *       "add" = "Drupal\exo_site_settings\Form\SiteSettingsForm",
 *       "edit" = "Drupal\exo_site_settings\Form\SiteSettingsForm",
 *       "delete" = "Drupal\exo_site_settings\Form\SiteSettingsDeleteForm",
 *     },
 *     "access" = "Drupal\exo_site_settings\SiteSettingsAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\exo_site_settings\SiteSettingsHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "exo_site_settings",
 *   data_table = "exo_site_settings_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer config pages",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "collection" = "/admin/settings",
 *   },
 *   bundle_entity_type = "exo_site_settings_type",
 *   field_ui_base_route = "entity.exo_site_settings_type.edit_form"
 * )
 */
class SiteSettings extends ContentEntityBase implements SiteSettingsInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->type->entity->label();
  }

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
  public function setId($id) {
    $this->set('id', $id);
    return $this;
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields[$entity_type->getKey('id')] = BaseFieldDefinition::create('string')
      ->setSetting('max_length', 64)
      ->setRequired(TRUE)
      ->addConstraint('UniqueField')
      ->addPropertyConstraints('value', ['Regex' => ['pattern' => '/^[a-z0-9_]+$/']]);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the config page entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
