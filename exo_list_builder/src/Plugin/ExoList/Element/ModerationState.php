<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Element;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\exo_list_builder\Plugin\ExoListContentTrait;
use Drupal\exo_list_builder\Plugin\ExoListElementBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a eXo list element for rendering the moderation state.
 *
 * @ExoListElement(
 *   id = "moderation_state",
 *   label = @Translation("Moderation State"),
 *   description = @Translation("The moderation state label."),
 *   weight = 0,
 *   field_type = {},
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {
 *     "moderation_state",
 *   },
 *   exclusive = true,
 *   provider = "content_moderation",
 * )
 */
class ModerationState extends ExoListElementBase implements ContainerFactoryPluginInterface {
  use ExoListContentTrait;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * LogGeneratorBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModerationInformationInterface $moderation_information) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, array $field) {
    if ($item = $this->getItem($entity, $field)) {
      $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
      $value = $workflow->getTypePlugin()->getState($item->value)->label();
      $pending_revision = NULL;
      if ($entity instanceof RevisionableInterface) {
        /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
        $storage = $this->entityTypeManager()->getStorage($entity->getEntityTypeId());
        $latest_revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $entity->language()->getId());
        $default_revision_id = $entity->isDefaultRevision() && !$entity->isNewRevision() && ($revision_id = $entity->getRevisionId()) ?
          $revision_id : $this->moderationInformation->getDefaultRevisionId($entity->getEntityTypeId(), $entity->id());
        if ($latest_revision_id !== NULL && $latest_revision_id != $default_revision_id) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $latest_revision */
          $latest_revision = $storage->loadRevision($latest_revision_id);
          if (!$latest_revision->wasDefaultRevision()) {
            $pending_revision = $latest_revision;
          }
        }
      }
      if ($pending_revision) {
        $label = $workflow->getTypePlugin()->getState($latest_revision->get($item->getFieldDefinition()->getName())->value)->label();
        if ($entity->hasLinkTemplate('latest-version')) {
          $url = $entity->toUrl('latest-version');
          if ($url->access()) {
            $label = '<a href="' . $url->toString() . '">' . $label . '</a>';
          }
        }
        $value .= ' (' . $label . ')';
      }
      return $value;
    }
    return parent::view($entity, $field);
  }

}
